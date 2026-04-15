<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DodoVroumApi\BookingService;
use App\Services\DodoVroumApi\ResidenceService;
use App\Services\DodoVroumApi\VehicleService;
use App\Services\DodoVroumApi\UserService;
use App\Exceptions\DodoVroumApiException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdminBookingController extends Controller
{
    public function __construct(
        protected BookingService $bookingService,
        protected UserService $userService,
        protected ResidenceService $residenceService,
        protected VehicleService $vehicleService
    ) {
    }

    /**
     * Afficher la liste des réservations
     */
    public function index(Request $request): Response
    {
        try {
            $filters = [];
            
            // Filtre de recherche
            if ($request->has('search') && $request->search) {
                $filters['search'] = $request->search;
            }
            
            // Filtre par statut
            if ($request->has('status') && $request->status) {
                $filters['status'] = $request->status;
            }

            // Récupérer les réservations depuis l'API
            $allBookings = $this->bookingService->all($filters);
            
            // S'assurer que c'est un tableau
            if (!is_array($allBookings)) {
                $allBookings = [];
            }
            
            Log::debug('Réservations récupérées dans AdminBookingController', [
                'all_bookings_count' => count($allBookings),
            ]);

            // Récupérer tous les utilisateurs une fois pour mapper les clientId aux noms
            $usersMap = [];
            try {
                $allUsers = $this->userService->all();
                
                // Créer un map clientId => nom
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
                Log::warning('Impossible de récupérer les utilisateurs pour le mapping', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Mapper les données pour le frontend
            // Filtrer d'abord les réservations qui ont un ID valide
            $validBookings = array_filter($allBookings, function ($booking) {
                $id = $booking['id'] ?? $booking['_id'] ?? null;
                return !empty($id);
            });
            // Réindexer pour garantir la correspondance des clés avec les données mappées
            $validBookings = array_values($validBookings);
            
            // Si filtre "en cours" est activé, on va filtrer après le mapping
            // car on a besoin de calculer isStayInProgress
            
            Log::info('Réservations valides après filtrage', [
                'total_bookings' => count($allBookings),
                'valid_bookings_count' => count($validBookings),
            ]);
            
            $mappedBookings = array_map(function ($booking) use ($usersMap) {
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
                } elseif (isset($booking['clientId'])) {
                    // Si on a seulement un clientId, chercher dans le map des utilisateurs
                    if (isset($usersMap[$booking['clientId']])) {
                        $customerName = $usersMap[$booking['clientId']];
                    } else {
                        // Si pas trouvé dans le map, utiliser un identifiant court
                        $customerName = 'Client #' . substr($booking['clientId'], 0, 8);
                    }
                }

                // Déterminer le type de réservation (résidence / véhicule / package)
                $bookingType = 'unknown';
                if (!empty($booking['offerId'] ?? $booking['offer_id'] ?? null) || isset($booking['offer'])) {
                    $bookingType = 'package';
                } elseif (!empty($booking['vehicleId'] ?? $booking['vehicle_id'] ?? null) || isset($booking['vehicle']) || isset($booking['voiture'])) {
                    $bookingType = 'vehicle';
                } elseif (!empty($booking['residenceId'] ?? $booking['residence_id'] ?? null) || isset($booking['residence']) || isset($booking['residenceName']) || isset($booking['property_name'])) {
                    $bookingType = 'residence';
                }

                // Extraire le nom et l'image de la propriété/résidence
                $propertyName = 'Propriété inconnue';
                $propertyImage = null;
                // Vérifier d'abord residenceName (format direct de l'API)
                if (isset($booking['residenceName']) && !empty($booking['residenceName'])) {
                    $propertyName = $booking['residenceName'];
                } elseif (isset($booking['residence'])) {
                    $residence = $booking['residence'];
                    $propertyName = $residence['nom'] ?? $residence['name'] ?? $residence['title'] ?? 'Propriété inconnue';
                    $propertyImage = $residence['imageUrl'] ?? ($residence['images'][0] ?? null);
                } elseif (isset($booking['property_name'])) {
                    $propertyName = $booking['property_name'];
                }

                // Extraire le nom et l'image du véhicule si présent
                $vehicleName = null;
                $vehicleDriverOption = $this->detectDriverOption($booking);
                if (isset($booking['vehicle'])) {
                    $vehicle = $booking['vehicle'];
                    $vehicleName = ($vehicle['marque'] ?? '') . ' ' . ($vehicle['modele'] ?? '');
                    $vehicleName = trim($vehicleName);
                    if (!$propertyImage) {
                        $propertyImage = $vehicle['imageUrl'] ?? ($vehicle['images'][0] ?? null);
                    }
                } elseif (isset($booking['voiture'])) {
                    $vehicle = $booking['voiture'];
                    $vehicleName = ($vehicle['marque'] ?? '') . ' ' . ($vehicle['modele'] ?? '');
                    $vehicleName = trim($vehicleName);
                    if (!$propertyImage) {
                        $propertyImage = $vehicle['imageUrl'] ?? ($vehicle['images'][0] ?? null);
                    }
                }

                // Extraire le nom et l'image de l'offre combinée si présente
                $offerName = null;
                if (isset($booking['offer'])) {
                    $offer = $booking['offer'];
                    $offerName = $offer['titre'] ?? $offer['title'] ?? null;
                    if (!$propertyImage) {
                        $propertyImage = $offer['imageUrl'] ?? ($offer['images'][0] ?? null);
                    }
                }

                // Récupérer le statut brut de l'API
                $rawStatus = $booking['status'] ?? 'pending';
                $statusUpper = strtoupper($rawStatus);
                
                // Vérifier si le propriétaire a confirmé
                $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
                $checkOutAt = $booking['checkOutAt'] ?? $booking['check_out_at'] ?? null;

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

                // Déterminer le statut final
                // PRIORITÉ 0: Checkout confirmé (bouton "Confirmer le départ") → Terminée
                if (!empty($checkOutAt)) {
                    $finalStatus = 'terminée';
                }
                // PRIORITÉ 1: Si la date de fin est passée, la réservation est terminée
                elseif ($isStayCompleted) {
                    $finalStatus = 'terminée';
                    // Log pour déboguer
                    if ($statusUpper !== 'TERMINEE' && $statusUpper !== 'COMPLETED') {
                        Log::info('Réservation automatiquement marquée comme terminée (date de fin passée)', [
                            'booking_id' => $booking['id'] ?? $booking['_id'] ?? null,
                            'raw_status' => $rawStatus,
                            'end_date' => $endDate,
                            'final_status' => $finalStatus,
                        ]);
                    }
                }
                // En attente de paiement (API NestJS) — avant confirmation propriétaire pour garder le badge distinct
                elseif ($statusUpper === 'AWAITING_PAYMENT') {
                    $finalStatus = 'awaiting_payment';
                }
                // PRIORITÉ 2: Si le propriétaire a confirmé (ownerConfirmedAt existe), le statut doit être "confirmed"
                elseif (!empty($ownerConfirmedAt)) {
                    $finalStatus = 'confirmed';
                    // Log pour déboguer les cas où le statut brut est "pending" mais le propriétaire a confirmé
                    if ($statusUpper === 'PENDING') {
                        Log::info('Réservation confirmée par propriétaire mais statut brut est PENDING', [
                            'booking_id' => $booking['id'] ?? $booking['_id'] ?? null,
                            'raw_status' => $rawStatus,
                            'ownerConfirmedAt' => $ownerConfirmedAt,
                            'final_status' => $finalStatus,
                        ]);
                    }
                }
                // PRIORITÉ 3: Si le statut brut est PENDING, rester en attente
                elseif ($statusUpper === 'PENDING') {
                    $finalStatus = 'pending';
                }
                // PRIORITÉ 4: Si l'API dit "confirmed" mais sans ownerConfirmedAt = pas encore approuvée par l'admin/proprio
                // On affiche "En attente" pour éviter que toutes les réservations paraissent déjà approuvées.
                // Le backend doit définir ownerConfirmedAt lors de PATCH /bookings/:id/approve pour que le statut passe à Confirmée.
                elseif (($statusUpper === 'CONFIRMEE' || $statusUpper === 'CONFIRMED') && empty($ownerConfirmedAt)) {
                    $finalStatus = 'pending';
                } else {
                    $finalStatus = strtolower($rawStatus);
                }

                // Calculer les paiements et déterminer le type de paiement
                $downPaymentAmount = 0;
                $downPaymentPercentage = null;
                $totalPaid = 0;
                $totalPrice = $booking['totalPrice'] ?? $booking['total_price'] ?? 0;
                $paymentType = 'NONE'; // Par défaut, aucun paiement
                
                if (isset($booking['payments']) && is_array($booking['payments'])) {
                    foreach ($booking['payments'] as $payment) {
                        // Calculer le montant total payé (tous les paiements validés/complétés)
                        $paymentStatus = strtolower($payment['status'] ?? $payment['statut'] ?? '');
                        $isCompleted = in_array($paymentStatus, ['completed', 'completé', 'complet', 'validated', 'validé', 'paid', 'payé']);
                        
                        if ($isCompleted) {
                            $amount = $payment['amount'] ?? $payment['montant'] ?? 0;
                            $totalPaid += (float) $amount;
                        }
                        
                        // Chercher les paiements de type "acompte" ou "downPayment" ou avec un pourcentage
                        $paymentTypeStr = strtolower($payment['type'] ?? $payment['paymentType'] ?? '');
                        $isDownPayment = str_contains($paymentTypeStr, 'acompte') || 
                                        str_contains($paymentTypeStr, 'downpayment') || 
                                        str_contains($paymentTypeStr, 'deposit') ||
                                        (isset($payment['isDownPayment']) && $payment['isDownPayment']);
                        
                        if ($isDownPayment && $isCompleted) {
                            $amount = $payment['amount'] ?? $payment['montant'] ?? 0;
                            $downPaymentAmount += (float) $amount;
                            
                            // Extraire le pourcentage depuis le type de paiement (ex: "Acompte (30%)")
                            if (preg_match('/(\d+)%/i', $paymentTypeStr, $matches)) {
                                $downPaymentPercentage = (int) $matches[1];
                            }
                        }
                    }
                    
                    // Si on a un montant mais pas de pourcentage, calculer le pourcentage
                    if ($downPaymentAmount > 0 && $downPaymentPercentage === null && $totalPrice > 0) {
                        $downPaymentPercentage = round(($downPaymentAmount / $totalPrice) * 100);
                    }
                    
                    // Déterminer le type de paiement
                    // Tolérance de 0.01 pour les arrondis
                    $tolerance = 0.01;
                    if ($totalPaid <= $tolerance) {
                        $paymentType = 'NONE';
                    } elseif (abs($totalPaid - $totalPrice) <= $tolerance) {
                        // Le montant payé est égal au prix total (ou très proche)
                        $paymentType = 'FULL_PAYMENT';
                    } else {
                        // Le montant payé est inférieur au prix total
                        $paymentType = 'DOWN_PAYMENT';
                    }
                }
                
                // Si pas de paiements mais qu'on a un pourcentage d'acompte dans les notes
                if ($downPaymentAmount == 0 && isset($booking['notes'])) {
                    // Essayer d'extraire le pourcentage depuis les notes (ex: "Acompte (30%)")
                    if (preg_match('/acompte.*?(\d+)%/i', $booking['notes'], $matches)) {
                        $downPaymentPercentage = (int) $matches[1];
                        $downPaymentAmount = ($totalPrice * $downPaymentPercentage) / 100;
                        $totalPaid = $downPaymentAmount; // Si on trouve un acompte dans les notes, on considère qu'il est payé
                        $paymentType = 'DOWN_PAYMENT';
                    }
                }
                
                // Si la réservation est en attente, considérer que le client a payé selon l'API
                // (acompte ou totalité selon les données de paiement)
                if ($finalStatus === 'pending') {
                    // Si aucun paiement n'est trouvé dans l'API mais qu'on a un acompte dans les notes
                    // ou si totalPaid est 0, on garde la logique normale
                    // Sinon, on utilise les données de l'API pour déterminer le type de paiement
                    // (déjà fait ci-dessus avec $paymentType)
                }
                
                // Calculer le solde restant
                $remainingBalance = max(0, $totalPrice - $totalPaid);
                $isFullyPaid = abs($remainingBalance) <= 0.01; // Tolérance pour les arrondis

                // Helpers de séjour / timeline
                $startDate = $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? null;
                // $endDate déjà récupéré plus haut pour la vérification du statut
                
                // Vérifier si le client a validé la récupération de la clé
                $keyRetrievedAt = $booking['keyRetrievedAt'] ?? $booking['key_retrieved_at'] ?? null;

                $today = new \DateTimeImmutable('today');
                $isCheckInDay = false;
                $isCheckInDatePassed = false;
                $isStayInProgress = false;
                // $isStayCompleted déjà calculé plus haut

                try {
                    if ($startDate) {
                        $start = new \DateTimeImmutable($startDate);
                        $isCheckInDay = $start->format('Y-m-d') === $today->format('Y-m-d');
                        $isCheckInDatePassed = $start < $today;
                    }

                    // Une réservation est "en cours" si :
                    // 1. Le client a validé qu'il a reçu la clé (keyRetrievedAt existe)
                    // 2. La date de fin n'est pas encore passée
                    if ($endDate && !empty($keyRetrievedAt)) {
                        $end = new \DateTimeImmutable($endDate);
                        $isStayInProgress = $today <= $end;
                    }
                } catch (\Exception $e) {
                    // En cas d'erreur de parsing, on laisse les helpers à false
                    Log::warning('Erreur lors du calcul de isStayInProgress', [
                        'booking_id' => $booking['id'] ?? $booking['_id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }

                // En attente d'approbation : uniquement quand le statut est encore "pending"
                $isPendingApproval = ($finalStatus === 'pending');

                [$unitPriceAmount, $unitPriceLabel] = $this->extractUnitPrice($booking);

                return [
                    'id' => $booking['id'] ?? $booking['_id'] ?? null,
                    'bookingType' => $bookingType,
                    'customerName' => $customerName,
                    'propertyName' => $propertyName,
                    'propertyImage' => $propertyImage,
                    'vehicleName' => $vehicleName,
                    'offerName' => $offerName,
                    'vehicleDriverOption' => $vehicleDriverOption,
                    'startDate' => $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? null,
                    'endDate' => $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null,
                    'totalPrice' => $totalPrice,
                    'unitPriceAmount' => $unitPriceAmount,
                    'unitPriceLabel' => $unitPriceLabel,
                    'downPayment' => $downPaymentAmount,
                    'downPaymentPercentage' => $downPaymentPercentage,
                    'totalPaid' => $totalPaid,
                    'remainingBalance' => $remainingBalance,
                    'isFullyPaid' => $isFullyPaid,
                    'isCheckInDay' => $isCheckInDay,
                    'isCheckInDatePassed' => $isCheckInDatePassed,
                    'isStayInProgress' => $isStayInProgress,
                    'isStayCompleted' => $isStayCompleted,
                    'isPendingApproval' => $isPendingApproval,
                    'paymentType' => $paymentType,
                    'status' => $finalStatus,
                    'rawStatus' => $rawStatus, // Garder le statut brut pour debug
                    'createdAt' => $booking['createdAt'] ?? $booking['created_at'] ?? null,
                    'keyRetrievedAt' => $booking['keyRetrievedAt'] ?? $booking['key_retrieved_at'] ?? null,
                    'ownerConfirmedAt' => $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null,
                    'checkOutAt' => $booking['checkOutAt'] ?? $booking['check_out_at'] ?? null,
                ];
            }, $validBookings);

            // Compléter les prix unitaires manquants en interrogeant l'API si nécessaire
            $residenceCache = [];
            $vehicleCache = [];
            $offerCache = [];

            foreach ($mappedBookings as $index => &$mappedBooking) {
                if ($mappedBooking['unitPriceAmount'] !== null) {
                    continue;
                }

                $source = $validBookings[$index] ?? null;
                if (!$source) {
                    continue;
                }

                $residenceId = $source['residenceId']
                    ?? $source['residence_id']
                    ?? data_get($source, 'residence.id')
                    ?? data_get($source, 'residence._id');

                if ($residenceId) {
                    if (!array_key_exists($residenceId, $residenceCache)) {
                        try {
                            $residenceCache[$residenceId] = $this->residenceService->find($residenceId);
                        } catch (\Exception $e) {
                            $residenceCache[$residenceId] = null;
                        }
                    }

                    if ($residenceCache[$residenceId]) {
                        [$amount, $label] = $this->extractUnitPrice(['residence' => $residenceCache[$residenceId]]);
                        if ($amount !== null) {
                            $mappedBooking['unitPriceAmount'] = $amount;
                            $mappedBooking['unitPriceLabel'] = $label;
                            continue;
                        }
                    }
                }

                $vehicleId = $source['vehicleId']
                    ?? $source['vehicle_id']
                    ?? data_get($source, 'vehicle.id')
                    ?? data_get($source, 'vehicle._id');

                if ($vehicleId) {
                    if (!array_key_exists($vehicleId, $vehicleCache)) {
                        try {
                            $vehicleCache[$vehicleId] = $this->vehicleService->find($vehicleId);
                        } catch (\Exception $e) {
                            $vehicleCache[$vehicleId] = null;
                        }
                    }

                    if ($vehicleCache[$vehicleId]) {
                        [$amount, $label] = $this->extractUnitPrice(['vehicle' => $vehicleCache[$vehicleId]]);
                        if ($amount !== null) {
                            $mappedBooking['unitPriceAmount'] = $amount;
                            $mappedBooking['unitPriceLabel'] = $label;
                            continue;
                        }
                    }
                }

                // Note: Pour les offres combinées, on garde l'ancien service pour l'instant
                // car ComboOfferService n'a pas encore été créé
                $offerId = $source['offerId']
                    ?? $source['offer_id']
                    ?? data_get($source, 'offer.id')
                    ?? data_get($source, 'offer._id');

                // TODO: Créer ComboOfferService et utiliser ici
                // Pour l'instant, on skip cette partie
            }
            unset($mappedBooking);

            // Appliquer les filtres côté Laravel (statut, type, propriétaire, période)
            // Statut
            if ($request->filled('status')) {
                $requestedStatus = strtolower($request->get('status'));
                $mappedBookings = array_filter($mappedBookings, function ($booking) use ($requestedStatus) {
                    $bookingStatus = strtolower($booking['status'] ?? '');
                    if ($requestedStatus === 'confirmed' || $requestedStatus === 'confirmee') {
                        return $bookingStatus === 'confirmed' || $bookingStatus === 'confirmee' || $bookingStatus === 'confirmée';
                    }
                    if ($requestedStatus === 'completed' || $requestedStatus === 'terminee' || $requestedStatus === 'terminée') {
                        return $bookingStatus === 'completed' || $bookingStatus === 'terminee' || $bookingStatus === 'terminée';
                    }
                    if ($requestedStatus === 'cancelled' || $requestedStatus === 'canceled') {
                        return $bookingStatus === 'cancelled' || $bookingStatus === 'canceled' || $bookingStatus === 'annulée' || $bookingStatus === 'annulee';
                    }
                    if ($requestedStatus === 'pending' || $requestedStatus === 'en attente') {
                        return $bookingStatus === 'pending' || $bookingStatus === 'en attente';
                    }
                    return $bookingStatus === $requestedStatus;
                });
                $mappedBookings = array_values($mappedBookings);
            }

            // Sans filtre statut explicite : ne pas afficher les tentatives abandonnées (AWAITING_PAYMENT → awaiting_payment)
            if (! $request->filled('status')) {
                $mappedBookings = array_values(array_filter($mappedBookings, function ($booking) {
                    return strtolower($booking['status'] ?? '') !== 'awaiting_payment';
                }));
            }

            // Type de réservation
            if ($request->filled('bookingType')) {
                $requestedType = strtolower($request->get('bookingType'));
                $mappedBookings = array_values(array_filter($mappedBookings, function ($booking) use ($requestedType) {
                    return strtolower($booking['bookingType'] ?? '') === $requestedType;
                }));
            }

            // Filtre propriétaire (par ownerId)
            if ($request->filled('ownerId')) {
                $ownerId = $request->get('ownerId');
                $mappedBookings = array_values(array_filter($mappedBookings, function ($booking) use ($ownerId) {
                    return isset($booking['ownerId']) && (string) $booking['ownerId'] === (string) $ownerId;
                }));
            }

            // Filtre période (startDate / endDate)
            $filterStart = $request->get('startDate');
            $filterEnd = $request->get('endDate');
            if ($filterStart || $filterEnd) {
                try {
                    $filterStartDate = $filterStart ? new \DateTimeImmutable($filterStart) : null;
                    $filterEndDate = $filterEnd ? new \DateTimeImmutable($filterEnd) : null;

                    $mappedBookings = array_values(array_filter($mappedBookings, function ($booking) use ($filterStartDate, $filterEndDate) {
                        $startDate = $booking['startDate'] ?? null;
                        $endDate = $booking['endDate'] ?? null;
                        if (!$startDate || !$endDate) {
                            return false;
                        }
                        try {
                            $start = new \DateTimeImmutable($startDate);
                            $end = new \DateTimeImmutable($endDate);
                        } catch (\Exception $e) {
                            return false;
                        }

                        if ($filterStartDate && $end < $filterStartDate) {
                            return false;
                        }
                        if ($filterEndDate && $start > $filterEndDate) {
                            return false;
                        }
                        return true;
                    }));
                } catch (\Exception $e) {
                    // En cas d'erreur dans les filtres de dates, ne pas filtrer
                }
            }

            // Trier par date de création (plus récentes en premier)
            usort($mappedBookings, function ($a, $b) {
                $dateA = $a['createdAt'] ?? '';
                $dateB = $b['createdAt'] ?? '';
                return strcmp($dateB, $dateA);
            });
            
            Log::debug('Réservations mappées dans AdminBookingController', [
                'mapped_count' => count($mappedBookings),
                'filter_status' => $request->get('status'),
            ]);

            // Pagination côté serveur
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            
            $collection = new Collection($mappedBookings);
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
            
            // Calculer les statistiques sur TOUTES les réservations (pas seulement celles filtrées)
            // Pour cela, on doit mapper toutes les réservations sans filtres pour les stats
            $allBookingsForStats = $this->bookingService->all([]);
            $allValidBookingsForStats = array_filter($allBookingsForStats, function ($booking) {
                $id = $booking['id'] ?? $booking['_id'] ?? null;
                return !empty($id);
            });
            $allValidBookingsForStats = array_values($allValidBookingsForStats);
            
            // Mapper toutes les réservations pour les statistiques (logique simplifiée)
            $allMappedBookingsForStats = array_map(function ($booking) {
                // Utiliser la même logique de mapping que pour l'affichage
                // (simplifié ici pour les stats uniquement)
                $rawStatus = $booking['status'] ?? 'pending';
                $statusUpper = strtoupper($rawStatus);
                $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
                $checkOutAt = $booking['checkOutAt'] ?? $booking['check_out_at'] ?? null;
                $endDate = $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null;

                // PRIORITÉ ABSOLUE : Exclure les réservations annulées
                if (in_array($statusUpper, ['CANCELLED', 'CANCELED', 'ANNULÉE', 'ANNULEE', 'ANNULE'])) {
                    return [
                        'status' => 'cancelled',
                        'totalPrice' => (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0),
                        'startDate' => $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? null,
                        'createdAt' => $booking['createdAt'] ?? $booking['created_at'] ?? null,
                        'rawStatus' => strtolower($rawStatus),
                    ];
                }

                $isStayCompleted = false;
                if ($endDate) {
                    try {
                        $today = new \DateTimeImmutable('today');
                        $end = new \DateTimeImmutable($endDate);
                        $isStayCompleted = $today > $end;
                    } catch (\Exception $e) {
                        // Ignorer les erreurs de parsing
                    }
                }

                // Déterminer le statut final (checkOutAt ou date de fin passée → terminée)
                if (!empty($checkOutAt)) {
                    $finalStatus = 'terminée';
                } elseif ($isStayCompleted) {
                    $finalStatus = 'terminée';
                } elseif (!empty($ownerConfirmedAt)) {
                    $finalStatus = 'confirmed';
                } elseif ($statusUpper === 'PENDING') {
                    $finalStatus = 'pending';
                } elseif (($statusUpper === 'CONFIRMEE' || $statusUpper === 'CONFIRMED') && empty($ownerConfirmedAt)) {
                    $finalStatus = 'pending';
                } else {
                    $finalStatus = strtolower($rawStatus);
                }
                
                return [
                    'status' => $finalStatus,
                    'totalPrice' => (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0),
                    'startDate' => $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? null,
                    'createdAt' => $booking['createdAt'] ?? $booking['created_at'] ?? null,
                    'rawStatus' => strtolower($rawStatus), // Conserver le statut brut pour le filtrage
                ];
            }, $allValidBookingsForStats);
            
            // Calculer les statistiques
            $totalBookings = count($allMappedBookingsForStats);
            $confirmedBookings = 0;
            $pendingBookings = 0;
            $cancelledBookings = 0;
            $totalRevenue = 0;
            $monthRevenue = 0;
            
            $currentMonth = date('Y-m');
            
            // Filtrer les réservations valides pour le calcul des revenus (confirmées ou terminées, exclure annulées et en attente)
            // Même logique que AdminRevenueController et AdminDashboardController
            $validBookingsForRevenue = array_filter($allMappedBookingsForStats, function($booking) {
                // PRIORITÉ 1 : Vérifier le statut brut (rawStatus) pour exclure les annulées
                $rawStatus = strtolower($booking['rawStatus'] ?? $booking['status'] ?? 'pending');
                if (in_array($rawStatus, ['cancelled', 'canceled', 'annulée', 'annulee', 'annule'])) {
                    return false; // Exclure les réservations annulées
                }
                
                // PRIORITÉ 2 : Vérifier le statut final
                $status = strtolower($booking['status'] ?? 'pending');
                
                // Inclure si :
                // 1. Le statut est "completed" ou "terminée" (séjour terminé)
                // 2. Le statut est "confirmed" ou "confirmee" (confirmée)
                if (in_array($status, ['completed', 'terminee', 'terminée'])) {
                    return true; // Séjour terminé
                }
                
                if (in_array($status, ['confirmed', 'confirmee', 'confirmée'])) {
                    return true; // Statut confirmé
                }
                
                // Exclure les réservations en attente (pending)
                return false;
            });
            
            foreach ($allMappedBookingsForStats as $booking) {
                $status = strtolower($booking['status'] ?? 'pending');
                if ($status === 'confirmed' || $status === 'confirmee') {
                    $confirmedBookings++;
                } elseif ($status === 'pending' || $status === 'en attente') {
                    $pendingBookings++;
                } elseif ($status === 'cancelled' || $status === 'annulee' || $status === 'annulée') {
                    $cancelledBookings++;
                }
            }
            
            // Calculer les revenus uniquement sur les réservations valides (commission DodoVroum = 10% du prix total)
            // Utiliser createdAt (date de création) pour cohérence avec AdminRevenueController et AdminDashboardController
            $now = new \DateTime();
            $currentMonthKey = $now->format('Y-m');
            
            Log::info('AdminBookingController - Réservations valides pour calcul revenus', [
                'total_valid_bookings' => count($validBookingsForRevenue),
                'valid_bookings_details' => array_map(function($b) {
                    return [
                        'status' => $b['status'] ?? 'N/A',
                        'totalPrice' => $b['totalPrice'] ?? 0,
                        'startDate' => $b['startDate'] ?? 'N/A',
                        'createdAt' => $b['createdAt'] ?? 'N/A',
                    ];
                }, array_slice($validBookingsForRevenue, 0, 10)),
            ]);
            
            foreach ($validBookingsForRevenue as $booking) {
                $price = (float) ($booking['totalPrice'] ?? 0);
                // Commission DodoVroum = 10% du prix total
                $commission = round($price * 0.1);
                $totalRevenue += $commission;
                
                // Revenus du mois : basé sur la date de création de la réservation (createdAt)
                // Pour cohérence avec AdminRevenueController et AdminDashboardController
                $bookingDate = $booking['createdAt'] ?? null;
                if ($bookingDate) {
                    try {
                        $bookingDateTime = new \DateTime($bookingDate);
                        if ($bookingDateTime->format('Y-m') === $currentMonthKey) {
                            $monthRevenue += $commission;
                        }
                    } catch (\Exception $e) {
                        // Ignorer les erreurs de date
                    }
                }
            }
            
            Log::debug('Réservations paginées dans AdminBookingController', [
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'items_count' => count($paginated->items()),
            ]);

            return Inertia::render('Bookings/Index', [
                'bookings' => $paginated->items(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                ],
                'filters' => [
                    'search' => $request->get('search', ''),
                    'status' => $request->get('status', ''),
                    'bookingType' => $request->get('bookingType', ''),
                    'ownerId' => $request->get('ownerId', ''),
                    'startDate' => $request->get('startDate', ''),
                    'endDate' => $request->get('endDate', ''),
                ],
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
                'context' => $e->getContext(),
            ]);

            return Inertia::render('Bookings/Index', [
                'bookings' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
                'filters' => [
                    'search' => $request->get('search', ''),
                    'status' => $request->get('status', ''),
                ],
                'error' => 'Erreur API lors de la récupération des réservations',
                'stats' => [
                    'totalBookings' => 0,
                    'confirmedBookings' => 0,
                    'pendingBookings' => 0,
                    'cancelledBookings' => 0,
                    'totalRevenue' => 0,
                    'monthRevenue' => 0,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des réservations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Bookings/Index', [
                'bookings' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
                'filters' => [
                    'search' => $request->get('search', ''),
                    'status' => $request->get('status', ''),
                ],
                'error' => 'Erreur lors de la récupération des réservations',
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
     * Afficher les détails d'une réservation
     */
    public function show(string $id): Response|RedirectResponse
    {
        try {
            // Récupérer la réservation par ID
            $booking = $this->bookingService->find($id);

            if (!$booking) {
                return redirect()->route('admin.bookings.index')
                    ->with('error', 'Réservation non trouvée');
            }

            // Récupérer tous les utilisateurs pour mapper les clientId
            $usersMap = [];
            try {
                $allUsers = $this->userService->all();
                
                foreach ($allUsers as $user) {
                    $userId = $user['id'] ?? $user['_id'] ?? null;
                    if ($userId) {
                        $firstName = $user['firstName'] ?? $user['prenom'] ?? '';
                        $lastName = $user['lastName'] ?? $user['nom'] ?? $user['name'] ?? '';
                        $fullName = trim($firstName . ' ' . $lastName);
                        if (empty($fullName)) {
                            $fullName = $user['email'] ?? 'Client inconnu';
                        }
                        $usersMap[$userId] = [
                            'name' => $fullName,
                            'email' => $user['email'] ?? null,
                            'phone' => $user['phone'] ?? $user['telephone'] ?? null,
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Impossible de récupérer les utilisateurs pour le mapping', [
                    'error' => $e->getMessage(),
                ]);
            }

            // Mapper les données pour le frontend
            $customerName = 'Client inconnu';
            $customerEmail = null;
            $customerPhone = null;
            
            if (isset($booking['user'])) {
                $user = $booking['user'];
                $firstName = $user['firstName'] ?? $user['prenom'] ?? '';
                $lastName = $user['lastName'] ?? $user['nom'] ?? $user['name'] ?? '';
                $customerName = trim($firstName . ' ' . $lastName);
                if (empty($customerName)) {
                    $customerName = $user['email'] ?? 'Client inconnu';
                }
                $customerEmail = $user['email'] ?? null;
                $customerPhone = $user['phone'] ?? $user['telephone'] ?? null;
            } elseif (isset($booking['clientId']) && isset($usersMap[$booking['clientId']])) {
                $customerName = $usersMap[$booking['clientId']]['name'];
                $customerEmail = $usersMap[$booking['clientId']]['email'];
                $customerPhone = $usersMap[$booking['clientId']]['phone'];
            } elseif (isset($booking['clientId'])) {
                $customerName = 'Client #' . substr($booking['clientId'], 0, 8);
            }

            $propertyName = 'Propriété inconnue';
            $residenceDetails = null;
            
            // D'abord, essayer de récupérer les détails complets depuis l'API si on a un residenceId
            $residenceId = $booking['residenceId'] ?? $booking['residence_id'] ?? null;
            if ($residenceId) {
                try {
                    Log::info('Tentative de récupération des détails de résidence', [
                        'residenceId' => $residenceId,
                        'booking_id' => $booking['id'] ?? $booking['_id'] ?? null,
                    ]);
                    
                    $residenceDetails = $this->residenceService->find($residenceId);
                    
                    if ($residenceDetails) {
                        // Utiliser les détails complets pour le nom
                        $propertyName = $residenceDetails['nom'] 
                            ?? $residenceDetails['name'] 
                            ?? $residenceDetails['title'] 
                            ?? 'Propriété inconnue';
                        
                        Log::info('Détails de résidence récupérés depuis l\'API avec succès', [
                            'residenceId' => $residenceId,
                            'propertyName' => $propertyName,
                            'has_ville' => isset($residenceDetails['ville']),
                            'has_adresse' => isset($residenceDetails['adresse']),
                            'has_capacite' => isset($residenceDetails['capacite']),
                            'residence_keys' => array_keys($residenceDetails),
                        ]);
                    } else {
                        Log::warning('Résidence non trouvée via ResidenceService::find()', [
                            'residenceId' => $residenceId,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Erreur lors de la récupération des détails de la résidence depuis l\'API', [
                        'residenceId' => $residenceId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                Log::warning('Aucun residenceId trouvé dans la réservation', [
                    'booking_id' => $booking['id'] ?? $booking['_id'] ?? null,
                    'booking_keys' => array_keys($booking),
                ]);
            }
            
            // Si on n'a pas réussi à récupérer les détails depuis l'API, utiliser les données de l'objet booking
            if (!$residenceDetails) {
                if (isset($booking['residenceName']) && !empty($booking['residenceName'])) {
                    $propertyName = $booking['residenceName'];
                    Log::info('Utilisation du residenceName depuis l\'objet booking', [
                        'propertyName' => $propertyName,
                    ]);
                } elseif (isset($booking['residence'])) {
                    $residence = $booking['residence'];
                    $propertyName = $residence['nom'] ?? $residence['name'] ?? $residence['title'] ?? 'Propriété inconnue';
                    $residenceDetails = $residence; // Utiliser les données partielles de l'objet booking
                    Log::info('Utilisation des données résidence depuis l\'objet booking', [
                        'propertyName' => $propertyName,
                        'has_ville' => isset($residenceDetails['ville']),
                        'has_adresse' => isset($residenceDetails['adresse']),
                        'has_capacite' => isset($residenceDetails['capacite']),
                    ]);
                } elseif (isset($booking['property_name'])) {
                    $propertyName = $booking['property_name'];
                }
            }

            $vehicleName = null;
            $vehicleDriverOption = $this->detectDriverOption($booking);
            $vehicleDetails = null;
            if (isset($booking['vehicle'])) {
                $vehicle = $booking['vehicle'];
                $vehicleName = ($vehicle['marque'] ?? '') . ' ' . ($vehicle['modele'] ?? '');
                $vehicleName = trim($vehicleName);
                $vehicleDetails = $vehicle;
            } elseif (isset($booking['voiture'])) {
                $vehicle = $booking['voiture'];
                $vehicleName = ($vehicle['marque'] ?? '') . ' ' . ($vehicle['modele'] ?? '');
                $vehicleName = trim($vehicleName);
                $vehicleDetails = $vehicle;
            } elseif (isset($booking['vehicleId'])) {
                // Si on a seulement l'ID, essayer de récupérer le véhicule
                try {
                    $vehicleDetails = $this->vehicleService->find($booking['vehicleId']);
                    if ($vehicleDetails) {
                        $vehicleName = ($vehicleDetails['brand'] ?? $vehicleDetails['marque'] ?? '') . ' ' . ($vehicleDetails['model'] ?? $vehicleDetails['modele'] ?? '');
                        $vehicleName = trim($vehicleName);
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
            } elseif (isset($booking['offerId'])) {
                // TODO: Créer ComboOfferService et utiliser ici
                // Pour l'instant, on ne récupère pas les détails de l'offre
                Log::debug('Offre combinée détectée mais service non disponible', [
                    'offerId' => $booking['offerId'],
                ]);
            }

            // Propriétaire : l'API peut exposer ownerId, proprietaireId, ou l'ID via residence/vehicle
            $resolvedOwnerId = BookingService::extractOwnerIdFromBooking($booking);

            $ownerName = $booking['ownerName'] ?? null;
            $ownerPhone = $booking['ownerPhone'] ?? null;
            $ownerAddress = $booking['ownerAddress'] ?? null;

            // Enrichir depuis les objets imbriqués (souvent présents quand ownerId est absent au niveau racine)
            foreach (['residence', 'vehicle', 'voiture', 'offer'] as $entityKey) {
                if (! isset($booking[$entityKey]) || ! is_array($booking[$entityKey])) {
                    continue;
                }
                $entity = $booking[$entityKey];
                $proprietaire = $entity['proprietaire'] ?? $entity['owner'] ?? null;
                if (! is_array($proprietaire)) {
                    continue;
                }
                if (empty($ownerName)) {
                    $firstName = $proprietaire['firstName'] ?? $proprietaire['prenom'] ?? '';
                    $lastName = $proprietaire['lastName'] ?? $proprietaire['nom'] ?? $proprietaire['name'] ?? '';
                    $ownerName = trim($firstName.' '.$lastName);
                    if (empty($ownerName)) {
                        $ownerName = $proprietaire['email'] ?? null;
                    }
                }
                $ownerPhone = $ownerPhone ?? ($proprietaire['phone'] ?? $proprietaire['telephone'] ?? null);
                $ownerAddress = $ownerAddress ?? ($proprietaire['address'] ?? $proprietaire['adresse'] ?? null);
            }

            if ($resolvedOwnerId && (empty($ownerName) || empty($ownerPhone))) {
                try {
                    $owner = $this->userService->find($resolvedOwnerId);
                    if ($owner) {
                        if (empty($ownerName)) {
                            $firstName = $owner['firstName'] ?? $owner['prenom'] ?? '';
                            $lastName = $owner['lastName'] ?? $owner['nom'] ?? $owner['name'] ?? '';
                            $ownerName = trim($firstName.' '.$lastName);
                            if (empty($ownerName)) {
                                $ownerName = $owner['email'] ?? null;
                            }
                        }
                        $ownerPhone = $ownerPhone ?? ($owner['phone'] ?? $owner['telephone'] ?? null);
                        $ownerAddress = $ownerAddress ?? ($owner['address'] ?? $owner['adresse'] ?? null);
                    }
                } catch (\Exception $e) {
                    Log::warning('Impossible de récupérer le propriétaire', [
                        'resolvedOwnerId' => $resolvedOwnerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Récupérer les avis de la réservation
            // TODO: Créer ReviewService et utiliser ici
            $reviews = [];
            $reviewDetails = null;
            // Pour l'instant, on ne récupère pas les avis car ReviewService n'existe pas encore
            Log::debug('Avis non récupérés - ReviewService non disponible', [
                'bookingId' => $booking['id'] ?? $booking['_id'] ?? null,
            ]);

            // Calculer les paiements et déterminer le type de paiement
            $downPaymentAmount = 0;
            $downPaymentPercentage = null;
            $totalPaid = 0;
            $totalPrice = $booking['totalPrice'] ?? $booking['total_price'] ?? 0;
            $paymentType = 'NONE';
            
            if (isset($booking['payments']) && is_array($booking['payments'])) {
                foreach ($booking['payments'] as $payment) {
                    $paymentStatus = strtolower($payment['status'] ?? $payment['statut'] ?? '');
                    $isCompleted = in_array($paymentStatus, ['completed', 'completé', 'complet', 'validated', 'validé', 'paid', 'payé']);
                    
                    if ($isCompleted) {
                        $amount = $payment['amount'] ?? $payment['montant'] ?? 0;
                        $totalPaid += (float) $amount;
                    }
                    
                    $paymentTypeStr = strtolower($payment['type'] ?? $payment['paymentType'] ?? '');
                    $isDownPayment = str_contains($paymentTypeStr, 'acompte') || 
                                    str_contains($paymentTypeStr, 'downpayment') || 
                                    str_contains($paymentTypeStr, 'deposit') ||
                                    (isset($payment['isDownPayment']) && $payment['isDownPayment']);
                    
                    if ($isDownPayment && $isCompleted) {
                        $amount = $payment['amount'] ?? $payment['montant'] ?? 0;
                        $downPaymentAmount += (float) $amount;
                        
                        if (preg_match('/(\d+)%/i', $paymentTypeStr, $matches)) {
                            $downPaymentPercentage = (int) $matches[1];
                        }
                    }
                }
                
                if ($downPaymentAmount > 0 && $downPaymentPercentage === null && $totalPrice > 0) {
                    $downPaymentPercentage = round(($downPaymentAmount / $totalPrice) * 100);
                }
                
                $tolerance = 0.01;
                if ($totalPaid <= $tolerance) {
                    $paymentType = 'NONE';
                } elseif (abs($totalPaid - $totalPrice) <= $tolerance) {
                    $paymentType = 'FULL_PAYMENT';
                } else {
                    $paymentType = 'DOWN_PAYMENT';
                }
            }
            
            if ($downPaymentAmount == 0 && isset($booking['notes'])) {
                if (preg_match('/acompte.*?(\d+)%/i', $booking['notes'], $matches)) {
                    $downPaymentPercentage = (int) $matches[1];
                    $downPaymentAmount = ($totalPrice * $downPaymentPercentage) / 100;
                    $totalPaid = $downPaymentAmount;
                    $paymentType = 'DOWN_PAYMENT';
                }
            }
            
            // Si la réservation est en attente, considérer que le client a payé selon l'API
            // (acompte ou totalité selon les données de paiement)
            $bookingStatus = strtolower($booking['status'] ?? 'pending');
            if ($bookingStatus === 'pending') {
                // Si aucun paiement n'est trouvé dans l'API mais qu'on a un acompte dans les notes
                // ou si totalPaid est 0, on garde la logique normale
                // Sinon, on utilise les données de l'API pour déterminer le type de paiement
                // (déjà fait ci-dessus avec $paymentType)
            }
            
            $remainingBalance = max(0, $totalPrice - $totalPaid);
            $isFullyPaid = abs($remainingBalance) <= 0.01;

            [$unitPriceAmount, $unitPriceLabel] = $this->extractUnitPrice($booking);
            if ($unitPriceAmount === null && $residenceDetails) {
                [$unitPriceAmount, $unitPriceLabel] = $this->extractUnitPrice(['residence' => $residenceDetails]);
            }
            if ($unitPriceAmount === null && $vehicleDetails) {
                [$unitPriceAmount, $unitPriceLabel] = $this->extractUnitPrice(['vehicle' => $vehicleDetails]);
            }

            // Helpers de séjour / timeline pour la fiche détail
            $startDate = $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? null;
            $endDate = $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null;
            
            // Vérifier si le client a validé la récupération de la clé
            $keyRetrievedAt = $booking['keyRetrievedAt'] ?? $booking['key_retrieved_at'] ?? null;
            
            $today = new \DateTimeImmutable('today');
            $isCheckInDay = false;
            $isCheckInDatePassed = false;
            $isStayInProgress = false;
            $isStayCompleted = false;

            try {
                if ($startDate) {
                    $start = new \DateTimeImmutable($startDate);
                    $isCheckInDay = $start->format('Y-m-d') === $today->format('Y-m-d');
                    $isCheckInDatePassed = $start < $today;
                }

                // Une réservation est "en cours" si :
                // 1. Le client a validé qu'il a reçu la clé (keyRetrievedAt existe)
                // 2. La date de fin n'est pas encore passée
                if ($endDate && !empty($keyRetrievedAt)) {
                    $end = new \DateTimeImmutable($endDate);
                    $isStayInProgress = $today <= $end;
                    $isStayCompleted = $today > $end;
                }
            } catch (\Exception $e) {
                // Ignorer les erreurs de parsing de dates
            }

            $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
            
            // Déterminer le statut final avec la même logique que dans index()
            $rawStatus = $booking['status'] ?? 'pending';
            $statusUpper = strtoupper($rawStatus);
            $checkOutAt = $booking['checkOutAt'] ?? $booking['check_out_at'] ?? null;

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

            // Déterminer le statut final
            // PRIORITÉ 0: Checkout confirmé → Terminée
            if (!empty($checkOutAt)) {
                $finalStatus = 'terminée';
            }
            // PRIORITÉ 1: Si la date de fin est passée, la réservation est terminée
            elseif ($isStayCompleted) {
                $finalStatus = 'terminée';
                if ($statusUpper !== 'TERMINEE' && $statusUpper !== 'COMPLETED') {
                    Log::info('Réservation automatiquement marquée comme terminée (date de fin passée)', [
                        'booking_id' => $booking['id'] ?? $booking['_id'] ?? null,
                        'raw_status' => $rawStatus,
                        'end_date' => $endDate,
                        'final_status' => $finalStatus,
                    ]);
                }
            }
            elseif ($statusUpper === 'AWAITING_PAYMENT') {
                $finalStatus = 'awaiting_payment';
            }
            // PRIORITÉ 2: Si le propriétaire a confirmé (ownerConfirmedAt existe), le statut doit être "confirmed"
            elseif (!empty($ownerConfirmedAt)) {
                $finalStatus = 'confirmed';
            }
            // PRIORITÉ 3: Si le statut brut est PENDING et qu'il n'y a pas de confirmation propriétaire, rester en pending
            elseif ($statusUpper === 'PENDING') {
                $finalStatus = 'pending';
            } 
            // PRIORITÉ 4: "confirmed" sans ownerConfirmedAt = pas encore approuvée → En attente
            elseif (($statusUpper === 'CONFIRMEE' || $statusUpper === 'CONFIRMED') && empty($ownerConfirmedAt)) {
                $finalStatus = 'pending';
            } else {
                $finalStatus = strtolower($rawStatus);
            }
            
            $isPendingApproval = ($finalStatus === 'pending');

            // Type de réservation pour la fiche détail
            $bookingType = 'unknown';
            if (!empty($booking['offerId'] ?? $booking['offer_id'] ?? null) || isset($booking['offer'])) {
                $bookingType = 'package';
            } elseif (!empty($booking['vehicleId'] ?? $booking['vehicle_id'] ?? null) || isset($booking['vehicle']) || isset($booking['voiture'])) {
                $bookingType = 'vehicle';
            } elseif (!empty($booking['residenceId'] ?? $booking['residence_id'] ?? null) || isset($booking['residence']) || isset($booking['residenceName']) || isset($booking['property_name'])) {
                $bookingType = 'residence';
            }

            // Extraire les paiements pour le monitoring
            $payments = [];
            if (isset($booking['payments']) && is_array($booking['payments'])) {
                foreach ($booking['payments'] as $payment) {
                    $payments[] = [
                        'id' => $payment['id'] ?? $payment['_id'] ?? null,
                        'amount' => $payment['amount'] ?? $payment['montant'] ?? 0,
                        'status' => $payment['status'] ?? $payment['statut'] ?? 'pending',
                        'type' => $payment['type'] ?? $payment['paymentType'] ?? 'unknown',
                        'method' => $payment['method'] ?? $payment['paymentMethod'] ?? null,
                        'transactionId' => $payment['transactionId'] ?? $payment['transaction_id'] ?? null,
                        'provider' => $payment['provider'] ?? null, // orange_money, mtn_money, wave
                        'phoneNumber' => $payment['phoneNumber'] ?? $payment['phone_number'] ?? null,
                        'createdAt' => $payment['createdAt'] ?? $payment['created_at'] ?? null,
                        'updatedAt' => $payment['updatedAt'] ?? $payment['updated_at'] ?? null,
                        'notes' => $payment['notes'] ?? null,
                    ];
                }
            }

            $mappedBooking = [
                'id' => $booking['id'] ?? $booking['_id'] ?? null,
                'bookingType' => $bookingType,
                'customerName' => $customerName,
                'customerEmail' => $customerEmail,
                'customerPhone' => $customerPhone,
                'clientId' => $booking['clientId'] ?? null,
                'propertyName' => $propertyName,
                'residenceId' => $booking['residenceId'] ?? null,
                'vehicleName' => $vehicleName,
                'vehicleId' => $booking['vehicleId'] ?? null,
                'offerName' => $offerName,
                'offerId' => $booking['offerId'] ?? null,
                'startDate' => $booking['startDate'] ?? $booking['start_date'] ?? $booking['checkInDate'] ?? null,
                'endDate' => $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null,
                'totalPrice' => $totalPrice,
                'vehicleDriverOption' => $vehicleDriverOption,
                'unitPriceAmount' => $unitPriceAmount,
                'unitPriceLabel' => $unitPriceLabel,
                'downPayment' => $downPaymentAmount,
                'downPaymentPercentage' => $downPaymentPercentage,
                'totalPaid' => $totalPaid,
                'remainingBalance' => $remainingBalance,
                'isFullyPaid' => $isFullyPaid,
                'paymentType' => $paymentType,
                'payments' => $payments,
                'status' => $finalStatus,
                'createdAt' => $booking['createdAt'] ?? $booking['created_at'] ?? null,
                'keyRetrievedAt' => $booking['keyRetrievedAt'] ?? $booking['key_retrieved_at'] ?? null,
                'ownerConfirmedAt' => $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null,
                'checkOutAt' => $booking['checkOutAt'] ?? $booking['check_out_at'] ?? null,
                'ownerId' => $resolvedOwnerId ?? ($booking['ownerId'] ?? null),
                'ownerName' => $ownerName,
                'ownerPhone' => $ownerPhone,
                'ownerAddress' => $ownerAddress,
                'isCheckInDay' => $isCheckInDay,
                'isCheckInDatePassed' => $isCheckInDatePassed,
                'isStayInProgress' => $isStayInProgress,
                'isStayCompleted' => $isStayCompleted,
                'isPendingApproval' => $isPendingApproval,
                'reviewId' => $booking['reviewId'] ?? null,
                'reviews' => $reviews,
                'residenceDetails' => $residenceDetails,
                'vehicleDetails' => $vehicleDetails,
                'offerDetails' => $offerDetails,
            ];

            return Inertia::render('Bookings/Show', [
                'booking' => $mappedBooking,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.bookings.index')
                ->with('error', 'Erreur lors de la récupération de la réservation');
        }
    }

    /**
     * Approuver une réservation.
     * Règle métier : seul l'administrateur peut approuver (route protégée par middleware 'admin').
     */
    public function approve(string $id): RedirectResponse
    {
        try {
            $this->bookingService->approve($id);

            return redirect()
                ->route('admin.bookings.index')
                ->with('success', 'Réservation approuvée avec succès.');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de l\'approbation de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return redirect()
                ->route('admin.bookings.index')
                ->with('error', $e->getMessage() ?: 'Une erreur est survenue lors de l\'approbation de la réservation.');
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'approbation de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.bookings.index')
                ->with('error', 'Une erreur est survenue lors de l\'approbation de la réservation.');
        }
    }

    /**
     * Rejeter une réservation.
     * Règle métier : seul l'administrateur peut rejeter (route protégée par middleware 'admin').
     */
    public function reject(Request $request, string $id): RedirectResponse
    {
        try {
            $reason = $request->input('reason');
            $this->bookingService->reject($id, $reason);

            return redirect()
                ->route('admin.bookings.index')
                ->with('success', 'Réservation rejetée avec succès.');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors du rejet de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return redirect()
                ->route('admin.bookings.index')
                ->with('error', $e->getMessage() ?: 'Une erreur est survenue lors du rejet de la réservation.');
        } catch (\Exception $e) {
            Log::error('Erreur lors du rejet de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.bookings.index')
                ->with('error', 'Une erreur est survenue lors du rejet de la réservation.');
        }
    }

    /**
     * Supprimer une réservation
     */
    public function destroy(string $id): RedirectResponse
    {
        try {
            $this->bookingService->deleteBooking($id);

            return redirect()
                ->route('admin.bookings.index')
                ->with('success', 'Réservation supprimée définitivement avec succès (suppression physique avec cascade).');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la suppression de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return redirect()
                ->route('admin.bookings.index')
                ->with('error', $e->getMessage() ?: 'Une erreur est survenue lors de la suppression de la réservation.');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.bookings.index')
                ->with('error', 'Une erreur est survenue lors de la suppression de la réservation.');
        }
    }

    /**
     * Confirmer la récupération de clé par le client
     * Transition: CONFIRMEE → CHECKIN_CLIENT
     */
    public function confirmKeyRetrieval(string $id): RedirectResponse
    {
        try {
            $this->bookingService->confirmKeyRetrieval($id);

            return redirect()
                ->route('admin.bookings.index')
                ->with('success', 'Récupération de clé confirmée avec succès.');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la confirmation de récupération de clé', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return redirect()
                ->route('admin.bookings.index')
                ->with('error', $e->getMessage() ?: 'Une erreur est survenue lors de la confirmation de récupération de clé.');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la confirmation de récupération de clé', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.bookings.index')
                ->with('error', 'Une erreur est survenue lors de la confirmation de récupération de clé.');
        }
    }

    /**
     * Confirmer la remise de clé par le propriétaire
     * Transition: CHECKIN_CLIENT → CHECKIN_PROPRIO (ou auto → EN_COURS_SEJOUR)
     */
    public function confirmOwnerKeyHandover(string $id): RedirectResponse
    {
        try {
            $this->bookingService->confirmOwnerKeyHandover($id);

            return redirect()
                ->route('admin.bookings.index')
                ->with('success', 'Remise de clé confirmée avec succès.');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la confirmation de remise de clé', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return redirect()
                ->route('admin.bookings.index')
                ->with('error', $e->getMessage() ?: 'Une erreur est survenue lors de la confirmation de remise de clé.');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la confirmation de remise de clé', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.bookings.index')
                ->with('error', 'Une erreur est survenue lors de la confirmation de remise de clé.');
        }
    }

    /**
     * Confirmer le départ (checkout) d'une réservation.
     * Règle métier : seul l'administrateur peut confirmer le départ (route protégée par middleware 'admin').
     */
    public function confirmCheckOut(string $id): RedirectResponse
    {
        try {
            $this->bookingService->confirmCheckOut($id);

            return redirect()
                ->route('admin.bookings.show', $id)
                ->with('success', 'Checkout confirmé avec succès.');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors de la confirmation du checkout', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return redirect()
                ->route('admin.bookings.show', $id)
                ->with('error', $e->getMessage() ?: 'Une erreur est survenue lors de la confirmation du checkout.');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la confirmation du checkout', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.bookings.show', $id)
                ->with('error', 'Une erreur est survenue lors de la confirmation du checkout.');
        }
    }

    /**
     * Marquer une réservation comme payée manuellement
     */
    public function markAsPaid(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'paymentMethod' => 'nullable|string|in:orange_money,mtn_money,wave,manual',
            'transactionId' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->bookingService->markAsPaid(
                $id,
                $validated['amount'] ?? null,
                $validated['paymentMethod'] ?? 'manual',
                $validated['transactionId'] ?? null,
                $validated['notes'] ?? null
            );

            return redirect()
                ->route('admin.bookings.show', $id)
                ->with('success', 'Réservation marquée comme payée avec succès.');
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur API lors du marquage de la réservation comme payée', [
                'id' => $id,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            return redirect()
                ->route('admin.bookings.show', $id)
                ->with('error', $e->getMessage() ?: 'Une erreur est survenue lors du marquage de la réservation comme payée.');
        } catch (\Exception $e) {
            Log::error('Erreur lors du marquage de la réservation comme payée', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.bookings.show', $id)
                ->with('error', 'Une erreur est survenue lors du marquage de la réservation comme payée.');
        }
    }

    /**
     * Déterminer si le client a choisi l'option avec chauffeur ou sans chauffeur
     */
    protected function detectDriverOption(array $booking): ?string
    {
        $candidates = [
            $booking['withDriver'] ?? null,
            $booking['driverOption'] ?? null,
            $booking['options']['withDriver'] ?? null,
            $booking['vehicle']['withDriver'] ?? null,
            $booking['vehicle']['driverOption'] ?? null,
            $booking['vehicle']['options']['withDriver'] ?? null,
            $booking['vehicleOptions']['withDriver'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $interpreted = $this->interpretDriverValue($candidate);
            if ($interpreted !== null) {
                return $interpreted ? 'with_driver' : 'without_driver';
            }
        }

        $notes = $booking['notes'] ?? $booking['note'] ?? null;
        if (is_string($notes) && $notes !== '') {
            $normalizedNotes = mb_strtolower($notes);
            if (str_contains($normalizedNotes, 'avec chauffeur') || str_contains($normalizedNotes, 'with driver')) {
                return 'with_driver';
            }
            if (str_contains($normalizedNotes, 'sans chauffeur') || str_contains($normalizedNotes, 'without driver')) {
                return 'without_driver';
            }
        }

        return null;
    }

    /**
     * Normaliser différentes représentations de l'option chauffeur
     */
    protected function interpretDriverValue(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            $withValues = [
                '1', 'true', 'yes', 'with', 'with_driver', 'with driver', 'driver', 'driver_included',
                'avec', 'avec chauffeur', 'avec_chauffeur',
            ];
            $withoutValues = [
                '0', 'false', 'no', 'without', 'without_driver', 'without driver',
                'sans', 'sans chauffeur', 'sans_chauffeur',
            ];

            if (in_array($normalized, $withValues, true)) {
                return true;
            }

            if (in_array($normalized, $withoutValues, true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * Extraire le prix unitaire (par nuit, par jour, etc.)
     *
     * @return array{0: float|null, 1: string|null}
     */
    protected function extractUnitPrice(array $booking): array
    {
        $candidates = [
            ['value' => $booking['residencePricePerNight'] ?? null, 'label' => 'night'],
            ['value' => $booking['pricePerNight'] ?? null, 'label' => 'night'],
            ['value' => $booking['pricePerDay'] ?? null, 'label' => 'day'],
            ['value' => $booking['unitPrice'] ?? null, 'label' => 'unit'],
            ['value' => $booking['residence']['prixParNuit'] ?? null, 'label' => 'night'],
            ['value' => $booking['residence']['pricePerNight'] ?? null, 'label' => 'night'],
            ['value' => $booking['residence']['price'] ?? null, 'label' => 'night'],
            ['value' => $booking['vehicle']['prixParJour'] ?? null, 'label' => 'day'],
            ['value' => $booking['vehicle']['pricePerDay'] ?? null, 'label' => 'day'],
            ['value' => $booking['vehicle']['price'] ?? null, 'label' => 'day'],
            ['value' => $booking['vehiclePricePerDay'] ?? null, 'label' => 'day'],
            ['value' => $booking['offer']['prixPack'] ?? null, 'label' => 'pack'],
            ['value' => $booking['offer']['price'] ?? null, 'label' => 'pack'],
        ];

        foreach ($candidates as $candidate) {
            $amount = $this->normalizeAmount($candidate['value']);
            if ($amount !== null) {
                return [$amount, $candidate['label']];
            }
        }

        $notes = $booking['notes'] ?? $booking['note'] ?? null;
        if (is_string($notes) && $notes !== '') {
            if (preg_match('/(\d[\d\s.,]*)\s*CFA\s*\/\s*(nuit|jour|day|night)/i', $notes, $matches)) {
                $amount = $this->normalizeAmount($matches[1]);
                if ($amount !== null) {
                    $label = str_contains(strtolower($matches[2]), 'nuit') || strtolower($matches[2]) === 'night'
                        ? 'night'
                        : 'day';
                    return [$amount, $label];
                }
            }
        }

        return [null, null];
    }

    /**
     * Normaliser une valeur numérique potentielle
     */
    protected function normalizeAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalized = str_replace([' ', ' '], '', $value);
            $normalized = str_replace(',', '.', $normalized);
            if (is_numeric($normalized)) {
                return (float) $normalized;
            }
            if (preg_match('/\d+/', $normalized, $matches)) {
                return (float) $matches[0];
            }
        }

        return null;
    }
}

