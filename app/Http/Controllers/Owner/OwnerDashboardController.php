<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\HasProprietaireId;
use App\Services\DodoVroumApi\BookingService;
use App\Services\DodoVroumApi\ResidenceService;
use App\Services\DodoVroumApi\VehicleService;
use App\Services\DodoVroumApi\UserService;
use App\Services\DodoVroumApiService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OwnerDashboardController extends Controller
{
    use HasProprietaireId;
    
    public function __construct(
        protected BookingService $bookingService,
        protected ResidenceService $residenceService,
        protected VehicleService $vehicleService,
        protected UserService $userService,
        protected DodoVroumApiService $apiService
    ) {
    }

    private function extractData(array $data): array
    {
        if (isset($data['data'])) {
            if (is_array($data['data']) && isset($data['data'][0])) {
                return $data['data'];
            } elseif (is_array($data['data'])) {
                return [$data['data']];
            }
        }
        return $data;
    }

    public function __invoke(): Response
    {
        $user = auth()->user();

        try {
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            Log::info('🔍 DEBUG OwnerDashboardController::__invoke', [
                'user_id' => $user->getAuthIdentifier(),
                'user_id_type' => gettype($user->getAuthIdentifier()),
                'proprietaireId' => $proprietaireId,
                'proprietaireId_type' => gettype($proprietaireId),
            ]);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour le dashboard', [
                    'user_id' => $user->getAuthIdentifier(),
                ]);
                
                return Inertia::render('Owner/Dashboard', [
                    'stats' => [
                        'residences' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'withoutImage' => 0, 'last' => null],
                        'vehicles' => ['total' => 0, 'available' => 0, 'unavailable' => 0, 'withoutImage' => 0, 'withoutPrice' => 0, 'last' => null],
                        'bookings' => ['total' => 0, 'active' => 0, 'pending' => 0, 'completed' => 0, 'cancelled' => 0],
                        'revenue' => ['today' => 0, 'month' => 0, 'total' => 0, 'paidBookings' => 0, 'unpaidBookings' => 0],
                        'comboOffers' => 0,
                    ],
                    'recentBookings' => [],
                    'alerts' => [],
                ]);
            }
            
            // Utiliser le proprietaireId réel pour filtrer les données
            $apiFilters = [];
            if (is_numeric($proprietaireId)) {
                $apiFilters['proprietaireId'] = (int) $proprietaireId;
            } else {
                $apiFilters['proprietaireId'] = $proprietaireId;
            }
            
            Log::info('🔍 DEBUG Filtres API dashboard', [
                'apiFilters' => $apiFilters,
                'apiFilters_count' => count($apiFilters),
            ]);
            
            // Récupérer les statistiques pour le propriétaire avec le proprietaireId réel
            $allResidences = $this->apiService->getResidences($apiFilters);
            $allVehicles = $this->apiService->getVehicles($apiFilters);
            $allBookings = $this->apiService->getBookings($apiFilters);
            $allComboOffers = $this->apiService->getComboOffers($apiFilters);
            
            Log::info('🔍 DEBUG Réponses API dashboard', [
                'allResidences_count' => count($allResidences),
                'allVehicles_count' => count($allVehicles),
                'allBookings_count' => count($allBookings),
                'allComboOffers_count' => count($allComboOffers),
            ]);
            
            // Double vérification côté serveur pour les résidences
            $residences = [];
            foreach ($allResidences as $residence) {
                $residenceProprietaireId = $residence['proprietaireId'] ?? $residence['proprietaire_id'] ?? $residence['ownerId'] ?? $residence['owner_id'] ?? null;
                if ($residenceProprietaireId && (
                    (string) $residenceProprietaireId === (string) $proprietaireId ||
                    (int) $residenceProprietaireId === (int) $proprietaireId
                )) {
                    $residences[] = $residence;
                }
            }
            
            // Double vérification côté serveur pour les véhicules
            $vehicles = [];
            foreach ($allVehicles as $vehicle) {
                $vehicleProprietaireId = $vehicle['proprietaireId'] ?? $vehicle['proprietaire_id'] ?? $vehicle['ownerId'] ?? $vehicle['owner_id'] ?? null;
                if ($vehicleProprietaireId && (
                    (string) $vehicleProprietaireId === (string) $proprietaireId ||
                    (int) $vehicleProprietaireId === (int) $proprietaireId
                )) {
                    $vehicles[] = $vehicle;
                }
            }
            
            // Double vérification côté serveur pour les réservations (même logique que OwnerBookingController)
            $bookings = [];
            foreach ($allBookings as $booking) {
                // Extraire le proprietaireId depuis la réservation (même logique que OwnerBookingController)
                // PRIORITÉ 1 : Résidence ou véhicule inclus dans la réservation
                $bookingProprietaireId = null;
                
                if (isset($booking['residence']) && is_array($booking['residence'])) {
                    $bookingProprietaireId = $booking['residence']['proprietaireId'] ?? $booking['residence']['proprietaire_id'] ?? $booking['residence']['ownerId'] ?? $booking['residence']['owner_id'] ?? null;
                }
                if (!$bookingProprietaireId && (isset($booking['vehicle']) && is_array($booking['vehicle']))) {
                    $bookingProprietaireId = $booking['vehicle']['proprietaireId'] ?? $booking['vehicle']['proprietaire_id'] ?? $booking['vehicle']['ownerId'] ?? $booking['vehicle']['owner_id'] ?? null;
                }
                if (!$bookingProprietaireId && (isset($booking['voiture']) && is_array($booking['voiture']))) {
                    $bookingProprietaireId = $booking['voiture']['proprietaireId'] ?? $booking['voiture']['proprietaire_id'] ?? $booking['voiture']['ownerId'] ?? $booking['voiture']['owner_id'] ?? null;
                }
                
                // PRIORITÉ 2 : Les champs directs de la réservation
                if (!$bookingProprietaireId && isset($booking['ownerId']) && !empty($booking['ownerId'])) {
                    $bookingProprietaireId = $booking['ownerId'];
                }
                if (!$bookingProprietaireId && isset($booking['proprietaireId']) && !empty($booking['proprietaireId'])) {
                    $bookingProprietaireId = $booking['proprietaireId'];
                }
                if (!$bookingProprietaireId && isset($booking['owner_id']) && !empty($booking['owner_id'])) {
                    $bookingProprietaireId = $booking['owner_id'];
                }
                if (!$bookingProprietaireId && isset($booking['proprietaire_id']) && !empty($booking['proprietaire_id'])) {
                    $bookingProprietaireId = $booking['proprietaire_id'];
                }
                
                // PRIORITÉ 3 : Dans l'offre combinée si présente
                if (!$bookingProprietaireId && isset($booking['offer']) && is_array($booking['offer'])) {
                    $offer = $booking['offer'];
                    $bookingProprietaireId = $offer['proprietaireId'] ?? $offer['proprietaire_id'] ?? $offer['ownerId'] ?? $offer['owner_id'] ?? null;
                    
                    if (!$bookingProprietaireId && isset($offer['residence']) && is_array($offer['residence'])) {
                        $bookingProprietaireId = $offer['residence']['proprietaireId'] ?? $offer['residence']['proprietaire_id'] ?? $offer['residence']['ownerId'] ?? $offer['residence']['owner_id'] ?? null;
                    }
                    if (!$bookingProprietaireId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                        $bookingProprietaireId = $offer['vehicle']['proprietaireId'] ?? $offer['vehicle']['proprietaire_id'] ?? $offer['vehicle']['ownerId'] ?? $offer['vehicle']['owner_id'] ?? null;
                    }
                    if (!$bookingProprietaireId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                        $bookingProprietaireId = $offer['voiture']['proprietaireId'] ?? $offer['voiture']['proprietaire_id'] ?? $offer['voiture']['ownerId'] ?? $offer['voiture']['owner_id'] ?? null;
                    }
                }
                
                // Comparer en string ET en int pour gérer les cas où l'un est string et l'autre int
                $matches = false;
                if ($bookingProprietaireId) {
                    $matches = (
                        (string) $bookingProprietaireId === (string) $proprietaireId ||
                        (is_numeric($bookingProprietaireId) && is_numeric($proprietaireId) && (int) $bookingProprietaireId === (int) $proprietaireId)
                    );
                }
                
                if ($matches) {
                    $bookings[] = $booking;
                }
            }
            
            // Double vérification côté serveur pour les offres combinées
            $comboOffers = [];
            foreach ($allComboOffers as $offer) {
                // Les offres ont le proprietaireId dans la résidence ou le véhicule
                $offerProprietaireId = null;
                if (isset($offer['residence']) && is_array($offer['residence'])) {
                    $offerProprietaireId = $offer['residence']['proprietaireId'] ?? $offer['residence']['ownerId'] ?? null;
                }
                if (!$offerProprietaireId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                    $offerProprietaireId = $offer['vehicle']['proprietaireId'] ?? $offer['vehicle']['ownerId'] ?? null;
                }
                if (!$offerProprietaireId) {
                    $offerProprietaireId = $offer['proprietaireId'] ?? $offer['ownerId'] ?? null;
                }
                
                if ($offerProprietaireId && (
                    (string) $offerProprietaireId === (string) $proprietaireId ||
                    (int) $offerProprietaireId === (int) $proprietaireId
                )) {
                    $comboOffers[] = $offer;
                }
            }
            
            // Calculer les statistiques détaillées
            $stats = $this->calculateDetailedStats($residences, $vehicles, $bookings, $comboOffers);
            
            // Récupérer les réservations récentes (5 dernières) avec informations complètes
            $recentBookings = array_slice($bookings, 0, 5);
            
            // Générer les alertes
            $alerts = $this->generateAlerts($residences, $vehicles, $bookings);

            // Fetch all users once to map client IDs to names
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
                        $usersMap[$userId] = $fullName;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch users for dashboard booking mapping', ['error' => $e->getMessage()]);
            }

            // Map bookings for the frontend
            $mappedBookings = array_map(function ($booking) use ($usersMap, $residences, $vehicles) {
                // Extract customer name
                $customerName = 'Client inconnu';
                if (isset($booking['user'])) {
                    $bookingUser = $booking['user'];
                    $firstName = $bookingUser['firstName'] ?? $bookingUser['prenom'] ?? '';
                    $lastName = $bookingUser['lastName'] ?? $bookingUser['nom'] ?? $bookingUser['name'] ?? '';
                    $customerName = trim($firstName . ' ' . $lastName);
                    if (empty($customerName)) {
                        $customerName = $bookingUser['email'] ?? 'Client inconnu';
                    }
                } elseif (isset($booking['customer_name'])) {
                    $customerName = $booking['customer_name'];
                } elseif (isset($booking['customer'])) {
                    $customerName = $booking['customer'];
                } elseif (isset($booking['clientId']) && isset($usersMap[$booking['clientId']])) {
                    $customerName = $usersMap[$booking['clientId']];
                } elseif (isset($booking['clientId'])) {
                    $customerName = 'Client #' . substr($booking['clientId'], 0, 8);
                }

                // Extract property/vehicle/offer name
                $propertyName = null;
                
                // Vérifier d'abord si c'est une résidence
                if (isset($booking['residenceName']) && !empty($booking['residenceName'])) {
                    $propertyName = $booking['residenceName'];
                } elseif (isset($booking['residence']) && is_array($booking['residence'])) {
                    $residence = $booking['residence'];
                    $propertyName = $residence['nom'] ?? $residence['name'] ?? $residence['title'] ?? null;
                } elseif (isset($booking['property_name']) && !empty($booking['property_name'])) {
                    $propertyName = $booking['property_name'];
                } elseif (isset($booking['residenceId']) && !empty($booking['residenceId'])) {
                    // Si on a un residenceId mais pas les détails, chercher dans les résidences récupérées
                    $residenceId = $booking['residenceId'];
                    foreach ($residences as $residence) {
                        $resId = $residence['id'] ?? $residence['_id'] ?? null;
                        if ($resId && ((string) $resId === (string) $residenceId || (is_numeric($resId) && is_numeric($residenceId) && (int) $resId === (int) $residenceId))) {
                            $propertyName = $residence['nom'] ?? $residence['name'] ?? $residence['title'] ?? null;
                            break;
                        }
                    }
                }
                
                // Si pas de résidence, vérifier si c'est un véhicule
                if (!$propertyName) {
                    if (isset($booking['vehicle']) && is_array($booking['vehicle'])) {
                        $vehicle = $booking['vehicle'];
                        $propertyName = $vehicle['titre'] ?? $vehicle['name'] ?? null;
                        if (!$propertyName) {
                            $marque = $vehicle['marque'] ?? $vehicle['brand'] ?? '';
                            $modele = $vehicle['modele'] ?? $vehicle['model'] ?? '';
                            $propertyName = trim($marque . ' ' . $modele);
                        }
                    } elseif (isset($booking['voiture']) && is_array($booking['voiture'])) {
                        $vehicle = $booking['voiture'];
                        $propertyName = $vehicle['titre'] ?? $vehicle['name'] ?? null;
                        if (!$propertyName) {
                            $marque = $vehicle['marque'] ?? $vehicle['brand'] ?? '';
                            $modele = $vehicle['modele'] ?? $vehicle['model'] ?? '';
                            $propertyName = trim($marque . ' ' . $modele);
                        }
                    } elseif (isset($booking['vehicleName']) && !empty($booking['vehicleName'])) {
                        $propertyName = $booking['vehicleName'];
                    } elseif (isset($booking['vehicleId']) && !empty($booking['vehicleId'])) {
                        // Si on a un vehicleId mais pas les détails, chercher dans les véhicules récupérés
                        $vehicleId = $booking['vehicleId'];
                        foreach ($vehicles as $vehicle) {
                            $vehId = $vehicle['id'] ?? $vehicle['_id'] ?? null;
                            if ($vehId && ((string) $vehId === (string) $vehicleId || (is_numeric($vehId) && is_numeric($vehicleId) && (int) $vehId === (int) $vehicleId))) {
                                $propertyName = $vehicle['titre'] ?? $vehicle['name'] ?? null;
                                if (!$propertyName) {
                                    $marque = $vehicle['marque'] ?? $vehicle['brand'] ?? '';
                                    $modele = $vehicle['modele'] ?? $vehicle['model'] ?? '';
                                    $propertyName = trim($marque . ' ' . $modele);
                                }
                                break;
                            }
                        }
                    }
                }
                
                // Si pas de résidence ni véhicule, vérifier si c'est une offre combinée
                if (!$propertyName && isset($booking['offer']) && is_array($booking['offer'])) {
                    $offer = $booking['offer'];
                    $propertyName = $offer['titre'] ?? $offer['title'] ?? $offer['name'] ?? null;
                } elseif (!$propertyName && isset($booking['offerName']) && !empty($booking['offerName'])) {
                    $propertyName = $booking['offerName'];
                }
                
                // Fallback si rien n'est trouvé
                if (!$propertyName) {
                    $propertyName = 'Non spécifié';
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

                // Déterminer le statut (aligné sur OwnerBookingController : checkOutAt / date fin → terminée)
                $rawStatus = $booking['status'] ?? 'pending';
                $statusUpper = strtoupper($rawStatus);
                $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
                $checkOutAt = $booking['checkOutAt'] ?? $booking['check_out_at'] ?? null;

                $isStayCompleted = false;
                if ($endDate) {
                    try {
                        $endDateTime = new \DateTime($endDate);
                        $now = new \DateTime();
                        $isStayCompleted = $endDateTime < $now;
                    } catch (\Exception $e) {
                    }
                }

                $hasOwnerConfirmed = $this->isOwnerConfirmedAtSet($ownerConfirmedAt);
                if (!empty($checkOutAt)) {
                    $finalStatus = 'terminée';
                } elseif ($isStayCompleted) {
                    $finalStatus = 'terminée';
                } elseif (($statusUpper === 'CONFIRMEE' || $statusUpper === 'CONFIRMED') && !$hasOwnerConfirmed) {
                    $finalStatus = 'pending';
                } else {
                    $finalStatus = strtolower($rawStatus);
                }

                $statusFormatted = 'En attente';
                if (in_array(strtolower($finalStatus), ['confirmed', 'confirmee'])) {
                    $statusFormatted = 'Confirmée';
                } elseif (strtolower($finalStatus) === 'pending') {
                    $statusFormatted = 'En attente';
                } elseif (in_array(strtolower($finalStatus), ['cancelled', 'canceled', 'annulée', 'annulee'])) {
                    $statusFormatted = 'Annulée';
                } elseif (in_array(strtolower($finalStatus), ['completed', 'terminee', 'terminée'])) {
                    $statusFormatted = 'Terminée';
                }

                return [
                    'id' => $booking['id'] ?? $booking['_id'] ?? null,
                    'customer' => $customerName,
                    'property' => $propertyName,
                    'dates' => $dates,
                    'status' => $statusFormatted,
                ];
            }, $recentBookings);

        } catch (\Exception $e) {
            // En cas d'erreur API, utiliser des valeurs par défaut
            Log::error('Erreur récupération données dashboard propriétaire', ['error' => $e->getMessage()]);
            
            $stats = [
                'residences' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'withoutImage' => 0, 'last' => null],
                'vehicles' => ['total' => 0, 'available' => 0, 'unavailable' => 0, 'withoutImage' => 0, 'withoutPrice' => 0, 'last' => null],
                'bookings' => ['total' => 0, 'active' => 0, 'pending' => 0, 'completed' => 0, 'cancelled' => 0],
                'revenue' => ['today' => 0, 'month' => 0, 'total' => 0, 'paidBookings' => 0, 'unpaidBookings' => 0],
                'comboOffers' => 0,
            ];
            $mappedBookings = [];
            $alerts = [];
        }

        return Inertia::render('Owner/Dashboard', [
            'stats' => $stats,
            'recentBookings' => $mappedBookings,
            'alerts' => $alerts ?? [],
        ]);
    }

    /**
     * Calculer les statistiques détaillées
     */
    private function calculateDetailedStats(array $residences, array $vehicles, array $bookings, array $comboOffers): array
    {
        // Stats résidences
        $activeResidences = 0;
        $inactiveResidences = 0;
        $residencesWithoutImage = 0;
        $lastResidence = null;
        
        foreach ($residences as $residence) {
            $isActive = $residence['isActive'] ?? $residence['is_active'] ?? $residence['available'] ?? true;
            if ($isActive === true || $isActive === 'true' || $isActive === 1) {
                $activeResidences++;
            } else {
                $inactiveResidences++;
            }
            
            // Vérifier si sans image
            if (empty($residence['imageUrl'] ?? $residence['image_url'] ?? $residence['images'] ?? [])) {
                $residencesWithoutImage++;
            }
            
            // Dernière résidence (par ID ou date de création)
            if (!$lastResidence) {
                $lastResidence = $residence;
            }
        }
        
        // Stats véhicules
        $availableVehicles = 0;
        $unavailableVehicles = 0;
        $vehiclesWithoutImage = 0;
        $vehiclesWithoutPrice = 0;
        $lastVehicle = null;
        
        foreach ($vehicles as $vehicle) {
            $isAvailable = $vehicle['available'] ?? $vehicle['isAvailable'] ?? $vehicle['is_available'] ?? true;
            if ($isAvailable === true || $isAvailable === 'true' || $isAvailable === 1) {
                $availableVehicles++;
            } else {
                $unavailableVehicles++;
            }
            
            // Vérifier si sans image
            if (empty($vehicle['imageUrl'] ?? $vehicle['image_url'] ?? $vehicle['images'] ?? [])) {
                $vehiclesWithoutImage++;
            }
            
            // Vérifier si sans prix
            $price = $vehicle['prixParJour'] ?? $vehicle['pricePerDay'] ?? $vehicle['price'] ?? 0;
            if (empty($price) || $price == 0) {
                $vehiclesWithoutPrice++;
            }
            
            // Dernier véhicule
            if (!$lastVehicle) {
                $lastVehicle = $vehicle;
            }
        }
        
        // Stats réservations
        $activeBookings = 0;
        $pendingBookings = 0;
        $completedBookings = 0;
        $cancelledBookings = 0;
        
        foreach ($bookings as $booking) {
            $rawStatus = $booking['status'] ?? 'pending';
            $statusUpper = strtoupper($rawStatus);
            $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
            $hasOwnerConfirmed = $this->isOwnerConfirmedAtSet($ownerConfirmedAt);

            if (($statusUpper === 'CONFIRMEE' || $statusUpper === 'CONFIRMED') && !$hasOwnerConfirmed) {
                $finalStatus = 'pending';
            } else {
                $finalStatus = strtolower($rawStatus);
            }

            if ($finalStatus === 'confirmed' || $finalStatus === 'confirmee' || $finalStatus === 'active') {
                $activeBookings++;
            } elseif ($finalStatus === 'pending' || $finalStatus === 'en attente') {
                $pendingBookings++;
            } elseif ($finalStatus === 'completed' || $finalStatus === 'terminee' || $finalStatus === 'terminée') {
                $completedBookings++;
            } elseif ($finalStatus === 'cancelled' || $finalStatus === 'annulee' || $finalStatus === 'annulée') {
                $cancelledBookings++;
            }
        }
        
        // Revenus
        $today = new \DateTime();
        $monthStart = new \DateTime($today->format('Y-m-01'));
        
        $revenueToday = 0;
        $revenueMonth = 0;
        $revenueTotal = 0;
        $paidBookings = 0;
        $unpaidBookings = 0;
        
        foreach ($bookings as $booking) {
            $totalPrice = (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0);
            // Montant à verser au propriétaire = 90% du prix total
            $ownerPayment = round($totalPrice * 0.9);
            $revenueTotal += $ownerPayment;
            
            // Vérifier si payé (simplifié - à améliorer avec les données de paiement)
            $isPaid = false;
            if (isset($booking['payments']) && is_array($booking['payments'])) {
                foreach ($booking['payments'] as $payment) {
                    $paymentStatus = strtolower($payment['status'] ?? '');
                    if (in_array($paymentStatus, ['completed', 'paid', 'validated'])) {
                        $isPaid = true;
                        break;
                    }
                }
            }
            
            if ($isPaid) {
                $paidBookings++;
            } else {
                $unpaidBookings++;
            }
            
            // Revenus du mois (montant à verser au propriétaire)
            $bookingDate = $booking['createdAt'] ?? $booking['created_at'] ?? null;
            if ($bookingDate) {
                try {
                    $bookingDateTime = new \DateTime($bookingDate);
                    if ($bookingDateTime >= $monthStart) {
                        $revenueMonth += $ownerPayment;
                    }
                    
                    // Revenus du jour (montant à verser au propriétaire)
                    if ($bookingDateTime->format('Y-m-d') === $today->format('Y-m-d')) {
                        $revenueToday += $ownerPayment;
                    }
                } catch (\Exception $e) {
                    // Ignorer les erreurs de date
                }
            }
        }
        
        return [
            'residences' => [
                'total' => count($residences),
                'active' => $activeResidences,
                'inactive' => $inactiveResidences,
                'withoutImage' => $residencesWithoutImage,
                'last' => $lastResidence ? [
                    'id' => $lastResidence['id'] ?? $lastResidence['_id'] ?? null,
                    'name' => $lastResidence['nom'] ?? $lastResidence['name'] ?? $lastResidence['title'] ?? 'Résidence sans nom',
                ] : null,
            ],
            'vehicles' => [
                'total' => count($vehicles),
                'available' => $availableVehicles,
                'unavailable' => $unavailableVehicles,
                'withoutImage' => $vehiclesWithoutImage,
                'withoutPrice' => $vehiclesWithoutPrice,
                'last' => $lastVehicle ? [
                    'id' => $lastVehicle['id'] ?? $lastVehicle['_id'] ?? null,
                    'name' => $lastVehicle['titre'] ?? $lastVehicle['name'] ?? ($lastVehicle['marque'] ?? '') . ' ' . ($lastVehicle['modele'] ?? ''),
                ] : null,
            ],
            'bookings' => [
                'total' => count($bookings),
                'active' => $activeBookings,
                'pending' => $pendingBookings,
                'completed' => $completedBookings,
                'cancelled' => $cancelledBookings,
            ],
            'revenue' => [
                'today' => $revenueToday,
                'month' => $revenueMonth,
                'total' => $revenueTotal,
                'paidBookings' => $paidBookings,
                'unpaidBookings' => $unpaidBookings,
            ],
            'comboOffers' => count($comboOffers),
        ];
    }

    /**
     * Générer les alertes pour le propriétaire
     */
    private function generateAlerts(array $residences, array $vehicles, array $bookings): array
    {
        $alerts = [];
        
        // Réservations en attente (dont confirmed sans ownerConfirmedAt)
        $pendingBookings = array_filter($bookings, function ($booking) {
            $rawStatus = $booking['status'] ?? 'pending';
            $statusUpper = strtoupper($rawStatus);
            $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
            if (($statusUpper === 'CONFIRMEE' || $statusUpper === 'CONFIRMED') && !$this->isOwnerConfirmedAtSet($ownerConfirmedAt)) {
                return true;
            }
            $status = strtolower($rawStatus);
            return $status === 'pending' || $status === 'en attente';
        });
        if (count($pendingBookings) > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => count($pendingBookings) . ' réservation(s) en attente de validation',
                'action' => 'Voir les réservations',
                'href' => '/owner/bookings?status=pending',
            ];
        }
        
        // Résidences sans photo
        $residencesWithoutImage = array_filter($residences, function($residence) {
            return empty($residence['imageUrl'] ?? $residence['image_url'] ?? $residence['images'] ?? []);
        });
        if (count($residencesWithoutImage) > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => count($residencesWithoutImage) . ' résidence(s) sans photo',
                'action' => 'Voir mes résidences',
                'href' => '/owner/residences',
            ];
        }
        
        // Véhicules sans photo
        $vehiclesWithoutImage = array_filter($vehicles, function($vehicle) {
            return empty($vehicle['imageUrl'] ?? $vehicle['image_url'] ?? $vehicle['images'] ?? []);
        });
        if (count($vehiclesWithoutImage) > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => count($vehiclesWithoutImage) . ' véhicule(s) sans photo',
                'action' => 'Voir mes véhicules',
                'href' => '/owner/vehicles',
            ];
        }
        
        // Véhicules sans prix
        $vehiclesWithoutPrice = array_filter($vehicles, function($vehicle) {
            $price = $vehicle['prixParJour'] ?? $vehicle['pricePerDay'] ?? $vehicle['price'] ?? 0;
            return empty($price) || $price == 0;
        });
        if (count($vehiclesWithoutPrice) > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => count($vehiclesWithoutPrice) . ' véhicule(s) sans prix',
                'action' => 'Voir mes véhicules',
                'href' => '/owner/vehicles',
            ];
        }
        
        return $alerts;
    }

    /**
     * Calculer le revenu total à partir des réservations (méthode legacy, conservée pour compatibilité)
     */
    private function calculateRevenue(array $bookings): string
    {
        $total = 0;
        foreach ($bookings as $booking) {
            $price = $booking['totalPrice'] ?? $booking['total_price'] ?? 0;
            $total += (float) $price;
        }
        
        return number_format($total, 0, ',', ' ');
    }

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
