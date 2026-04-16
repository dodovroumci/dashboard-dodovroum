<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\HasProprietaireId;
use App\Services\DodoVroumApiService;
use App\Services\DodoVroumApi\ResidenceService;
use App\Services\DodoVroumApi\Mappers\ResidenceMapper;
use App\Exceptions\DodoVroumApiException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OwnerResidenceController extends Controller
{
    use HasProprietaireId;
    
    protected DodoVroumApiService $apiService;
    protected ResidenceService $residenceService;

    public function __construct(DodoVroumApiService $apiService, ResidenceService $residenceService)
    {
        $this->apiService = $apiService;
        $this->residenceService = $residenceService;
    }

    /**
     * Afficher la liste des résidences du propriétaire
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        try {
            $filters = $request->only(['search', 'type', 'status']);
            $userId = (string) $user->getAuthIdentifier();
            
            // Récupérer le proprietaireId réel depuis les données utilisateur (pour filtrage API + comparaison)
            $userProprietaireId = $this->getProprietaireId($user);
            // L'API peut associer la résidence à l'ID utilisateur (JWT) plutôt qu'au proprietaireId ; on garde les deux pour la comparaison
            $userAuthId = (string) $user->getAuthIdentifier();

            if (!$userProprietaireId && !$userAuthId) {
                Log::error('Impossible de récupérer le proprietaireId pour l\'utilisateur', [
                    'user_id' => $userId,
                ]);
                return Inertia::render('Owner/Residences/Index', [
                    'residences' => [],
                    'pagination' => (new LengthAwarePaginator([], 0, 15, 1))->toArray(),
                    'filters' => $filters ?? [],
                    'error' => 'Impossible de récupérer vos résidences. Veuillez contacter le support.',
                ]);
            }

            // Utiliser le token du propriétaire pour GET /residences : l'API NestJS ne renvoie alors que ses résidences.
            // La liste de l'API ne contient pas toujours proprietaireId/ownerId, donc on ne peut pas filtrer côté dashboard.
            $apiFilters = [];
            if (!empty($filters['search'])) {
                $apiFilters['search'] = $filters['search'];
            }
            if (!empty($filters['status'])) {
                $apiFilters['status'] = $filters['status'];
            }

            try {
                $allResidences = $this->residenceService->all($apiFilters);
            } catch (\Exception $e) {
                Log::error('Erreur API lors de la récupération des résidences', [
                    'proprietaireId' => $userProprietaireId,
                    'error' => $e->getMessage()
                ]);
                return Inertia::render('Owner/Residences/Index', [
                    'residences' => [],
                    'pagination' => (new LengthAwarePaginator([], 0, 15, 1))->toArray(),
                    'filters' => $filters ?? [],
                    'stats' => [
                        'totalResidences' => 0,
                        'availableResidences' => 0,
                        'totalBookings' => 0,
                        'monthRevenue' => 0,
                    ],
                    'error' => 'Erreur lors de la récupération des résidences. L\'API est temporairement indisponible. Veuillez réessayer plus tard.',
                ]);
            }

            // Enrichir avec chambres, capacité, type, adresse : l'API liste ne renvoie pas tout, le détail oui
            foreach ($allResidences as &$residence) {
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
                        if (isset($detail['bathrooms']) || isset($detail['nombreSallesBain'])) {
                            $residence['bathrooms'] = $detail['bathrooms'] ?? $detail['nombreSallesBain'] ?? $residence['bathrooms'] ?? 0;
                            $residence['nombreSallesBain'] = $residence['bathrooms'];
                        }
                        if (isset($detail['capacity']) || isset($detail['capacite'])) {
                            $residence['capacity'] = $detail['capacity'] ?? $detail['capacite'] ?? $residence['capacity'] ?? 0;
                            $residence['capacite'] = $residence['capacity'];
                        }
                        if (isset($detail['typeResidence']) || isset($detail['type'])) {
                            $residence['typeResidence'] = $detail['typeResidence'] ?? $detail['type'] ?? $residence['typeResidence'] ?? null;
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
                    Log::debug('Enrichissement résidence liste (détail)', ['id' => $id, 'error' => $e->getMessage()]);
                }
            }
            unset($residence);

            // Résidences déjà filtrées par l'API (token propriétaire) ; si l'API renvoie owner/proprietaire, on filtre en plus par sécurité
            $residences = [];
            foreach ($allResidences as $residence) {
                $residenceOwnerId = null;
                if (isset($residence['proprietaire']) && is_array($residence['proprietaire'])) {
                    $residenceOwnerId = $residence['proprietaire']['id'] ?? $residence['proprietaire']['_id'] ?? null;
                }
                if (!$residenceOwnerId && isset($residence['owner']) && is_array($residence['owner'])) {
                    $residenceOwnerId = $residence['owner']['id'] ?? $residence['owner']['_id'] ?? null;
                }
                if (!$residenceOwnerId) {
                    $residenceOwnerId = $residence['proprietaireId'] ?? $residence['proprietaire_id'] ?? $residence['ownerId'] ?? $residence['owner_id'] ?? null;
                }
                if (!$residenceOwnerId && isset($residence['proprietaire']) && is_string($residence['proprietaire'])) {
                    $residenceOwnerId = $residence['proprietaire'];
                }

                $matches = true;
                if ($residenceOwnerId !== null && $residenceOwnerId !== '') {
                    $residenceOwnerStr = (string) $residenceOwnerId;
                    $residenceOwnerInt = is_numeric($residenceOwnerId) ? (int) $residenceOwnerId : null;
                    $matches = ($residenceOwnerStr === $userAuthId || $residenceOwnerStr === (string) $userProprietaireId)
                        || ($residenceOwnerInt !== null && $userProprietaireId !== null && is_numeric($userProprietaireId) && $residenceOwnerInt === (int) $userProprietaireId)
                        || ($residenceOwnerInt !== null && is_numeric($userAuthId) && $residenceOwnerInt === (int) $userAuthId);
                }

                if ($matches) {
                    $residences[] = $residence;
                }
            }
            
            // Calculer les statistiques AVANT le mapping
            $totalResidences = count($residences);
            $availableResidences = 0;
            $totalBookings = 0;
            $monthRevenue = 0;
            
            $currentMonth = date('Y-m');
            
            foreach ($residences as $residence) {
                if (($residence['available'] ?? $residence['isActive'] ?? true) === true) {
                    $availableResidences++;
                }
            }
            
            $ownerIdForBookings = $userProprietaireId ?? $userAuthId;
            try {
                $allBookings = $ownerIdForBookings ? $this->apiService->getBookings(['proprietaireId' => $ownerIdForBookings]) : [];
                
                // Filtrer les réservations pour ce mois
                foreach ($allBookings as $booking) {
                    $bookingProprietaireId = null;
                    if (isset($booking['residence']) && is_array($booking['residence'])) {
                        $bookingProprietaireId = $booking['residence']['proprietaireId'] ?? $booking['residence']['ownerId'] ?? null;
                    }
                    
                    if ($bookingProprietaireId && (
                        (string) $bookingProprietaireId === (string) $userProprietaireId || (string) $bookingProprietaireId === $userAuthId ||
                        (is_numeric($bookingProprietaireId) && is_numeric($ownerIdForBookings) && (int) $bookingProprietaireId === (int) $ownerIdForBookings)
                    )) {
                        $totalBookings++;
                        
                        // Calculer les revenus du mois
                        $startDate = $booking['startDate'] ?? $booking['start_date'] ?? null;
                        if ($startDate && strpos($startDate, $currentMonth) === 0) {
                            $monthRevenue += (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erreur lors du calcul des statistiques', ['error' => $e->getMessage()]);
            }
            
            // Mapper les résidences après filtrage
            $residences = array_map(function($residence) {
                return \App\Services\DodoVroumApi\Mappers\ResidenceMapper::fromApi($residence);
            }, $residences);
            
            // Liste récupérée avec le token du propriétaire : toutes les résidences lui appartiennent, donc éditables/supprimables
            $residencesWithCanEdit = array_map(function($residence) {
                $residence['canEdit'] = true;
                return $residence;
            }, $residences);
            
            // Pagination côté serveur
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            
            $collection = collect($residencesWithCanEdit);
            $paginated = new LengthAwarePaginator(
                $collection->forPage($currentPage, $perPage),
                $collection->count(),
                $perPage,
                $currentPage,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return Inertia::render('Owner/Residences/Index', [
                'residences' => $paginated->items(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
                'filters' => $filters,
                'stats' => [
                    'totalResidences' => $totalResidences,
                    'availableResidences' => $availableResidences,
                    'totalBookings' => $totalBookings,
                    'monthRevenue' => $monthRevenue,
                ],
                ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération des résidences', [
                'error' => $e->getMessage(),
            ]);
            return Inertia::render('Owner/Residences/Index', [
                'residences' => [],
                'pagination' => (new LengthAwarePaginator([], 0, 15, 1))->toArray(),
                'filters' => $filters ?? [],
                'error' => 'Erreur lors de la récupération des résidences.',
            ]);
        }
    }

    /**
     * Afficher une résidence spécifique
     */
    public function show(string $id): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        try {
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour l\'affichage de la résidence', [
                    'user_id' => $user->getAuthIdentifier(),
                    'residence_id' => $id,
                ]);
                abort(403, 'Accès non autorisé');
            }
            
            // Récupérer la résidence directement depuis l'API
            $residence = $this->apiService->getResidence($id);
            
            if (!$residence) {
                abort(404, 'Résidence non trouvée');
            }

            // Vérifier que la résidence appartient au propriétaire
            $residenceProprietaireId = null;
            
            if (isset($residence['proprietaire']) && is_array($residence['proprietaire'])) {
                $residenceProprietaireId = $residence['proprietaire']['id'] ?? $residence['proprietaire']['_id'] ?? null;
            } elseif (isset($residence['owner']) && is_array($residence['owner'])) {
                $residenceProprietaireId = $residence['owner']['id'] ?? $residence['owner']['_id'] ?? null;
            } else {
                $residenceProprietaireId = $residence['proprietaireId'] ?? $residence['ownerId'] ?? null;
            }
            
            // Comparer en string ET en int pour gérer les cas où l'un est string et l'autre int
            $matches = false;
            if ($residenceProprietaireId) {
                $matches = (
                    (string) $residenceProprietaireId === (string) $proprietaireId ||
                    (is_numeric($residenceProprietaireId) && is_numeric($proprietaireId) && (int) $residenceProprietaireId === (int) $proprietaireId)
                );
            }
            
            if (!$matches) {
                Log::warning('Accès non autorisé à la résidence', [
                    'residence_id' => $id,
                    'user_id' => $user->getAuthIdentifier(),
                    'expected_proprietaireId' => $proprietaireId,
                    'residence_proprietaireId' => $residenceProprietaireId,
                ]);
                abort(403, 'Vous n\'êtes pas autorisé à voir cette résidence');
            }
            
            // Mapper la résidence au format attendu par le frontend
            $mappedResidence = ResidenceMapper::fromApi($residence);
            
            // Ajouter le flag canEdit
            $mappedResidence['canEdit'] = true; // Si on arrive ici, c'est que la résidence appartient au propriétaire
            
            // Ajouter les dates bloquées depuis l'endpoint dédié
            try {
                $blockedDatesPeriods = $this->apiService->getResidenceBlockedDates($id);
                // Convertir les périodes bloquées en liste de dates individuelles
                $blockedDatesList = [];
                foreach ($blockedDatesPeriods as $blockedDate) {
                    $startDate = new \DateTime($blockedDate['startDate'] ?? $blockedDate['start_date'] ?? '');
                    $endDate = new \DateTime($blockedDate['endDate'] ?? $blockedDate['end_date'] ?? '');
                    $startDate->setTime(0, 0, 0);
                    $endDate->setTime(0, 0, 0);
                    
                    $current = clone $startDate;
                    while ($current <= $endDate) {
                        $blockedDatesList[] = $current->format('Y-m-d');
                        $current->modify('+1 day');
                    }
                }
                $mappedResidence['blockedDates'] = array_unique($blockedDatesList);
            } catch (\Exception $e) {
                // Si l'endpoint n'existe pas encore, utiliser un tableau vide
                Log::warning('Impossible de récupérer les dates bloquées', [
                    'id' => $id,
                    'error' => $e->getMessage()
                ]);
                $mappedResidence['blockedDates'] = [];
            }
            
            // Récupérer les réservations liées à cette résidence
            $allBookings = $this->apiService->getBookings(['proprietaireId' => $proprietaireId]);
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

            $stats = $this->calculateResidenceStats($residenceBookings, $mappedResidence);

            // Mapper les réservations pour le frontend
            $mappedBookings = $this->mapBookingsForResidence($residenceBookings);

            return Inertia::render('Owner/Residences/Show', [
                'residence' => $mappedResidence,
                'stats' => $stats,
                'bookings' => $mappedBookings,
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération de la résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            abort(404, 'Résidence non trouvée');
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération de la résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(404, 'Résidence non trouvée');
        }
    }

    /**
     * Afficher le formulaire de création
     */
    public function create(): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        return Inertia::render('Owner/Residences/Create');
    }

    /**
     * Créer une nouvelle résidence
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        $proprietaireId = $this->getProprietaireId($user);
        if (!$proprietaireId) {
            Log::error('Impossible de récupérer le proprietaireId pour la création de la résidence', [
                'user_id' => $user->getAuthIdentifier(),
            ]);
            return back()->withErrors([
                'error' => 'Impossible de créer une résidence. Votre compte propriétaire n\'est pas correctement configuré. Veuillez contacter le support.',
            ])->withInput();
        }

        // Ajouter automatiquement le proprietaireId de l'utilisateur connecté
        $data = $request->all();
        $data['proprietaireId'] = $proprietaireId;
        
        // Tronquer la description à 500 caractères pour éviter l'erreur de base de données
        if (isset($data['description']) && is_string($data['description']) && mb_strlen($data['description']) > 500) {
            $data['description'] = mb_substr($data['description'], 0, 500);
        }

        try {
            $this->residenceService->create($data);
            
            return redirect()->route('owner.residences.index')
                ->with('success', 'Résidence créée avec succès');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la création de la résidence', [
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);
            
            return back()->withErrors([
                'error' => $e->getMessage() ?: 'Erreur lors de la création de la résidence'
            ])->withInput();
        } catch (\Exception $e) {
            Log::error('Erreur création résidence', [
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors([
                'error' => 'Erreur lors de la création de la résidence'
            ])->withInput();
        }
    }

    /**
     * Afficher le formulaire d'édition
     */
    public function edit(string $id): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        try {
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour l\'édition de la résidence', [
                    'user_id' => $user->getAuthIdentifier(),
                    'residence_id' => $id,
                ]);
                abort(403, 'Accès non autorisé');
            }
            
            // Récupérer la résidence directement depuis l'API (plus efficace que findForUser)
            $residence = $this->apiService->getResidence($id);
            
            if (!$residence) {
                abort(404, 'Résidence non trouvée');
            }
            
            // Vérifier que la résidence appartient au propriétaire
            $residenceProprietaireId = null;
            
            if (isset($residence['proprietaire']) && is_array($residence['proprietaire'])) {
                $residenceProprietaireId = $residence['proprietaire']['id'] ?? $residence['proprietaire']['_id'] ?? null;
            } elseif (isset($residence['owner']) && is_array($residence['owner'])) {
                $residenceProprietaireId = $residence['owner']['id'] ?? $residence['owner']['_id'] ?? null;
            } else {
                $residenceProprietaireId = $residence['proprietaireId'] ?? $residence['ownerId'] ?? null;
            }
            
            // Comparer en string ET en int pour gérer les cas où l'un est string et l'autre int
            $matches = false;
            if ($residenceProprietaireId) {
                $matches = (
                    (string) $residenceProprietaireId === (string) $proprietaireId ||
                    (is_numeric($residenceProprietaireId) && is_numeric($proprietaireId) && (int) $residenceProprietaireId === (int) $proprietaireId)
                );
            }
            
            if (!$matches) {
                Log::warning('Accès non autorisé à la résidence pour édition', [
                    'residence_id' => $id,
                    'user_id' => $user->getAuthIdentifier(),
                    'expected_proprietaireId' => $proprietaireId,
                    'residence_proprietaireId' => $residenceProprietaireId,
                ]);
                abort(403, 'Vous n\'êtes pas autorisé à modifier cette résidence');
            }
            
            // Mapper la résidence au format attendu par le frontend
            $mappedResidence = ResidenceMapper::fromApi($residence);

            return Inertia::render('Owner/Residences/Edit', [
                'residence' => $mappedResidence,
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération de la résidence pour édition', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            abort(404, 'Résidence non trouvée');
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération de la résidence pour édition', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(404, 'Résidence non trouvée');
        }
    }

    /**
     * Mettre à jour une résidence
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        // Récupérer le proprietaireId réel depuis les données utilisateur
        $proprietaireId = $this->getProprietaireId($user);
        
        if (!$proprietaireId) {
            Log::error('Impossible de récupérer le proprietaireId pour la mise à jour de la résidence', [
                'user_id' => $user->getAuthIdentifier(),
                'residence_id' => $id,
            ]);
            abort(403, 'Accès non autorisé');
        }
        
        // Vérifier que la résidence appartient au propriétaire (plus efficace que findForUser)
        $residence = $this->apiService->getResidence($id);
        
        if (!$residence) {
            abort(404, 'Résidence non trouvée');
        }
        
        // Vérifier la propriété
        $residenceProprietaireId = null;
        
        if (isset($residence['proprietaire']) && is_array($residence['proprietaire'])) {
            $residenceProprietaireId = $residence['proprietaire']['id'] ?? $residence['proprietaire']['_id'] ?? null;
        } elseif (isset($residence['owner']) && is_array($residence['owner'])) {
            $residenceProprietaireId = $residence['owner']['id'] ?? $residence['owner']['_id'] ?? null;
        } else {
            $residenceProprietaireId = $residence['proprietaireId'] ?? $residence['ownerId'] ?? null;
        }
        
        $matches = false;
        if ($residenceProprietaireId) {
            $matches = (
                (string) $residenceProprietaireId === (string) $proprietaireId ||
                (is_numeric($residenceProprietaireId) && is_numeric($proprietaireId) && (int) $residenceProprietaireId === (int) $proprietaireId)
            );
        }
        
        if (!$matches) {
            Log::warning('Accès non autorisé à la résidence pour mise à jour', [
                'residence_id' => $id,
                'user_id' => $user->getAuthIdentifier(),
                'expected_proprietaireId' => $proprietaireId,
                'residence_proprietaireId' => $residenceProprietaireId,
            ]);
            abort(403, 'Vous n\'êtes pas autorisé à modifier cette résidence');
        }

        try {
            // Récupérer les données et tronquer la description si nécessaire
            $data = $request->all();
            if (isset($data['description']) && is_string($data['description']) && mb_strlen($data['description']) > 500) {
                $data['description'] = mb_substr($data['description'], 0, 500);
            }
            
            $this->residenceService->update($id, $data);
            
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
            
            return redirect()->route('owner.residences.index')
                ->with('success', 'Résidence mise à jour avec succès');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la mise à jour de la résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'context' => $e->getContext(),
            ]);
            
            $errorMessage = $e->getMessage();
            $context = $e->getContext();
            $statusCode = $context['status'] ?? null;
            
            if ($statusCode === 403 || str_contains(strtolower($errorMessage), 'autorisé') || str_contains(strtolower($errorMessage), 'propriétaire')) {
                $errorMessage = 'Impossible de modifier cette résidence. Vous n\'êtes pas autorisé à effectuer cette action.';
            }
            
            return back()->withErrors([
                'error' => $errorMessage ?: 'Erreur lors de la mise à jour de la résidence'
            ])->withInput();
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors([
                'error' => 'Erreur lors de la mise à jour de la résidence'
            ])->withInput();
        }
    }

    /**
     * Supprimer une résidence
     * Vérification de propriété : la résidence doit apparaître dans la liste renvoyée par l'API avec le token propriétaire (même logique que l'index).
     */
    public function destroy(string $id)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        // Liste des résidences du propriétaire (API avec token propriétaire = uniquement les siennes)
        $ownerResidences = $this->residenceService->getResidencesForUser($user);
        $belongsToOwner = collect($ownerResidences)->contains(function ($r) use ($id) {
            $rid = $r['id'] ?? $r['_id'] ?? null;
            return $rid && (string) $rid === (string) $id;
        });

        if (!$belongsToOwner) {
            Log::warning('Accès non autorisé à la résidence pour suppression', [
                'residence_id' => $id,
                'user_id' => $user->getAuthIdentifier(),
            ]);
            abort(403, 'Vous n\'êtes pas autorisé à supprimer cette résidence');
        }

        // Suppression via token admin (propriété déjà vérifiée ci-dessus)
        // L'API NestJS n'autorise souvent DELETE /residences/:id qu'avec le token admin.
        try {
            $deleted = $this->apiService->deleteResidence($id);
            
            if ($deleted) {
                return redirect()->route('owner.residences.index')
                    ->with('success', 'Résidence supprimée avec succès');
            }
            
            return redirect()->route('owner.residences.index')
                ->with('error', 'Erreur lors de la suppression');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la suppression de la résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('owner.residences.index')
                ->with('error', $e->getMessage() ?: 'Erreur lors de la suppression de la résidence');
        } catch (\Exception $e) {
            Log::error('Erreur suppression résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('owner.residences.index')
                ->with('error', 'Erreur lors de la suppression de la résidence');
        }
    }

    /**
     * Activer ou désactiver une résidence (bascule isActive)
     */
    public function toggleActive(string $id)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        $proprietaireId = $this->getProprietaireId($user);
        if (!$proprietaireId) {
            Log::error('Impossible de récupérer le proprietaireId pour toggle résidence', [
                'user_id' => $user->getAuthIdentifier(),
                'residence_id' => $id,
            ]);
            abort(403, 'Accès non autorisé');
        }

        $residence = $this->apiService->getResidence($id);
        if (!$residence) {
            abort(404, 'Résidence non trouvée');
        }

        $residenceProprietaireId = null;
        if (isset($residence['proprietaire']) && is_array($residence['proprietaire'])) {
            $residenceProprietaireId = $residence['proprietaire']['id'] ?? $residence['proprietaire']['_id'] ?? null;
        } elseif (isset($residence['owner']) && is_array($residence['owner'])) {
            $residenceProprietaireId = $residence['owner']['id'] ?? $residence['owner']['_id'] ?? null;
        } else {
            $residenceProprietaireId = $residence['proprietaireId'] ?? $residence['ownerId'] ?? null;
        }

        $matches = $residenceProprietaireId && (
            (string) $residenceProprietaireId === (string) $proprietaireId
            || (is_numeric($residenceProprietaireId) && is_numeric($proprietaireId) && (int) $residenceProprietaireId === (int) $proprietaireId)
        );
        if (!$matches) {
            Log::warning('Accès non autorisé à la résidence pour toggle active', [
                'residence_id' => $id,
                'user_id' => $user->getAuthIdentifier(),
            ]);
            abort(403, 'Vous n\'êtes pas autorisé à modifier cette résidence');
        }

        $currentActive = $residence['isActive'] ?? $residence['is_active'] ?? $residence['isAvailable'] ?? true;
        $newActive = !$currentActive;

        try {
            $this->apiService->updateResidence($id, ['isActive' => $newActive]);
            $message = $newActive ? 'Résidence activée.' : 'Résidence désactivée.';
            return redirect()->route('owner.residences.show', $id)->with('success', $message);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors du toggle active résidence', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('owner.residences.show', $id)
                ->with('error', $e->getMessage() ?: 'Erreur lors de la mise à jour.');
        } catch (\Exception $e) {
            Log::error('Erreur toggle active résidence', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->route('owner.residences.show', $id)
                ->with('error', 'Erreur lors de la mise à jour.');
        }
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

        // Calculer le taux d'occupation (simplifié - basé sur les réservations confirmées)
        $occupationRate = 0;
        if ($totalBookings > 0) {
            $occupationRate = round(($confirmedBookings / $totalBookings) * 100, 1);
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
        $mapped = [];
        
        foreach ($bookings as $booking) {
            // Extraire le nom du client
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
            }
            
            // Extraire les dates
            $startDate = $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? null;
            $endDate = $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null;
            
            $datesFormatted = '';
            if ($startDate && $endDate) {
                try {
                    $start = new \DateTime($startDate);
                    $end = new \DateTime($endDate);
                    $datesFormatted = $start->format('d M Y') . ' - ' . $end->format('d M Y');
                } catch (\Exception $e) {
                    $datesFormatted = $startDate . ' - ' . $endDate;
                }
            }
            
            // Vérifier si la date de fin est passée
            $isStayCompleted = false;
            if ($endDate) {
                try {
                    $today = new \DateTimeImmutable('today');
                    $end = new \DateTimeImmutable($endDate);
                    $isStayCompleted = $today > $end;
                    
                    // Log pour déboguer
                    Log::info('Vérification date de fin réservation (espace propriétaire)', [
                        'booking_id' => $booking['id'] ?? $booking['_id'] ?? null,
                        'endDate' => $endDate,
                        'today' => $today->format('Y-m-d'),
                        'end_date_formatted' => $end->format('Y-m-d'),
                        'isStayCompleted' => $isStayCompleted,
                        'comparison' => $today > $end ? 'today > end' : 'today <= end',
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Erreur lors de la vérification de la date de fin', [
                        'booking_id' => $booking['id'] ?? $booking['_id'] ?? null,
                        'endDate' => $endDate,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Formater le statut de manière cohérente avec les autres pages.
            $rawStatus = (string) ($booking['status'] ?? 'pending');
            $statusLower = strtolower(trim($rawStatus));
            $statusUpper = strtoupper($rawStatus);
            $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
            $checkOutAt = $booking['checkOutAt'] ?? $booking['check_out_at'] ?? null;
            $createdAt = $booking['createdAt'] ?? $booking['created_at'] ?? null;
            $ownerConfirmedFlag = $booking['ownerConfirmed'] ?? $booking['owner_confirmed'] ?? null;

            $isOwnerConfirmed = !empty($ownerConfirmedAt)
                && strtolower((string) $ownerConfirmedAt) !== 'null'
                && strtolower((string) $ownerConfirmedAt) !== 'undefined';
            if (!$isOwnerConfirmed && is_bool($ownerConfirmedFlag)) {
                $isOwnerConfirmed = $ownerConfirmedFlag;
            }
            if (!$isOwnerConfirmed && is_numeric($ownerConfirmedFlag)) {
                $isOwnerConfirmed = ((int) $ownerConfirmedFlag) === 1;
            }

            $isPaymentValidated = false;
            $payments = $booking['payments'] ?? [];
            if (is_array($payments)) {
                foreach ($payments as $payment) {
                    $paymentStatus = strtolower(trim((string) ($payment['status'] ?? '')));
                    if (in_array($paymentStatus, ['completed', 'paid', 'validated', 'success', 'succeeded'], true)) {
                        $isPaymentValidated = true;
                        break;
                    }
                }
            }

            // Statut canonique exploitable côté frontend.
            $statusCanonical = 'pending';
            if (!empty($checkOutAt) || $isStayCompleted) {
                $statusCanonical = 'completed';
            } elseif ($statusUpper === 'AWAITING_PAYMENT' || $statusLower === 'awaitingpayment') {
                $statusCanonical = 'awaiting_payment';
            } elseif ($isPaymentValidated) {
                $statusCanonical = 'paid';
            } elseif ($isOwnerConfirmed) {
                $statusCanonical = 'confirmed';
            } elseif (in_array($statusLower, ['paid', 'payé', 'paye'], true)) {
                $statusCanonical = 'paid';
            } elseif (in_array($statusLower, ['cancelled', 'canceled', 'annulee', 'annulée'], true)) {
                $statusCanonical = 'cancelled';
            } elseif (in_array($statusLower, ['failed', 'echec', 'échec', 'echoue', 'échoué'], true)) {
                $statusCanonical = 'failed';
            } elseif (in_array($statusLower, ['expired', 'expirée', 'expiree'], true)) {
                $statusCanonical = 'expired';
            } elseif (in_array($statusLower, ['completed', 'terminee', 'terminée'], true)) {
                $statusCanonical = 'completed';
            } elseif (in_array($statusLower, ['confirmed', 'confirmee', 'confirmée'], true)) {
                $statusCanonical = 'confirmed';
            } else {
                $statusCanonical = 'pending';
            }

            // Pending expiré après 5 minutes.
            if ($statusCanonical === 'pending' && !empty($createdAt)) {
                try {
                    $createdAtTime = new \DateTimeImmutable((string) $createdAt);
                    $now = new \DateTimeImmutable();
                    if (($now->getTimestamp() - $createdAtTime->getTimestamp()) > 5 * 60) {
                        $statusCanonical = 'expired';
                    }
                } catch (\Exception $e) {
                    // Garder pending si la date est invalide.
                }
            }

            $statusFormatted = match ($statusCanonical) {
                'confirmed' => 'Confirmée',
                'paid' => 'Payée',
                'awaiting_payment' => 'En attente de paiement',
                'cancelled' => 'Annulée',
                'failed' => 'Échouée',
                'expired' => 'Expirée',
                'completed' => 'Terminée',
                default => 'En attente',
            };
            
            $mapped[] = [
                'id' => $booking['id'] ?? $booking['_id'] ?? null,
                'customer' => $customerName,
                'dates' => $datesFormatted,
                'startDate' => $startDate, // Ajouter la date de début brute
                'endDate' => $endDate, // Ajouter la date de fin brute
                'amount' => (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0),
                'status' => $statusFormatted,
                'statusRaw' => $statusCanonical,
            ];
        }
        
        // Trier par date de début (plus récentes en premier)
        usort($mapped, function($a, $b) {
            return $b['id'] <=> $a['id']; // Simplifié - à améliorer avec vraie date
        });
        
        return $mapped;
    }

    /**
     * Récupérer les dates bloquées d'une résidence
     */
    public function getBlockedDates(string $id): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        try {
            $residence = $this->apiService->getResidence($id);
            
            if (!$residence) {
                return response()->json(['error' => 'Résidence non trouvée'], 404);
            }
            
            $proprietaireId = $this->getProprietaireId($user);
            
            // Vérifier que la résidence appartient au propriétaire
            $residenceOwnerId = $residence['proprietaireId'] ?? $residence['proprietaire']['id'] ?? null;
            if ($residenceOwnerId !== $proprietaireId) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            // Récupérer les dates bloquées depuis l'endpoint dédié (peut retourner [] si l'endpoint n'existe pas)
            $blockedDates = $this->apiService->getResidenceBlockedDates($id);
            
            // Convertir les périodes bloquées en liste de dates individuelles
            $datesList = [];
            foreach ($blockedDates as $blockedDate) {
                $startDate = new \DateTime($blockedDate['startDate'] ?? $blockedDate['start_date'] ?? '');
                $endDate = new \DateTime($blockedDate['endDate'] ?? $blockedDate['end_date'] ?? '');
                $startDate->setTime(0, 0, 0);
                $endDate->setTime(0, 0, 0);
                
                $current = clone $startDate;
                while ($current <= $endDate) {
                    $datesList[] = $current->format('Y-m-d');
                    $current->modify('+1 day');
                }
            }
            
            return response()->json([
                'blockedDates' => array_unique($datesList)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération dates bloquées résidence', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Erreur lors de la récupération'], 500);
        }
    }

    /**
     * Bloquer une date pour une résidence
     */
    public function blockDate(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        $request->validate([
            'date' => 'required|date|date_format:Y-m-d'
        ]);

        try {
            $residence = $this->apiService->getResidence($id);
            $proprietaireId = $this->getProprietaireId($user);
            
            // Vérifier que la résidence appartient au propriétaire
            $residenceOwnerId = $residence['proprietaireId'] ?? $residence['proprietaire']['id'] ?? null;
            if ($residenceOwnerId !== $proprietaireId) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            // Vérifier qu'il n'y a pas de réservation sur cette date
            $date = $request->input('date');
            $dateObj = new \DateTime($date);
            
            // Récupérer toutes les réservations de cette résidence
            $allBookings = $this->apiService->getBookings(['residenceId' => $id]);
            
            // Vérifier si la date est dans l'intervalle d'une réservation
            foreach ($allBookings as $booking) {
                $startDate = $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? null;
                $endDate = $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null;
                
                if ($startDate && $endDate) {
                    try {
                        $start = new \DateTime($startDate);
                        $end = new \DateTime($endDate);
                        $start->setTime(0, 0, 0);
                        $end->setTime(0, 0, 0);
                        $dateObj->setTime(0, 0, 0);
                        
                        // Vérifier si la date est dans l'intervalle [startDate, endDate]
                        if ($dateObj >= $start && $dateObj <= $end) {
                            // Ignorer les réservations annulées
                            $status = strtolower($booking['status'] ?? '');
                            if ($status !== 'cancelled' && $status !== 'annulee' && $status !== 'annulée') {
                                return response()->json([
                                    'error' => 'Impossible de bloquer une date avec une réservation existante'
                                ], 400);
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignorer les erreurs de parsing de date
                        Log::warning('Erreur parsing date réservation', [
                            'booking_id' => $booking['id'] ?? null,
                            'startDate' => $startDate,
                            'endDate' => $endDate,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Utiliser l'endpoint dédié pour bloquer une date
            $startDate = new \DateTime($date);
            $endDate = new \DateTime($date);
            $startDate->setTime(0, 0, 0);
            $endDate->setTime(23, 59, 59);
            
            $blockData = [
                'startDate' => $startDate->format('Y-m-d\TH:i:s\Z'),
                'endDate' => $endDate->format('Y-m-d\TH:i:s\Z'),
                'reason' => 'Bloqué par le propriétaire'
            ];
            
            $this->apiService->blockResidenceDates($id, $blockData);

            // Récupérer les dates bloquées mises à jour pour la réponse
            $updatedBlockedDates = $this->apiService->getResidenceBlockedDates($id);
            $datesList = [];
            foreach ($updatedBlockedDates as $blockedDate) {
                $startDate = new \DateTime($blockedDate['startDate'] ?? $blockedDate['start_date'] ?? '');
                $endDate = new \DateTime($blockedDate['endDate'] ?? $blockedDate['end_date'] ?? '');
                $startDate->setTime(0, 0, 0);
                $endDate->setTime(0, 0, 0);
                
                $current = clone $startDate;
                while ($current <= $endDate) {
                    $datesList[] = $current->format('Y-m-d');
                    $current->modify('+1 day');
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Date bloquée avec succès',
                'blockedDates' => array_unique($datesList)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur blocage date résidence', [
                'id' => $id,
                'date' => $request->input('date'),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Erreur lors du blocage de la date'], 500);
        }
    }

    /**
     * Débloquer une date pour une résidence
     */
    public function unblockDate(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        // Pour DELETE, lire depuis query params ou body
        if (!$request->has('date') && $request->query('date')) {
            $request->merge(['date' => $request->query('date')]);
        }
        
        $request->validate([
            'date' => 'required|date|date_format:Y-m-d'
        ]);
        
        $date = $request->input('date');

        try {
            $residence = $this->apiService->getResidence($id);
            $proprietaireId = $this->getProprietaireId($user);
            
            // Vérifier que la résidence appartient au propriétaire
            $residenceOwnerId = $residence['proprietaireId'] ?? $residence['proprietaire']['id'] ?? null;
            if ($residenceOwnerId !== $proprietaireId) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            // Récupérer les dates bloquées pour trouver celle à supprimer
            $blockedDates = $this->apiService->getResidenceBlockedDates($id);
            
            // Trouver la période bloquée qui contient cette date
            $dateObj = new \DateTime($date);
            $dateObj->setTime(0, 0, 0);
            
            foreach ($blockedDates as $blockedDate) {
                $startDate = new \DateTime($blockedDate['startDate'] ?? $blockedDate['start_date'] ?? '');
                $endDate = new \DateTime($blockedDate['endDate'] ?? $blockedDate['end_date'] ?? '');
                $startDate->setTime(0, 0, 0);
                $endDate->setTime(0, 0, 0);
                
                if ($dateObj >= $startDate && $dateObj <= $endDate) {
                    // Supprimer cette période bloquée
                    $blockedDateId = $blockedDate['id'] ?? $blockedDate['_id'] ?? null;
                    if ($blockedDateId) {
                        $this->apiService->unblockResidenceDates($id, $blockedDateId);
                    }
                    break;
                }
            }

            // Récupérer les dates bloquées mises à jour pour la réponse
            $updatedBlockedDates = $this->apiService->getResidenceBlockedDates($id);
            $datesList = [];
            foreach ($updatedBlockedDates as $blockedDate) {
                $startDate = new \DateTime($blockedDate['startDate'] ?? $blockedDate['start_date'] ?? '');
                $endDate = new \DateTime($blockedDate['endDate'] ?? $blockedDate['end_date'] ?? '');
                $startDate->setTime(0, 0, 0);
                $endDate->setTime(0, 0, 0);
                
                $current = clone $startDate;
                while ($current <= $endDate) {
                    $datesList[] = $current->format('Y-m-d');
                    $current->modify('+1 day');
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Date débloquée avec succès',
                'blockedDates' => array_unique($datesList)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur déblocage date résidence', [
                'id' => $id,
                'date' => $request->input('date'),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Erreur lors du déblocage de la date'], 500);
        }
    }
}
