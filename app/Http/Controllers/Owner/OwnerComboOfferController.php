<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\HasProprietaireId;
use App\Services\DodoVroumApiService;
use App\Services\DodoVroumApi\ResidenceService;
use App\Services\DodoVroumApi\VehicleService;
use App\Services\DodoVroumApi\OfferService;
use App\Exceptions\DodoVroumApiException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OwnerComboOfferController extends Controller
{
    use HasProprietaireId;
    
    protected DodoVroumApiService $apiService;
    protected ResidenceService $residenceService;
    protected VehicleService $vehicleService;
    protected OfferService $offerService;

    public function __construct(DodoVroumApiService $apiService, ResidenceService $residenceService, VehicleService $vehicleService, OfferService $offerService)
    {
        $this->apiService = $apiService;
        $this->residenceService = $residenceService;
        $this->vehicleService = $vehicleService;
        $this->offerService = $offerService;
    }

    /**
     * Afficher la liste des offres combinées du propriétaire
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        try {
            $filters = $request->only(['search', 'status']);
            
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour les offres combinées', [
                    'user_id' => $user->getAuthIdentifier(),
                ]);
                return Inertia::render('Owner/ComboOffers/Index', [
                    'comboOffers' => [],
                    'pagination' => (new LengthAwarePaginator([], 0, 15, 1))->toArray(),
                    'filters' => $filters ?? [],
                    'error' => 'Impossible de récupérer vos offres combinées. Veuillez contacter le support.',
                ]);
            }
            
            // Utiliser le token du propriétaire : GET /offers ne renvoie que ses offres (comme pour résidences/véhicules)
            $apiFilters = [];
            if (!empty($filters['search'])) {
                $apiFilters['search'] = $filters['search'];
            }
            if (!empty($filters['status'])) {
                $apiFilters['status'] = $filters['status'];
            }
            
            $allOffers = $this->offerService->all($apiFilters);

            // Offres déjà filtrées par l'API (token propriétaire) ; garder un filtre de sécurité si l'API renvoie ownerId
            $offers = [];
            foreach ($allOffers as $offer) {
                $offerProprietaireId = $offer['proprietaireId'] ?? $offer['ownerId'] ?? null;
                if (!$offerProprietaireId && isset($offer['residence']) && is_array($offer['residence'])) {
                    $residence = $offer['residence'];
                    $offerProprietaireId = $residence['proprietaire']['id'] ?? $residence['proprietaire']['_id'] ?? $residence['proprietaireId'] ?? $residence['ownerId'] ?? null;
                }
                if (!$offerProprietaireId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                    $offerProprietaireId = $offer['voiture']['proprietaireId'] ?? $offer['voiture']['ownerId'] ?? null;
                }
                if (!$offerProprietaireId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                    $offerProprietaireId = $offer['vehicle']['proprietaireId'] ?? $offer['vehicle']['ownerId'] ?? null;
                }

                $matches = true;
                if ($offerProprietaireId !== null && $offerProprietaireId !== '') {
                    $matches = (
                        (string) $offerProprietaireId === (string) $proprietaireId ||
                        (is_numeric($offerProprietaireId) && is_numeric($proprietaireId) && (int) $offerProprietaireId === (int) $proprietaireId)
                    );
                }
                if ($matches) {
                    $offers[] = $offer;
                }
            }
            
            // Appliquer les filtres search et status côté serveur si nécessaire
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $offers = array_filter($offers, function($offer) use ($search) {
                    // L'API retourne 'titre' au lieu de 'title'
                    $title = strtolower($offer['titre'] ?? $offer['title'] ?? $offer['name'] ?? '');
                    $residenceName = '';
                    if (isset($offer['residence']) && is_array($offer['residence'])) {
                        $residenceName = strtolower($offer['residence']['nom'] ?? $offer['residence']['name'] ?? $offer['residence']['title'] ?? '');
                    }
                    // L'API retourne 'voiture' au lieu de 'vehicle'
                    $vehicle = $offer['voiture'] ?? $offer['vehicle'] ?? null;
                    $vehicleName = '';
                    if ($vehicle && is_array($vehicle)) {
                        $vehicleName = strtolower($vehicle['titre'] ?? $vehicle['name'] ?? ($vehicle['marque'] ?? '') . ' ' . ($vehicle['modele'] ?? ''));
                    }
                    return strpos($title, $search) !== false || 
                           strpos($residenceName, $search) !== false || 
                           strpos($vehicleName, $search) !== false;
                });
                $offers = array_values($offers);
            }
            
            // Filtrer par statut si nécessaire
            if (!empty($filters['status'])) {
                $statusFilter = strtolower($filters['status']);
                $offers = array_filter($offers, function($offer) use ($statusFilter) {
                    // Déterminer si l'offre est active
                    $isActive = $offer['isActive'] ?? $offer['is_active'] ?? $offer['available'] ?? true;
                    
                    // Convertir en booléen si c'est une chaîne
                    if (is_string($isActive)) {
                        $isActive = in_array(strtolower($isActive), ['true', '1', 'yes', 'active']);
                    }
                    
                    // Vérifier aussi le champ status s'il existe
                    $status = strtolower($offer['status'] ?? '');
                    if ($status) {
                        $isActive = ($status === 'active') ? true : (($status === 'inactive') ? false : $isActive);
                    }
                    
                    if ($statusFilter === 'active') {
                        return $isActive === true;
                    } elseif ($statusFilter === 'inactive') {
                        return $isActive === false;
                    }
                    
                    return true;
                });
                $offers = array_values($offers);
            }
            
            // Calculer les statistiques AVANT le mapping
            $totalOffers = count($offers);
            $activeOffers = 0;
            $totalBookings = 0;
            $confirmedBookings = 0;
            $monthRevenue = 0;
            
            $currentMonth = date('Y-m');
            
            foreach ($offers as $offer) {
                if (($offer['isActive'] ?? $offer['is_active'] ?? $offer['available'] ?? true) === true) {
                    $activeOffers++;
                }
            }
            
            // Récupérer les réservations pour calculer les stats
            try {
                $allBookings = $proprietaireId ? $this->apiService->getBookings(['proprietaireId' => $proprietaireId]) : [];
                
                // Filtrer les réservations liées aux offres combinées
                foreach ($allBookings as $booking) {
                    $bookingOfferId = $booking['offerId'] ?? $booking['offer_id'] ?? null;
                    $isComboOfferBooking = !empty($bookingOfferId);
                    
                    if ($isComboOfferBooking) {
                        $bookingProprietaireId = null;
                        if (isset($booking['offer']) && is_array($booking['offer'])) {
                            if (isset($booking['offer']['residence']) && is_array($booking['offer']['residence'])) {
                                $bookingProprietaireId = $booking['offer']['residence']['proprietaireId'] ?? $booking['offer']['residence']['ownerId'] ?? null;
                            }
                        }
                        
                        if ($bookingProprietaireId && (
                            (string) $bookingProprietaireId === (string) $proprietaireId ||
                            (is_numeric($bookingProprietaireId) && is_numeric($proprietaireId) && (int) $bookingProprietaireId === (int) $proprietaireId)
                        )) {
                            $totalBookings++;
                            
                            $status = strtolower($booking['status'] ?? 'pending');
                            if ($status === 'confirmed' || $status === 'confirmee') {
                                $confirmedBookings++;
                            }
                            
                            // Calculer les revenus du mois
                            $startDate = $booking['startDate'] ?? $booking['start_date'] ?? null;
                            if ($startDate && strpos($startDate, $currentMonth) === 0) {
                                $monthRevenue += (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erreur lors du calcul des statistiques offres combinées', ['error' => $e->getMessage()]);
            }
            
            // Taux de conversion
            $conversionRate = $totalBookings > 0 ? ($confirmedBookings / $totalBookings) * 100 : 0;
            
            // Mapper les offres au format attendu par le frontend
            $mappedOffers = array_map(function($offer) use ($proprietaireId) {
                $title = $offer['titre'] ?? $offer['title'] ?? $offer['name'] ?? 'Offre sans nom';
                $price = $offer['prixPack'] ?? $offer['price'] ?? $offer['prix'] ?? 0;
                $discount = $offer['remisePourcent'] ?? $offer['discount'] ?? $offer['remise'] ?? 0;
                
                // Calculer le prix original et le prix réduit
                if ($discount > 0 && $price > 0) {
                    $originalPrice = $price / (1 - ($discount / 100));
                    $discountedPrice = $price;
                } else {
                    $originalPrice = $price;
                    $discountedPrice = $price;
                }
                
                // Extraire le proprietaireId de l'offre pour vérifier les droits
                $offerProprietaireId = $offer['proprietaireId'] ?? $offer['ownerId'] ?? null;
                if (!$offerProprietaireId && isset($offer['residence']) && is_array($offer['residence'])) {
                    $offerProprietaireId = $offer['residence']['proprietaireId'] ?? $offer['residence']['ownerId'] ?? null;
                }
                if (!$offerProprietaireId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                    $offerProprietaireId = $offer['vehicle']['proprietaireId'] ?? $offer['vehicle']['ownerId'] ?? null;
                }
                if (!$offerProprietaireId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                    $offerProprietaireId = $offer['voiture']['proprietaireId'] ?? $offer['voiture']['ownerId'] ?? null;
                }
                
                // Vérifier si l'utilisateur peut modifier/supprimer
                $canEdit = false;
                if ($offerProprietaireId && $proprietaireId) {
                    $canEdit = (
                        (string) $offerProprietaireId === (string) $proprietaireId ||
                        (is_numeric($offerProprietaireId) && is_numeric($proprietaireId) && (int) $offerProprietaireId === (int) $proprietaireId)
                    );
                }
                
                // L'API retourne 'voiture' au lieu de 'vehicle'
                $vehicle = $offer['voiture'] ?? $offer['vehicle'] ?? null;
                
                return [
                    'id' => $offer['id'] ?? $offer['_id'] ?? null,
                    'title' => $title,
                    'name' => $title,
                    'description' => $offer['description'] ?? null,
                    'residenceId' => $offer['residenceId'] ?? ($offer['residence']['id'] ?? $offer['residence']['_id'] ?? null),
                    'residence' => $offer['residence'] ?? null,
                    'vehicleId' => $vehicle['id'] ?? $vehicle['_id'] ?? $offer['vehicleId'] ?? $offer['vehicle_id'] ?? null,
                    'vehicle' => $vehicle,
                    'voiture' => $vehicle, // Garder aussi pour compatibilité
                    'originalPrice' => $originalPrice,
                    'discountedPrice' => $discountedPrice,
                    'price' => $discountedPrice,
                    'discount' => $discount,
                    'discountPercentage' => $discount,
                    'nbJours' => $offer['nbJours'] ?? $offer['nb_jours'] ?? null,
                    'imageUrl' => $offer['imageUrl'] ?? $offer['image_url'] ?? $offer['image'] ?? null,
                    'startDate' => $offer['validFrom'] ?? $offer['valid_from'] ?? $offer['startDate'] ?? null,
                    'endDate' => $offer['validTo'] ?? $offer['valid_to'] ?? $offer['endDate'] ?? null,
                    'status' => $offer['status'] ?? 'active',
                    'available' => $offer['available'] ?? true,
                    'isActive' => $offer['isActive'] ?? $offer['is_active'] ?? true,
                    'isVerified' => $offer['isVerified'] ?? $offer['is_verified'] ?? false,
                    'canEdit' => $canEdit,
                ];
            }, $offers);
            
            // Pagination côté serveur
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            
            $collection = collect($mappedOffers);
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

            return Inertia::render('Owner/ComboOffers/Index', [
                'comboOffers' => $paginated->items(),
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
                    'totalOffers' => $totalOffers,
                    'activeOffers' => $activeOffers,
                    'totalBookings' => $totalBookings,
                    'confirmedBookings' => $confirmedBookings,
                    'monthRevenue' => $monthRevenue,
                    'conversionRate' => round($conversionRate, 2),
                ],
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération des offres combinées', [
                'error' => $e->getMessage(),
            ]);
            return Inertia::render('Owner/ComboOffers/Index', [
                'comboOffers' => [],
                'pagination' => (new LengthAwarePaginator([], 0, 15, 1))->toArray(),
                'filters' => $filters ?? [],
                'error' => 'Erreur lors de la récupération des offres combinées.',
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
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour la création de l\'offre combinée', [
                    'user_id' => $user->getAuthIdentifier(),
                ]);
                abort(403, 'Accès non autorisé');
            }
            
            // Utiliser le token du propriétaire pour récupérer toutes ses résidences et véhicules (comme "Mes résidences")
            $residences = $this->residenceService->all([]);
            $vehicles = $this->vehicleService->all([]);
            
            return Inertia::render('Owner/ComboOffers/Create', [
                'residences' => is_array($residences) ? array_slice($residences, 0, 100) : [],
                'vehicles' => is_array($vehicles) ? array_slice($vehicles, 0, 100) : [],
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la préparation du formulaire de création', [
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('owner.combo-offers.index')
                ->with('error', 'Erreur lors du chargement du formulaire');
        }
    }

    /**
     * Créer une nouvelle offre combinée
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'residenceId' => 'required|string',
            'vehicleId' => 'required|string',
            'originalPrice' => 'required|numeric|min:0',
            'discountedPrice' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|max:100',
            'nbJours' => 'nullable|integer|min:1',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
            'imageUrl' => 'nullable|url',
            'images' => 'nullable|array',
            'images.*' => 'nullable|url',
            'isActive' => 'nullable|boolean',
        ]);

        try {
            // Calculer le prix final et la réduction si nécessaire
            $originalPrice = $validated['originalPrice'];
            $discountedPrice = $validated['discountedPrice'];
            $discount = $validated['discount'] ?? null;
            
            if ($discount === null && $originalPrice > 0) {
                $discount = (($originalPrice - $discountedPrice) / $originalPrice) * 100;
            }
            
            // Convertir les dates en format ISO
            $validFrom = $validated['startDate'];
            $validTo = $validated['endDate'];
            if (strpos($validFrom, 'T') === false) {
                $validFrom .= 'T00:00:00Z';
            }
            if (strpos($validTo, 'T') === false) {
                $validTo .= 'T23:59:59Z';
            }
            
            // Gérer les images : utiliser images si présent, sinon imageUrl
            $imageUrl = null;
            if (!empty($validated['images']) && is_array($validated['images'])) {
                // Prendre la première image si plusieurs
                $imageUrl = !empty($validated['images'][0]) ? $validated['images'][0] : null;
            } elseif (!empty($validated['imageUrl'])) {
                $imageUrl = $validated['imageUrl'];
            }
            
            // Convertir les champs pour l'API
            $dataForApi = [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'residenceId' => $validated['residenceId'],
                'vehicleId' => $validated['vehicleId'],
                'price' => $discountedPrice, // Prix final après réduction
                'discount' => round($discount, 2),
                'validFrom' => $validFrom,
                'validTo' => $validTo,
                'imageUrl' => $imageUrl,
                'isActive' => $validated['isActive'] ?? true,
            ];
            
            // Ne pas inclure nbJours s'il est null, vide ou inférieur à 1
            if (isset($validated['nbJours']) && !empty($validated['nbJours']) && (int) $validated['nbJours'] >= 1) {
                $dataForApi['nbJours'] = (int) $validated['nbJours'];
            }

            $this->offerService->create($dataForApi);

            return redirect()->route('owner.combo-offers.index')
                ->with('success', 'Offre combinée créée avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur création offre combinée', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            // Extraire le message d'erreur
            $errorMessage = $e->getMessage();
            
            // Messages d'erreur spécifiques de l'API
            if (strpos($errorMessage, 'même propriétaire') !== false || 
                strpos($errorMessage, 'propriétaire') !== false) {
                $errorMessage = 'Le véhicule et la résidence doivent appartenir au même propriétaire';
            } elseif (strpos($errorMessage, 'Résidence non trouvée') !== false) {
                $errorMessage = 'Résidence non trouvée';
            } elseif (strpos($errorMessage, 'Véhicule non trouvé') !== false) {
                $errorMessage = 'Véhicule non trouvé';
            } elseif (strpos($errorMessage, 'active') !== false) {
                $errorMessage = 'La résidence ou le véhicule sélectionné n\'est pas actif';
            }

            return back()->withErrors([
                'error' => $errorMessage,
                'residenceId' => strpos($errorMessage, 'résidence') !== false ? $errorMessage : null,
                'vehicleId' => strpos($errorMessage, 'véhicule') !== false ? $errorMessage : null,
            ])->withInput();
        }
    }

    /**
     * Afficher une offre combinée spécifique
     */
    public function show(string $id): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        try {
            $offer = $this->apiService->getComboOffer($id);
            
            if (!$offer) {
                abort(404, 'Offre combinée non trouvée');
            }
            
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour l\'offre combinée', [
                    'user_id' => $user->getAuthIdentifier(),
                    'offer_id' => $id,
                ]);
                abort(403, 'Vous n\'êtes pas autorisé à voir cette offre');
            }
            
            // Vérifier que l'offre appartient au propriétaire
            $offerProprietaireId = null;
            
            // Vérifier directement sur l'offre d'abord
            $offerProprietaireId = $offer['proprietaireId'] ?? $offer['ownerId'] ?? null;
            
            // Vérifier via la résidence
            if (!$offerProprietaireId && isset($offer['residence']) && is_array($offer['residence'])) {
                $residence = $offer['residence'];
                if (isset($residence['proprietaire']) && is_array($residence['proprietaire'])) {
                    $offerProprietaireId = $residence['proprietaire']['id'] ?? $residence['proprietaire']['_id'] ?? null;
                } elseif (isset($residence['owner']) && is_array($residence['owner'])) {
                    $offerProprietaireId = $residence['owner']['id'] ?? $residence['owner']['_id'] ?? null;
                } else {
                    $offerProprietaireId = $residence['proprietaireId'] ?? $residence['ownerId'] ?? null;
                }
            }
            
            // Si pas trouvé, vérifier via le véhicule
            if (!$offerProprietaireId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                $vehicle = $offer['vehicle'];
                if (isset($vehicle['proprietaire']) && is_array($vehicle['proprietaire'])) {
                    $offerProprietaireId = $vehicle['proprietaire']['id'] ?? $vehicle['proprietaire']['_id'] ?? null;
                } elseif (isset($vehicle['owner']) && is_array($vehicle['owner'])) {
                    $offerProprietaireId = $vehicle['owner']['id'] ?? $vehicle['owner']['_id'] ?? null;
                } else {
                    $offerProprietaireId = $vehicle['proprietaireId'] ?? $vehicle['ownerId'] ?? null;
                }
            }
            
            // Si pas trouvé, vérifier via voiture (alias)
            if (!$offerProprietaireId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                $voiture = $offer['voiture'];
                $offerProprietaireId = $voiture['proprietaireId'] ?? $voiture['ownerId'] ?? null;
            }
            
            // Comparer en string ET en int pour gérer les cas où l'un est string et l'autre int
            $matches = false;
            if ($offerProprietaireId) {
                $matches = (
                    (string) $offerProprietaireId === (string) $proprietaireId ||
                    (is_numeric($offerProprietaireId) && is_numeric($proprietaireId) && (int) $offerProprietaireId === (int) $proprietaireId)
                );
            }
            
            if (!$matches) {
                abort(403, 'Vous n\'êtes pas autorisé à voir cette offre');
            }
            
            // Mapper l'offre au format attendu par le frontend
            $title = $offer['titre'] ?? $offer['title'] ?? $offer['name'] ?? 'Offre sans nom';
            $price = $offer['prixPack'] ?? $offer['price'] ?? $offer['prix'] ?? 0;
            $discount = $offer['remisePourcent'] ?? $offer['discount'] ?? $offer['remise'] ?? 0;
            
            // Calculer le prix original et le prix réduit
            if ($discount > 0 && $price > 0) {
                $originalPrice = $price / (1 - ($discount / 100));
                $discountedPrice = $price;
            } else {
                $originalPrice = $price;
                $discountedPrice = $price;
            }
            
            // L'API retourne 'voiture' au lieu de 'vehicle'
            $vehicle = $offer['voiture'] ?? $offer['vehicle'] ?? null;
            $residence = $offer['residence'] ?? null;
            
            // Extraire les IDs
            $vehicleId = $vehicle['id'] ?? $vehicle['_id'] ?? $offer['vehicleId'] ?? $offer['vehicle_id'] ?? null;
            $residenceId = $residence['id'] ?? $residence['_id'] ?? $offer['residenceId'] ?? $offer['residence_id'] ?? null;
            
            // Toujours récupérer les données complètes du véhicule si on a l'ID
            if ($vehicleId) {
                try {
                    $fullVehicle = $this->apiService->getVehicle($vehicleId);
                    if ($fullVehicle) {
                        $vehicle = $fullVehicle;
                    }
                } catch (\Exception $e) {
                    Log::warning('Impossible de récupérer les données complètes du véhicule', [
                        'vehicle_id' => $vehicleId,
                        'error' => $e->getMessage(),
                    ]);
                    // Si on ne peut pas récupérer les données complètes, utiliser celles de l'offre
                    if (!$vehicle) {
                        Log::error('Véhicule non trouvé dans l\'offre et impossible de le récupérer', [
                            'vehicle_id' => $vehicleId,
                        ]);
                    }
                }
            }
            
            // Toujours récupérer les données complètes de la résidence si on a l'ID
            if ($residenceId) {
                try {
                    $fullResidence = $this->apiService->getResidence($residenceId);
                    if ($fullResidence) {
                        $residence = $fullResidence;
                    }
                } catch (\Exception $e) {
                    Log::warning('Impossible de récupérer les données complètes de la résidence', [
                        'residence_id' => $residenceId,
                        'error' => $e->getMessage(),
                    ]);
                    // Si on ne peut pas récupérer les données complètes, utiliser celles de l'offre
                    if (!$residence) {
                        Log::error('Résidence non trouvée dans l\'offre et impossible de la récupérer', [
                            'residence_id' => $residenceId,
                        ]);
                    }
                }
            }
            
            // Log pour déboguer
            Log::info('OwnerComboOfferController::show - Données du véhicule', [
                'vehicle_id' => $vehicleId,
                'has_vehicle' => !empty($vehicle),
                'vehicle_keys' => $vehicle ? array_keys($vehicle) : [],
                'vehicle_titre' => $vehicle['titre'] ?? $vehicle['name'] ?? null,
                'vehicle_marque' => $vehicle['marque'] ?? $vehicle['brand'] ?? null,
                'vehicle_modele' => $vehicle['modele'] ?? $vehicle['model'] ?? null,
            ]);
            
            // Normaliser les images
            $images = [];
            if (isset($offer['images']) && is_array($offer['images'])) {
                $images = array_filter($offer['images'], function($img) {
                    return !empty($img) && is_string($img);
                });
            }
            if (empty($images) && !empty($offer['imageUrl'])) {
                $images = [$offer['imageUrl']];
            }
            
            $mappedOffer = [
                'id' => $offer['id'] ?? $offer['_id'] ?? null,
                'title' => $title,
                'name' => $title,
                'description' => $offer['description'] ?? null,
                'residenceId' => $residenceId,
                'residence' => $residence,
                'vehicleId' => $vehicleId,
                'vehicle' => $vehicle,
                'originalPrice' => $originalPrice,
                'discountedPrice' => $discountedPrice,
                'price' => $discountedPrice,
                'discount' => $discount,
                'discountPercentage' => $discount,
                'nbJours' => $offer['nbJours'] ?? $offer['nb_jours'] ?? null,
                'imageUrl' => $offer['imageUrl'] ?? $offer['image_url'] ?? $offer['image'] ?? null,
                'images' => $images,
                'startDate' => $offer['validFrom'] ?? $offer['valid_from'] ?? $offer['startDate'] ?? null,
                'endDate' => $offer['validTo'] ?? $offer['valid_to'] ?? $offer['endDate'] ?? null,
                'status' => $offer['status'] ?? 'active',
                'available' => $offer['available'] ?? true,
                'isActive' => $offer['isActive'] ?? $offer['is_active'] ?? true,
                'canEdit' => true, // Si on arrive ici, c'est que l'offre appartient au propriétaire
            ];
            
            // Ajouter les dates bloquées depuis l'endpoint dédié
            try {
                $blockedDatesPeriods = $this->apiService->getComboOfferBlockedDates($id);
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
                $mappedOffer['blockedDates'] = array_unique($blockedDatesList);
            } catch (\Exception $e) {
                // Si l'endpoint n'existe pas encore, utiliser un tableau vide
                Log::warning('Impossible de récupérer les dates bloquées de l\'offre combinée', [
                    'id' => $id,
                    'error' => $e->getMessage()
                ]);
                $mappedOffer['blockedDates'] = [];
            }
            
            // Récupérer les réservations liées à cette offre combinée
            $allBookings = $this->apiService->getBookings(['offerId' => $id]);
            
            // Filtrer les réservations pour s'assurer qu'elles appartiennent bien au propriétaire
            $offerBookings = array_filter($allBookings, function($booking) use ($proprietaireId) {
                $bookingProprietaireId = null;
                if (isset($booking['offer']) && is_array($booking['offer'])) {
                    // Vérifier via la résidence de l'offre
                    if (isset($booking['offer']['residence']) && is_array($booking['offer']['residence'])) {
                        $bookingProprietaireId = $booking['offer']['residence']['proprietaireId'] ?? $booking['offer']['residence']['ownerId'] ?? null;
                    }
                    // Vérifier via le véhicule de l'offre
                    if (!$bookingProprietaireId && isset($booking['offer']['vehicle']) && is_array($booking['offer']['vehicle'])) {
                        $bookingProprietaireId = $booking['offer']['vehicle']['proprietaireId'] ?? $booking['offer']['vehicle']['ownerId'] ?? null;
                    }
                }
                return $bookingProprietaireId && (
                    (string) $bookingProprietaireId === (string) $proprietaireId ||
                    (is_numeric($bookingProprietaireId) && is_numeric($proprietaireId) && (int) $bookingProprietaireId === (int) $proprietaireId)
                );
            });
            
            // Calculer les stats pour l'offre combinée
            $totalBookings = count($offerBookings);
            $totalRevenue = 0;
            $confirmedBookings = 0;
            
            foreach ($offerBookings as $booking) {
                $totalRevenue += (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0);
                $status = strtolower($booking['status'] ?? 'pending');
                if ($status === 'confirmed' || $status === 'confirmee') {
                    $confirmedBookings++;
                }
            }
            
            // Taux de conversion (réservations confirmées / total)
            $conversionRate = $totalBookings > 0 ? ($confirmedBookings / $totalBookings) * 100 : 0;
            
            // Récupérer les 5 dernières réservations
            $recentBookings = array_slice($offerBookings, 0, 5);
            
            // Mapper les réservations pour le frontend
            $mappedBookings = array_map(function ($booking) {
                $customerName = $booking['user']['firstName'] ?? $booking['user']['prenom'] ?? $booking['user']['email'] ?? 'Client inconnu';
                $startDate = $booking['startDate'] ?? $booking['start_date'] ?? null;
                $endDate = $booking['endDate'] ?? $booking['end_date'] ?? null;
                $datesFormatted = '';
                if ($startDate && $endDate) {
                    try {
                        $start = new \DateTime($startDate);
                        $end = new \DateTime($endDate);
                        $datesFormatted = $start->format('d M') . ' - ' . $end->format('d M Y');
                    } catch (\Exception $e) {
                        $datesFormatted = $startDate . ' - ' . $endDate;
                    }
                }
                $status = $booking['status'] ?? 'pending';
                $statusFormatted = 'En attente';
                if (strtolower($status) === 'confirmed' || strtolower($status) === 'confirmee') {
                    $statusFormatted = 'Confirmée';
                } elseif (strtolower($status) === 'cancelled') {
                    $statusFormatted = 'Annulée';
                } elseif (strtolower($status) === 'completed') {
                    $statusFormatted = 'Terminée';
                }
                
                return [
                    'id' => $booking['id'] ?? $booking['_id'] ?? null,
                    'customer' => $customerName,
                    'dates' => $datesFormatted,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'amount' => $booking['totalPrice'] ?? $booking['total_price'] ?? 0,
                    'status' => $statusFormatted,
                    'statusRaw' => $status,
                ];
            }, $recentBookings);
            
            return Inertia::render('Owner/ComboOffers/Show', [
                'comboOffer' => $mappedOffer,
                'stats' => [
                    'totalBookings' => $totalBookings,
                    'confirmedBookings' => $confirmedBookings,
                    'totalRevenue' => $totalRevenue,
                    'conversionRate' => round($conversionRate, 2),
                ],
                'bookings' => $mappedBookings,
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération de l\'offre combinée', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            abort(404, 'Offre combinée non trouvée');
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
            $offer = $this->apiService->getComboOffer($id);
            
            if (!$offer) {
                abort(404, 'Offre combinée non trouvée');
            }
            
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour l\'édition de l\'offre combinée', [
                    'user_id' => $user->getAuthIdentifier(),
                    'offer_id' => $id,
                ]);
                abort(403, 'Vous n\'êtes pas autorisé à modifier cette offre');
            }
            
            // Vérifier que l'offre appartient au propriétaire
            $offerProprietaireId = null;
            
            // Vérifier directement sur l'offre d'abord
            $offerProprietaireId = $offer['proprietaireId'] ?? $offer['ownerId'] ?? null;
            
            // Vérifier via la résidence
            if (!$offerProprietaireId && isset($offer['residence']) && is_array($offer['residence'])) {
                $residence = $offer['residence'];
                if (isset($residence['proprietaire']) && is_array($residence['proprietaire'])) {
                    $offerProprietaireId = $residence['proprietaire']['id'] ?? $residence['proprietaire']['_id'] ?? null;
                } elseif (isset($residence['owner']) && is_array($residence['owner'])) {
                    $offerProprietaireId = $residence['owner']['id'] ?? $residence['owner']['_id'] ?? null;
                } else {
                    $offerProprietaireId = $residence['proprietaireId'] ?? $residence['ownerId'] ?? null;
                }
            }
            
            // Si pas trouvé, vérifier via le véhicule
            if (!$offerProprietaireId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                $vehicle = $offer['vehicle'];
                if (isset($vehicle['proprietaire']) && is_array($vehicle['proprietaire'])) {
                    $offerProprietaireId = $vehicle['proprietaire']['id'] ?? $vehicle['proprietaire']['_id'] ?? null;
                } elseif (isset($vehicle['owner']) && is_array($vehicle['owner'])) {
                    $offerProprietaireId = $vehicle['owner']['id'] ?? $vehicle['owner']['_id'] ?? null;
                } else {
                    $offerProprietaireId = $vehicle['proprietaireId'] ?? $vehicle['ownerId'] ?? null;
                }
            }
            
            // Si pas trouvé, vérifier via voiture (alias)
            if (!$offerProprietaireId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                $voiture = $offer['voiture'];
                $offerProprietaireId = $voiture['proprietaireId'] ?? $voiture['ownerId'] ?? null;
            }
            
            // Comparer en string ET en int pour gérer les cas où l'un est string et l'autre int
            $matches = false;
            if ($offerProprietaireId) {
                $matches = (
                    (string) $offerProprietaireId === (string) $proprietaireId ||
                    (is_numeric($offerProprietaireId) && is_numeric($proprietaireId) && (int) $offerProprietaireId === (int) $proprietaireId)
                );
            }
            
            if (!$matches) {
                abort(403, 'Vous n\'êtes pas autorisé à modifier cette offre');
            }
            
            // Mapper les données de l'API vers le format attendu par le frontend
            $title = $offer['titre'] ?? $offer['title'] ?? 'Offre sans nom';
            $price = $offer['prixPack'] ?? $offer['price'] ?? 0;
            $discount = $offer['remisePourcent'] ?? $offer['discount'] ?? 0;
            $originalPrice = $discount > 0 ? ($price / (1 - $discount / 100)) : $price;
            $discountedPrice = $price;
            
            // Convertir les dates ISO en format date simple
            $validFrom = $offer['validFrom'] ?? $offer['valid_from'] ?? null;
            $validTo = $offer['validTo'] ?? $offer['valid_to'] ?? null;
            
            if ($validFrom && strpos($validFrom, 'T') !== false) {
                $validFrom = substr($validFrom, 0, 10); // Extraire YYYY-MM-DD
            }
            if ($validTo && strpos($validTo, 'T') !== false) {
                $validTo = substr($validTo, 0, 10); // Extraire YYYY-MM-DD
            }
            
            // L'API retourne 'voiture' au lieu de 'vehicle'
            $vehicle = $offer['voiture'] ?? $offer['vehicle'] ?? null;
            
            $mappedOffer = [
                'id' => $offer['id'] ?? null,
                'title' => $title,
                'description' => $offer['description'] ?? null,
                'residenceId' => $offer['residence']['id'] ?? $offer['residenceId'] ?? $offer['residence_id'] ?? null,
                'vehicleId' => $vehicle['id'] ?? $offer['vehicleId'] ?? $offer['vehicle_id'] ?? null,
                'originalPrice' => $originalPrice,
                'discountedPrice' => $discountedPrice,
                'price' => $discountedPrice,
                'discount' => $discount,
                'discountPercentage' => $discount,
                'nbJours' => $offer['nbJours'] ?? $offer['nb_jours'] ?? null,
                'startDate' => $validFrom,
                'endDate' => $validTo,
                'imageUrl' => $offer['imageUrl'] ?? $offer['image_url'] ?? null,
                'images' => $offer['imageUrl'] ? [$offer['imageUrl']] : [],
                'isActive' => $offer['isActive'] ?? $offer['is_active'] ?? true,
                'isVerified' => $offer['isVerified'] ?? $offer['is_verified'] ?? false,
            ];
            
            // Utiliser le token du propriétaire pour récupérer toutes ses résidences et véhicules
            $residences = $this->residenceService->all([]);
            $vehicles = $this->vehicleService->all([]);
            
            return Inertia::render('Owner/ComboOffers/Edit', [
                'comboOffer' => $mappedOffer,
                'residences' => is_array($residences) ? array_slice($residences, 0, 100) : [],
                'vehicles' => is_array($vehicles) ? array_slice($vehicles, 0, 100) : [],
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération de l\'offre combinée pour édition', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            abort(404, 'Offre combinée non trouvée');
        }
    }

    /**
     * Mettre à jour une offre combinée
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        // Vérifier d'abord que l'offre appartient au propriétaire
        try {
            $offer = $this->apiService->getComboOffer($id);
            
            if (!$offer) {
                abort(404, 'Offre combinée non trouvée');
            }
            
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour la mise à jour de l\'offre combinée', [
                    'user_id' => $user->getAuthIdentifier(),
                    'offer_id' => $id,
                ]);
                abort(403, 'Vous n\'êtes pas autorisé à modifier cette offre');
            }
            
            // Vérifier que l'offre appartient au propriétaire
            $offerProprietaireId = null;
            
            // Vérifier directement sur l'offre d'abord
            $offerProprietaireId = $offer['proprietaireId'] ?? $offer['ownerId'] ?? null;
            
            // Vérifier via la résidence
            if (!$offerProprietaireId && isset($offer['residence']) && is_array($offer['residence'])) {
                $residence = $offer['residence'];
                if (isset($residence['proprietaire']) && is_array($residence['proprietaire'])) {
                    $offerProprietaireId = $residence['proprietaire']['id'] ?? $residence['proprietaire']['_id'] ?? null;
                } elseif (isset($residence['owner']) && is_array($residence['owner'])) {
                    $offerProprietaireId = $residence['owner']['id'] ?? $residence['owner']['_id'] ?? null;
                } else {
                    $offerProprietaireId = $residence['proprietaireId'] ?? $residence['ownerId'] ?? null;
                }
            }
            
            // Si pas trouvé, vérifier via le véhicule
            if (!$offerProprietaireId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                $vehicle = $offer['vehicle'];
                if (isset($vehicle['proprietaire']) && is_array($vehicle['proprietaire'])) {
                    $offerProprietaireId = $vehicle['proprietaire']['id'] ?? $vehicle['proprietaire']['_id'] ?? null;
                } elseif (isset($vehicle['owner']) && is_array($vehicle['owner'])) {
                    $offerProprietaireId = $vehicle['owner']['id'] ?? $vehicle['owner']['_id'] ?? null;
                } else {
                    $offerProprietaireId = $vehicle['proprietaireId'] ?? $vehicle['ownerId'] ?? null;
                }
            }
            
            // Si pas trouvé, vérifier via voiture (alias)
            if (!$offerProprietaireId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                $voiture = $offer['voiture'];
                $offerProprietaireId = $voiture['proprietaireId'] ?? $voiture['ownerId'] ?? null;
            }
            
            // Comparer en string ET en int pour gérer les cas où l'un est string et l'autre int
            $matches = false;
            if ($offerProprietaireId) {
                $matches = (
                    (string) $offerProprietaireId === (string) $proprietaireId ||
                    (is_numeric($offerProprietaireId) && is_numeric($proprietaireId) && (int) $offerProprietaireId === (int) $proprietaireId)
                );
            }
            
            if (!$matches) {
                abort(403, 'Vous n\'êtes pas autorisé à modifier cette offre');
            }
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la vérification de l\'offre combinée', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            abort(404, 'Offre combinée non trouvée');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'residenceId' => 'required|string',
            'vehicleId' => 'required|string',
            'originalPrice' => 'required|numeric|min:0',
            'discountedPrice' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|max:100',
            'nbJours' => 'nullable|integer|min:1',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
            'imageUrl' => 'nullable|url',
            'images' => 'nullable|array',
            'images.*' => 'nullable|url',
            'isActive' => 'nullable|boolean',
        ]);

        try {
            // Calculer le prix final et la réduction si nécessaire
            $originalPrice = $validated['originalPrice'];
            $discountedPrice = $validated['discountedPrice'];
            $discount = $validated['discount'] ?? null;
            
            if ($discount === null && $originalPrice > 0) {
                $discount = (($originalPrice - $discountedPrice) / $originalPrice) * 100;
            }
            
            // Convertir les dates en format ISO
            $validFrom = $validated['startDate'];
            $validTo = $validated['endDate'];
            if (strpos($validFrom, 'T') === false) {
                $validFrom .= 'T00:00:00Z';
            }
            if (strpos($validTo, 'T') === false) {
                $validTo .= 'T23:59:59Z';
            }
            
            // Gérer les images : utiliser images si présent, sinon imageUrl
            $imageUrl = null;
            if (!empty($validated['images']) && is_array($validated['images'])) {
                // Prendre la première image si plusieurs
                $imageUrl = !empty($validated['images'][0]) ? $validated['images'][0] : null;
            } elseif (!empty($validated['imageUrl'])) {
                $imageUrl = $validated['imageUrl'];
            }
            
            // Convertir les champs pour l'API
            $dataForApi = [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'residenceId' => $validated['residenceId'],
                'vehicleId' => $validated['vehicleId'],
                'price' => $discountedPrice, // Prix final après réduction
                'discount' => round($discount, 2),
                'validFrom' => $validFrom,
                'validTo' => $validTo,
                'imageUrl' => $imageUrl,
                'isActive' => $validated['isActive'] ?? true,
            ];
            
            // Ne pas inclure nbJours s'il est null, vide ou inférieur à 1
            if (isset($validated['nbJours']) && !empty($validated['nbJours']) && (int) $validated['nbJours'] >= 1) {
                $dataForApi['nbJours'] = (int) $validated['nbJours'];
            }

            $this->apiService->updateComboOffer($id, $dataForApi);

            return redirect()->route('owner.combo-offers.index')
                ->with('success', 'Offre combinée mise à jour avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour offre combinée', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $validated,
            ]);

            // Extraire le message d'erreur
            $errorMessage = $e->getMessage();
            
            // Messages d'erreur spécifiques de l'API
            if (strpos($errorMessage, 'même propriétaire') !== false || 
                strpos($errorMessage, 'propriétaire') !== false) {
                $errorMessage = 'Le véhicule et la résidence doivent appartenir au même propriétaire';
            } elseif (strpos($errorMessage, 'Résidence non trouvée') !== false) {
                $errorMessage = 'Résidence non trouvée';
            } elseif (strpos($errorMessage, 'Véhicule non trouvé') !== false) {
                $errorMessage = 'Véhicule non trouvé';
            } elseif (strpos($errorMessage, 'active') !== false) {
                $errorMessage = 'La résidence ou le véhicule sélectionné n\'est pas actif';
            }

            return back()->withErrors([
                'error' => $errorMessage,
                'residenceId' => strpos($errorMessage, 'résidence') !== false ? $errorMessage : null,
                'vehicleId' => strpos($errorMessage, 'véhicule') !== false ? $errorMessage : null,
            ])->withInput();
        }
    }

    /**
     * Supprimer une offre combinée
     */
    public function destroy(string $id)
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        // Vérifier d'abord que l'offre appartient au propriétaire
        try {
            $offer = $this->apiService->getComboOffer($id);
            
            if (!$offer) {
                abort(404, 'Offre combinée non trouvée');
            }
            
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour la suppression de l\'offre combinée', [
                    'user_id' => $user->getAuthIdentifier(),
                    'offer_id' => $id,
                ]);
                abort(403, 'Vous n\'êtes pas autorisé à supprimer cette offre');
            }
            
            // Vérifier que l'offre appartient au propriétaire
            $offerProprietaireId = null;
            
            // Vérifier directement sur l'offre d'abord
            $offerProprietaireId = $offer['proprietaireId'] ?? $offer['ownerId'] ?? null;
            
            // Vérifier via la résidence
            if (!$offerProprietaireId && isset($offer['residence']) && is_array($offer['residence'])) {
                $residence = $offer['residence'];
                if (isset($residence['proprietaire']) && is_array($residence['proprietaire'])) {
                    $offerProprietaireId = $residence['proprietaire']['id'] ?? $residence['proprietaire']['_id'] ?? null;
                } elseif (isset($residence['owner']) && is_array($residence['owner'])) {
                    $offerProprietaireId = $residence['owner']['id'] ?? $residence['owner']['_id'] ?? null;
                } else {
                    $offerProprietaireId = $residence['proprietaireId'] ?? $residence['ownerId'] ?? null;
                }
            }
            
            // Si pas trouvé, vérifier via le véhicule
            if (!$offerProprietaireId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                $vehicle = $offer['vehicle'];
                if (isset($vehicle['proprietaire']) && is_array($vehicle['proprietaire'])) {
                    $offerProprietaireId = $vehicle['proprietaire']['id'] ?? $vehicle['proprietaire']['_id'] ?? null;
                } elseif (isset($vehicle['owner']) && is_array($vehicle['owner'])) {
                    $offerProprietaireId = $vehicle['owner']['id'] ?? $vehicle['owner']['_id'] ?? null;
                } else {
                    $offerProprietaireId = $vehicle['proprietaireId'] ?? $vehicle['ownerId'] ?? null;
                }
            }
            
            // Si pas trouvé, vérifier via voiture (alias)
            if (!$offerProprietaireId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                $voiture = $offer['voiture'];
                $offerProprietaireId = $voiture['proprietaireId'] ?? $voiture['ownerId'] ?? null;
            }
            
            // Comparer en string ET en int pour gérer les cas où l'un est string et l'autre int
            $matches = false;
            if ($offerProprietaireId) {
                $matches = (
                    (string) $offerProprietaireId === (string) $proprietaireId ||
                    (is_numeric($offerProprietaireId) && is_numeric($proprietaireId) && (int) $offerProprietaireId === (int) $proprietaireId)
                );
            }
            
            if (!$matches) {
                abort(403, 'Vous n\'êtes pas autorisé à supprimer cette offre');
            }

            $this->apiService->deleteComboOffer($id);

            return redirect()->route('owner.combo-offers.index')
                ->with('success', 'Offre combinée supprimée avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur suppression offre combinée', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return back()->with('error', 'Erreur lors de la suppression de l\'offre combinée: ' . $e->getMessage());
        }
    }

    /**
     * Récupérer les résidences et véhicules du propriétaire connecté
     */
    public function getOwnerProperties(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 403);
        }

        try {
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::warning('getOwnerProperties: proprietaireId manquant', [
                    'user_id' => $user->getAuthIdentifier(),
                ]);
                return response()->json([
                    'residences' => [],
                    'vehicles' => [],
                ]);
            }

            // Utiliser le token du propriétaire : l'API ne renvoie que ses résidences et véhicules
            $ownerResidences = $this->residenceService->all([]);
            $ownerVehicles = $this->vehicleService->all([]);

            if (!is_array($ownerResidences)) {
                $ownerResidences = [];
            }
            if (!is_array($ownerVehicles)) {
                $ownerVehicles = [];
            }

            Log::info('Résidences et véhicules récupérés pour le propriétaire', [
                'proprietaireId' => $proprietaireId,
                'residences_count' => count($ownerResidences),
                'vehicles_count' => count($ownerVehicles),
            ]);

            return response()->json([
                'residences' => array_values($ownerResidences),
                'vehicles' => array_values($ownerVehicles),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération propriétés propriétaire', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'residences' => [],
                'vehicles' => [],
                'error' => 'Erreur lors de la récupération des propriétés',
            ], 500);
        }
    }

    /**
     * Récupérer les dates bloquées d'une offre combinée
     */
    public function getBlockedDates(string $id): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Non authentifié'], 401);
        }

        try {
            $offer = $this->apiService->getComboOffer($id);
            $proprietaireId = $this->getProprietaireId($user);
            
            // Vérifier que l'offre appartient au propriétaire
            $offerOwnerId = $offer['ownerId'] ?? $offer['owner']['id'] ?? null;
            if ($offerOwnerId !== $proprietaireId) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            // Récupérer les dates bloquées depuis l'endpoint dédié
            $blockedDates = $this->apiService->getComboOfferBlockedDates($id);
            
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
            Log::error('Erreur récupération dates bloquées offre combinée', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Erreur lors de la récupération'], 500);
        }
    }

    /**
     * Bloquer une date pour une offre combinée
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
            $offer = $this->apiService->getComboOffer($id);
            $proprietaireId = $this->getProprietaireId($user);
            
            $offerOwnerId = $offer['ownerId'] ?? $offer['owner']['id'] ?? null;
            if ($offerOwnerId !== $proprietaireId) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            $date = $request->input('date');
            $dateObj = new \DateTime($date);
            
            // Récupérer toutes les réservations de cette offre combinée
            $allBookings = $this->apiService->getBookings(['offerId' => $id]);
            
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
                        Log::warning('Erreur parsing date réservation offre combinée', [
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
            
            $this->apiService->blockComboOfferDates($id, $blockData);

            // Récupérer les dates bloquées mises à jour pour la réponse
            $updatedBlockedDates = $this->apiService->getComboOfferBlockedDates($id);
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
            Log::error('Erreur blocage date offre combinée', [
                'id' => $id,
                'date' => $request->input('date'),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Erreur lors du blocage de la date'], 500);
        }
    }

    /**
     * Débloquer une date pour une offre combinée
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
            $offer = $this->apiService->getComboOffer($id);
            $proprietaireId = $this->getProprietaireId($user);
            
            $offerOwnerId = $offer['ownerId'] ?? $offer['owner']['id'] ?? null;
            if ($offerOwnerId !== $proprietaireId) {
                return response()->json(['error' => 'Accès non autorisé'], 403);
            }

            // Récupérer les dates bloquées pour trouver celle à supprimer
            $blockedDates = $this->apiService->getComboOfferBlockedDates($id);
            
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
                        $this->apiService->unblockComboOfferDates($id, $blockedDateId);
                    }
                    break;
                }
            }
            
            // Récupérer les dates bloquées mises à jour pour la réponse
            $updatedBlockedDates = $this->apiService->getComboOfferBlockedDates($id);
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
            Log::error('Erreur déblocage date offre combinée', [
                'id' => $id,
                'date' => $request->input('date'),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Erreur lors du déblocage de la date'], 500);
        }
    }
}


