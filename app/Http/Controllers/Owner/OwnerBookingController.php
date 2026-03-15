<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\HasProprietaireId;
use App\Services\DodoVroumApiService;
use App\Services\DodoVroumApi\BookingService;
use App\Services\DodoVroumApi\ResidenceService;
use App\Services\DodoVroumApi\VehicleService;
use App\Services\DodoVroumApi\UserService;
use App\Exceptions\DodoVroumApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OwnerBookingController extends Controller
{
    use HasProprietaireId;
    
    protected DodoVroumApiService $apiService;
    protected BookingService $bookingService;
    protected UserService $userService;
    protected ResidenceService $residenceService;
    protected VehicleService $vehicleService;

    public function __construct(
        DodoVroumApiService $apiService,
        BookingService $bookingService,
        UserService $userService,
        ResidenceService $residenceService,
        VehicleService $vehicleService
    ) {
        $this->apiService = $apiService;
        $this->bookingService = $bookingService;
        $this->userService = $userService;
        $this->residenceService = $residenceService;
        $this->vehicleService = $vehicleService;
    }

    /**
     * Afficher la liste des réservations du propriétaire
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        try {
            // Récupérer le proprietaireId réel
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour les réservations', [
                    'user_id' => $user->getAuthIdentifier(),
                ]);
                return Inertia::render('Owner/Bookings/Index', [
                    'bookings' => [],
                    'pagination' => (new LengthAwarePaginator([], 0, 15, 1))->toArray(),
                    'filters' => [],
                    'stats' => [
                        'totalBookings' => 0,
                        'confirmedBookings' => 0,
                        'pendingBookings' => 0,
                        'cancelledBookings' => 0,
                        'totalRevenue' => 0,
                        'monthRevenue' => 0,
                    ],
                    'error' => 'Impossible de récupérer vos réservations. Veuillez contacter le support.',
                ]);
            }
            
            // Préparer les filtres API (sans proprietaireId car l'API ne filtre pas correctement)
            // On filtrera côté serveur pour garantir la sécurité
            $filters = [];
            
            if ($request->has('search') && $request->search) {
                $filters['search'] = $request->search;
            }
            
            if ($request->has('status') && $request->status) {
                $filters['status'] = $request->status;
            }
            
            // Récupérer toutes les réservations et filtrer côté serveur par proprietaireId
            // (l'API ne filtre pas correctement par proprietaireId pour les réservations)
            $allBookings = $this->apiService->getBookings($filters);
            $bookings = [];
            
            Log::info('OwnerBookingController::index - Début du filtrage', [
                'proprietaireId' => $proprietaireId,
                'total_bookings_api' => count($allBookings),
            ]);
            
            foreach ($allBookings as $booking) {
                // Extraire le proprietaireId depuis la réservation (même logique que le tableau de bord)
                // PRIORITÉ 1 : Résidence ou véhicule inclus dans la réservation
                // PRIORITÉ 2 : Les champs directs de la réservation (ownerId/proprietaireId)
                $bookingOwnerId = null;
                
                // Comme dans le tableau de bord : chercher d'abord dans residence/vehicle
                if (isset($booking['residence']) && is_array($booking['residence'])) {
                    $bookingOwnerId = $booking['residence']['proprietaireId'] ?? $booking['residence']['proprietaire_id'] ?? $booking['residence']['ownerId'] ?? $booking['residence']['owner_id'] ?? null;
                }
                if (!$bookingOwnerId && (isset($booking['vehicle']) && is_array($booking['vehicle']))) {
                    $bookingOwnerId = $booking['vehicle']['proprietaireId'] ?? $booking['vehicle']['proprietaire_id'] ?? $booking['vehicle']['ownerId'] ?? $booking['vehicle']['owner_id'] ?? null;
                }
                if (!$bookingOwnerId && (isset($booking['voiture']) && is_array($booking['voiture']))) {
                    $bookingOwnerId = $booking['voiture']['proprietaireId'] ?? $booking['voiture']['proprietaire_id'] ?? $booking['voiture']['ownerId'] ?? $booking['voiture']['owner_id'] ?? null;
                }
                
                // Si pas trouvé, essayer les champs directs de la réservation
                if (!$bookingOwnerId && isset($booking['ownerId']) && !empty($booking['ownerId'])) {
                    $bookingOwnerId = $booking['ownerId'];
                }
                if (!$bookingOwnerId && isset($booking['proprietaireId']) && !empty($booking['proprietaireId'])) {
                    $bookingOwnerId = $booking['proprietaireId'];
                }
                if (!$bookingOwnerId && isset($booking['owner_id']) && !empty($booking['owner_id'])) {
                    $bookingOwnerId = $booking['owner_id'];
                }
                if (!$bookingOwnerId && isset($booking['proprietaire_id']) && !empty($booking['proprietaire_id'])) {
                    $bookingOwnerId = $booking['proprietaire_id'];
                }
                
                // Si pas trouvé, chercher dans l'offre combinée
                if (!$bookingOwnerId && isset($booking['offer']) && is_array($booking['offer'])) {
                    // Pour les offres combinées, vérifier via l'offre elle-même
                    $offer = $booking['offer'];
                    $bookingOwnerId = $offer['proprietaireId'] ?? $offer['ownerId'] ?? null;
                    
                    // Si pas trouvé, essayer via la résidence de l'offre
                    if (!$bookingOwnerId && isset($offer['residence']) && is_array($offer['residence'])) {
                        $bookingOwnerId = $offer['residence']['proprietaireId'] ?? $offer['residence']['proprietaire_id'] ?? $offer['residence']['ownerId'] ?? $offer['residence']['owner_id'] ?? null;
                    }
                    
                    // Si toujours pas trouvé, essayer via le véhicule de l'offre
                    if (!$bookingOwnerId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                        $bookingOwnerId = $offer['vehicle']['proprietaireId'] ?? $offer['vehicle']['proprietaire_id'] ?? $offer['vehicle']['ownerId'] ?? $offer['vehicle']['owner_id'] ?? null;
                    }
                    if (!$bookingOwnerId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                        $bookingOwnerId = $offer['voiture']['proprietaireId'] ?? $offer['voiture']['proprietaire_id'] ?? $offer['voiture']['ownerId'] ?? $offer['voiture']['owner_id'] ?? null;
                    }
                }
                
                // Si on a seulement l'ID de la résidence (sans objet résidence complet), récupérer la résidence complète
                if (!$bookingOwnerId && isset($booking['residenceId']) && !empty($booking['residenceId'])) {
                    // Si on a seulement l'ID de la résidence, essayer de récupérer la résidence complète
                    try {
                        $residence = $this->apiService->getResidence($booking['residenceId']);
                        if ($residence && is_array($residence)) {
                            $bookingOwnerId = $residence['proprietaireId'] ?? $residence['proprietaire_id'] ?? $residence['ownerId'] ?? $residence['owner_id'] ?? null;
                        }
                    } catch (\Exception $e) {
                        // Ignorer l'erreur et continuer
                    }
                }
                
                // Si on a seulement l'ID du véhicule (sans objet véhicule complet), récupérer le véhicule complet
                if (!$bookingOwnerId && isset($booking['vehicleId']) && !empty($booking['vehicleId'])) {
                    // Si on a seulement l'ID du véhicule, essayer de récupérer le véhicule complet
                    try {
                        $vehicle = $this->apiService->getVehicle($booking['vehicleId']);
                        if ($vehicle && is_array($vehicle)) {
                            $bookingOwnerId = $vehicle['proprietaireId'] ?? $vehicle['proprietaire_id'] ?? $vehicle['ownerId'] ?? $vehicle['owner_id'] ?? null;
                        }
                    } catch (\Exception $e) {
                        // Ignorer l'erreur et continuer
                    }
                }
                
                // Si on a seulement l'ID de l'offre (sans objet offre complet), récupérer l'offre complète
                if (!$bookingOwnerId && isset($booking['offerId']) && !empty($booking['offerId'])) {
                    // Si on a seulement l'ID de l'offre, essayer de récupérer l'offre complète
                    try {
                        $offer = $this->apiService->getComboOffer($booking['offerId']);
                        if ($offer && is_array($offer)) {
                            $bookingOwnerId = $offer['proprietaireId'] ?? $offer['proprietaire_id'] ?? $offer['ownerId'] ?? $offer['owner_id'] ?? null;
                            // Si pas trouvé, essayer via la résidence de l'offre
                            if (!$bookingOwnerId && isset($offer['residence']) && is_array($offer['residence'])) {
                                $bookingOwnerId = $offer['residence']['proprietaireId'] ?? $offer['residence']['proprietaire_id'] ?? $offer['residence']['ownerId'] ?? $offer['residence']['owner_id'] ?? null;
                            }
                            // Si toujours pas trouvé, essayer via le véhicule de l'offre
                            if (!$bookingOwnerId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                                $bookingOwnerId = $offer['vehicle']['proprietaireId'] ?? $offer['vehicle']['proprietaire_id'] ?? $offer['vehicle']['ownerId'] ?? $offer['vehicle']['owner_id'] ?? null;
                            }
                            if (!$bookingOwnerId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                                $bookingOwnerId = $offer['voiture']['proprietaireId'] ?? $offer['voiture']['proprietaire_id'] ?? $offer['voiture']['ownerId'] ?? $offer['voiture']['owner_id'] ?? null;
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignorer l'erreur et continuer
                    }
                }
                
                // Comparer en string ET en int pour gérer les cas où l'un est string et l'autre int
                $matches = false;
                if ($bookingOwnerId) {
                    $matches = (
                        (string) $bookingOwnerId === (string) $proprietaireId ||
                        (is_numeric($bookingOwnerId) && is_numeric($proprietaireId) && (int) $bookingOwnerId === (int) $proprietaireId)
                    );
                }
                
                // Log pour déboguer (seulement les premières réservations pour éviter trop de logs)
                if (count($bookings) < 3 && count($allBookings) > 0) {
                    Log::debug('OwnerBookingController::index - Filtrage réservation', [
                        'booking_id' => $booking['id'] ?? $booking['_id'] ?? 'unknown',
                        'booking_ownerId_direct' => $booking['ownerId'] ?? null,
                        'booking_proprietaireId_direct' => $booking['proprietaireId'] ?? null,
                        'booking_owner_id_found' => $bookingOwnerId,
                        'proprietaire_id_recherche' => $proprietaireId,
                        'matches' => $matches,
                        'has_residence' => isset($booking['residence']),
                        'has_vehicle' => isset($booking['vehicle']) || isset($booking['voiture']),
                        'has_offer' => isset($booking['offer']),
                        'vehicle_ownerId' => isset($booking['vehicle']['proprietaireId']) || isset($booking['vehicle']['ownerId']) ? ($booking['vehicle']['proprietaireId'] ?? $booking['vehicle']['ownerId'] ?? null) : null,
                    ]);
                }
                
                // Si le proprietaireId correspond, ajouter la réservation
                if ($matches) {
                    $bookings[] = $booking;
                }
            }
            
            Log::info('OwnerBookingController::index - Filtrage terminé', [
                'proprietaireId' => $proprietaireId,
                'proprietaireId_type' => gettype($proprietaireId),
                'total_bookings_api' => count($allBookings),
                'bookings_filtrees' => count($bookings),
            ]);
            
            // Si aucune réservation trouvée, logger un exemple de structure pour déboguer
            if (count($bookings) === 0 && count($allBookings) > 0) {
                $sampleBooking = $allBookings[0];
                Log::warning('OwnerBookingController::index - Aucune réservation ne correspond au proprietaireId', [
                    'proprietaireId_recherche' => $proprietaireId,
                    'sample_booking_id' => $sampleBooking['id'] ?? $sampleBooking['_id'] ?? 'unknown',
                    'sample_booking_keys' => array_keys($sampleBooking),
                    'sample_has_ownerId' => isset($sampleBooking['ownerId']),
                    'sample_has_proprietaireId' => isset($sampleBooking['proprietaireId']),
                    'sample_has_residence' => isset($sampleBooking['residence']),
                    'sample_has_vehicle' => isset($sampleBooking['vehicle']),
                    'sample_has_voiture' => isset($sampleBooking['voiture']),
                    'sample_has_offer' => isset($sampleBooking['offer']),
                    'sample_residence_keys' => isset($sampleBooking['residence']) && is_array($sampleBooking['residence']) ? array_keys($sampleBooking['residence']) : null,
                    'sample_residence_proprietaireId' => isset($sampleBooking['residence']['proprietaireId']) ? $sampleBooking['residence']['proprietaireId'] : (isset($sampleBooking['residence']['ownerId']) ? $sampleBooking['residence']['ownerId'] : null),
                ]);
            }
            
            // Calculer les statistiques AVANT le mapping
            $totalBookings = count($bookings);
            $confirmedBookings = 0;
            $pendingBookings = 0;
            $cancelledBookings = 0;
            $totalRevenue = 0;
            $monthRevenue = 0;
            
            $currentMonth = date('Y-m');

            foreach ($bookings as $booking) {
                $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
                $hasOwnerConfirmed = $this->isOwnerConfirmedAtSet($ownerConfirmedAt);
                $rawStatus = strtolower($booking['status'] ?? 'pending');
                if (in_array($rawStatus, ['cancelled', 'canceled', 'annulée', 'annulee'])) {
                    $cancelledBookings++;
                } elseif (in_array($rawStatus, ['completed', 'terminee', 'terminée'])) {
                    // Terminées : ne pas compter en confirmées ni en attente
                } elseif ($hasOwnerConfirmed && in_array($rawStatus, ['confirmed', 'confirmee'])) {
                    $confirmedBookings++;
                } else {
                    $pendingBookings++;
                }

                $price = (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0);
                $totalRevenue += $price;
                
                // Revenus du mois
                $startDate = $booking['startDate'] ?? $booking['start_date'] ?? null;
                if ($startDate && strpos($startDate, $currentMonth) === 0) {
                    $monthRevenue += $price;
                }
            }

            // Récupérer tous les utilisateurs pour mapper les clientId
            $usersMap = [];
            try {
                $allUsers = $this->userService->all();
                foreach ($allUsers as $apiUser) {
                    $userId = $apiUser['id'] ?? $apiUser['_id'] ?? null;
                    if ($userId) {
                        $firstName = $apiUser['firstName'] ?? $apiUser['prenom'] ?? '';
                        $lastName = $apiUser['lastName'] ?? $apiUser['nom'] ?? $apiUser['name'] ?? '';
                        $fullName = trim($firstName . ' ' . $lastName);
                        if (empty($fullName)) {
                            $fullName = $apiUser['email'] ?? 'Client inconnu';
                        }
                        $usersMap[$userId] = [
                            'name' => $fullName,
                            'email' => $apiUser['email'] ?? null,
                            'phone' => $apiUser['phone'] ?? $apiUser['telephone'] ?? null,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Impossible de récupérer les utilisateurs pour le mapping', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Mapper les réservations pour le frontend AVANT la pagination
            $mappedBookings = array_map(function ($booking) use ($usersMap) {
                $customerName = 'Client inconnu';
                if (isset($booking['user']) && is_array($booking['user'])) {
                    $user = $booking['user'];
                    $firstName = $user['firstName'] ?? $user['prenom'] ?? '';
                    $lastName = $user['lastName'] ?? $user['nom'] ?? $user['name'] ?? '';
                    $customerName = trim($firstName . ' ' . $lastName);
                    if (empty($customerName)) {
                        $customerName = $user['email'] ?? 'Client inconnu';
                    }
                } elseif (isset($booking['customer_name']) && !empty($booking['customer_name'])) {
                    $customerName = $booking['customer_name'];
                } elseif (isset($booking['customer']) && !empty($booking['customer'])) {
                    $customerName = $booking['customer'];
                } elseif (isset($booking['clientId']) && isset($usersMap[$booking['clientId']])) {
                    $customerName = $usersMap[$booking['clientId']]['name'];
                } elseif (isset($booking['clientId'])) {
                    $customerName = 'Client #' . substr($booking['clientId'], 0, 8);
                }
                
                // Déterminer le type et le nom de la propriété
                $propertyName = null;
                $propertyImage = null;
                $bookingType = 'unknown';
                
                if (isset($booking['offer']) && is_array($booking['offer'])) {
                    $bookingType = 'package';
                    $propertyName = $booking['offer']['titre'] ?? $booking['offer']['title'] ?? 'Offre combinée';
                    $propertyImage = $booking['offer']['imageUrl'] ?? ($booking['offer']['images'][0] ?? null);
                } elseif (isset($booking['residence']) && is_array($booking['residence'])) {
                    $bookingType = 'residence';
                    $residence = $booking['residence'];
                    $propertyName = $residence['nom'] ?? $residence['name'] ?? $residence['title'] ?? 'Résidence';
                    $propertyImage = $residence['imageUrl'] ?? ($residence['images'][0] ?? null);
                } elseif (isset($booking['vehicle']) && is_array($booking['vehicle'])) {
                    $bookingType = 'vehicle';
                    $vehicle = $booking['vehicle'];
                    $propertyName = $vehicle['titre'] ?? $vehicle['name'] ?? ($vehicle['marque'] ?? '') . ' ' . ($vehicle['modele'] ?? '');
                    $propertyImage = $vehicle['imageUrl'] ?? ($vehicle['images'][0] ?? null);
                } elseif (isset($booking['voiture']) && is_array($booking['voiture'])) {
                    $bookingType = 'vehicle';
                    $vehicle = $booking['voiture'];
                    $propertyName = $vehicle['titre'] ?? $vehicle['name'] ?? ($vehicle['marque'] ?? '') . ' ' . ($vehicle['modele'] ?? '');
                    $propertyImage = $vehicle['imageUrl'] ?? ($vehicle['images'][0] ?? null);
                }
                
                $startDate = $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? $booking['check_in_date'] ?? null;
                $endDate = $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? $booking['check_out_date'] ?? null;
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
                
                // Déterminer le statut réel en vérifiant ownerConfirmedAt
                // Si le statut est "confirmed" mais qu'il n'y a pas de ownerConfirmedAt,
                // c'est probablement une réservation en PENDING qui n'a pas encore été approuvée
                $rawStatus = $booking['status'] ?? 'pending';
                $statusUpper = strtoupper($rawStatus);
                $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
                $hasOwnerConfirmed = $this->isOwnerConfirmedAtSet($ownerConfirmedAt);

                // Afficher "Confirmée" uniquement si le propriétaire a bien confirmé (ownerConfirmedAt renseigné)
                if (($statusUpper === 'CONFIRMEE' || $statusUpper === 'CONFIRMED') && !$hasOwnerConfirmed) {
                    $finalStatus = 'pending';
                } elseif ($statusUpper === 'PENDING') {
                    $finalStatus = 'pending';
                } else {
                    $finalStatus = strtolower($rawStatus);
                }
                
                return [
                    'id' => $booking['id'] ?? $booking['_id'] ?? null,
                    'customer' => $customerName,
                    'customerName' => $customerName,
                    'property' => $propertyName ?? 'Non spécifié',
                    'propertyName' => $propertyName ?? 'Non spécifié',
                    'propertyImage' => $propertyImage,
                    'bookingType' => $bookingType,
                    'dates' => $datesFormatted,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'totalPrice' => (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0),
                    'total' => (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0),
                    'status' => $finalStatus, // Utiliser le statut final calculé
                    'rawStatus' => $rawStatus, // Garder le statut brut pour debug
                    'ownerConfirmedAt' => $ownerConfirmedAt, // Ajouter pour référence
                ];
            }, $bookings);
            
            // Pagination côté serveur (après le mapping)
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            
            $collection = collect($mappedBookings);
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

            return Inertia::render('Owner/Bookings/Index', [
                'bookings' => $paginated->items(),
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
                    'totalBookings' => $totalBookings,
                    'confirmedBookings' => $confirmedBookings,
                    'pendingBookings' => $pendingBookings,
                    'cancelledBookings' => $cancelledBookings,
                    'totalRevenue' => $totalRevenue,
                    'monthRevenue' => $monthRevenue,
                ],
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération des réservations', [
                'error' => $e->getMessage(),
            ]);
            return Inertia::render('Owner/Bookings/Index', [
                'bookings' => [],
                'pagination' => (new LengthAwarePaginator([], 0, 15, 1))->toArray(),
                'filters' => $filters ?? [],
                'error' => 'Erreur lors de la récupération des réservations.',
                'stats' => [
                    'totalBookings' => 0,
                    'confirmedBookings' => 0,
                    'pendingBookings' => 0,
                    'cancelledBookings' => 0,
                    'totalRevenue' => 0,
                    'monthRevenue' => 0,
                ],
            ]);
        }
    }

    /**
     * Afficher une réservation spécifique
     */
    public function show(string $id): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        try {
            // Récupérer la réservation
            $booking = $this->bookingService->find($id);
            
            if (!$booking) {
                abort(404, 'Réservation non trouvée');
            }

            // Vérifier que la réservation appartient au propriétaire (même logique que dans index)
            $proprietaireId = $this->getProprietaireId($user);
            if (!$proprietaireId) {
                abort(403, 'Accès non autorisé');
            }

            $bookingOwnerId = null;
            if (isset($booking['residence']) && is_array($booking['residence'])) {
                $bookingOwnerId = $booking['residence']['proprietaireId'] ?? $booking['residence']['proprietaire_id'] ?? $booking['residence']['ownerId'] ?? $booking['residence']['owner_id'] ?? null;
            }
            if (!$bookingOwnerId && (isset($booking['vehicle']) && is_array($booking['vehicle']))) {
                $bookingOwnerId = $booking['vehicle']['proprietaireId'] ?? $booking['vehicle']['proprietaire_id'] ?? $booking['vehicle']['ownerId'] ?? $booking['vehicle']['owner_id'] ?? null;
            }
            if (!$bookingOwnerId && (isset($booking['voiture']) && is_array($booking['voiture']))) {
                $bookingOwnerId = $booking['voiture']['proprietaireId'] ?? $booking['voiture']['proprietaire_id'] ?? $booking['voiture']['ownerId'] ?? $booking['voiture']['owner_id'] ?? null;
            }
            if (!$bookingOwnerId && isset($booking['ownerId']) && !empty($booking['ownerId'])) {
                $bookingOwnerId = $booking['ownerId'];
            }
            if (!$bookingOwnerId && isset($booking['proprietaireId']) && !empty($booking['proprietaireId'])) {
                $bookingOwnerId = $booking['proprietaireId'];
            }
            if (!$bookingOwnerId && isset($booking['offer']) && is_array($booking['offer'])) {
                $offer = $booking['offer'];
                $bookingOwnerId = $offer['proprietaireId'] ?? $offer['proprietaire_id'] ?? $offer['ownerId'] ?? $offer['owner_id'] ?? null;
                if (!$bookingOwnerId && isset($offer['residence']) && is_array($offer['residence'])) {
                    $bookingOwnerId = $offer['residence']['proprietaireId'] ?? $offer['residence']['proprietaire_id'] ?? $offer['residence']['ownerId'] ?? $offer['residence']['owner_id'] ?? null;
                }
                if (!$bookingOwnerId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                    $bookingOwnerId = $offer['vehicle']['proprietaireId'] ?? $offer['vehicle']['proprietaire_id'] ?? $offer['vehicle']['ownerId'] ?? $offer['vehicle']['owner_id'] ?? null;
                }
            }

            $matches = false;
            if ($bookingOwnerId) {
                $matches = (
                    (string) $bookingOwnerId === (string) $proprietaireId ||
                    (is_numeric($bookingOwnerId) && is_numeric($proprietaireId) && (int) $bookingOwnerId === (int) $proprietaireId)
                );
            }

            if (!$matches) {
                abort(404, 'Réservation non trouvée ou accès non autorisé');
            }

            // Récupérer tous les utilisateurs pour mapper les clientId
            $usersMap = [];
            try {
                $allUsers = $this->userService->all();
                foreach ($allUsers as $apiUser) {
                    $userId = $apiUser['id'] ?? $apiUser['_id'] ?? null;
                    if ($userId) {
                        $firstName = $apiUser['firstName'] ?? $apiUser['prenom'] ?? '';
                        $lastName = $apiUser['lastName'] ?? $apiUser['nom'] ?? $apiUser['name'] ?? '';
                        $fullName = trim($firstName . ' ' . $lastName);
                        if (empty($fullName)) {
                            $fullName = $apiUser['email'] ?? 'Client inconnu';
                        }
                        $usersMap[$userId] = [
                            'name' => $fullName,
                            'email' => $apiUser['email'] ?? null,
                            'phone' => $apiUser['phone'] ?? $apiUser['telephone'] ?? null,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Impossible de récupérer les utilisateurs pour le mapping', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Mapper les données pour le frontend (similaire à AdminBookingController)
            $customerName = 'Client inconnu';
            $customerEmail = null;
            $customerPhone = null;
            
            if (isset($booking['user'])) {
                $userData = $booking['user'];
                $firstName = $userData['firstName'] ?? $userData['prenom'] ?? '';
                $lastName = $userData['lastName'] ?? $userData['nom'] ?? $userData['name'] ?? '';
                $customerName = trim($firstName . ' ' . $lastName);
                if (empty($customerName)) {
                    $customerName = $userData['email'] ?? 'Client inconnu';
                }
                $customerEmail = $userData['email'] ?? null;
                $customerPhone = $userData['phone'] ?? $userData['telephone'] ?? null;
            } elseif (isset($booking['customer_name']) && !empty($booking['customer_name'])) {
                $customerName = $booking['customer_name'];
            } elseif (isset($booking['customer']) && !empty($booking['customer'])) {
                $customerName = $booking['customer'];
            } elseif (isset($booking['clientId']) && isset($usersMap[$booking['clientId']])) {
                $customerName = $usersMap[$booking['clientId']]['name'];
                $customerEmail = $usersMap[$booking['clientId']]['email'];
                $customerPhone = $usersMap[$booking['clientId']]['phone'];
            } elseif (isset($booking['clientId'])) {
                $customerName = 'Client #' . substr($booking['clientId'], 0, 8);
            }

            $propertyName = 'Propriété inconnue';
            $residenceDetails = null;
            if (isset($booking['residenceName']) && !empty($booking['residenceName'])) {
                $propertyName = $booking['residenceName'];
                if (isset($booking['residenceId'])) {
                    try {
                        $residenceDetails = $this->residenceService->find($booking['residenceId']);
                    } catch (\Exception $e) {
                        Log::warning('Impossible de récupérer les détails de la résidence', [
                            'residenceId' => $booking['residenceId'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } elseif (isset($booking['residence'])) {
                $residence = $booking['residence'];
                $propertyName = $residence['nom'] ?? $residence['name'] ?? $residence['title'] ?? 'Propriété inconnue';
                $residenceDetails = $residence;
            } elseif (isset($booking['residenceId'])) {
                try {
                    $residenceDetails = $this->residenceService->find($booking['residenceId']);
                    if ($residenceDetails) {
                        $propertyName = $residenceDetails['name'] ?? $residenceDetails['title'] ?? 'Propriété inconnue';
                    }
                } catch (\Exception $e) {
                    Log::warning('Impossible de récupérer la résidence', [
                        'residenceId' => $booking['residenceId'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $vehicleName = null;
            $vehicleDetails = null;
            $vehicleDriverOption = null;
            if (isset($booking['vehicle'])) {
                $vehicle = $booking['vehicle'];
                // Prioriser le titre, sinon utiliser marque + modèle
                $vehicleName = $vehicle['titre'] ?? (($vehicle['marque'] ?? '') . ' ' . ($vehicle['modele'] ?? ''));
                $vehicleName = trim($vehicleName);
                if (empty($vehicleName)) {
                    $vehicleName = ($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? '');
                    $vehicleName = trim($vehicleName);
                }
                $vehicleDetails = $vehicle;
                // Détecter l'option chauffeur
                if (isset($vehicle['withDriver']) || isset($vehicle['avecChauffeur'])) {
                    $vehicleDriverOption = 'with_driver';
                } elseif (isset($vehicle['withoutDriver']) || isset($vehicle['sansChauffeur'])) {
                    $vehicleDriverOption = 'without_driver';
                }
            } elseif (isset($booking['voiture'])) {
                $vehicle = $booking['voiture'];
                $vehicleName = $vehicle['titre'] ?? (($vehicle['marque'] ?? '') . ' ' . ($vehicle['modele'] ?? ''));
                $vehicleName = trim($vehicleName);
                if (empty($vehicleName)) {
                    $vehicleName = ($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? '');
                    $vehicleName = trim($vehicleName);
                }
                $vehicleDetails = $vehicle;
                if (isset($vehicle['withDriver']) || isset($vehicle['avecChauffeur'])) {
                    $vehicleDriverOption = 'with_driver';
                } elseif (isset($vehicle['withoutDriver']) || isset($vehicle['sansChauffeur'])) {
                    $vehicleDriverOption = 'without_driver';
                }
            } elseif (isset($booking['vehicleId'])) {
                try {
                    $vehicleDetails = $this->vehicleService->find($booking['vehicleId']);
                    if ($vehicleDetails) {
                        $vehicleName = $vehicleDetails['titre'] ?? (($vehicleDetails['brand'] ?? $vehicleDetails['marque'] ?? '') . ' ' . ($vehicleDetails['model'] ?? $vehicleDetails['modele'] ?? ''));
                        $vehicleName = trim($vehicleName);
                        if (isset($vehicleDetails['withDriver']) || isset($vehicleDetails['avecChauffeur'])) {
                            $vehicleDriverOption = 'with_driver';
                        } elseif (isset($vehicleDetails['withoutDriver']) || isset($vehicleDetails['sansChauffeur'])) {
                            $vehicleDriverOption = 'without_driver';
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Impossible de récupérer le véhicule', [
                        'vehicleId' => $booking['vehicleId'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $offerName = null;
            $offerDetails = null;
            if (isset($booking['offer'])) {
                $offer = $booking['offer'];
                $offerName = $offer['titre'] ?? $offer['title'] ?? null;
                $offerDetails = $offer;
            }

            // Déterminer le statut final (confirmée uniquement si ownerConfirmedAt est vraiment renseigné)
            $rawStatus = $booking['status'] ?? 'pending';
            $statusUpper = strtoupper($rawStatus);
            $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
            $checkOutAt = $booking['checkOutAt'] ?? $booking['check_out_at'] ?? null;
            $endDate = $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null;
            $hasOwnerConfirmed = $this->isOwnerConfirmedAtSet($ownerConfirmedAt);

            if (!empty($checkOutAt)) {
                $finalStatus = 'terminée';
            } elseif ($statusUpper === 'PENDING') {
                $finalStatus = 'pending';
            } elseif (($statusUpper === 'CONFIRMEE' || $statusUpper === 'CONFIRMED') && !$hasOwnerConfirmed) {
                $finalStatus = 'pending';
            } else {
                $finalStatus = strtolower($rawStatus);
            }

            // Séjour en cours : clé récupérée et date de fin pas encore passée
            $isStayInProgress = false;
            if (!empty($booking['keyRetrievedAt'] ?? $booking['key_retrieved_at'] ?? null) && $endDate) {
                try {
                    $end = new \DateTimeImmutable($endDate);
                    $isStayInProgress = (new \DateTimeImmutable('now')) <= $end;
                } catch (\Exception $e) {
                    // ignore
                }
            }

            // Type de réservation
            $bookingType = 'unknown';
            if (!empty($booking['offerId'] ?? $booking['offer_id'] ?? null) || isset($booking['offer'])) {
                $bookingType = 'package';
            } elseif (!empty($booking['vehicleId'] ?? $booking['vehicle_id'] ?? null) || isset($booking['vehicle']) || isset($booking['voiture'])) {
                $bookingType = 'vehicle';
            } elseif (!empty($booking['residenceId'] ?? $booking['residence_id'] ?? null) || isset($booking['residence']) || isset($booking['residenceName']) || isset($booking['property_name'])) {
                $bookingType = 'residence';
            }

            // Extraire le prix unitaire (simplifié)
            $unitPriceAmount = null;
            $unitPriceLabel = null;
            if ($residenceDetails) {
                $unitPriceAmount = $residenceDetails['prixParNuit'] ?? $residenceDetails['pricePerNight'] ?? null;
                $unitPriceLabel = 'night';
            } elseif ($vehicleDetails) {
                $unitPriceAmount = $vehicleDetails['prixParJour'] ?? $vehicleDetails['pricePerDay'] ?? null;
                $unitPriceLabel = 'day';
            }

            $mappedBooking = [
                'id' => $booking['id'] ?? $booking['_id'] ?? null,
                'bookingType' => $bookingType,
                'customerName' => $customerName,
                'customer' => $customerName,
                'customerEmail' => $customerEmail,
                'customerPhone' => $customerPhone,
                'clientId' => $booking['clientId'] ?? null,
                'propertyName' => $propertyName,
                'residenceId' => $booking['residenceId'] ?? null,
                'residenceDetails' => $residenceDetails,
                'vehicleName' => $vehicleName,
                'vehicleId' => $booking['vehicleId'] ?? null,
                'vehicleDetails' => $vehicleDetails,
                'vehicleDriverOption' => $vehicleDriverOption,
                'offerName' => $offerName,
                'offerId' => $booking['offerId'] ?? null,
                'offerDetails' => $offerDetails,
                'startDate' => $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? null,
                'endDate' => $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null,
                'totalPrice' => (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0),
                'total' => (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0),
                'unitPriceAmount' => $unitPriceAmount,
                'unitPriceLabel' => $unitPriceLabel,
                'status' => $finalStatus,
                'createdAt' => $booking['createdAt'] ?? $booking['created_at'] ?? null,
                'keyRetrievedAt' => $booking['keyRetrievedAt'] ?? $booking['key_retrieved_at'] ?? null,
                'ownerConfirmedAt' => $ownerConfirmedAt,
                'checkOutAt' => $booking['checkOutAt'] ?? $booking['check_out_at'] ?? null,
                'isStayInProgress' => $isStayInProgress,
                'ownerId' => $booking['ownerId'] ?? null,
                'reviewId' => $booking['reviewId'] ?? null,
            ];

            return Inertia::render('Owner/Bookings/Show', [
                'booking' => $mappedBooking,
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la récupération de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);
            abort(404, 'Réservation non trouvée');
        } catch (\Exception $e) {
            Log::error('Exception lors de la récupération de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            abort(404, 'Réservation non trouvée');
        }
    }

    /**
     * Approuver une réservation.
     * Règle métier : seul le propriétaire de la réservation peut approuver (vérification proprietaireId).
     */
    public function approve(string $id): RedirectResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        try {
            // Récupérer le proprietaireId réel
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour l\'approbation', [
                    'user_id' => $user->getAuthIdentifier(),
                ]);
                return redirect()
                    ->route('owner.bookings.index')
                    ->with('error', 'Erreur d\'authentification.');
            }

            // Récupérer la réservation
            $booking = $this->bookingService->find($id);
            
            if (!$booking) {
                return redirect()
                    ->route('owner.bookings.index')
                    ->with('error', 'Réservation non trouvée.');
            }

            // Vérifier que la réservation appartient au propriétaire (même logique que dans index)
            $bookingOwnerId = null;
            
            if (isset($booking['residence']) && is_array($booking['residence'])) {
                $bookingOwnerId = $booking['residence']['proprietaireId'] ?? $booking['residence']['proprietaire_id'] ?? $booking['residence']['ownerId'] ?? $booking['residence']['owner_id'] ?? null;
            }
            if (!$bookingOwnerId && (isset($booking['vehicle']) && is_array($booking['vehicle']))) {
                $bookingOwnerId = $booking['vehicle']['proprietaireId'] ?? $booking['vehicle']['proprietaire_id'] ?? $booking['vehicle']['ownerId'] ?? $booking['vehicle']['owner_id'] ?? null;
            }
            if (!$bookingOwnerId && (isset($booking['voiture']) && is_array($booking['voiture']))) {
                $bookingOwnerId = $booking['voiture']['proprietaireId'] ?? $booking['voiture']['proprietaire_id'] ?? $booking['voiture']['ownerId'] ?? $booking['voiture']['owner_id'] ?? null;
            }
            
            // Champs directs de la réservation
            if (!$bookingOwnerId && isset($booking['ownerId']) && !empty($booking['ownerId'])) {
                $bookingOwnerId = $booking['ownerId'];
            }
            if (!$bookingOwnerId && isset($booking['proprietaireId']) && !empty($booking['proprietaireId'])) {
                $bookingOwnerId = $booking['proprietaireId'];
            }
            if (!$bookingOwnerId && isset($booking['owner_id']) && !empty($booking['owner_id'])) {
                $bookingOwnerId = $booking['owner_id'];
            }
            if (!$bookingOwnerId && isset($booking['proprietaire_id']) && !empty($booking['proprietaire_id'])) {
                $bookingOwnerId = $booking['proprietaire_id'];
            }
            
            // Dans l'offre combinée si présente
            if (!$bookingOwnerId && isset($booking['offer']) && is_array($booking['offer'])) {
                $offer = $booking['offer'];
                $bookingOwnerId = $offer['proprietaireId'] ?? $offer['proprietaire_id'] ?? $offer['ownerId'] ?? $offer['owner_id'] ?? null;
                
                if (!$bookingOwnerId && isset($offer['residence']) && is_array($offer['residence'])) {
                    $bookingOwnerId = $offer['residence']['proprietaireId'] ?? $offer['residence']['proprietaire_id'] ?? $offer['residence']['ownerId'] ?? $offer['residence']['owner_id'] ?? null;
                }
                if (!$bookingOwnerId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                    $bookingOwnerId = $offer['vehicle']['proprietaireId'] ?? $offer['vehicle']['proprietaire_id'] ?? $offer['vehicle']['ownerId'] ?? $offer['vehicle']['owner_id'] ?? null;
                }
                if (!$bookingOwnerId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                    $bookingOwnerId = $offer['voiture']['proprietaireId'] ?? $offer['voiture']['proprietaire_id'] ?? $offer['voiture']['ownerId'] ?? $offer['voiture']['owner_id'] ?? null;
                }
            }
            
            // Comparer en string ET en int pour gérer les cas où l'un est string et l'autre int
            $matches = false;
            if ($bookingOwnerId) {
                $matches = (
                    (string) $bookingOwnerId === (string) $proprietaireId ||
                    (is_numeric($bookingOwnerId) && is_numeric($proprietaireId) && (int) $bookingOwnerId === (int) $proprietaireId)
                );
            }
            
            if (!$matches) {
                Log::warning('Tentative d\'approbation d\'une réservation qui n\'appartient pas au propriétaire', [
                    'booking_id' => $id,
                    'booking_owner_id' => $bookingOwnerId,
                    'proprietaire_id' => $proprietaireId,
                ]);
                return redirect()
                    ->route('owner.bookings.index')
                    ->with('error', 'Réservation non trouvée ou accès non autorisé.');
            }

            $this->bookingService->approve($id);

            return redirect()
                ->route('owner.bookings.index')
                ->with('success', 'Réservation approuvée avec succès.');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de l\'approbation de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return redirect()
                ->route('owner.bookings.index')
                ->with('error', $e->getMessage() ?: 'Une erreur est survenue lors de l\'approbation de la réservation.');
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'approbation de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('owner.bookings.index')
                ->with('error', 'Une erreur est survenue lors de l\'approbation de la réservation.');
        }
    }

    /**
     * Rejeter une réservation.
     * Règle métier : seul le propriétaire de la réservation peut rejeter (vérification proprietaireId).
     */
    public function reject(Request $request, string $id): RedirectResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        try {
            // Récupérer le proprietaireId réel
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour le rejet', [
                    'user_id' => $user->getAuthIdentifier(),
                ]);
                return redirect()
                    ->route('owner.bookings.index')
                    ->with('error', 'Erreur d\'authentification.');
            }

            // Récupérer la réservation
            $booking = $this->bookingService->find($id);
            
            if (!$booking) {
                return redirect()
                    ->route('owner.bookings.index')
                    ->with('error', 'Réservation non trouvée.');
            }

            // Vérifier que la réservation appartient au propriétaire (même logique que dans index)
            $bookingOwnerId = null;
            
            if (isset($booking['residence']) && is_array($booking['residence'])) {
                $bookingOwnerId = $booking['residence']['proprietaireId'] ?? $booking['residence']['proprietaire_id'] ?? $booking['residence']['ownerId'] ?? $booking['residence']['owner_id'] ?? null;
            }
            if (!$bookingOwnerId && (isset($booking['vehicle']) && is_array($booking['vehicle']))) {
                $bookingOwnerId = $booking['vehicle']['proprietaireId'] ?? $booking['vehicle']['proprietaire_id'] ?? $booking['vehicle']['ownerId'] ?? $booking['vehicle']['owner_id'] ?? null;
            }
            if (!$bookingOwnerId && (isset($booking['voiture']) && is_array($booking['voiture']))) {
                $bookingOwnerId = $booking['voiture']['proprietaireId'] ?? $booking['voiture']['proprietaire_id'] ?? $booking['voiture']['ownerId'] ?? $booking['voiture']['owner_id'] ?? null;
            }
            
            // Champs directs de la réservation
            if (!$bookingOwnerId && isset($booking['ownerId']) && !empty($booking['ownerId'])) {
                $bookingOwnerId = $booking['ownerId'];
            }
            if (!$bookingOwnerId && isset($booking['proprietaireId']) && !empty($booking['proprietaireId'])) {
                $bookingOwnerId = $booking['proprietaireId'];
            }
            if (!$bookingOwnerId && isset($booking['owner_id']) && !empty($booking['owner_id'])) {
                $bookingOwnerId = $booking['owner_id'];
            }
            if (!$bookingOwnerId && isset($booking['proprietaire_id']) && !empty($booking['proprietaire_id'])) {
                $bookingOwnerId = $booking['proprietaire_id'];
            }
            
            // Dans l'offre combinée si présente
            if (!$bookingOwnerId && isset($booking['offer']) && is_array($booking['offer'])) {
                $offer = $booking['offer'];
                $bookingOwnerId = $offer['proprietaireId'] ?? $offer['proprietaire_id'] ?? $offer['ownerId'] ?? $offer['owner_id'] ?? null;
                
                if (!$bookingOwnerId && isset($offer['residence']) && is_array($offer['residence'])) {
                    $bookingOwnerId = $offer['residence']['proprietaireId'] ?? $offer['residence']['proprietaire_id'] ?? $offer['residence']['ownerId'] ?? $offer['residence']['owner_id'] ?? null;
                }
                if (!$bookingOwnerId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                    $bookingOwnerId = $offer['vehicle']['proprietaireId'] ?? $offer['vehicle']['proprietaire_id'] ?? $offer['vehicle']['ownerId'] ?? $offer['vehicle']['owner_id'] ?? null;
                }
                if (!$bookingOwnerId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                    $bookingOwnerId = $offer['voiture']['proprietaireId'] ?? $offer['voiture']['proprietaire_id'] ?? $offer['voiture']['ownerId'] ?? $offer['voiture']['owner_id'] ?? null;
                }
            }
            
            // Comparer en string ET en int pour gérer les cas où l'un est string et l'autre int
            $matches = false;
            if ($bookingOwnerId) {
                $matches = (
                    (string) $bookingOwnerId === (string) $proprietaireId ||
                    (is_numeric($bookingOwnerId) && is_numeric($proprietaireId) && (int) $bookingOwnerId === (int) $proprietaireId)
                );
            }
            
            if (!$matches) {
                Log::warning('Tentative de rejet d\'une réservation qui n\'appartient pas au propriétaire', [
                    'booking_id' => $id,
                    'booking_owner_id' => $bookingOwnerId,
                    'proprietaire_id' => $proprietaireId,
                ]);
                return redirect()
                    ->route('owner.bookings.index')
                    ->with('error', 'Réservation non trouvée ou accès non autorisé.');
            }

            $reason = $request->input('reason');
            $this->bookingService->reject($id, $reason);

            return redirect()
                ->route('owner.bookings.index')
                ->with('success', 'Réservation rejetée avec succès.');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors du rejet de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return redirect()
                ->route('owner.bookings.index')
                ->with('error', $e->getMessage() ?: 'Une erreur est survenue lors du rejet de la réservation.');
        } catch (\Exception $e) {
            Log::error('Erreur lors du rejet de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('owner.bookings.index')
                ->with('error', 'Une erreur est survenue lors du rejet de la réservation.');
        }
    }

    /**
     * Confirmer le départ (checkout) pour une réservation.
     * Règle métier : seul le propriétaire de la réservation peut confirmer le départ (vérification proprietaireId).
     */
    public function confirmCheckOut(string $id): RedirectResponse
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Non authentifié');
        }

        try {
            $proprietaireId = $this->getProprietaireId($user);
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour confirm-checkout', ['user_id' => $user->getAuthIdentifier()]);
                return redirect()->route('owner.bookings.show', $id)->with('error', 'Erreur d\'authentification.');
            }

            $booking = $this->bookingService->find($id);
            if (!$booking) {
                return redirect()->route('owner.bookings.index')->with('error', 'Réservation non trouvée.');
            }

            $bookingOwnerId = $this->extractBookingOwnerId($booking);
            $matches = $bookingOwnerId && (
                (string) $bookingOwnerId === (string) $proprietaireId
                || (is_numeric($bookingOwnerId) && is_numeric($proprietaireId) && (int) $bookingOwnerId === (int) $proprietaireId)
            );
            if (!$matches) {
                Log::warning('Tentative confirm-checkout sur une réservation qui n\'appartient pas au propriétaire', ['booking_id' => $id]);
                return redirect()->route('owner.bookings.index')->with('error', 'Réservation non trouvée ou accès non autorisé.');
            }

            $this->bookingService->confirmCheckOut($id);
            return redirect()->route('owner.bookings.show', $id)->with('success', 'Checkout confirmé avec succès.');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API confirm-checkout', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->route('owner.bookings.show', $id)->with('error', $e->getMessage() ?: 'Erreur lors de la confirmation du checkout.');
        } catch (\Exception $e) {
            Log::error('Erreur confirm-checkout', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->route('owner.bookings.show', $id)->with('error', 'Une erreur est survenue lors de la confirmation du checkout.');
        }
    }

    private function extractBookingOwnerId(array $booking): ?string
    {
        if (isset($booking['residence']) && is_array($booking['residence'])) {
            $id = $booking['residence']['proprietaireId'] ?? $booking['residence']['ownerId'] ?? $booking['residence']['proprietaire_id'] ?? $booking['residence']['owner_id'] ?? null;
            if ($id) return (string) $id;
        }
        if (isset($booking['vehicle']) && is_array($booking['vehicle'])) {
            $id = $booking['vehicle']['proprietaireId'] ?? $booking['vehicle']['ownerId'] ?? $booking['vehicle']['proprietaire_id'] ?? $booking['vehicle']['owner_id'] ?? null;
            if ($id) return (string) $id;
        }
        if (isset($booking['voiture']) && is_array($booking['voiture'])) {
            $id = $booking['voiture']['proprietaireId'] ?? $booking['voiture']['ownerId'] ?? null;
            if ($id) return (string) $id;
        }
        foreach (['ownerId', 'proprietaireId', 'owner_id', 'proprietaire_id'] as $key) {
            if (!empty($booking[$key])) return (string) $booking[$key];
        }
        if (isset($booking['offer']) && is_array($booking['offer'])) {
            $offer = $booking['offer'];
            $id = $offer['proprietaireId'] ?? $offer['ownerId'] ?? null;
            if ($id) return (string) $id;
        }
        return null;
    }

    /**
     * Retourne true seulement si ownerConfirmedAt est une vraie date (pas null, "", "null", etc.)
     */
    private function isOwnerConfirmedAtSet(mixed $ownerConfirmedAt): bool
    {
        if ($ownerConfirmedAt === null || $ownerConfirmedAt === false) {
            return false;
        }
        $s = trim((string) $ownerConfirmedAt);
        if ($s === '' || strtolower($s) === 'null' || strtolower($s) === 'undefined') {
            return false;
        }
        try {
            new \DateTimeImmutable($s);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
