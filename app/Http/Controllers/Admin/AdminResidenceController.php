<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreResidenceRequest;
use App\Http\Requests\Admin\UpdateResidenceRequest;
use App\Services\DodoVroumApi\ResidenceService;
use App\Services\DodoVroumApi\UserService;
use App\Services\DodoVroumApi\BookingService;
use App\Services\DodoVroumApi\Mappers\ResidenceMapper;
use App\Services\DodoVroumApiService;
use App\Exceptions\DodoVroumApiException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdminResidenceController extends Controller
{
    public function __construct(
        protected ResidenceService $residenceService,
        protected UserService $userService,
        protected BookingService $bookingService,
        protected DodoVroumApiService $apiService
    ) {
    }

    /**
     * Afficher la liste des résidences
     */
    public function index(Request $request): Response
    {
        try {
            $filters = $request->only(['search', 'type', 'status']);
            
            Log::debug('Filtres reçus dans index', [
                'filters' => $filters,
            ]);
            
            // L'API supporte maintenant le filtre 'status' basé sur les réservations actives
            // On passe 'status' à l'API, mais on filtre 'type' côté serveur car l'API ne l'accepte pas
            $apiFilters = array_filter([
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null, // L'API gère maintenant ce filtre
            ], function($value) {
                return $value !== null && $value !== '';
            });
            
            // Récupérer les résidences mappées depuis le service
            $residences = $this->residenceService->allMapped($apiFilters);
            
            // Appliquer les filtres côté serveur si nécessaire
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $residences = array_filter($residences, function($residence) use ($search) {
                    $name = strtolower($residence['name'] ?? $residence['title'] ?? '');
                    $address = strtolower($residence['address'] ?? '');
                    $city = strtolower($residence['city'] ?? '');
                    return strpos($name, $search) !== false || 
                           strpos($address, $search) !== false || 
                           strpos($city, $search) !== false;
                });
                $residences = array_values($residences);
            }
            
            if (!empty($filters['type'])) {
                $filterType = strtolower(trim($filters['type']));
                $residences = array_filter($residences, function($residence) use ($filterType) {
                    $type = strtolower(trim($residence['typeResidence'] ?? $residence['type'] ?? ''));
                    
                    // Comparaison exacte
                    if ($type === $filterType) {
                        return true;
                    }
                    
                    // Comparaison avec mapping
                    $typeMapping = [
                        'villa' => ['villa', 'penthouse'],
                        'appartement' => ['appartement', 'apartment', 'appart'],
                        'maison' => ['maison', 'house', 'home'],
                        'studio' => ['studio'],
                    ];
                    
                    if (isset($typeMapping[$filterType])) {
                        return in_array($type, $typeMapping[$filterType]);
                    }
                    
                    return false;
                });
                $residences = array_values($residences);
                
                Log::debug('Filtre par type appliqué', [
                    'filter_type' => $filterType,
                    'residences_count_after_filter' => count($residences),
                ]);
            }

            // Enrichir avec chambres, capacité : l'API liste ne renvoie pas toujours tout, le détail oui
            foreach ($residences as &$residence) {
                $id = $residence['id'] ?? $residence['_id'] ?? null;
                if (!$id) {
                    continue;
                }
                try {
                    $detail = $this->apiService->getResidence($id);
                    if ($detail) {
                        if (isset($detail['bedrooms']) || isset($detail['nombreChambres'])) {
                            $residence['bedrooms'] = $detail['bedrooms'] ?? $detail['nombreChambres'] ?? $residence['bedrooms'] ?? 0;
                            $residence['nombreChambres'] = $residence['bedrooms'];
                        }
                        if (isset($detail['capacity']) || isset($detail['capacite'])) {
                            $residence['capacity'] = $detail['capacity'] ?? $detail['capacite'] ?? $residence['capacity'] ?? 0;
                            $residence['capacite'] = $residence['capacity'];
                        }
                        if (isset($detail['typeResidence']) || isset($detail['type'])) {
                            $residence['typeResidence'] = $residence['typeResidence'] ?? $detail['typeResidence'] ?? $detail['type'] ?? null;
                            $residence['type'] = $residence['typeResidence'];
                        }
                        if (isset($detail['address']) || isset($detail['adresse'])) {
                            $residence['address'] = $detail['address'] ?? $detail['adresse'] ?? $residence['address'] ?? null;
                            $residence['adresse'] = $residence['address'];
                        }
                        if (isset($detail['city']) || isset($detail['ville'])) {
                            $residence['city'] = $detail['city'] ?? $detail['ville'] ?? $residence['city'] ?? null;
                            $residence['ville'] = $residence['city'];
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug('Enrichissement résidence liste admin', ['id' => $id, 'error' => $e->getMessage()]);
                }
            }
            unset($residence);
            
            // Calculer les statistiques AVANT la pagination
            // Récupérer toutes les résidences non mappées pour les stats
            $allResidencesRaw = $this->apiService->getResidences([]);
            
            $totalResidences = count($allResidencesRaw);
            $availableResidences = 0;
            $activeBookings = 0; // Réservations en cours
            $monthRevenue = 0;
            
            $currentMonth = date('Y-m');
            $today = new \DateTimeImmutable('today');
            
            foreach ($allResidencesRaw as $residence) {
                if (($residence['available'] ?? $residence['isActive'] ?? true) === true) {
                    $availableResidences++;
                }
            }
            
            // Récupérer les réservations pour calculer les stats
            try {
                $allBookings = $this->apiService->getBookings([]);
                
                foreach ($allBookings as $booking) {
                    // Vérifier si la réservation concerne une résidence
                    if (isset($booking['residence']) || isset($booking['residenceId']) || isset($booking['residence_id'])) {
                        // Vérifier si c'est une réservation en cours
                        // Une réservation est "en cours" si :
                        // 1. Le client a validé qu'il a reçu la clé (keyRetrievedAt existe)
                        // 2. La date de fin n'est pas encore passée
                        $keyRetrievedAt = $booking['keyRetrievedAt'] ?? $booking['key_retrieved_at'] ?? null;
                        $endDate = $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null;
                        
                        if (!empty($keyRetrievedAt) && $endDate) {
                            try {
                                $end = new \DateTimeImmutable($endDate);
                                if ($today <= $end) {
                                    $activeBookings++;
                                }
                            } catch (\Exception $e) {
                                // Ignorer les erreurs de parsing de dates
                            }
                        }
                        
                        // Calculer les revenus du mois
                        $startDate = $booking['startDate'] ?? $booking['start_date'] ?? null;
                        if ($startDate && strpos($startDate, $currentMonth) === 0) {
                            $monthRevenue += (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erreur lors du calcul des statistiques résidences', ['error' => $e->getMessage()]);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            $total = count($residences);
            $offset = ($currentPage - 1) * $perPage;
            $items = array_slice($residences, $offset, $perPage);
            
            $paginator = new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $currentPage,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
            
            Log::debug('AdminResidenceController::index', [
                'filters' => $filters,
                'residences_count' => $total,
                'current_page' => $currentPage,
                'per_page' => $perPage,
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération des résidences', [
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);
            $residences = [];
            $paginator = new LengthAwarePaginator([], 0, 15, 1);
            $totalResidences = 0;
            $availableResidences = 0;
            $activeBookings = 0;
            $monthRevenue = 0;
        } catch (\Exception $e) {
            Log::error('Erreur récupération résidences', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $residences = [];
            $paginator = new LengthAwarePaginator([], 0, 15, 1);
            $totalResidences = 0;
            $availableResidences = 0;
            $activeBookings = 0;
            $monthRevenue = 0;
        }

        return Inertia::render('Residences/Index', [
            'residences' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => $filters ?? [],
            'stats' => [
                'totalResidences' => $totalResidences ?? 0,
                'availableResidences' => $availableResidences ?? 0,
                'activeBookings' => $activeBookings ?? 0,
                'monthRevenue' => $monthRevenue ?? 0,
            ],
        ]);
    }

    /**
     * Afficher le formulaire de création
     */
    public function create(): Response
    {
        try {
            $owners = $this->userService->getOwners();
            
            Log::debug('Propriétaires récupérés pour création résidence', [
                'owners_count' => count($owners),
            ]);
            
            return Inertia::render('Residences/Create', [
                'users' => $owners,
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération des propriétaires', [
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);
            
            return Inertia::render('Residences/Create', [
                'users' => [],
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération propriétaires pour création résidence', [
                'error' => $e->getMessage(),
            ]);
            
            return Inertia::render('Residences/Create', [
                'users' => [],
            ]);
        }
    }

    /**
     * Afficher les détails d'une résidence
     */
    public function show(string $id): Response|RedirectResponse
    {
        try {
            $residence = $this->residenceService->find($id);
            
            if (!$residence) {
                return redirect()->route('admin.residences.index')
                    ->with('error', 'Résidence non trouvée');
            }
            
            // Récupérer les réservations liées à cette résidence
            $allBookings = $this->apiService->getBookings([]);
            $residenceBookings = [];
            foreach ($allBookings as $booking) {
                $bookingResidenceId = null;
                if (isset($booking['residence']) && is_array($booking['residence'])) {
                    $bookingResidenceId = $booking['residence']['id'] ?? $booking['residence']['_id'] ?? null;
                } else {
                    $bookingResidenceId = $booking['residenceId'] ?? $booking['residence_id'] ?? null;
                }
                
                if ($bookingResidenceId && (string) $bookingResidenceId === (string) $id) {
                    $residenceBookings[] = $booking;
                }
            }

            $stats = $this->calculateResidenceStats($residenceBookings, $residence);

            // Mapper les réservations pour le frontend
            $mappedBookings = $this->mapBookingsForResidence($residenceBookings);
            
            // Récupérer les informations du propriétaire
            $owner = null;
            $proprietaireId = $residence['proprietaireId'] ?? $residence['ownerId'] ?? null;
            if ($proprietaireId) {
                try {
                    $owner = $this->userService->find($proprietaireId);
                    if ($owner) {
                        $residence['owner'] = $owner;
                        $residence['ownerName'] = $owner['name'] ?? $owner['nom'] ?? $owner['email'] ?? 'Propriétaire inconnu';
                    }
                } catch (\Exception $e) {
                    Log::warning('Impossible de récupérer le propriétaire de la résidence', [
                        'residence_id' => $id,
                        'proprietaireId' => $proprietaireId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération de la résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);
            return redirect()->route('admin.residences.index')
                ->with('error', 'Erreur lors de la récupération de la résidence');
        } catch (\Exception $e) {
            Log::error('Erreur récupération résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('admin.residences.index')
                ->with('error', 'Erreur lors de la récupération de la résidence');
        }

        return Inertia::render('Residences/Show', [
            'residence' => $residence,
            'stats' => $stats,
            'bookings' => $mappedBookings,
        ]);
    }
    
    /**
     * Calculer les statistiques d'une résidence
     */
    private function calculateResidenceStats(array $bookings, array $residence): array
    {
        $totalBookings = count($bookings);
        $totalRevenue = 0;
        $averageRating = 0;
        $totalReviews = 0;
        $confirmedBookings = 0;
        $cancelledBookings = 0;
        $completedBookings = 0;
        
        // Calculer les revenus et les statuts
        foreach ($bookings as $booking) {
            $price = (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0);
            $totalRevenue += $price;
            
            $status = strtolower($booking['status'] ?? 'pending');
            
            // Vérifier si la date de fin est passée
            $endDate = $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null;
            $isStayCompleted = false;
            if ($endDate) {
                try {
                    $today = new \DateTimeImmutable('today');
                    $end = new \DateTimeImmutable($endDate);
                    $isStayCompleted = $today > $end;
                } catch (\Exception $e) {
                    // En cas d'erreur de parsing, on ignore
                }
            }
            
            // Si la date de fin est passée, considérer comme terminée
            if ($isStayCompleted) {
                $completedBookings++;
            } elseif ($status === 'confirmed' || $status === 'confirmee') {
                $confirmedBookings++;
            } elseif ($status === 'cancelled' || $status === 'annulee') {
                $cancelledBookings++;
            } elseif ($status === 'completed' || $status === 'terminee') {
                $completedBookings++;
            }
            
            // Récupérer les notes/avis si disponibles
            // Vérifier plusieurs structures possibles pour les avis
            $reviewRating = null;
            if (isset($booking['review']) && is_array($booking['review'])) {
                $reviewRating = $booking['review']['rating'] ?? $booking['review']['note'] ?? null;
            } elseif (isset($booking['reviewId']) && !empty($booking['reviewId'])) {
                // Si on a un reviewId mais pas l'objet review, on considère qu'il y a un avis
                // (mais on ne peut pas récupérer la note sans appeler l'API des avis)
                // Pour l'instant, on ignore ces cas
            }
            
            if ($reviewRating !== null) {
                $totalReviews++;
                $averageRating += (float) $reviewRating;
            }
        }
        
        if ($totalReviews > 0) {
            $averageRating = round($averageRating / $totalReviews, 1);
        } else {
            // Utiliser la note de la résidence si disponible
            // Vérifier plusieurs structures possibles
            $residenceRating = null;
            $residenceReviewsCount = 0;
            
            // Vérifier d'abord dans l'objet notation
            if (isset($residence['notation']) && is_array($residence['notation'])) {
                $residenceRating = $residence['notation']['note'] ?? $residence['notation']['rating'] ?? null;
                $residenceReviewsCount = $residence['notation']['avis'] ?? $residence['notation']['reviewsCount'] ?? $residence['notation']['count'] ?? 0;
            }
            
            // Si pas trouvé, vérifier directement dans la résidence
            if ($residenceRating === null) {
                $residenceRating = $residence['rating'] ?? $residence['note'] ?? null;
            }
            if ($residenceReviewsCount === 0) {
                $residenceReviewsCount = $residence['reviewsCount'] ?? $residence['avis'] ?? 0;
            }
            
            $averageRating = $residenceRating !== null ? (float) $residenceRating : 0;
            $totalReviews = (int) $residenceReviewsCount;
        }

        // Calculer le taux d'occupation (simplifié : nombre de jours réservés / nombre de jours dans l'année)
        $occupationRate = 0;
        if ($totalBookings > 0) {
            // Approximation simple : si on a des réservations, on peut estimer un taux
            // Ici on pourrait calculer le nombre de jours effectivement réservés
            $occupationRate = min(100, round(($confirmedBookings / max($totalBookings, 1)) * 100, 1));
        }
        
        return [
            'totalBookings' => $totalBookings,
            'totalRevenue' => $totalRevenue,
            'averageRating' => $averageRating,
            'totalReviews' => $totalReviews,
            'occupationRate' => $occupationRate,
            'confirmedBookings' => $confirmedBookings,
            'cancelledBookings' => $cancelledBookings,
            'completedBookings' => $completedBookings,
        ];
    }
    
    /**
     * Mapper les réservations pour l'affichage
     */
    private function mapBookingsForResidence(array $bookings): array
    {
        $usersMap = [];
        try {
            $usersData = $this->apiService->getUsers();
            $allUsers = is_array($usersData) ? $usersData : [];
            foreach ($allUsers as $user) {
                $userId = $user['id'] ?? $user['_id'] ?? null;
                if ($userId) {
                    $firstName = $user['firstName'] ?? $user['prenom'] ?? '';
                    $lastName = $user['lastName'] ?? $user['nom'] ?? $user['name'] ?? '';
                    $fullName = trim($firstName . ' ' . $lastName);
                    if (empty($fullName)) {
                        $fullName = $user['email'] ?? 'Client inconnu';
                    }
                    $usersMap[$userId] = $fullName;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Erreur lors de la récupération des utilisateurs pour le mapping des réservations', ['error' => $e->getMessage()]);
        }
        
        return array_map(function ($booking) use ($usersMap) {
            // Extract customer name
            $customerName = 'Client inconnu';
            if (isset($booking['user'])) {
                $user = $booking['user'];
                $firstName = $user['firstName'] ?? $user['prenom'] ?? '';
                $lastName = $user['lastName'] ?? $user['nom'] ?? $user['name'] ?? '';
                $customerName = trim($firstName . ' ' . $lastName);
                if (empty($customerName)) {
                    $customerName = $user['email'] ?? 'Client inconnu';
                }
            } elseif (isset($booking['customer_name'])) {
                $customerName = $booking['customer_name'];
            } elseif (isset($booking['customer'])) {
                $customerName = $booking['customer'];
            } elseif (isset($booking['clientId']) && isset($usersMap[$booking['clientId']])) {
                $customerName = $usersMap[$booking['clientId']];
            }
            
            // Extract dates
            $startDate = $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? null;
            $endDate = $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null;
            
            $dates = '';
            if ($startDate && $endDate) {
                try {
                    $start = new \DateTime($startDate);
                    $end = new \DateTime($endDate);
                    $dates = $start->format('d M') . ' - ' . $end->format('d M');
                } catch (\Exception $e) {
                    $dates = $startDate . ' - ' . $endDate;
                }
            }
            
            // Vérifier si la date de fin est passée
            $isStayCompleted = false;
            if ($endDate) {
                try {
                    $today = new \DateTimeImmutable('today');
                    $end = new \DateTimeImmutable($endDate);
                    $isStayCompleted = $today > $end;
                } catch (\Exception $e) {
                    // En cas d'erreur de parsing, on ignore
                }
            }
            
            // Format status
            $statusRaw = $booking['status'] ?? 'pending';
            $status = 'En attente';
            
            // PRIORITÉ 1: Si la date de fin est passée, la réservation est terminée
            if ($isStayCompleted) {
                $status = 'Terminée';
            }
            // PRIORITÉ 2: Utiliser le statut de l'API
            elseif (strtolower($statusRaw) === 'confirmed' || strtolower($statusRaw) === 'confirmee') {
                $status = 'Confirmée';
            } elseif (strtolower($statusRaw) === 'cancelled' || strtolower($statusRaw) === 'canceled') {
                $status = 'Annulée';
            } elseif (strtolower($statusRaw) === 'completed' || strtolower($statusRaw) === 'terminee' || strtolower($statusRaw) === 'terminée') {
                $status = 'Terminée';
            }
            
            return [
                'id' => $booking['id'] ?? $booking['_id'] ?? null,
                'customer' => $customerName,
                'dates' => $dates,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'amount' => (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0),
                'status' => $status,
                'statusRaw' => $statusRaw,
            ];
        }, $bookings);
    }

    /**
     * Afficher le formulaire d'édition
     */
    public function edit(string $id): Response|RedirectResponse
    {
        try {
            $residence = $this->residenceService->find($id);
            
            if (!$residence) {
                return redirect()->route('admin.residences.index')
                    ->with('error', 'Résidence non trouvée');
            }
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération de la résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('admin.residences.index')
                ->with('error', 'Erreur lors de la récupération de la résidence');
        } catch (\Exception $e) {
            Log::error('Erreur récupération résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('admin.residences.index')
                ->with('error', 'Erreur lors de la récupération de la résidence');
        }

        return Inertia::render('Residences/Edit', [
            'residence' => $residence,
        ]);
    }

    /**
     * Créer une nouvelle résidence
     */
    public function store(StoreResidenceRequest $request)
    {
        try {
            // Tronquer la description à 500 caractères si nécessaire
            $data = $request->validated();
            if (isset($data['description']) && is_string($data['description']) && mb_strlen($data['description']) > 500) {
                $data['description'] = mb_substr($data['description'], 0, 500);
            }
            
            // Appeler le service pour créer la résidence
            $result = $this->residenceService->create($data);
            
            // Vérifier que la création a réussi
            if (empty($result)) {
                Log::warning('Résidence créée mais réponse API vide', [
                    'data' => $request->validated(),
                ]);
                return back()->withErrors([
                    'api_error' => 'La résidence a été créée mais aucune confirmation n\'a été reçue de l\'API.'
                ])->withInput();
            }
            
            // Extraire l'ID de la résidence créée
            $residenceId = $result[0]['id'] ?? $result['id'] ?? null;
            
            Log::info('Résidence créée avec succès sur dodovroum.com', [
                'residence_id' => $residenceId,
                'data_keys' => array_keys($data),
            ]);
            
            return redirect()->route('admin.residences.index')
                ->with('success', 'La résidence a été synchronisée avec succès sur dodovroum.com');
                
        } catch (DodoVroumApiException $e) {
            // Extraire le message d'erreur détaillé
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
            $errorContext = $e->getContext();
            
            // Essayer d'extraire le message d'erreur du contexte si disponible
            if (isset($errorContext['error_body']) && is_array($errorContext['error_body'])) {
                $apiError = $errorContext['error_body']['message'] ?? $errorContext['error_body']['error'] ?? null;
                if ($apiError) {
                    $errorMessage = is_array($apiError) ? implode(' ', $apiError) : $apiError;
                }
            }
            if (is_array($errorMessage)) {
                $errorMessage = implode(' ', $errorMessage);
            }
            
            Log::error('Erreur API lors de la création de la résidence', [
                'error' => $errorMessage,
                'code' => $errorCode,
                'context' => $errorContext,
                'data' => $request->validated(),
            ]);
            
            // Messages d'erreur spécifiques selon le code HTTP
            if ($errorCode === 403) {
                return back()->withErrors([
                    'api_error' => $errorMessage ?: 'Vous n\'avez pas les permissions nécessaires pour créer une résidence. Vérifiez que votre compte admin a les droits appropriés.'
                ])->withInput();
            }
            
            if ($errorCode === 400 || $errorCode === 422) {
                $apiStatus = $errorContext['status'] ?? null;
                $isOwnerIdRejected = ($apiStatus === 400 || $errorCode === 400)
                    && (stripos($errorMessage, 'ownerId') !== false && stripos($errorMessage, 'should not exist') !== false);
                if ($isOwnerIdRejected) {
                    return back()->withErrors([
                        'api_error' => 'L\'API n\'accepte pas encore la création d\'une résidence pour un propriétaire. Le backend (NestJS) doit accepter le champ proprietaireId lorsque l\'appelant est admin. Voir docs/API-ADMIN-CREER-POUR-PROPRIETAIRE.md.'
                    ])->withInput();
                }
                return back()->withErrors([
                    'api_error' => $errorMessage ?: 'Les données envoyées sont invalides. Vérifiez tous les champs du formulaire.'
                ])->withInput();
            }
            
            if ($errorCode === 500) {
                return back()->withErrors([
                    'api_error' => $errorMessage ?: 'Erreur serveur lors de la création de la résidence. Veuillez réessayer plus tard.'
                ])->withInput();
            }
            
            return back()->withErrors([
                'api_error' => $errorMessage ?: 'Erreur lors de la création de la résidence sur dodovroum.com'
            ])->withInput();
            
        } catch (\Exception $e) {
            Log::error('Erreur création résidence (exception générale)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated(),
            ]);
            
            return back()->withErrors([
                'api_error' => 'Erreur lors de la création de la résidence: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Mettre à jour une résidence
     */
    public function update(UpdateResidenceRequest $request, string $id)
    {
        try {
            // Tronquer la description à 500 caractères si nécessaire
            $data = $request->validated();
            if (isset($data['description']) && is_string($data['description']) && mb_strlen($data['description']) > 500) {
                $data['description'] = mb_substr($data['description'], 0, 500);
            }
            
            // Appeler le service pour mettre à jour la résidence
            $result = $this->residenceService->update($id, $data);
            
            // Vérifier que la mise à jour a réussi
            if (empty($result)) {
                Log::warning('Résidence mise à jour mais réponse API vide', [
                    'residence_id' => $id,
                    'data' => $request->validated(),
                ]);
                return back()->withErrors([
                    'api_error' => 'La résidence a été mise à jour mais aucune confirmation n\'a été reçue de l\'API.'
                ])->withInput();
            }
            
            Log::info('Résidence mise à jour avec succès sur dodovroum.com', [
                'residence_id' => $id,
            ]);
            
            // 🔄 Actualiser les offres combinées qui utilisent cette résidence
            try {
                $comboOfferService = app(\App\Services\DodoVroumApi\ComboOfferService::class);
                $comboOfferService->refreshOffersForResidence($id);
            } catch (\Exception $e) {
                Log::warning('Erreur lors de l\'actualisation des offres combinées après mise à jour résidence', [
                    'residence_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            return redirect()->route('admin.residences.index')
                ->with('success', 'La résidence a été synchronisée avec succès sur dodovroum.com');
                
        } catch (DodoVroumApiException $e) {
            // Extraire le message d'erreur détaillé
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
            $errorContext = $e->getContext();
            
            // Essayer d'extraire le message d'erreur du contexte si disponible
            if (isset($errorContext['error_body']) && is_array($errorContext['error_body'])) {
                $apiError = $errorContext['error_body']['message'] ?? $errorContext['error_body']['error'] ?? null;
                if ($apiError) {
                    $errorMessage = $apiError;
                }
            }
            
            Log::error('Erreur API lors de la mise à jour de la résidence', [
                'id' => $id,
                'error' => $errorMessage,
                'code' => $errorCode,
                'context' => $errorContext,
            ]);
            
            // Messages d'erreur spécifiques selon le code HTTP
            if ($errorCode === 403) {
                return back()->withErrors([
                    'api_error' => $errorMessage ?: 'Vous n\'avez pas les permissions nécessaires pour modifier cette résidence.'
                ])->withInput();
            }
            
            if ($errorCode === 400 || $errorCode === 422) {
                return back()->withErrors([
                    'api_error' => $errorMessage ?: 'Les données envoyées sont invalides. Vérifiez tous les champs du formulaire.'
                ])->withInput();
            }
            
            if ($errorCode === 404) {
                return back()->withErrors([
                    'api_error' => 'La résidence n\'existe pas ou a été supprimée.'
                ])->withInput();
            }
            
            if ($errorCode === 500) {
                return back()->withErrors([
                    'api_error' => $errorMessage ?: 'Erreur serveur lors de la mise à jour de la résidence. Veuillez réessayer plus tard.'
                ])->withInput();
            }
            
            return back()->withErrors([
                'api_error' => $errorMessage ?: 'Erreur lors de la mise à jour de la résidence sur dodovroum.com'
            ])->withInput();
            
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour résidence (exception générale)', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->withErrors([
                'api_error' => 'Erreur lors de la mise à jour de la résidence: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Supprimer une résidence
     */
    /**
     * Vérifier si une résidence a des réservations liées
     */
    public function checkBookings(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            $allBookings = $this->bookingService->all();
            $residenceBookings = [];
            
            foreach ($allBookings as $booking) {
                $bookingResidenceId = null;
                
                if (isset($booking['residence']) && is_array($booking['residence'])) {
                    $bookingResidenceId = $booking['residence']['id'] ?? $booking['residence']['_id'] ?? null;
                } elseif (isset($booking['residenceId']) || isset($booking['residence_id'])) {
                    $bookingResidenceId = $booking['residenceId'] ?? $booking['residence_id'] ?? null;
                }
                
                if ($bookingResidenceId && (string) $bookingResidenceId === (string) $id) {
                    $residenceBookings[] = $booking;
                }
            }
            
            $hasBookings = count($residenceBookings) > 0;
            
            Log::info('Vérification réservations pour résidence', [
                'residence_id' => $id,
                'has_bookings' => $hasBookings,
                'bookings_count' => count($residenceBookings),
            ]);
            
            return response()->json([
                'hasBookings' => $hasBookings,
                'bookingsCount' => count($residenceBookings),
                'message' => $hasBookings 
                    ? "Cette résidence a " . count($residenceBookings) . " réservation(s) liée(s). La suppression n'est pas possible."
                    : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification des réservations', [
                'residence_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'hasBookings' => false,
                'bookingsCount' => 0,
                'message' => null,
            ]);
        }
    }

    public function destroy(string $id)
    {
        try {
            $deleted = $this->residenceService->delete($id);
            
            if ($deleted) {
                return redirect()->route('admin.residences.index')
                    ->with('success', 'Résidence supprimée avec succès');
            }
            
            return redirect()->route('admin.residences.index')
                ->with('error', 'Erreur lors de la suppression');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la suppression de la résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('admin.residences.index')
                ->with('error', $e->getMessage() ?: 'Erreur lors de la suppression de la résidence');
        } catch (\Exception $e) {
            Log::error('Erreur suppression résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('admin.residences.index')
                ->with('error', 'Erreur lors de la suppression de la résidence');
        }
    }
}
