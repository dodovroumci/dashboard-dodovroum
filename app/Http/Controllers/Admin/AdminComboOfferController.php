<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DodoVroumApi\BookingService;
use App\Services\DodoVroumApi\UserService;
use App\Services\DodoVroumApiService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdminComboOfferController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected UserService $userService,
        protected DodoVroumApiService $apiService
    ) {
    }

    /**
     * Afficher la liste des offres combinées
     */
    public function index(Request $request): Response
    {
        try {
            $filters = $request->only(['search', 'status']);
            $offers = $this->apiService->getComboOffers($filters);
            
            // Filtrer les valeurs null du tableau
            if (is_array($offers)) {
                $offers = array_values(array_filter($offers, function($item) {
                    return $item !== null && is_array($item);
                }));
            }
            
            // Appliquer les filtres côté serveur si nécessaire
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $offers = array_filter($offers, function($offer) use ($search) {
                    // L'API retourne 'titre' au lieu de 'title'
                    $title = strtolower($offer['titre'] ?? $offer['title'] ?? '');
                    $residenceName = strtolower($offer['residence']['nom'] ?? $offer['residence']['name'] ?? $offer['residence']['title'] ?? '');
                    // L'API retourne 'voiture' au lieu de 'vehicle'
                    $vehicle = $offer['voiture'] ?? $offer['vehicle'] ?? null;
                    $vehicleName = '';
                    if ($vehicle) {
                        $vehicleName = strtolower($vehicle['titre'] ?? $vehicle['name'] ?? ($vehicle['marque'] ?? '') . ' ' . ($vehicle['modele'] ?? ''));
                    }
                    return strpos($title, $search) !== false || 
                           strpos($residenceName, $search) !== false || 
                           strpos($vehicleName, $search) !== false;
                });
                $offers = array_values($offers);
            }
            
            // Mapper les champs de l'API vers les champs attendus par le frontend
            $apiService = $this->apiService; // Pour utilisation dans la closure
            $offers = array_map(function($offer) use ($apiService) {
                // L'API retourne: titre, prixPack, remisePourcent, voiture (pas vehicle)
                $title = $offer['titre'] ?? $offer['title'] ?? 'Offre sans nom';
                $price = $offer['prixPack'] ?? $offer['price'] ?? 0;
                $discount = $offer['remisePourcent'] ?? $offer['discount'] ?? 0;
                
                // Calculer le prix original et le prix réduit à partir du prix et de la réduction
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
                $residence = $offer['residence'] ?? null;
                
                // Toujours récupérer les données complètes pour avoir les noms à jour
                $residenceId = $residence['id'] ?? $offer['residenceId'] ?? $offer['residence_id'] ?? null;
                $vehicleId = $vehicle['id'] ?? $offer['vehicleId'] ?? $offer['vehicle_id'] ?? null;
                
                if ($residenceId) {
                    try {
                        $fullResidence = $apiService->getResidence($residenceId);
                        if ($fullResidence) {
                            $residence = $fullResidence;
                        } else {
                            // Si getResidence retourne null (404), utiliser les données imbriquées
                            \Log::warning('Résidence non trouvée via API, utilisation des données imbriquées', [
                                'residence_id' => $residenceId,
                            ]);
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Erreur lors de la récupération de la résidence complète dans index', [
                            'residence_id' => $residenceId,
                            'error' => $e->getMessage(),
                        ]);
                        // Ignorer l'erreur et utiliser les données imbriquées
                    }
                }
                
                if ($vehicleId) {
                    try {
                        $fullVehicle = $apiService->getVehicle($vehicleId);
                        if ($fullVehicle) {
                            // Priorité : title > name > brand + model (reconstruction pour garantir la fraîcheur)
                            $vehicleName = null;
                            
                            // Priorité 1 : Title explicite
                            if (!empty($fullVehicle['title'])) {
                                $vehicleName = trim($fullVehicle['title']);
                            }
                            // Priorité 2 : Name explicite
                            elseif (!empty($fullVehicle['name'])) {
                                $vehicleName = trim($fullVehicle['name']);
                            }
                            // Priorité 3 : Reconstruction depuis brand + model (garantit la fraîcheur)
                            else {
                                $brand = $fullVehicle['brand'] ?? $fullVehicle['marque'] ?? '';
                                $model = $fullVehicle['model'] ?? $fullVehicle['modele'] ?? '';
                                if (!empty($brand) || !empty($model)) {
                                    $vehicleName = trim("$brand $model");
                                }
                            }
                            
                            // Fallback : titre, nom (pour compatibilité)
                            if (!$vehicleName) {
                                if (!empty($fullVehicle['titre'])) {
                                    $vehicleName = trim($fullVehicle['titre']);
                                } elseif (!empty($fullVehicle['nom'])) {
                                    $vehicleName = trim($fullVehicle['nom']);
                                }
                            }
                            
                            // Ajouter explicitement title, titre, name pour le frontend
                            if ($vehicleName) {
                                $fullVehicle['title'] = $vehicleName;
                                $fullVehicle['titre'] = $vehicleName;
                                $fullVehicle['name'] = $vehicleName;
                            }
                            
                            $vehicle = $fullVehicle;
                            \Log::debug('Véhicule complet récupéré pour offre combinée dans index', [
                                'vehicle_id' => $vehicleId,
                                'vehicle_title' => $fullVehicle['title'] ?? null,
                                'vehicle_titre' => $fullVehicle['titre'] ?? null,
                                'vehicle_name' => $fullVehicle['name'] ?? null,
                                'vehicle_marque' => $fullVehicle['marque'] ?? $fullVehicle['brand'] ?? null,
                                'vehicle_modele' => $fullVehicle['modele'] ?? $fullVehicle['model'] ?? null,
                            ]);
                        } else {
                            // Si getVehicle retourne null (404), utiliser les données imbriquées et normaliser le nom
                            \Log::warning('Véhicule non trouvé via API, utilisation des données imbriquées', [
                                'vehicle_id' => $vehicleId,
                            ]);
                            if ($vehicle) {
                                $vehicle = $this->normalizeVehicleName($vehicle);
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::warning('Erreur lors de la récupération du véhicule complet dans index', [
                            'vehicle_id' => $vehicleId,
                            'error' => $e->getMessage(),
                        ]);
                        // Utiliser les données imbriquées et normaliser le nom
                        if ($vehicle) {
                            $vehicle = $this->normalizeVehicleName($vehicle);
                        }
                    }
                } elseif ($vehicle) {
                    // Si pas d'ID mais qu'on a des données imbriquées, normaliser le nom
                    $vehicle = $this->normalizeVehicleName($vehicle);
                }
                
                return [
                    'id' => $offer['id'] ?? null,
                    'title' => $title,
                    'name' => $title,
                    'description' => $offer['description'] ?? null,
                    'residenceId' => $residence['id'] ?? $offer['residenceId'] ?? $offer['residence_id'] ?? null,
                    'residence' => $residence,
                    'vehicleId' => $vehicle['id'] ?? $offer['vehicleId'] ?? $offer['vehicle_id'] ?? null,
                    'vehicle' => $vehicle,
                    'price' => $price,
                    'originalPrice' => $originalPrice,
                    'discountedPrice' => $discountedPrice,
                    'discount' => $discount,
                    'discountPercentage' => $discount,
                    'nbJours' => $offer['nbJours'] ?? $offer['nb_jours'] ?? null,
                    'startDate' => $validFrom,
                    'endDate' => $validTo,
                    'validFrom' => $validFrom,
                    'validTo' => $validTo,
                    'imageUrl' => $offer['imageUrl'] ?? $offer['image_url'] ?? null,
                    'images' => $this->normalizeImages($offer),
                    'isActive' => $offer['isActive'] ?? $offer['is_active'] ?? true,
                    'isVerified' => $offer['isVerified'] ?? $offer['is_verified'] ?? false,
                    'status' => ($offer['isActive'] ?? true) ? 'active' : 'inactive',
                ];
            }, $offers);
            
            // Calculer les statistiques AVANT la pagination
            $totalOffers = count($offers);
            $confirmedBookings = 0;
            $totalBookings = 0;
            $monthRevenue = 0;
            
            $currentMonth = date('Y-m');
            
            // Récupérer les réservations pour calculer les stats
            try {
                $allBookings = $this->apiService->getBookings([]);
                
                foreach ($allBookings as $booking) {
                    // Vérifier si la réservation concerne une offre combinée
                    $bookingOfferId = $booking['offerId'] ?? $booking['offer_id'] ?? ($booking['offer']['id'] ?? $booking['offer']['_id'] ?? null);
                    if ($bookingOfferId && in_array($bookingOfferId, array_column($offers, 'id'))) {
                        $totalBookings++;
                        $status = strtolower($booking['status'] ?? 'pending');
                        if ($status === 'confirmed' || $status === 'confirmee') {
                            $confirmedBookings++;
                        }
                        $startDate = $booking['startDate'] ?? $booking['start_date'] ?? null;
                        if ($startDate && strpos($startDate, $currentMonth) === 0) {
                            $monthRevenue += (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0);
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Erreur lors du calcul des statistiques offres combinées', ['error' => $e->getMessage()]);
            }
            
            $conversionRate = $totalBookings > 0 ? round(($confirmedBookings / $totalBookings) * 100, 2) : 0;
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            $total = count($offers);
            $offset = ($currentPage - 1) * $perPage;
            $items = array_slice($offers, $offset, $perPage);
            
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
            
            \Log::info('AdminComboOfferController::index', [
                'filters' => $filters,
                'offers_count' => $total,
                'current_page' => $currentPage,
                'per_page' => $perPage,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur récupération offres combinées', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $offers = [];
            $paginator = new LengthAwarePaginator([], 0, 15, 1);
            $totalOffers = 0;
            $confirmedBookings = 0;
            $totalBookings = 0;
            $monthRevenue = 0;
            $conversionRate = 0;
        }

        // Récupérer les résidences et véhicules pour les formulaires
        $residences = $this->apiService->getResidences();
        $vehicles = $this->apiService->getVehicles();

        return Inertia::render('ComboOffers/Index', [
            'comboOffers' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'residences' => is_array($residences) ? array_slice($residences, 0, 100) : [],
            'vehicles' => is_array($vehicles) ? array_slice($vehicles, 0, 100) : [],
            'filters' => $filters ?? [],
            'stats' => [
                'totalOffers' => $totalOffers ?? 0,
                'confirmedBookings' => $confirmedBookings ?? 0,
                'monthRevenue' => $monthRevenue ?? 0,
                'conversionRate' => $conversionRate ?? 0,
            ],
        ]);
    }

    /**
     * Afficher le formulaire de création
     */
    public function create(): Response
    {
        try {
            // Récupérer les propriétaires
            $usersData = $this->apiService->getUsers(['role' => 'proprietaire']);
            
            // Si aucun résultat, essayer avec d'autres variantes de filtre
            if (empty($usersData)) {
                $usersData = $this->apiService->getUsers(['type' => 'owner']);
            }
            if (empty($usersData)) {
                $usersData = $this->apiService->getUsers(['isOwner' => true]);
            }
            // Si toujours vide, récupérer tous les utilisateurs et filtrer côté serveur
            if (empty($usersData)) {
                $usersData = $this->apiService->getUsers();
            }
            
            // Extraire les utilisateurs si la réponse est imbriquée
            $allUsers = [];
            if (is_array($usersData)) {
                if (isset($usersData['data']) && is_array($usersData['data'])) {
                    // Si les données sont dans data.data (structure imbriquée)
                    if (isset($usersData['data']['data']) && is_array($usersData['data']['data'])) {
                        $allUsers = $usersData['data']['data'];
                    } elseif (isset($usersData['data'][0])) {
                        // Si data est un tableau
                        $allUsers = $usersData['data'];
                    } else {
                        // Si data est un objet unique
                        $allUsers = [$usersData['data']];
                    }
                } elseif (isset($usersData[0])) {
                    // Si c'est directement un tableau
                    $allUsers = $usersData;
                }
            }
            
            // Filtrer pour ne garder que les propriétaires
            $owners = [];
            foreach ($allUsers as $user) {
                $isOwner = false;
                
                // Vérifier le rôle
                if (isset($user['role'])) {
                    $role = strtolower($user['role']);
                    $isOwner = in_array($role, ['proprietaire', 'owner', 'propriétaire']);
                }
                
                // Vérifier le type
                if (!$isOwner && isset($user['type'])) {
                    $type = strtolower($user['type']);
                    $isOwner = in_array($type, ['proprietaire', 'owner', 'propriétaire']);
                }
                
                // Vérifier isOwner
                if (!$isOwner && isset($user['isOwner'])) {
                    $isOwner = (bool)$user['isOwner'];
                }
                
                // Vérifier isProprietaire
                if (!$isOwner && isset($user['isProprietaire'])) {
                    $isOwner = (bool)$user['isProprietaire'];
                }
                
                if ($isOwner) {
                    // Construire le nom complet
                    $firstName = $user['firstName'] ?? $user['prenom'] ?? '';
                    $lastName = $user['lastName'] ?? $user['nom'] ?? $user['name'] ?? '';
                    $fullName = trim($firstName . ' ' . $lastName);
                    if (empty($fullName)) {
                        $fullName = $user['email'] ?? 'Propriétaire inconnu';
                    }
                    
                    $owners[] = [
                        'id' => $user['id'] ?? $user['_id'] ?? null,
                        'name' => $fullName,
                        'email' => $user['email'] ?? null,
                    ];
                }
            }
            
            \Log::info('Propriétaires récupérés pour création offre combinée', [
                'total_users' => count($allUsers),
                'owners_count' => count($owners),
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur récupération propriétaires pour création offre combinée', [
                'error' => $e->getMessage(),
            ]);
            $owners = [];
        }

        return Inertia::render('ComboOffers/Create', [
            'owners' => $owners,
        ]);
    }

    /**
     * Afficher les détails d'une offre combinée
     */
    public function show(string $id): Response|RedirectResponse
    {
        try {
            $offer = $this->apiService->getComboOffer($id);
            
            if (!$offer) {
                return redirect()->route('admin.combo-offers.index')
                    ->with('error', 'Offre combinée non trouvée');
            }
            
            // Mapper les données de l'API vers le format attendu par le frontend
            $title = $offer['titre'] ?? $offer['title'] ?? $offer['name'] ?? 'Offre sans nom';
            $price = $offer['prixPack'] ?? $offer['price'] ?? $offer['prix'] ?? 0;
            $discount = $offer['remisePourcent'] ?? $offer['discount'] ?? $offer['remise'] ?? 0;
            
            // Calculer le prix original et le prix réduit
            // Si on a un discount, le prix donné est le prix après réduction
            // Prix original = prix réduit / (1 - discount/100)
            if ($discount > 0 && $price > 0) {
                $originalPrice = $price / (1 - ($discount / 100));
                $discountedPrice = $price;
            } else {
                // Si pas de discount, le prix est le prix original
                $originalPrice = $price;
                $discountedPrice = $price;
            }
            
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
            $residence = $offer['residence'] ?? null;
            
            // Toujours récupérer les données complètes depuis l'API pour garantir que les noms et autres données sont à jour
            $residenceId = $residence['id'] ?? $offer['residenceId'] ?? $offer['residence_id'] ?? null;
            $vehicleId = $vehicle['id'] ?? $offer['vehicleId'] ?? $offer['vehicle_id'] ?? null;
            
            // Récupérer les données complètes de la résidence pour avoir les noms à jour
            if ($residenceId) {
                try {
                    $fullResidence = $this->apiService->getResidence($residenceId);
                    if ($fullResidence) {
                        $residence = $fullResidence;
                        \Log::debug('Résidence complète récupérée pour offre combinée', [
                            'residence_id' => $residenceId,
                            'residence_name' => $residence['nom'] ?? $residence['name'] ?? $residence['title'] ?? null,
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Erreur lors de la récupération de la résidence complète', [
                        'residence_id' => $residenceId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Récupérer les données complètes du véhicule pour avoir les noms à jour
            if ($vehicleId) {
                try {
                    $fullVehicle = $this->apiService->getVehicle($vehicleId);
                    if ($fullVehicle) {
                        // Priorité : title > name > brand + model (reconstruction pour garantir la fraîcheur)
                        $vehicleName = null;
                        
                        // Priorité 1 : Title explicite
                        if (!empty($fullVehicle['title'])) {
                            $vehicleName = trim($fullVehicle['title']);
                        }
                        // Priorité 2 : Name explicite
                        elseif (!empty($fullVehicle['name'])) {
                            $vehicleName = trim($fullVehicle['name']);
                        }
                        // Priorité 3 : Reconstruction depuis brand + model (garantit la fraîcheur)
                        else {
                            $brand = $fullVehicle['brand'] ?? $fullVehicle['marque'] ?? '';
                            $model = $fullVehicle['model'] ?? $fullVehicle['modele'] ?? '';
                            if (!empty($brand) || !empty($model)) {
                                $vehicleName = trim("$brand $model");
                            }
                        }
                        
                        // Fallback : titre, nom (pour compatibilité)
                        if (!$vehicleName) {
                            if (!empty($fullVehicle['titre'])) {
                                $vehicleName = trim($fullVehicle['titre']);
                            } elseif (!empty($fullVehicle['nom'])) {
                                $vehicleName = trim($fullVehicle['nom']);
                            }
                        }
                        
                        // Ajouter explicitement title, titre, name pour le frontend
                        if ($vehicleName) {
                            $fullVehicle['title'] = $vehicleName;
                            $fullVehicle['titre'] = $vehicleName;
                            $fullVehicle['name'] = $vehicleName;
                        }
                        
                        $vehicle = $fullVehicle;
                        \Log::debug('Véhicule complet récupéré pour offre combinée', [
                            'vehicle_id' => $vehicleId,
                            'vehicle_title' => $fullVehicle['title'] ?? null,
                            'vehicle_name' => $fullVehicle['name'] ?? null,
                            'vehicle_brand' => $fullVehicle['brand'] ?? $fullVehicle['marque'] ?? null,
                            'vehicle_model' => $fullVehicle['model'] ?? $fullVehicle['modele'] ?? null,
                        ]);
                    } else {
                        // Si getVehicle retourne null (404), utiliser les données imbriquées et normaliser le nom
                        \Log::warning('Véhicule non trouvé via API dans show, utilisation des données imbriquées', [
                            'vehicle_id' => $vehicleId,
                        ]);
                        if ($vehicle) {
                            $vehicle = $this->normalizeVehicleName($vehicle);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Erreur lors de la récupération du véhicule complet', [
                        'vehicle_id' => $vehicleId,
                        'error' => $e->getMessage(),
                    ]);
                    // Utiliser les données imbriquées et normaliser le nom
                    if ($vehicle) {
                        $vehicle = $this->normalizeVehicleName($vehicle);
                    }
                }
            } elseif ($vehicle) {
                // Si pas d'ID mais qu'on a des données imbriquées, normaliser le nom
                $vehicle = $this->normalizeVehicleName($vehicle);
            }
            
            // Normaliser les images en incluant celles de la résidence et du véhicule
            $images = $this->normalizeImages($offer, $residence, $vehicle);
            
            $mappedOffer = [
                'id' => $offer['id'] ?? null,
                'title' => $title,
                'name' => $title,
                'description' => $offer['description'] ?? null,
                'residenceId' => $residence['id'] ?? $offer['residenceId'] ?? $offer['residence_id'] ?? null,
                'residence' => $residence,
                'vehicleId' => $vehicle['id'] ?? $offer['vehicleId'] ?? $offer['vehicle_id'] ?? null,
                'vehicle' => $vehicle,
                'price' => $price,
                'originalPrice' => $originalPrice,
                'discountedPrice' => $discountedPrice,
                'discount' => $discount,
                'discountPercentage' => $discount,
                'nbJours' => $offer['nbJours'] ?? $offer['nb_jours'] ?? null,
                'startDate' => $validFrom,
                'endDate' => $validTo,
                'validFrom' => $validFrom,
                'validTo' => $validTo,
                'imageUrl' => $offer['imageUrl'] ?? $offer['image_url'] ?? null,
                'images' => $images,
                'isActive' => $offer['isActive'] ?? $offer['is_active'] ?? true,
                'isVerified' => $offer['isVerified'] ?? $offer['is_verified'] ?? false,
                'status' => ($offer['isActive'] ?? true) ? 'active' : 'inactive',
            ];
            
            // Récupérer les réservations liées à cette offre combinée
            $allBookings = $this->apiService->getBookings([]);
            $offerBookings = [];
            foreach ($allBookings as $booking) {
                $bookingOfferId = null;
                if (isset($booking['offer']) && is_array($booking['offer'])) {
                    $bookingOfferId = $booking['offer']['id'] ?? $booking['offer']['_id'] ?? null;
                } else {
                    $bookingOfferId = $booking['offerId'] ?? $booking['offer_id'] ?? null;
                }
                
                if ($bookingOfferId && (string) $bookingOfferId === (string) $id) {
                    $offerBookings[] = $booking;
                }
            }
            
            // Calculer les statistiques
            $stats = $this->calculateComboOfferStats($offerBookings, $mappedOffer);
            
            // Mapper les réservations pour le frontend
            $mappedBookings = $this->mapBookingsForComboOffer($offerBookings);
            
            // Récupérer les informations du propriétaire
            $owner = null;
            $proprietaireId = $mappedOffer['ownerId'] ?? $mappedOffer['proprietaireId'] ?? $offer['ownerId'] ?? $offer['proprietaireId'] ?? null;
            if ($proprietaireId) {
                try {
                    $owner = $this->userService->find($proprietaireId);
                    if ($owner) {
                        $mappedOffer['owner'] = $owner;
                        $mappedOffer['ownerName'] = $owner['name'] ?? $owner['nom'] ?? $owner['email'] ?? 'Propriétaire inconnu';
                    }
                } catch (\Exception $e) {
                    Log::warning('Impossible de récupérer le propriétaire de l\'offre combinée', [
                        'offer_id' => $id,
                        'proprietaireId' => $proprietaireId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Log pour déboguer les données
            \Log::info('AdminComboOfferController::show - Données de l\'offre', [
                'id' => $id,
                'title' => $title,
                'price' => $price,
                'validFrom' => $validFrom,
                'validTo' => $validTo,
                'offer_has_images' => isset($offer['images']),
                'offer_images_count' => isset($offer['images']) && is_array($offer['images']) ? count($offer['images']) : 0,
                'offer_imageUrl' => $offer['imageUrl'] ?? null,
                'residence_id' => $residence['id'] ?? null,
                'residence_has_images' => isset($residence['images']),
                'residence_images_count' => isset($residence['images']) && is_array($residence['images']) ? count($residence['images']) : 0,
                'residence_imageUrl' => $residence['imageUrl'] ?? null,
                'residence_proprietaire' => $residence['proprietaire'] ?? null,
                'residence_owner' => $residence['owner'] ?? null,
                'vehicle_id' => $vehicle['id'] ?? null,
                'vehicle_has_images' => isset($vehicle['images']),
                'vehicle_images_count' => isset($vehicle['images']) && is_array($vehicle['images']) ? count($vehicle['images']) : 0,
                'vehicle_imageUrl' => $vehicle['imageUrl'] ?? null,
                'vehicle_proprietaire' => $vehicle['proprietaire'] ?? null,
                'vehicle_owner' => $vehicle['owner'] ?? null,
                'normalized_images' => $images,
                'normalized_images_count' => count($images),
            ]);
            
            // Log complet de la structure de l'offre depuis l'API
            \Log::info('AdminComboOfferController::show - Structure complète de l\'offre API', [
                'offer_keys' => array_keys($offer),
                'offer_structure' => [
                    'id' => $offer['id'] ?? null,
                    'titre' => $offer['titre'] ?? null,
                    'title' => $offer['title'] ?? null,
                    'imageUrl' => $offer['imageUrl'] ?? null,
                    'images' => $offer['images'] ?? null,
                    'residence' => isset($offer['residence']) ? [
                        'id' => $offer['residence']['id'] ?? null,
                        'nom' => $offer['residence']['nom'] ?? null,
                        'proprietaire' => $offer['residence']['proprietaire'] ?? null,
                        'owner' => $offer['residence']['owner'] ?? null,
                    ] : null,
                    'voiture' => isset($offer['voiture']) ? [
                        'id' => $offer['voiture']['id'] ?? null,
                        'titre' => $offer['voiture']['titre'] ?? null,
                        'proprietaire' => $offer['voiture']['proprietaire'] ?? null,
                        'owner' => $offer['voiture']['owner'] ?? null,
                    ] : null,
                    'vehicle' => isset($offer['vehicle']) ? [
                        'id' => $offer['vehicle']['id'] ?? null,
                        'proprietaire' => $offer['vehicle']['proprietaire'] ?? null,
                        'owner' => $offer['vehicle']['owner'] ?? null,
                    ] : null,
                ],
            ]);
            
            // Log complet de la résidence récupérée
            \Log::info('AdminComboOfferController::show - Structure complète de la résidence', [
                'residence_keys' => array_keys($residence),
                'residence_proprietaire_keys' => isset($residence['proprietaire']) && is_array($residence['proprietaire']) ? array_keys($residence['proprietaire']) : null,
                'residence_proprietaire_full' => $residence['proprietaire'] ?? null,
            ]);
            
            // Log complet du véhicule récupéré
            \Log::info('AdminComboOfferController::show - Structure complète du véhicule', [
                'vehicle_keys' => array_keys($vehicle),
                'vehicle_proprietaire_keys' => isset($vehicle['proprietaire']) && is_array($vehicle['proprietaire']) ? array_keys($vehicle['proprietaire']) : null,
                'vehicle_proprietaire_full' => $vehicle['proprietaire'] ?? null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur récupération offre combinée', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('admin.combo-offers.index')
                ->with('error', 'Erreur lors de la récupération de l\'offre combinée');
        }

        // Log final pour vérifier ce qui est envoyé au frontend
        \Log::info('AdminComboOfferController::show - Données envoyées au frontend', [
            'id' => $mappedOffer['id'] ?? null,
            'title' => $mappedOffer['title'] ?? null,
            'has_images' => isset($mappedOffer['images']),
            'images_type' => isset($mappedOffer['images']) ? gettype($mappedOffer['images']) : null,
            'images_is_array' => isset($mappedOffer['images']) && is_array($mappedOffer['images']),
            'images_count' => isset($mappedOffer['images']) && is_array($mappedOffer['images']) ? count($mappedOffer['images']) : 0,
            'images_value' => $mappedOffer['images'] ?? null,
            'has_imageUrl' => isset($mappedOffer['imageUrl']),
            'imageUrl' => $mappedOffer['imageUrl'] ?? null,
        ]);
        
        // Log final pour vérifier ce qui est envoyé au frontend
        \Log::info('AdminComboOfferController::show - Données envoyées au frontend', [
            'id' => $mappedOffer['id'] ?? null,
            'title' => $mappedOffer['title'] ?? null,
            'has_images' => isset($mappedOffer['images']),
            'images_type' => isset($mappedOffer['images']) ? gettype($mappedOffer['images']) : null,
            'images_is_array' => isset($mappedOffer['images']) && is_array($mappedOffer['images']),
            'images_count' => isset($mappedOffer['images']) && is_array($mappedOffer['images']) ? count($mappedOffer['images']) : 0,
            'images_value' => $mappedOffer['images'] ?? null,
            'has_imageUrl' => isset($mappedOffer['imageUrl']),
            'imageUrl' => $mappedOffer['imageUrl'] ?? null,
            'has_residence' => isset($mappedOffer['residence']),
            'has_vehicle' => isset($mappedOffer['vehicle']),
            'residence_id' => $mappedOffer['residenceId'] ?? null,
            'vehicle_id' => $mappedOffer['vehicleId'] ?? null,
        ]);
        
        return Inertia::render('ComboOffers/Show', [
            'comboOffer' => $mappedOffer,
            'stats' => $stats,
            'bookings' => $mappedBookings,
        ]);
    }
    
    /**
     * Calculer les statistiques d'une offre combinée
     */
    private function calculateComboOfferStats(array $bookings, array $offer): array
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
        }
        
        // Calculer le taux d'occupation (simplifié - basé sur les réservations confirmées)
        $occupationRate = 0;
        if ($totalBookings > 0) {
            $occupationRate = round(($confirmedBookings / $totalBookings) * 100, 1);
        }
        
        // Calculer le taux de conversion (identique à l'occupation rate pour les offres combinées)
        $conversionRate = $occupationRate;
        
        return [
            'totalBookings' => $totalBookings,
            'totalRevenue' => $totalRevenue,
            'averageRating' => $averageRating,
            'totalReviews' => $totalReviews,
            'occupationRate' => $occupationRate,
            'conversionRate' => $conversionRate,
            'confirmedBookings' => $confirmedBookings,
            'cancelledBookings' => $cancelledBookings,
            'completedBookings' => $completedBookings,
        ];
    }
    
    /**
     * Mapper les réservations pour l'affichage
     */
    private function mapBookingsForComboOffer(array $bookings): array
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
            \Log::warning('Erreur lors de la récupération des utilisateurs pour le mapping des réservations', ['error' => $e->getMessage()]);
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
    public function edit(string $id): Response
    {
        try {
            $offer = $this->apiService->getComboOffer($id);
            
            if (!$offer) {
                return redirect()->route('admin.combo-offers.index')
                    ->with('error', 'Offre combinée non trouvée');
            }
            
            // Mapper les données de l'API vers le format attendu par le frontend
            // L'API retourne: titre, prixPack, remisePourcent, voiture (pas vehicle)
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
            $residence = $offer['residence'] ?? null;
            
            // Toujours récupérer les données complètes pour avoir les noms à jour
            $residenceId = $residence['id'] ?? $offer['residenceId'] ?? $offer['residence_id'] ?? null;
            $vehicleId = $vehicle['id'] ?? $offer['vehicleId'] ?? $offer['vehicle_id'] ?? null;
            
            if ($residenceId) {
                try {
                    $fullResidence = $this->apiService->getResidence($residenceId);
                    if ($fullResidence) {
                        $residence = $fullResidence;
                    }
                } catch (\Exception $e) {
                    // Ignorer l'erreur et utiliser les données imbriquées
                }
            }
            
            if ($vehicleId) {
                try {
                    $fullVehicle = $this->apiService->getVehicle($vehicleId);
                    if ($fullVehicle) {
                        // Priorité : title > name > brand + model (reconstruction pour garantir la fraîcheur)
                        $vehicleName = null;
                        
                        // Priorité 1 : Title explicite
                        if (!empty($fullVehicle['title'])) {
                            $vehicleName = trim($fullVehicle['title']);
                        }
                        // Priorité 2 : Name explicite
                        elseif (!empty($fullVehicle['name'])) {
                            $vehicleName = trim($fullVehicle['name']);
                        }
                        // Priorité 3 : Reconstruction depuis brand + model (garantit la fraîcheur)
                        else {
                            $brand = $fullVehicle['brand'] ?? $fullVehicle['marque'] ?? '';
                            $model = $fullVehicle['model'] ?? $fullVehicle['modele'] ?? '';
                            if (!empty($brand) || !empty($model)) {
                                $vehicleName = trim("$brand $model");
                            }
                        }
                        
                        // Fallback : titre, nom (pour compatibilité)
                        if (!$vehicleName) {
                            if (!empty($fullVehicle['titre'])) {
                                $vehicleName = trim($fullVehicle['titre']);
                            } elseif (!empty($fullVehicle['nom'])) {
                                $vehicleName = trim($fullVehicle['nom']);
                            }
                        }
                        
                        // Ajouter explicitement title, titre, name pour le frontend
                        if ($vehicleName) {
                            $fullVehicle['title'] = $vehicleName;
                            $fullVehicle['titre'] = $vehicleName;
                            $fullVehicle['name'] = $vehicleName;
                        }
                        
                        $vehicle = $fullVehicle;
                    } else {
                        // Si getVehicle retourne null (404), utiliser les données imbriquées et normaliser le nom
                        if ($vehicle) {
                            $vehicle = $this->normalizeVehicleName($vehicle);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Erreur lors de la récupération du véhicule complet dans edit', [
                        'vehicle_id' => $vehicleId,
                        'error' => $e->getMessage(),
                    ]);
                    // Utiliser les données imbriquées et normaliser le nom
                    if ($vehicle) {
                        $vehicle = $this->normalizeVehicleName($vehicle);
                    }
                }
            } elseif ($vehicle) {
                // Si pas d'ID mais qu'on a des données imbriquées, normaliser le nom
                $vehicle = $this->normalizeVehicleName($vehicle);
            }
            
            $mappedOffer = [
                'id' => $offer['id'] ?? null,
                'title' => $title,
                'description' => $offer['description'] ?? null,
                'residenceId' => $residence['id'] ?? $offer['residenceId'] ?? $offer['residence_id'] ?? null,
                'residence' => $residence,
                'vehicleId' => $vehicle['id'] ?? $offer['vehicleId'] ?? $offer['vehicle_id'] ?? null,
                'vehicle' => $vehicle,
                'originalPrice' => $originalPrice,
                'discountedPrice' => $discountedPrice,
                'discount' => $discount,
                'discountPercentage' => $discount,
                'nbJours' => $offer['nbJours'] ?? $offer['nb_jours'] ?? null,
                'startDate' => $validFrom,
                'endDate' => $validTo,
                'imageUrl' => $offer['imageUrl'] ?? $offer['image_url'] ?? null,
                'images' => $this->normalizeImages($offer),
                'isActive' => $offer['isActive'] ?? $offer['is_active'] ?? true,
                'isVerified' => $offer['isVerified'] ?? $offer['is_verified'] ?? false,
            ];
        } catch (\Exception $e) {
            \Log::error('Erreur récupération offre combinée', ['error' => $e->getMessage()]);
            return redirect()->route('admin.combo-offers.index')
                ->with('error', 'Erreur lors de la récupération de l\'offre combinée');
        }

        $residences = $this->apiService->getResidences();
        $vehicles = $this->apiService->getVehicles();

        return Inertia::render('ComboOffers/Edit', [
            'comboOffer' => $mappedOffer,
            'residences' => is_array($residences) ? array_slice($residences, 0, 100) : [],
            'vehicles' => is_array($vehicles) ? array_slice($vehicles, 0, 100) : [],
        ]);
    }

    /**
     * Créer une nouvelle offre combinée
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'residenceId' => 'required|string',
            'vehicleId' => 'required|string',
            'originalPrice' => 'required|numeric|min:0',
            'discountedPrice' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|max:100',
            'nbJours' => 'nullable|integer|min:1',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date',
            'imageUrl' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'nullable|string',
            'isActive' => 'nullable|boolean',
            'isVerified' => 'nullable|boolean',
        ]);

        try {
            // Calculer le prix final et la réduction si nécessaire
            $originalPrice = $validated['originalPrice'];
            $discountedPrice = $validated['discountedPrice'];
            $discount = $validated['discount'] ?? null;
            
            if ($discount === null && $originalPrice > 0) {
                $discount = (($originalPrice - $discountedPrice) / $originalPrice) * 100;
            }
            
            // Dates de validité : par défaut aujourd'hui → +1 an si non renseignées
            $startDate = $validated['startDate'] ?? null;
            $endDate = $validated['endDate'] ?? null;
            if (empty($startDate)) {
                $startDate = now()->format('Y-m-d');
            }
            if (empty($endDate)) {
                $endDate = now()->addYear()->format('Y-m-d');
            }
            if ($endDate && $startDate && strtotime($endDate) <= strtotime($startDate)) {
                $endDate = date('Y-m-d', strtotime($startDate . ' +1 year'));
            }
            $validFrom = $startDate;
            $validTo = $endDate;
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
            // IMPORTANT: L'API attend les noms de champs ANGLAIS lors de la création (POST)
            // mais retourne les noms FRANÇAIS lors de la lecture (GET)
            $dataForApi = [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'residenceId' => $validated['residenceId'],
                'vehicleId' => $validated['vehicleId'],
                'price' => $discountedPrice, // Prix final après réduction
                'discount' => round($discount ?? 0, 2),
                'nbJours' => $validated['nbJours'] ?? null,
                'validFrom' => $validFrom,
                'validTo' => $validTo,
                'imageUrl' => $imageUrl,
                'isActive' => $validated['isActive'] ?? true,
                'isVerified' => $validated['isVerified'] ?? false,
            ];
            
            \Log::info('Création offre combinée - Données envoyées à l\'API', [
                'data' => $dataForApi,
            ]);

            $this->apiService->createComboOffer($dataForApi);

            return redirect()->route('admin.combo-offers.index')
                ->with('success', 'Offre combinée créée avec succès');
        } catch (\Exception $e) {
            \Log::error('Erreur création offre combinée', [
                'error' => $e->getMessage(),
                'data' => $validated,
                'trace' => $e->getTraceAsString(),
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
     * Mettre à jour une offre combinée
     */
    public function update(Request $request, string $id)
    {
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
            'isVerified' => 'nullable|boolean',
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
            
            // Validation préalable : vérifier que la résidence et le véhicule appartiennent au même propriétaire
            try {
                $residence = $this->apiService->getResidence($validated['residenceId']);
                $vehicle = $this->apiService->getVehicle($validated['vehicleId']);
                
                if (!$residence) {
                    return back()->withErrors([
                        'residenceId' => 'La résidence sélectionnée n\'existe pas ou n\'est plus disponible.',
                    ])->withInput();
                }
                
                if (!$vehicle) {
                    return back()->withErrors([
                        'vehicleId' => 'Le véhicule sélectionné n\'existe pas ou n\'est plus disponible.',
                    ])->withInput();
                }
                
                // Extraire les IDs des propriétaires
                $residenceOwnerId = $residence['ownerId'] 
                    ?? $residence['proprietaireId'] 
                    ?? (isset($residence['owner']) && is_array($residence['owner']) ? ($residence['owner']['id'] ?? null) : null)
                    ?? (isset($residence['proprietaire']) && is_array($residence['proprietaire']) ? ($residence['proprietaire']['id'] ?? null) : null);
                
                $vehicleOwnerId = $vehicle['ownerId'] 
                    ?? $vehicle['proprietaireId'] 
                    ?? (isset($vehicle['owner']) && is_array($vehicle['owner']) ? ($vehicle['owner']['id'] ?? null) : null)
                    ?? (isset($vehicle['proprietaire']) && is_array($vehicle['proprietaire']) ? ($vehicle['proprietaire']['id'] ?? null) : null);
                
                // Vérifier que les propriétaires correspondent
                if ($residenceOwnerId && $vehicleOwnerId && (string) $residenceOwnerId !== (string) $vehicleOwnerId) {
                    \Log::warning('Tentative de mise à jour offre combinée avec propriétaires différents', [
                        'residence_id' => $validated['residenceId'],
                        'residence_owner_id' => $residenceOwnerId,
                        'vehicle_id' => $validated['vehicleId'],
                        'vehicle_owner_id' => $vehicleOwnerId,
                    ]);
                    
                    return back()->withErrors([
                        'error' => 'Le véhicule et la résidence doivent appartenir au même propriétaire.',
                        'residenceId' => 'Cette résidence appartient à un autre propriétaire que le véhicule sélectionné.',
                        'vehicleId' => 'Ce véhicule appartient à un autre propriétaire que la résidence sélectionnée.',
                    ])->withInput();
                }
            } catch (\Exception $e) {
                \Log::warning('Erreur lors de la validation préalable de l\'offre combinée', [
                    'error' => $e->getMessage(),
                    'residence_id' => $validated['residenceId'] ?? null,
                    'vehicle_id' => $validated['vehicleId'] ?? null,
                ]);
                // Continuer quand même, NestJS fera la validation finale
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
                'nbJours' => $validated['nbJours'] ?? null,
                'validFrom' => $validFrom,
                'validTo' => $validTo,
                'imageUrl' => $imageUrl,
                'isActive' => $validated['isActive'] ?? true,
                'isVerified' => $validated['isVerified'] ?? false,
            ];
            
            // ⚠️ IMPORTANT : Ne JAMAIS envoyer ownerId ou proprietaireId lors de la mise à jour
            // L'API NestJS utilise le token JWT pour identifier automatiquement le propriétaire
            unset($dataForApi['ownerId']);
            unset($dataForApi['proprietaireId']);
            unset($dataForApi['owner_id']);
            unset($dataForApi['proprietaire_id']);
            
            \Log::info('Mise à jour offre combinée - Données envoyées à l\'API', [
                'offer_id' => $id,
                'data_keys' => array_keys($dataForApi),
                'data' => $dataForApi,
            ]);

            $this->apiService->updateComboOffer($id, $dataForApi);

            return redirect()->route('admin.combo-offers.index')
                ->with('success', 'Offre combinée mise à jour avec succès');
        } catch (\Exception $e) {
            \Log::error('Erreur mise à jour offre combinée', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $validated,
                'trace' => $e->getTraceAsString(),
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
     * Récupérer les résidences et véhicules d'un propriétaire (API)
     */
    public function getOwnerProperties(Request $request)
    {
        $ownerId = $request->get('ownerId');
        
        \Log::info('getOwnerProperties appelé', [
            'ownerId' => $ownerId,
            'request_all' => $request->all(),
        ]);
        
        if (!$ownerId) {
            \Log::warning('getOwnerProperties: ownerId manquant');
            return response()->json([
                'residences' => [],
                'vehicles' => [],
            ]);
        }

        try {
            // L'API supporte maintenant le filtre proprietaireId directement pour les résidences
            // Récupérer les résidences avec le filtre propriétaire
            $ownerResidences = $this->apiService->getResidences(['proprietaireId' => $ownerId]);
            
            // L'API NestJS utilise ownerId en interne (pas proprietaireId)
            // Essayer d'abord avec ownerId comme paramètre de filtre (filtrage côté base de données)
            $allVehicles = [];
            try {
                // Essayer avec ownerId (le paramètre que l'API NestJS utilise en interne)
                $filteredVehicles = $this->apiService->getVehicles(['ownerId' => $ownerId]);
                \Log::info('Véhicules récupérés avec filtre ownerId (filtrage base de données)', [
                    'ownerId' => $ownerId,
                    'count' => count($filteredVehicles),
                    'filter_type' => 'database',
                ]);
                
                // Si on a des résultats avec le filtre ownerId, on les utilise (filtrage côté base de données)
                if (!empty($filteredVehicles)) {
                    $allVehicles = $filteredVehicles;
                    \Log::info('Utilisation des véhicules filtrés par la base de données (ownerId)', [
                        'count' => count($allVehicles),
                        'filter_type' => 'database',
                    ]);
                } else {
                    // Si aucun résultat avec ownerId, essayer avec proprietaireId (fallback)
                    $filteredVehicles = $this->apiService->getVehicles(['proprietaireId' => $ownerId]);
                    \Log::info('Aucun résultat avec ownerId, essai avec proprietaireId', [
                        'ownerId' => $ownerId,
                        'count' => count($filteredVehicles),
                        'filter_type' => 'fallback',
                    ]);
                    
                    if (!empty($filteredVehicles)) {
                        $allVehicles = $filteredVehicles;
                        \Log::info('Utilisation des véhicules filtrés par proprietaireId (fallback)', [
                            'count' => count($allVehicles),
                            'filter_type' => 'fallback',
                        ]);
                    } else {
                        // Dernier recours : récupérer tous les véhicules et filtrer manuellement
                        // (seulement si l'API ne supporte aucun filtre)
                        $allVehicles = $this->apiService->getVehicles([]);
                        \Log::warning('Aucun filtre API ne fonctionne, récupération de tous les véhicules pour filtrage manuel', [
                            'total_count' => count($allVehicles),
                            'ownerId_expected' => $ownerId,
                            'filter_type' => 'manual',
                            'note' => 'Ceci est un fallback - l\'API devrait supporter le filtrage par ownerId',
                        ]);
                    }
                }
                
                // Log de la structure complète des premiers véhicules pour débogage
                if (!empty($allVehicles)) {
                    \Log::info('Structure des véhicules retournés par l\'API', [
                        'first_vehicle_keys' => !empty($allVehicles[0]) ? array_keys($allVehicles[0]) : [],
                        'first_vehicle_sample' => !empty($allVehicles[0]) ? $allVehicles[0] : null,
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Erreur lors de la récupération des véhicules', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $allVehicles = [];
            }
            
            // S'assurer que les résultats sont des tableaux
            if (!is_array($ownerResidences)) {
                $ownerResidences = [];
            }
            if (!is_array($allVehicles)) {
                $allVehicles = [];
            }
            
            // Si l'API a filtré correctement (via ownerId ou proprietaireId), utiliser directement les résultats
            // Validation de sécurité : vérifier que tous les véhicules retournés appartiennent au propriétaire
            $ownerIdStr = (string) $ownerId;
            $validatedVehicles = [];
            
            \Log::info('Validation des véhicules retournés par l\'API (filtrage base de données)', [
                'ownerId' => $ownerId,
                'ownerIdStr' => $ownerIdStr,
                'total_vehicles_from_api' => count($allVehicles),
                'filter_type' => 'database',
            ]);
            
            foreach ($allVehicles as $vehicle) {
                $vehicleOwnerId = null;
                
                // Extraire l'ID du propriétaire (même logique que précédemment)
                if (isset($vehicle['proprietaireId'])) {
                    $vehicleOwnerId = (string) trim($vehicle['proprietaireId']);
                } elseif (isset($vehicle['proprietaire'])) {
                    if (is_array($vehicle['proprietaire'])) {
                        $vehicleOwnerId = (string) trim($vehicle['proprietaire']['id'] ?? $vehicle['proprietaire']['_id'] ?? '');
                    } elseif (is_string($vehicle['proprietaire'])) {
                        $vehicleOwnerId = (string) trim($vehicle['proprietaire']);
                    }
                } elseif (isset($vehicle['owner']) && is_array($vehicle['owner'])) {
                    $vehicleOwnerId = (string) trim($vehicle['owner']['id'] ?? $vehicle['owner']['_id'] ?? '');
                } elseif (isset($vehicle['ownerId'])) {
                    $vehicleOwnerId = (string) trim($vehicle['ownerId']);
                }
                
                // Si le véhicule appartient au propriétaire, l'ajouter
                if ($vehicleOwnerId && $vehicleOwnerId === $ownerIdStr) {
                    $validatedVehicles[] = $vehicle;
                } else {
                    // Loguer un avertissement si l'API a retourné un véhicule qui n'appartient pas au propriétaire
                    // (cela ne devrait pas arriver si l'API filtre correctement)
                    \Log::warning('Véhicule retourné par l\'API ne correspond pas au propriétaire', [
                        'vehicle_id' => $vehicle['id'] ?? null,
                        'extracted_owner_id' => $vehicleOwnerId,
                        'expected_owner_id' => $ownerIdStr,
                        'note' => 'L\'API devrait filtrer correctement - ce véhicule ne devrait pas être retourné',
                    ]);
                }
            }
            
            $ownerVehicles = $validatedVehicles;
            
            \Log::info('Véhicules validés après filtrage API', [
                'ownerId' => $ownerId,
                'vehicles_from_api' => count($allVehicles),
                'vehicles_validated' => count($ownerVehicles),
                'filter_type' => 'database',
            ]);
            
            // Log détaillé pour TOUS les véhicules pour voir pourquoi certains ne correspondent pas
            if (count($allVehicles) > 0) {
                \Log::info('Détails de tous les véhicules analysés', [
                    'ownerId_expected' => $ownerIdStr,
                    'vehicles_analyzed' => array_map(function($v) use ($ownerIdStr) {
                        $proprietaireId = null;
                        $extractionMethod = null;
                        
                        // Même logique d'extraction (proprietaireId en priorité, puis owner.id/ownerId en fallback)
                        if (isset($v['proprietaireId'])) {
                            $proprietaireId = (string) trim($v['proprietaireId']);
                            $extractionMethod = 'proprietaireId';
                        } elseif (isset($v['proprietaire'])) {
                            if (is_array($v['proprietaire'])) {
                                $proprietaireId = (string) trim($v['proprietaire']['id'] ?? $v['proprietaire']['_id'] ?? '');
                                $extractionMethod = 'proprietaire.id';
                            } else {
                                $proprietaireId = (string) trim($v['proprietaire']);
                                $extractionMethod = 'proprietaire (string)';
                            }
                        } elseif (isset($v['owner']) && is_array($v['owner'])) {
                            $proprietaireId = (string) trim($v['owner']['id'] ?? $v['owner']['_id'] ?? '');
                            $extractionMethod = 'owner.id';
                        } elseif (isset($v['ownerId'])) {
                            $proprietaireId = (string) trim($v['ownerId']);
                            $extractionMethod = 'ownerId';
                        }
                        
                        return [
                            'vehicle_id' => $v['id'] ?? null,
                            'extracted_owner_id' => $proprietaireId,
                            'extraction_method' => $extractionMethod,
                            'matches' => $proprietaireId === $ownerIdStr,
                            'raw_proprietaireId' => $v['proprietaireId'] ?? null,
                            'raw_proprietaire' => isset($v['proprietaire']) ? (is_array($v['proprietaire']) ? ($v['proprietaire']['id'] ?? $v['proprietaire']['_id'] ?? null) : $v['proprietaire']) : null,
                            'raw_ownerId' => $v['ownerId'] ?? null,
                            'raw_owner' => isset($v['owner']) && is_array($v['owner']) ? ($v['owner']['id'] ?? $v['owner']['_id'] ?? null) : null,
                        ];
                    }, $allVehicles),
                ]);
            }
            
            // Log détaillé pour le débogage si aucun véhicule ne correspond
            if (count($allVehicles) > 0 && count($ownerVehicles) === 0) {
                \Log::warning('Aucun véhicule filtré pour le propriétaire', [
                    'ownerId' => $ownerId,
                    'total_vehicles' => count($allVehicles),
                    'first_vehicle_owner_ids' => array_map(function($v) {
                        $proprietaireId = null;
                        // Extraire l'ID du propriétaire (proprietaireId uniquement)
                        $proprietaireId = null;
                        if (isset($v['proprietaireId'])) {
                            $proprietaireId = $v['proprietaireId'];
                        } elseif (isset($v['proprietaire'])) {
                            if (is_array($v['proprietaire'])) {
                                $proprietaireId = $v['proprietaire']['id'] ?? $v['proprietaire']['_id'] ?? null;
                            } else {
                                $proprietaireId = $v['proprietaire'];
                            }
                        }
                        return [
                            'vehicle_id' => $v['id'] ?? null,
                            'extracted_owner_id' => $proprietaireId,
                            'proprietaireId' => $v['proprietaireId'] ?? null,
                            'proprietaire.id' => isset($v['proprietaire']) && is_array($v['proprietaire']) ? ($v['proprietaire']['id'] ?? $v['proprietaire']['_id'] ?? null) : null,
                            'proprietaire' => $v['proprietaire'] ?? null,
                        ];
                    }, array_slice($allVehicles, 0, 5)),
                ]);
            }
            
            \Log::info('Filtrage véhicules par propriétaire terminé', [
                'ownerId' => $ownerId,
                'total_vehicles' => count($allVehicles),
                'filtered_vehicles' => count($ownerVehicles),
            ]);
            
            \Log::info('Propriétés récupérées pour le propriétaire', [
                'ownerId' => $ownerId,
                'residences_count' => count($ownerResidences),
                'vehicles_total' => count($allVehicles),
                'vehicles_filtered' => count($ownerVehicles),
                'filtered_vehicle_ids' => array_map(function($v) {
                    return $v['id'] ?? null;
                }, $ownerVehicles),
            ]);
            
            // S'assurer qu'on retourne SEULEMENT les véhicules filtrés
            $response = [
                'residences' => array_values($ownerResidences),
                'vehicles' => array_values($ownerVehicles),
            ];
            
            \Log::info('Réponse getOwnerProperties envoyée', [
                'ownerId' => $ownerId,
                'residences_count' => count($response['residences']),
                'vehicles_count' => count($response['vehicles']),
                'vehicles_ids' => array_map(function($v) {
                    return [
                        'id' => $v['id'] ?? null,
                        'proprietaireId' => $v['proprietaireId'] ?? null,
                    ];
                }, $response['vehicles']),
            ]);
            
            return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('Erreur récupération propriétés du propriétaire', [
                'ownerId' => $ownerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'residences' => [],
                'vehicles' => [],
            ], 500);
        }
    }

    /**
     * Supprimer une offre combinée
     */
    /**
     * Vérifier si une offre combinée a des réservations liées
     */
    public function checkBookings(string $id): \Illuminate\Http\JsonResponse
    {
        try {
            $allBookings = $this->bookingService->all();
            $offerBookings = [];
            
            foreach ($allBookings as $booking) {
                $bookingOfferId = null;
                
                // Vérifier les différents formats possibles pour l'ID de l'offre
                if (isset($booking['offerId']) || isset($booking['offer_id'])) {
                    $bookingOfferId = $booking['offerId'] ?? $booking['offer_id'] ?? null;
                } elseif (isset($booking['offer']) && is_array($booking['offer'])) {
                    $bookingOfferId = $booking['offer']['id'] ?? $booking['offer']['_id'] ?? null;
                }
                
                if ($bookingOfferId && (string) $bookingOfferId === (string) $id) {
                    $offerBookings[] = $booking;
                }
            }
            
            $hasBookings = count($offerBookings) > 0;
            
            Log::info('Vérification réservations pour offre combinée', [
                'offer_id' => $id,
                'has_bookings' => $hasBookings,
                'bookings_count' => count($offerBookings),
            ]);
            
            return response()->json([
                'hasBookings' => $hasBookings,
                'count' => count($offerBookings),
                'bookingsCount' => count($offerBookings),
                'message' => $hasBookings 
                    ? "Cette offre combinée a " . count($offerBookings) . " réservation(s) liée(s). La suppression n'est pas possible."
                    : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification des réservations', [
                'offer_id' => $id,
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
            // Vérifier s'il y a des réservations liées
            $bookings = $this->checkBookings($id);
            $bookingsData = $bookings->getData(true);
            
            if (isset($bookingsData['hasBookings']) && $bookingsData['hasBookings']) {
                $bookingsCount = $bookingsData['count'] ?? 0;
                return back()->with('error', "Impossible de supprimer : cette offre combinée possède {$bookingsCount} réservation(s) active(s).");
            }
            
            $this->apiService->deleteComboOffer($id);

            return redirect()->route('admin.combo-offers.index')
                ->with('success', 'Offre combinée supprimée avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur suppression offre combinée', [
                'error' => $e->getMessage(),
                'id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            // Extraire le message d'erreur spécifique de l'API si disponible
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'réservations') !== false || strpos($errorMessage, 'bookings') !== false) {
                return back()->with('error', $errorMessage);
            }

            return back()->with('error', 'Erreur lors de la suppression de l\'offre combinée: ' . $errorMessage);
        }
    }

    /**
     * Normaliser le nom d'un véhicule avec priorité : title > name > brand + model
     * Cette méthode garantit que le nom est toujours à jour même si les données imbriquées sont obsolètes
     */
    protected function normalizeVehicleName(array $vehicle): array
    {
        // Priorité : title > name > brand + model (reconstruction pour garantir la fraîcheur)
        $vehicleName = null;
        
        // Priorité 1 : Title explicite
        if (!empty($vehicle['title'])) {
            $vehicleName = trim($vehicle['title']);
        }
        // Priorité 2 : Name explicite
        elseif (!empty($vehicle['name'])) {
            $vehicleName = trim($vehicle['name']);
        }
        // Priorité 3 : Reconstruction depuis brand + model (garantit la fraîcheur)
        else {
            $brand = $vehicle['brand'] ?? $vehicle['marque'] ?? '';
            $model = $vehicle['model'] ?? $vehicle['modele'] ?? '';
            if (!empty($brand) || !empty($model)) {
                $vehicleName = trim("$brand $model");
            }
        }
        
        // Fallback : titre, nom (pour compatibilité)
        if (!$vehicleName) {
            if (!empty($vehicle['titre'])) {
                $vehicleName = trim($vehicle['titre']);
            } elseif (!empty($vehicle['nom'])) {
                $vehicleName = trim($vehicle['nom']);
            }
        }
        
        // Ajouter explicitement title, titre, name pour le frontend
        if ($vehicleName) {
            $vehicle['title'] = $vehicleName;
            $vehicle['titre'] = $vehicleName;
            $vehicle['name'] = $vehicleName;
        }
        
        return $vehicle;
    }

    /**
     * Normaliser les images d'une offre combinée depuis les données de l'API
     * Récupère les images de l'offre, puis de la résidence, puis du véhicule
     */
    protected function normalizeImages(array $offer, ?array $residence = null, ?array $vehicle = null): array
    {
        $images = [];
        
        // 1. D'abord, essayer de récupérer les images directement de l'offre
        if (isset($offer['images']) && is_array($offer['images']) && !empty($offer['images'])) {
            // Filtrer les valeurs vides et s'assurer que c'est un tableau de strings
            $images = array_filter(array_map(function($img) {
                if (is_string($img) && !empty($img)) {
                    return $img;
                }
                if (is_array($img)) {
                    // Gérer les objets avec url
                    if (isset($img['url']) && !empty($img['url'])) {
                        return $img['url'];
                    }
                    // Gérer les objets avec src
                    if (isset($img['src']) && !empty($img['src'])) {
                        return $img['src'];
                    }
                }
                return null;
            }, $offer['images']), function($img) {
                return !empty($img);
            });
        }
        // Si imageUrl existe (singulier) - pour compatibilité
        elseif (array_key_exists('imageUrl', $offer) && !empty($offer['imageUrl'])) {
            $images = is_array($offer['imageUrl']) ? $offer['imageUrl'] : [$offer['imageUrl']];
        }
        // Si image_url existe (snake_case)
        elseif (array_key_exists('image_url', $offer) && !empty($offer['image_url'])) {
            $images = is_array($offer['image_url']) ? $offer['image_url'] : [$offer['image_url']];
        }
        // Si imageUrls existe (pluriel)
        elseif (isset($offer['imageUrls']) && is_array($offer['imageUrls']) && !empty($offer['imageUrls'])) {
            $images = array_filter($offer['imageUrls'], function($img) {
                return !empty($img);
            });
        }
        // Si image existe (singulier)
        elseif (array_key_exists('image', $offer) && !empty($offer['image'])) {
            $images = is_array($offer['image']) ? $offer['image'] : [$offer['image']];
        }
        
        // 2. Si aucune image dans l'offre, récupérer les images de la résidence (relation obligatoire)
        if (empty($images) && $residence) {
            // Images de la résidence
            if (isset($residence['images']) && is_array($residence['images']) && !empty($residence['images'])) {
                $residenceImages = array_filter($residence['images'], function($img) {
                    return !empty($img) && is_string($img);
                });
                if (!empty($residenceImages)) {
                    $images = array_merge($images, $residenceImages);
                }
            }
            // imageUrl de la résidence
            if (empty($images) && isset($residence['imageUrl']) && !empty($residence['imageUrl'])) {
                $images[] = $residence['imageUrl'];
            }
        }
        
        // 3. Si toujours aucune image, récupérer les images du véhicule (relation obligatoire)
        if (empty($images) && $vehicle) {
            // Images du véhicule
            if (isset($vehicle['images']) && is_array($vehicle['images']) && !empty($vehicle['images'])) {
                $vehicleImages = array_filter($vehicle['images'], function($img) {
                    return !empty($img) && is_string($img);
                });
                if (!empty($vehicleImages)) {
                    $images = array_merge($images, $vehicleImages);
                }
            }
            // imageUrl du véhicule
            if (empty($images) && isset($vehicle['imageUrl']) && !empty($vehicle['imageUrl'])) {
                $images[] = $vehicle['imageUrl'];
            }
        }
        
        // 4. Fallback : essayer depuis les données imbriquées dans l'offre (au cas où les relations ne sont pas passées)
        if (empty($images)) {
            // Essayer les images de la résidence depuis l'offre
            if (isset($offer['residence']) && is_array($offer['residence'])) {
                $residenceImages = $offer['residence']['images'] ?? [];
                if (is_array($residenceImages) && !empty($residenceImages)) {
                    $images = array_merge($images, array_filter($residenceImages, function($img) {
                        return !empty($img) && is_string($img);
                    }));
                }
                if (empty($images) && isset($offer['residence']['imageUrl']) && !empty($offer['residence']['imageUrl'])) {
                    $images[] = $offer['residence']['imageUrl'];
                }
            }
            
            // Essayer les images du véhicule depuis l'offre
            $vehicleData = $offer['voiture'] ?? $offer['vehicle'] ?? null;
            if ($vehicleData && is_array($vehicleData)) {
                $vehicleImages = $vehicleData['images'] ?? [];
                if (is_array($vehicleImages) && !empty($vehicleImages)) {
                    $images = array_merge($images, array_filter($vehicleImages, function($img) {
                        return !empty($img) && is_string($img);
                    }));
                }
                if (empty($images) && isset($vehicleData['imageUrl']) && !empty($vehicleData['imageUrl'])) {
                    $images[] = $vehicleData['imageUrl'];
                }
            }
        }
        
        // Réindexer le tableau pour éviter les trous et supprimer les doublons
        return array_values(array_unique($images));
    }
}

