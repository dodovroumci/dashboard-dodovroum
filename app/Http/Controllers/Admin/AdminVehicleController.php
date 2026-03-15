<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVehicleRequest;
use App\Http\Requests\Admin\UpdateVehicleRequest;
use App\Services\DodoVroumApi\VehicleService;
use App\Services\DodoVroumApi\UserService;
use App\Services\DodoVroumApi\BookingService;
use App\Services\DodoVroumApiService;
use App\Exceptions\DodoVroumApiException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdminVehicleController extends Controller
{
    public function __construct(
        protected VehicleService $vehicleService,
        protected UserService $userService,
        protected BookingService $bookingService,
        protected DodoVroumApiService $apiService
    ) {
    }

    /**
     * Afficher la liste des véhicules
     */
    public function index(Request $request): Response
    {
        try {
            $filters = $request->only(['search', 'type', 'status']);
            
            Log::debug('Filtres reçus dans index véhicules', [
                'filters' => $filters,
            ]);
            
            // L'API supporte maintenant le filtre 'status' basé sur les réservations actives
            // On passe 'status' à l'API, mais on filtre 'type' côté serveur car l'API ne l'accepte peut-être pas
            $apiFilters = array_filter([
                'search' => $filters['search'] ?? null,
                'status' => $filters['status'] ?? null, // L'API gère maintenant ce filtre
            ], function($value) {
                return $value !== null && $value !== '';
            });
            
            // Récupérer les véhicules mappés depuis le service
            $vehicles = $this->vehicleService->allMapped($apiFilters);
            
            // Appliquer les filtres côté serveur si nécessaire
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $vehicles = array_filter($vehicles, function($vehicle) use ($search) {
                    $name = strtolower($vehicle['name'] ?? '');
                    $brand = strtolower($vehicle['brand'] ?? '');
                    $model = strtolower($vehicle['model'] ?? '');
                    return strpos($name, $search) !== false || 
                           strpos($brand, $search) !== false || 
                           strpos($model, $search) !== false;
                });
                $vehicles = array_values($vehicles);
            }
            
            // Filtrer par type côté serveur
            if (!empty($filters['type'])) {
                $filterType = strtolower(trim($filters['type']));
                $vehicles = array_filter($vehicles, function($vehicle) use ($filterType) {
                    $type = strtolower(trim($vehicle['type'] ?? $vehicle['typeVehicule'] ?? ''));
                    
                    // Comparaison exacte
                    if ($type === $filterType) {
                        return true;
                    }
                    
                    // Comparaison avec le nom
                    $name = strtolower($vehicle['name'] ?? '');
                    if (strpos($name, $filterType) !== false) {
                        return true;
                    }
                    
                    return false;
                });
                $vehicles = array_values($vehicles);
                
                Log::debug('Filtre par type appliqué véhicules', [
                    'filter_type' => $filterType,
                    'vehicles_count_after_filter' => count($vehicles),
                ]);
            }
            
            // Calculer les statistiques AVANT la pagination
            // Récupérer toutes les véhicules non mappés pour les stats
            $allVehiclesRaw = $this->apiService->getVehicles([]);
            
            $totalVehicles = count($allVehiclesRaw);
            $availableVehicles = 0;
            $totalBookings = 0;
            $monthRevenue = 0;
            
            $currentMonth = date('Y-m');
            
            foreach ($allVehiclesRaw as $vehicle) {
                if (($vehicle['available'] ?? $vehicle['isActive'] ?? true) === true) {
                    $availableVehicles++;
                }
            }
            
            // Récupérer les réservations pour calculer les stats
            try {
                $allBookings = $this->apiService->getBookings([]);
                
                foreach ($allBookings as $booking) {
                    // Vérifier si la réservation concerne un véhicule
                    if (isset($booking['vehicle']) || isset($booking['vehicleId']) || isset($booking['vehicle_id']) || isset($booking['voiture'])) {
                        $totalBookings++;
                        $startDate = $booking['startDate'] ?? $booking['start_date'] ?? null;
                        if ($startDate && strpos($startDate, $currentMonth) === 0) {
                            $monthRevenue += (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erreur lors du calcul des statistiques véhicules', ['error' => $e->getMessage()]);
            }
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            $total = count($vehicles);
            $offset = ($currentPage - 1) * $perPage;
            $items = array_slice($vehicles, $offset, $perPage);
            
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
            
            Log::debug('AdminVehicleController::index', [
                'filters' => $filters,
                'vehicles_count' => $total,
                'current_page' => $currentPage,
                'per_page' => $perPage,
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération des véhicules', [
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);
            $vehicles = [];
            $paginator = new LengthAwarePaginator([], 0, 15, 1);
            $totalVehicles = 0;
            $availableVehicles = 0;
            $totalBookings = 0;
            $monthRevenue = 0;
        } catch (\Exception $e) {
            Log::error('Erreur récupération véhicules', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $vehicles = [];
            $paginator = new LengthAwarePaginator([], 0, 15, 1);
            $totalVehicles = 0;
            $availableVehicles = 0;
            $totalBookings = 0;
            $monthRevenue = 0;
        }

        // Récupérer les types de véhicules depuis l'API
        try {
            $vehicleTypes = $this->vehicleService->getTypes();
        } catch (\Exception $e) {
            Log::warning('Erreur récupération types de véhicules', ['error' => $e->getMessage()]);
            $vehicleTypes = [];
        }

        return Inertia::render('Vehicles/Index', [
            'vehicles' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => $filters ?? [],
            'vehicleTypes' => $vehicleTypes,
            'stats' => [
                'totalVehicles' => $totalVehicles ?? 0,
                'availableVehicles' => $availableVehicles ?? 0,
                'totalBookings' => $totalBookings ?? 0,
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
            $vehicleTypes = $this->vehicleService->getTypes();
            $owners = $this->userService->getOwners();
            
            // Formater les propriétaires pour le frontend
            $formattedOwners = array_map(function($user) {
                $firstName = $user['firstName'] ?? $user['prenom'] ?? '';
                $lastName = $user['lastName'] ?? $user['nom'] ?? $user['name'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName);
                if (empty($fullName)) {
                    $fullName = $user['email'] ?? 'Propriétaire inconnu';
                }
                
                return [
                    'id' => $user['id'] ?? $user['_id'] ?? null,
                    'name' => $fullName,
                    'email' => $user['email'] ?? null,
                ];
            }, $owners);
            
            Log::debug('Propriétaires récupérés pour création véhicule', [
                'owners_count' => count($formattedOwners),
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération des données pour création véhicule', [
                'error' => $e->getMessage(),
            ]);
            $vehicleTypes = [];
            $formattedOwners = [];
        } catch (\Exception $e) {
            Log::error('Erreur récupération données pour création véhicule', [
                'error' => $e->getMessage(),
            ]);
            $vehicleTypes = [];
            $formattedOwners = [];
        }
        
        return Inertia::render('Vehicles/Create', [
            'vehicleTypes' => $vehicleTypes,
            'owners' => $formattedOwners,
        ]);
    }

    /**
     * Afficher les détails d'un véhicule
     */
    public function show(string $id): Response|RedirectResponse
    {
        try {
            $vehicle = $this->vehicleService->find($id);
            
            if (!$vehicle) {
                return redirect()->route('admin.vehicles.index')
                    ->with('error', 'Véhicule non trouvé');
            }
            
            // Log pour déboguer les images
            $imagesCount = 0;
            $imagesPreview = null;
            if (isset($vehicle['images']) && is_array($vehicle['images'])) {
                $imagesCount = count($vehicle['images']);
                // Prendre les 3 premières URLs pour le log (sans exposer toutes les URLs)
                $imagesPreview = array_slice($vehicle['images'], 0, 3);
            }
            
            Log::debug('AdminVehicleController::show - Véhicule récupéré', [
                'vehicle_id' => $id,
                'has_images' => isset($vehicle['images']),
                'images_count' => $imagesCount,
                'images_preview' => $imagesPreview,
                'images_type' => isset($vehicle['images']) ? gettype($vehicle['images']) : 'not_set',
                'vehicle_keys' => array_keys($vehicle),
            ]);
            
            // Récupérer les réservations liées à ce véhicule
            // Utiliser la méthode dédiée qui gère la pagination et le filtrage
            $vehicleBookings = $this->bookingService->getBookingsForVehicle($id);
            
            Log::debug('Réservations trouvées pour véhicule dans show()', [
                'vehicle_id' => $id,
                'vehicle_bookings_count' => count($vehicleBookings),
            ]);
            
            // Calculer les statistiques
            $stats = $this->calculateVehicleStats($vehicleBookings, $vehicle);
            
            // Mapper les réservations pour le frontend
            $mappedBookings = $this->mapBookingsForVehicle($vehicleBookings);
            
            // Récupérer les informations du propriétaire
            $owner = null;
            $proprietaireId = $vehicle['proprietaireId'] ?? $vehicle['ownerId'] ?? null;
            if ($proprietaireId) {
                try {
                    $owner = $this->userService->find($proprietaireId);
                    if ($owner) {
                        $vehicle['owner'] = $owner;
                        $vehicle['ownerName'] = $owner['name'] ?? $owner['nom'] ?? $owner['email'] ?? 'Propriétaire inconnu';
                    }
                } catch (\Exception $e) {
                    Log::warning('Impossible de récupérer le propriétaire du véhicule', [
                        'vehicle_id' => $id,
                        'proprietaireId' => $proprietaireId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération du véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);
            return redirect()->route('admin.vehicles.index')
                ->with('error', 'Erreur lors de la récupération du véhicule');
        } catch (\Exception $e) {
            Log::error('Erreur récupération véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('admin.vehicles.index')
                ->with('error', 'Erreur lors de la récupération du véhicule');
        }

        return Inertia::render('Vehicles/Show', [
            'vehicle' => $vehicle,
            'stats' => $stats,
            'bookings' => $mappedBookings,
        ]);
    }
    
    /**
     * Calculer les statistiques d'un véhicule
     */
    private function calculateVehicleStats(array $bookings, array $vehicle): array
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
            if ($status === 'confirmed' || $status === 'confirmee') {
                $confirmedBookings++;
            } elseif ($status === 'cancelled' || $status === 'annulee') {
                $cancelledBookings++;
            } elseif ($status === 'completed' || $status === 'terminee') {
                $completedBookings++;
            }
            
            // Récupérer les notes/avis si disponibles
            if (isset($booking['review']) && isset($booking['review']['rating'])) {
                $totalReviews++;
                $averageRating += (float) $booking['review']['rating'];
            }
        }
        
        if ($totalReviews > 0) {
            $averageRating = round($averageRating / $totalReviews, 1);
        } else {
            // Utiliser la note du véhicule si disponible
            $averageRating = (float) ($vehicle['notation']['note'] ?? $vehicle['rating'] ?? 0);
            $totalReviews = (int) ($vehicle['notation']['avis'] ?? $vehicle['reviewsCount'] ?? 0);
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
    private function mapBookingsForVehicle(array $bookings): array
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
            
            // Format status
            $statusRaw = $booking['status'] ?? 'pending';
            $status = 'En attente';
            if (strtolower($statusRaw) === 'confirmed' || strtolower($statusRaw) === 'confirmee') {
                $status = 'Confirmée';
            } elseif (strtolower($statusRaw) === 'cancelled') {
                $status = 'Annulée';
            } elseif (strtolower($statusRaw) === 'completed') {
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
            $vehicle = $this->vehicleService->find($id);
            
            if (!$vehicle) {
                return redirect()->route('admin.vehicles.index')
                    ->with('error', 'Véhicule non trouvé');
            }
            
            $vehicleTypes = $this->vehicleService->getTypes();
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération du véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('admin.vehicles.index')
                ->with('error', 'Erreur lors de la récupération du véhicule');
        } catch (\Exception $e) {
            Log::error('Erreur récupération véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('admin.vehicles.index')
                ->with('error', 'Erreur lors de la récupération du véhicule');
        }

        return Inertia::render('Vehicles/Edit', [
            'vehicle' => $vehicle,
            'vehicleTypes' => $vehicleTypes ?? [],
        ]);
    }

    /**
     * Créer un nouveau véhicule
     */
    public function store(StoreVehicleRequest $request)
    {
        Log::info('AdminVehicleController::store appelé', [
            'method' => $request->method(),
            'url' => $request->url(),
            'has_data' => $request->hasAny(['name', 'brand', 'model']),
            'all_data_keys' => array_keys($request->all()),
            'raw_data' => $request->all(),
        ]);
        
        try {
            // Tronquer la description à 500 caractères si nécessaire
            $validated = $request->validated();
            
            Log::info('Données validées pour création véhicule', [
                'validated_keys' => array_keys($validated),
                'validated_data' => $validated,
                'proprietaireId' => $validated['proprietaireId'] ?? 'NOT_SET',
            ]);
            
            // 🛡️ BLINDAGE : Vérifier que proprietaireId est valide avant d'appeler le service
            $proprietaireId = $validated['proprietaireId'] ?? null;
            if (empty($proprietaireId) || $proprietaireId === "1" || $proprietaireId === 1) {
                $errorMessage = "Erreur : Aucun propriétaire valide sélectionné. Veuillez sélectionner un propriétaire dans le formulaire.";
                Log::error('Blindage AdminVehicleController::store - proprietaireId invalide', [
                    'proprietaireId_received' => $proprietaireId,
                    'validated_keys' => array_keys($validated),
                    'error' => $errorMessage,
                ]);
                return redirect()->back()
                    ->withErrors(['proprietaireId' => $errorMessage])
                    ->withInput();
            }
            
            if (isset($validated['description']) && is_string($validated['description']) && mb_strlen($validated['description']) > 500) {
                $validated['description'] = mb_substr($validated['description'], 0, 500);
            }
            
            Log::info('Tentative de création de véhicule', [
                'data_keys' => array_keys($validated),
                'proprietaireId' => $validated['proprietaireId'] ?? null,
            ]);
            
            $this->vehicleService->create($validated);
            
            return redirect()->route('admin.vehicles.index')
                ->with('success', 'Véhicule créé avec succès');
        } catch (DodoVroumApiException $e) {
            $errorMessage = $e->getMessage();
            $context = $e->getContext();
            
            Log::error('Erreur API lors de la création du véhicule', [
                'error' => $errorMessage,
                'context' => $context,
                'validated_data' => $request->validated(),
            ]);
            
            // Extraire un message d'erreur plus lisible
            if (isset($context['error_body']) && is_array($context['error_body'])) {
                $apiMessage = $context['error_body']['message'] ?? $context['error_body']['error'] ?? null;
                if ($apiMessage) {
                    if (is_array($apiMessage)) {
                        $errorMessage = implode(', ', $apiMessage);
                    } else {
                        $errorMessage = $apiMessage;
                    }
                }
            }
            
            return back()->withErrors([
                'error' => $errorMessage ?: 'Erreur lors de la création du véhicule. Veuillez vérifier que tous les champs sont correctement remplis.'
            ])->withInput();
        } catch (\Exception $e) {
            Log::error('Erreur création véhicule', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'validated_data' => $request->validated(),
            ]);
            
            return back()->withErrors([
                'error' => 'Erreur lors de la création du véhicule : ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Mettre à jour un véhicule
     */
    public function update(UpdateVehicleRequest $request, string $id)
    {
        // 🔍 Log de debug pour confirmer que le contrôleur est appelé
        Log::info('🔵 AdminVehicleController::update - Entrée dans la méthode', [
            'id' => $id,
            'method' => $request->method(),
            'url' => $request->url(),
            'has_data' => $request->hasAny(['name', 'brand', 'model']),
            'all_data_keys' => array_keys($request->all()),
        ]);
        
        try {
            // Tronquer la description à 500 caractères si nécessaire
            $validated = $request->validated();
            
            Log::info('🔵 AdminVehicleController::update - Données validées', [
                'id' => $id,
                'validated_keys' => array_keys($validated),
            ]);
            if (isset($validated['description']) && is_string($validated['description']) && mb_strlen($validated['description']) > 500) {
                $validated['description'] = mb_substr($validated['description'], 0, 500);
            }
            
            $updatedVehicle = $this->vehicleService->update($id, $validated);
            
            // 🔍 DEBUG : Vérifier ce que l'API a retourné
            Log::info('🔵 AdminVehicleController::update - Réponse API', [
                'vehicle_id' => $id,
                'title_in_response' => $updatedVehicle['title'] ?? $updatedVehicle['titre'] ?? $updatedVehicle['name'] ?? null,
                'response_keys' => array_keys($updatedVehicle),
            ]);
            
            // 🔄 Actualiser les offres combinées qui utilisent ce véhicule
            try {
                $comboOfferService = app(\App\Services\DodoVroumApi\ComboOfferService::class);
                $comboOfferService->refreshOffersForVehicle($id);
            } catch (\Exception $e) {
                Log::warning('Erreur lors de l\'actualisation des offres combinées après mise à jour véhicule', [
                    'vehicle_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            return redirect()->route('admin.vehicles.index')
                ->with('success', 'Véhicule mis à jour avec succès');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la mise à jour du véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);
            
            return back()->withErrors([
                'error' => $e->getMessage() ?: 'Erreur lors de la mise à jour du véhicule'
            ])->withInput();
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return back()->withErrors([
                'error' => 'Erreur lors de la mise à jour du véhicule'
            ])->withInput();
        }
    }

    /**
     * Supprimer un véhicule
     */
    /**
     * Vérifier si un véhicule a des réservations liées
     */
    public function checkBookings(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            // Utiliser la méthode dédiée qui gère la pagination et le filtrage
            $vehicleBookings = $this->bookingService->getBookingsForVehicle($id);
            
            // Vérifier aussi si le véhicule est utilisé dans des offres combinées
            $comboOffers = [];
            try {
                $allOffers = $this->apiService->getComboOffers([]);
                foreach ($allOffers as $offer) {
                    $vehicle = $offer['voiture'] ?? $offer['vehicle'] ?? null;
                    $vehicleId = null;
                    
                    if (is_array($vehicle) && isset($vehicle['id'])) {
                        $vehicleId = $vehicle['id'];
                    } elseif (isset($offer['vehicleId'])) {
                        $vehicleId = $offer['vehicleId'];
                    } elseif (isset($offer['vehicle_id'])) {
                        $vehicleId = $offer['vehicle_id'];
                    }
                    
                    if ($vehicleId && (string) $vehicleId === (string) $id) {
                        $comboOffers[] = $offer;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erreur lors de la vérification des offres combinées', [
                    'vehicle_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            Log::debug('checkBookings - Début vérification', [
                'vehicle_id' => $id,
                'vehicle_bookings_count' => count($vehicleBookings),
                'combo_offers_count' => count($comboOffers),
            ]);
            
            $hasBookings = count($vehicleBookings) > 0;
            $hasComboOffers = count($comboOffers) > 0;
            $hasBlockingRelations = $hasBookings || $hasComboOffers;
            
            Log::info('checkBookings - Résultat vérification', [
                'vehicle_id' => $id,
                'has_bookings' => $hasBookings,
                'bookings_count' => count($vehicleBookings),
                'has_combo_offers' => $hasComboOffers,
                'combo_offers_count' => count($comboOffers),
                'has_blocking_relations' => $hasBlockingRelations,
                'vehicle_bookings_ids' => array_map(function($b) {
                    return $b['id'] ?? $b['_id'] ?? 'unknown';
                }, $vehicleBookings),
                'combo_offers_ids' => array_map(function($o) {
                    return $o['id'] ?? $o['_id'] ?? 'unknown';
                }, $comboOffers),
            ]);
            
            // Préparer les détails des réservations pour l'affichage
            $bookingsDetails = [];
            if ($hasBookings) {
                foreach ($vehicleBookings as $booking) {
                    $startDate = $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? null;
                    $endDate = $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null;
                    $status = $booking['status'] ?? 'unknown';
                    
                    // Extraire le nom du client
                    $customerName = 'Client inconnu';
                    if (isset($booking['user']) && is_array($booking['user'])) {
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
                    
                    $bookingsDetails[] = [
                        'id' => $booking['id'] ?? $booking['_id'] ?? null,
                        'customer' => $customerName,
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                        'status' => $status,
                        'totalPrice' => $booking['totalPrice'] ?? $booking['total_price'] ?? 0,
                    ];
                }
            }
            
            // Construire le message d'erreur
            $messages = [];
            if ($hasBookings) {
                $messages[] = count($vehicleBookings) . " réservation(s) liée(s)";
            }
            if ($hasComboOffers) {
                $messages[] = count($comboOffers) . " offre(s) combinée(s) liée(s)";
            }
            $errorMessage = !empty($messages) 
                ? "Ce véhicule a " . implode(" et ", $messages) . ". La suppression n'est pas possible tant que des données sont liées."
                : null;
            
            Log::info('Vérification réservations et offres combinées pour véhicule', [
                'vehicle_id' => $id,
                'has_bookings' => $hasBookings,
                'bookings_count' => count($vehicleBookings),
                'has_combo_offers' => $hasComboOffers,
                'combo_offers_count' => count($comboOffers),
                'has_blocking_relations' => $hasBlockingRelations,
            ]);
            
            return response()->json([
                'hasBookings' => $hasBlockingRelations,
                'bookingsCount' => count($vehicleBookings),
                'comboOffersCount' => count($comboOffers),
                'bookings' => $bookingsDetails,
                'comboOffers' => array_map(function($offer) {
                    return [
                        'id' => $offer['id'] ?? $offer['_id'] ?? null,
                        'title' => $offer['titre'] ?? $offer['title'] ?? 'Offre sans nom',
                    ];
                }, $comboOffers),
                'message' => $errorMessage,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification des réservations', [
                'vehicle_id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'hasBookings' => false,
                'bookingsCount' => 0,
                'message' => null,
            ]);
        }
    }

    /**
     * Récupère les réservations liées à un véhicule spécifique
     * Supporte les objets imbriqués et les types de données mixtes
     * Version ultra-robuste qui explore toutes les profondeurs possibles de l'objet
     * 
     * @param string $id ID du véhicule (CUID NestJS)
     * @param array $allBookings Toutes les réservations
     * @return array Réservations filtrées
     */
    private function getBookingsByVehicleId(string $id, array $allBookings): array
    {
        $vehicleBookings = [];
        $debugInfo = [];
        
        foreach ($allBookings as $index => $booking) {
            $bookingVehicleId = null;
            $source = null;
            
            // 1. Check direct (CUID NestJS) - champs standards
            if (isset($booking['vehicleId']) && $booking['vehicleId'] !== null) {
                $bookingVehicleId = $booking['vehicleId'];
                $source = 'vehicleId (direct)';
            } elseif (isset($booking['vehicle_id']) && $booking['vehicle_id'] !== null) {
                $bookingVehicleId = $booking['vehicle_id'];
                $source = 'vehicle_id (direct)';
            } elseif (isset($booking['id_vehicule']) && $booking['id_vehicule'] !== null) {
                $bookingVehicleId = $booking['id_vehicule'];
                $source = 'id_vehicule (direct)';
            }
            
            // 2. Check imbriqué (Si Prisma fait un "include" - relation complète)
            if (!$bookingVehicleId && isset($booking['vehicle']) && $booking['vehicle'] !== null) {
                if (is_array($booking['vehicle'])) {
                    // Objet complet : { id: "...", brand: "...", ... }
                    $bookingVehicleId = $booking['vehicle']['id'] ?? $booking['vehicle']['_id'] ?? null;
                    $source = $bookingVehicleId ? 'vehicle.id (imbriqué)' : null;
                } elseif (is_string($booking['vehicle'])) {
                    // ID direct en string
                    $bookingVehicleId = $booking['vehicle'];
                    $source = 'vehicle (string)';
                }
            }
            
            // 3. Check alias Mapper (variantes possibles)
            if (!$bookingVehicleId) {
                if (isset($booking['vehiculeId']) && $booking['vehiculeId'] !== null) {
                    $bookingVehicleId = $booking['vehiculeId'];
                    $source = 'vehiculeId (alias)';
                } elseif (isset($booking['vehicule_id']) && $booking['vehicule_id'] !== null) {
                    $bookingVehicleId = $booking['vehicule_id'];
                    $source = 'vehicule_id (alias)';
                }
            }
            
            // Collecter les infos de debug pour toutes les réservations
            $debugInfo[] = [
                'booking_id' => $booking['id'] ?? $booking['_id'] ?? 'unknown',
                'has_vehicleId' => isset($booking['vehicleId']),
                'vehicleId_value' => $booking['vehicleId'] ?? null,
                'has_vehicle' => isset($booking['vehicle']),
                'vehicle_type' => isset($booking['vehicle']) ? gettype($booking['vehicle']) : 'not_set',
                'vehicle.id_value' => isset($booking['vehicle']) && is_array($booking['vehicle']) ? ($booking['vehicle']['id'] ?? null) : null,
                'detected_vehicle_id' => $bookingVehicleId,
                'detected_source' => $source,
                'matches_target' => $bookingVehicleId && (string) $bookingVehicleId === (string) $id,
            ];
            
            // Comparaison stricte en string pour éviter les bugs d'ID numériques
            if ($bookingVehicleId && (string) $bookingVehicleId === (string) $id) {
                $vehicleBookings[] = $booking;
                
                // Log détaillé pour la première correspondance trouvée
                if (count($vehicleBookings) === 1) {
                    Log::info('getBookingsByVehicleId - Première réservation trouvée', [
                        'vehicle_id' => $id,
                        'booking_id' => $booking['id'] ?? $booking['_id'] ?? null,
                        'booking_vehicle_id_source' => $source,
                        'booking_vehicle_id_value' => $bookingVehicleId,
                    ]);
                }
            }
        }
        
        // Log de debug complet pour toutes les réservations
        Log::info('getBookingsByVehicleId - Analyse complète', [
            'vehicle_id' => $id,
            'total_bookings' => count($allBookings),
            'vehicle_bookings_found' => count($vehicleBookings),
            'debug_info' => $debugInfo,
        ]);
        
        return array_values($vehicleBookings);
    }
    
    /**
     * Détecte la source de l'ID du véhicule dans une réservation
     * Utile pour le débogage
     */
    private function detectVehicleIdSource(array $booking): string
    {
        if (isset($booking['vehicleId'])) {
            return 'vehicleId (direct)';
        }
        if (isset($booking['vehicle_id'])) {
            return 'vehicle_id (direct)';
        }
        if (isset($booking['id_vehicule'])) {
            return 'id_vehicule (direct)';
        }
        if (isset($booking['vehicle']) && is_array($booking['vehicle'])) {
            return 'vehicle.id (imbriqué)';
        }
        if (isset($booking['vehicle']) && is_string($booking['vehicle'])) {
            return 'vehicle (string)';
        }
        return 'non trouvé';
    }

    public function destroy(string $id)
    {
        Log::info('AdminVehicleController::destroy appelé', [
            'vehicle_id' => $id,
            'method' => request()->method(),
        ]);
        
        try {
            $deleted = $this->vehicleService->delete($id);
            
            Log::info('AdminVehicleController::destroy - Résultat', [
                'vehicle_id' => $id,
                'deleted' => $deleted,
            ]);
            
            if ($deleted) {
                return redirect()->route('admin.vehicles.index')
                    ->with('success', 'Véhicule supprimé avec succès');
            }
            
            Log::warning('AdminVehicleController::destroy - Suppression échouée', [
                'vehicle_id' => $id,
                'deleted' => $deleted,
            ]);
            
            return redirect()->route('admin.vehicles.index')
                ->with('error', 'Erreur lors de la suppression du véhicule');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la suppression du véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);
            
            return redirect()->route('admin.vehicles.index')
                ->with('error', $e->getMessage() ?: 'Erreur lors de la suppression du véhicule');
        } catch (\Exception $e) {
            Log::error('Erreur suppression véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('admin.vehicles.index')
                ->with('error', 'Erreur lors de la suppression du véhicule : ' . $e->getMessage());
        }
    }
}
