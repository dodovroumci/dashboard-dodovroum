<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\HasProprietaireId;
use App\Services\DodoVroumApiService;
use App\Services\DodoVroumApi\VehicleService;
use App\Services\DodoVroumApi\Mappers\VehicleMapper;
use App\Exceptions\DodoVroumApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OwnerVehicleController extends Controller
{
    use HasProprietaireId;
    
    protected DodoVroumApiService $apiService;
    protected VehicleService $vehicleService;

    public function __construct(DodoVroumApiService $apiService, VehicleService $vehicleService)
    {
        $this->apiService = $apiService;
        $this->vehicleService = $vehicleService;
    }

    /**
     * Afficher la liste des véhicules du propriétaire
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
            
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour l\'utilisateur', [
                    'user_id' => $userId,
                ]);
                return Inertia::render('Owner/Vehicles/Index', [
                    'vehicles' => [],
                    'pagination' => (new LengthAwarePaginator([], 0, 15, 1))->toArray(),
                    'filters' => $filters ?? [],
                    'error' => 'Impossible de récupérer vos véhicules. Veuillez contacter le support.',
                ]);
            }
            
            
            // Utiliser le filtre proprietaireId directement dans l'API (comme AdminComboOfferController)
            // L'API filtre côté serveur, ce qui est plus efficace
            $apiFilters = [];
            
            // Convertir en int si c'est un nombre, sinon garder tel quel
            if ($proprietaireId !== null && $proprietaireId !== '') {
                // Si c'est un nombre (string ou int), le convertir en int
                if (is_numeric($proprietaireId)) {
                    $apiFilters['proprietaireId'] = (int) $proprietaireId;
                } else {
                    // Sinon, garder tel quel (au cas où l'API accepterait des UUID)
                    $apiFilters['proprietaireId'] = $proprietaireId;
                }
            }
            
            // Ajouter les autres filtres seulement s'ils existent
            if (!empty($filters['search'])) {
                $apiFilters['search'] = $filters['search'];
            }
            if (!empty($filters['status'])) {
                $apiFilters['status'] = $filters['status'];
            }
            
            // Récupérer les véhicules (token admin ou filtre) ; on enrichira avec le détail pour places/année
            $allVehicles = $this->apiService->getVehicles($apiFilters);

            // Enrichir avec places (seats) et année : l'API liste ne les renvoie pas toujours, le détail oui
            foreach ($allVehicles as &$vehicle) {
                $id = $vehicle['id'] ?? $vehicle['_id'] ?? null;
                if (!$id) {
                    continue;
                }
                try {
                    $detail = $this->apiService->getVehicle($id);
                    if ($detail) {
                        if (isset($detail['seats']) || isset($detail['places'])) {
                            $vehicle['seats'] = $detail['seats'] ?? $detail['places'] ?? $vehicle['seats'] ?? 0;
                            $vehicle['places'] = $vehicle['seats'];
                        }
                        if (isset($detail['year']) || isset($detail['annee'])) {
                            $vehicle['year'] = $detail['year'] ?? $detail['annee'] ?? $vehicle['year'] ?? null;
                            $vehicle['annee'] = $vehicle['year'];
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug('Enrichissement véhicule liste (détail)', ['id' => $id, 'error' => $e->getMessage()]);
                }
            }
            unset($vehicle);

            // Double vérification côté serveur (au cas où l'API ne filtre pas correctement)
            $vehicles = [];
            $filteredOutCount = 0;
            
            foreach ($allVehicles as $index => $vehicle) {
                // Extraire le proprietaireId comme dans AdminComboOfferController
                $proprietaireId = null;
                
                if (isset($vehicle['proprietaire']) && is_array($vehicle['proprietaire'])) {
                    $proprietaireId = $vehicle['proprietaire']['id'] ?? $vehicle['proprietaire']['_id'] ?? null;
                }
                
                if (!$proprietaireId && isset($vehicle['owner']) && is_array($vehicle['owner'])) {
                    $proprietaireId = $vehicle['owner']['id'] ?? $vehicle['owner']['_id'] ?? null;
                }
                
                if (!$proprietaireId) {
                    $proprietaireId = $vehicle['proprietaireId'] ?? $vehicle['ownerId'] ?? $vehicle['userId'] ?? null;
                }
                
                if (!$proprietaireId && isset($vehicle['proprietaire']) && is_string($vehicle['proprietaire'])) {
                    $proprietaireId = $vehicle['proprietaire'];
                }
                
                
                // Si le propriétaire correspond, ajouter le véhicule
                // Comparer en string ET en int pour gérer les cas où l'un est string et l'autre int
                $matches = false;
                if ($proprietaireId && isset($apiFilters['proprietaireId'])) {
                    $matches = (
                        (string) $proprietaireId === (string) $apiFilters['proprietaireId'] ||
                        (int) $proprietaireId === (int) $apiFilters['proprietaireId']
                    );
                }
                
                if ($matches) {
                    $vehicles[] = $vehicle;
                } else {
                    $filteredOutCount++;
                }
            }
            
            // Calculer les statistiques AVANT le mapping
            $totalVehicles = count($vehicles);
            $availableVehicles = 0;
            $totalBookings = 0;
            $monthRevenue = 0;
            
            $currentMonth = date('Y-m');
            
            foreach ($vehicles as $vehicle) {
                if (($vehicle['available'] ?? $vehicle['isActive'] ?? true) === true) {
                    $availableVehicles++;
                }
            }
            
            // Récupérer les réservations pour calculer les stats
            try {
                $allBookings = $this->apiService->getBookings(['proprietaireId' => $apiFilters['proprietaireId']]);
                
                // Filtrer les réservations pour ce mois
                foreach ($allBookings as $booking) {
                    $bookingProprietaireId = null;
                    if (isset($booking['vehicle']) && is_array($booking['vehicle'])) {
                        $bookingProprietaireId = $booking['vehicle']['proprietaireId'] ?? $booking['vehicle']['ownerId'] ?? null;
                    }
                    
                    if ($bookingProprietaireId && (
                        (string) $bookingProprietaireId === (string) $apiFilters['proprietaireId'] ||
                        (is_numeric($bookingProprietaireId) && is_numeric($apiFilters['proprietaireId']) && (int) $bookingProprietaireId === (int) $apiFilters['proprietaireId'])
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
                Log::warning('Erreur lors du calcul des statistiques véhicules', ['error' => $e->getMessage()]);
            }
            
            // Mapper les véhicules après filtrage
            $vehicles = array_map(function($vehicle) {
                return \App\Services\DodoVroumApi\Mappers\VehicleMapper::fromApi($vehicle);
            }, $vehicles);
            
            // Appliquer le filtre de recherche côté serveur si nécessaire
            if (!empty($filters['search'])) {
                $search = strtolower(trim($filters['search']));
                $vehicles = array_filter($vehicles, function($vehicle) use ($search) {
                    $name = strtolower($vehicle['name'] ?? '');
                    $brand = strtolower($vehicle['brand'] ?? $vehicle['marque'] ?? '');
                    $model = strtolower($vehicle['model'] ?? $vehicle['modele'] ?? '');
                    $plateNumber = strtolower($vehicle['plateNumber'] ?? $vehicle['plate_number'] ?? '');
                    
                    return strpos($name, $search) !== false || 
                           strpos($brand, $search) !== false || 
                           strpos($model, $search) !== false ||
                           strpos($plateNumber, $search) !== false;
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
                    
                    // Comparaison avec le nom du véhicule (peut contenir le type)
                    $name = strtolower($vehicle['name'] ?? '');
                    if (strpos($name, $filterType) !== false) {
                        return true;
                    }
                    
                    return false;
                });
                $vehicles = array_values($vehicles);
            }
            
            // Ajouter un flag canEdit pour chaque véhicule
            // Utiliser le proprietaireId réel pour la comparaison
            $vehiclesWithCanEdit = array_map(function($vehicle) use ($proprietaireId) {
                $vehicleOwnerId = (string) ($vehicle['proprietaireId'] ?? $vehicle['ownerId'] ?? null);
                // Si le proprietaireId du véhicule correspond au proprietaireId de l'utilisateur connecté, on peut modifier
                $vehicle['canEdit'] = !empty($vehicleOwnerId) && (string) $vehicleOwnerId === (string) $proprietaireId;
                return $vehicle;
            }, $vehicles);
            
            // Pagination côté serveur
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            
            $collection = collect($vehiclesWithCanEdit);
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

            return Inertia::render('Owner/Vehicles/Index', [
                'vehicles' => $paginated->items(),
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
                    'totalVehicles' => $totalVehicles,
                    'availableVehicles' => $availableVehicles,
                    'totalBookings' => $totalBookings,
                    'monthRevenue' => $monthRevenue,
                ],
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération des véhicules', [
                'error' => $e->getMessage(),
            ]);
            return Inertia::render('Owner/Vehicles/Index', [
                'vehicles' => [],
                'pagination' => (new LengthAwarePaginator([], 0, 15, 1))->toArray(),
                'filters' => $filters ?? [],
                'error' => 'Erreur lors de la récupération des véhicules.',
            ]);
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

        try {
            $vehicleTypes = $this->vehicleService->getTypes();
        } catch (\Exception $e) {
            Log::warning('Erreur lors de la récupération des types de véhicules', [
                'error' => $e->getMessage(),
            ]);
            $vehicleTypes = [];
        }

        return Inertia::render('Owner/Vehicles/Create', [
            'vehicleTypes' => $vehicleTypes,
        ]);
    }

    /**
     * Créer un nouveau véhicule
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        // Validation des données
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'type' => 'required|string|in:berline,suv,4x4,utilitaire,moto',
            'seats' => 'required|integer|min:1|max:50',
            'plateNumber' => 'required|string|max:20',
            'pricePerDay' => 'required|numeric|min:0',
            'color' => 'nullable|string|max:50',
            'transmission' => 'nullable|string|in:manuel,automatique,manual,automatic',
            'fuel' => 'nullable|string|in:essence,diesel,electrique,hybride,petrol,gasoline,electric,hybrid',
            'mileage' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'nullable|string|url',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
        ]);

        // Tronquer la description à 500 caractères pour éviter l'erreur de base de données
        if (isset($validated['description']) && is_string($validated['description']) && mb_strlen($validated['description']) > 500) {
            $validated['description'] = mb_substr($validated['description'], 0, 500);
        }

        // Ajouter automatiquement le proprietaireId de l'utilisateur connecté
        $proprietaireId = $this->getProprietaireId($user);
        if (!$proprietaireId) {
            Log::error('Impossible de récupérer le proprietaireId pour la création du véhicule', [
                'user_id' => $user->getAuthIdentifier(),
            ]);
            return back()->withErrors([
                'error' => 'Impossible de déterminer votre identité. Veuillez contacter le support.'
            ])->withInput();
        }

        $validated['proprietaireId'] = $proprietaireId;

        try {
            $this->vehicleService->create($validated);
            
            return redirect()->route('owner.vehicles.index')
                ->with('success', 'Véhicule créé avec succès');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la création du véhicule', [
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);
            
            $errorMessage = $e->getMessage() ?: 'Erreur lors de la création du véhicule';
            
            return back()
                ->withErrors(['error' => $errorMessage])
                ->with('error', $errorMessage)
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Erreur création véhicule', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $errorMessage = 'Erreur lors de la création du véhicule: ' . $e->getMessage();
            
            return back()
                ->withErrors(['error' => $errorMessage])
                ->with('error', $errorMessage)
                ->withInput();
        }
    }

    /**
     * Afficher un véhicule spécifique
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
                Log::error('Impossible de récupérer le proprietaireId pour l\'affichage du véhicule', [
                    'user_id' => $user->getAuthIdentifier(),
                    'vehicle_id' => $id,
                ]);
                abort(403, 'Accès non autorisé');
            }
            
            // Récupérer le véhicule via le token propriétaire (liste puis recherche par ID)
            // GET /vehicles/:id peut ne pas exister côté API, donc on utilise VehicleService::find()
            $mappedVehicle = $this->vehicleService->find($id);
            
            if (!$mappedVehicle) {
                abort(404, 'Véhicule non trouvé');
            }
            
            // Déjà filtré par le token propriétaire, donc canEdit = true
            $mappedVehicle['canEdit'] = true;
            
            // Ajouter les dates bloquées depuis l'endpoint dédié
            try {
                $blockedDatesPeriods = $this->apiService->getVehicleBlockedDates($id);
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
                $mappedVehicle['blockedDates'] = array_unique($blockedDatesList);
            } catch (\Exception $e) {
                // Si l'endpoint n'existe pas encore, utiliser un tableau vide
                Log::warning('Impossible de récupérer les dates bloquées du véhicule', [
                    'id' => $id,
                    'error' => $e->getMessage()
                ]);
                $mappedVehicle['blockedDates'] = [];
            }
            
            // Récupérer les réservations liées à ce véhicule
            $allBookings = $this->apiService->getBookings(['proprietaireId' => $proprietaireId]);
            $vehicleBookings = [];
            foreach ($allBookings as $booking) {
                $bookingVehicleId = null;
                if (isset($booking['vehicle']) && is_array($booking['vehicle'])) {
                    $bookingVehicleId = $booking['vehicle']['id'] ?? $booking['vehicle']['_id'] ?? null;
                } else {
                    $bookingVehicleId = $booking['vehicleId'] ?? $booking['vehicle_id'] ?? null;
                }
                
                if ($bookingVehicleId && (string) $bookingVehicleId === (string) $id) {
                    $vehicleBookings[] = $booking;
                }
            }
            
            // Calculer les statistiques
            $stats = $this->calculateVehicleStats($vehicleBookings, $mappedVehicle);
            
            // Mapper les réservations pour le frontend
            $mappedBookings = $this->mapBookingsForVehicle($vehicleBookings);

            return Inertia::render('Owner/Vehicles/Show', [
                'vehicle' => $mappedVehicle,
                'stats' => $stats,
                'bookings' => $mappedBookings,
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération du véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            abort(404, 'Véhicule non trouvé');
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération du véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(404, 'Véhicule non trouvé');
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
            // Même logique que show() : récupérer via token propriétaire (liste + recherche par ID)
            $vehicle = $this->vehicleService->find($id);
            
            if (!$vehicle) {
                abort(404, 'Véhicule non trouvé ou accès non autorisé');
            }

            $vehicleTypes = $this->vehicleService->getTypes();

            return Inertia::render('Owner/Vehicles/Edit', [
                'vehicle' => $vehicle,
                'vehicleTypes' => $vehicleTypes,
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération du véhicule pour édition', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            abort(404, 'Véhicule non trouvé');
        }
    }

    /**
     * Mettre à jour un véhicule
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        // Vérifier que le véhicule existe et appartient au propriétaire (find = liste avec token propriétaire)
        $vehicle = $this->vehicleService->find($id);
        
        if (!$vehicle) {
            abort(404, 'Véhicule non trouvé ou accès non autorisé');
        }

        Log::info('Tentative de mise à jour du véhicule', [
            'vehicle_id' => $id,
            'user_id' => $user->getAuthIdentifier(),
        ]);

        // Véhicule déjà filtré par token propriétaire
        // Si l'API backend rejette la modification, l'erreur sera gérée dans le catch
        try {
            // Récupérer les données et tronquer la description si nécessaire
            $data = $request->all();
            if (isset($data['description']) && is_string($data['description']) && mb_strlen($data['description']) > 500) {
                $data['description'] = mb_substr($data['description'], 0, 500);
            }
            
            $this->vehicleService->update($id, $data);
            
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
            
            return redirect()->route('owner.vehicles.index')
                ->with('success', 'Véhicule mis à jour avec succès');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la mise à jour du véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'context' => $e->getContext(),
            ]);
            
            // Message d'erreur plus explicite pour les erreurs d'autorisation
            $errorMessage = $e->getMessage();
            $context = $e->getContext();
            $statusCode = $context['status'] ?? null;
            
            if ($statusCode === 403 || str_contains(strtolower($errorMessage), 'autorisé') || str_contains(strtolower($errorMessage), 'propriétaire')) {
                $errorMessage = 'Impossible de modifier ce véhicule. Vous n\'êtes pas autorisé à effectuer cette action. Si ce véhicule apparaît dans votre liste, veuillez contacter le support technique.';
            }
            
            return back()->withErrors([
                'error' => $errorMessage ?: 'Erreur lors de la mise à jour du véhicule'
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
    public function destroy(string $id): RedirectResponse
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, 'Non authentifié');
        }

        try {
            $vehicle = $this->vehicleService->find($id);

            if (!$vehicle) {
                return redirect()->route('owner.vehicles.index')
                    ->with('error', 'Véhicule non trouvé ou accès non autorisé');
            }

            $proprietaireId = $this->getProprietaireId($user);
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour la suppression du véhicule', [
                    'user_id' => $user->getAuthIdentifier(),
                ]);
                return redirect()->route('owner.vehicles.index')
                    ->with('error', 'Erreur d\'authentification.');
            }

            $vehicleOwnerId = $vehicle['proprietaireId'] ?? $vehicle['ownerId'] ?? null;
            if (isset($vehicle['proprietaire']) && is_array($vehicle['proprietaire'])) {
                $vehicleOwnerId = $vehicleOwnerId ?? $vehicle['proprietaire']['id'] ?? $vehicle['proprietaire']['_id'] ?? null;
            }
            $matches = $vehicleOwnerId && (
                (string) $vehicleOwnerId === (string) $proprietaireId
                || (is_numeric($vehicleOwnerId) && is_numeric($proprietaireId) && (int) $vehicleOwnerId === (int) $proprietaireId)
            );
            if (!$matches) {
                Log::warning('Tentative de suppression d\'un véhicule qui n\'appartient pas au propriétaire', [
                    'vehicle_id' => $id,
                    'user_id' => $user->getAuthIdentifier(),
                ]);
                return redirect()->route('owner.vehicles.index')
                    ->with('error', 'Vous n\'êtes pas autorisé à supprimer ce véhicule.');
            }

            $deleted = $this->vehicleService->delete($id);

            if ($deleted) {
                return redirect()->route('owner.vehicles.index')
                    ->with('success', 'Véhicule supprimé avec succès');
            }

            return redirect()->route('owner.vehicles.index')
                ->with('error', 'Erreur lors de la suppression');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la suppression du véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            $message = $e->getMessage();
            if (str_contains(strtolower($message), 'internal server error') || ($e->getContext()['status'] ?? null) === 500) {
                $message = 'La suppression a échoué côté serveur. Le véhicule est peut-être lié à des réservations ou offres combinées.';
            }

            return redirect()->route('owner.vehicles.index')
                ->with('error', $message ?: 'Erreur lors de la suppression du véhicule');
        } catch (\Exception $e) {
            Log::error('Erreur suppression véhicule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('owner.vehicles.index')
                ->with('error', 'Erreur lors de la suppression du véhicule.');
        }
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
            $averageRating = (float) ($vehicle['rating'] ?? 0);
            $totalReviews = (int) ($vehicle['reviewsCount'] ?? 0);
        }
        
        // Calculer le taux d'occupation (simplifié)
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
            
            // Formater le statut
            $status = $booking['status'] ?? 'pending';
            $statusFormatted = 'En attente';
            if (strtolower($status) === 'confirmed' || strtolower($status) === 'confirmee') {
                $statusFormatted = 'Confirmée';
            } elseif (strtolower($status) === 'cancelled' || strtolower($status) === 'annulee') {
                $statusFormatted = 'Annulée';
            } elseif (strtolower($status) === 'completed' || strtolower($status) === 'terminee') {
                $statusFormatted = 'Terminée';
            }
            
            $mapped[] = [
                'id' => $booking['id'] ?? $booking['_id'] ?? null,
                'customer' => $customerName,
                'dates' => $datesFormatted,
                'startDate' => $startDate, // Ajouter la date de début brute
                'endDate' => $endDate, // Ajouter la date de fin brute
                'amount' => (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0),
                'status' => $statusFormatted,
                'statusRaw' => $status,
            ];
        }
        
        // Trier par date de début (plus récentes en premier)
        usort($mapped, function($a, $b) {
            return $b['id'] <=> $a['id']; // Simplifié - à améliorer avec vraie date
        });
        
        return $mapped;
    }

    /**
     * Récupérer les dates bloquées d'un véhicule
     */
    public function getBlockedDates(string $id): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        try {
            $vehicle = $this->apiService->getVehicle($id);
            $proprietaireId = $this->getProprietaireId($user);
            
            // Vérifier que le véhicule appartient au propriétaire
            $vehicleOwnerId = $vehicle['proprietaireId'] ?? $vehicle['proprietaire']['id'] ?? null;
            if ($vehicleOwnerId !== $proprietaireId) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            // Récupérer les dates bloquées depuis l'endpoint dédié
            $blockedDates = $this->apiService->getVehicleBlockedDates($id);
            
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
            Log::error('Erreur récupération dates bloquées véhicule', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Erreur lors de la récupération'], 500);
        }
    }

    /**
     * Bloquer une date pour un véhicule
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
            $vehicle = $this->apiService->getVehicle($id);
            $proprietaireId = $this->getProprietaireId($user);
            
            $vehicleOwnerId = $vehicle['proprietaireId'] ?? $vehicle['proprietaire']['id'] ?? null;
            if ($vehicleOwnerId !== $proprietaireId) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $date = $request->input('date');
            $dateObj = new \DateTime($date);
            
            // Récupérer toutes les réservations de ce véhicule
            $allBookings = $this->apiService->getBookings(['vehicleId' => $id]);
            
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
                        Log::warning('Erreur parsing date réservation véhicule', [
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
            
            $this->apiService->blockVehicleDates($id, $blockData);

            // Récupérer les dates bloquées mises à jour pour la réponse
            $updatedBlockedDates = $this->apiService->getVehicleBlockedDates($id);
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
            Log::error('Erreur blocage date véhicule', [
                'id' => $id,
                'date' => $request->input('date'),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Erreur lors du blocage de la date'], 500);
        }
    }

    /**
     * Débloquer une date pour un véhicule
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
            $vehicle = $this->apiService->getVehicle($id);
            $proprietaireId = $this->getProprietaireId($user);
            
            $vehicleOwnerId = $vehicle['proprietaireId'] ?? $vehicle['proprietaire']['id'] ?? null;
            if ($vehicleOwnerId !== $proprietaireId) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            // Récupérer les dates bloquées pour trouver celle à supprimer
            $blockedDates = $this->apiService->getVehicleBlockedDates($id);
            
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
                        $this->apiService->unblockVehicleDates($id, $blockedDateId);
                    }
                    break;
                }
            }

            // Récupérer les dates bloquées mises à jour pour la réponse
            $updatedBlockedDates = $this->apiService->getVehicleBlockedDates($id);
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
            Log::error('Erreur déblocage date véhicule', [
                'id' => $id,
                'date' => $request->input('date'),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Erreur lors du déblocage de la date'], 500);
        }
    }
}
