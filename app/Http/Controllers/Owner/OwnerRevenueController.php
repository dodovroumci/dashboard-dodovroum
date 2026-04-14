<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Concerns\EvaluatesBookingRevenueEligibility;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Owner\Concerns\HasProprietaireId;
use App\Services\BookingOwnerScopeService;
use App\Services\DodoVroumApi\StatsService;
use App\Services\DodoVroumApiService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OwnerRevenueController extends Controller
{
    use EvaluatesBookingRevenueEligibility;
    use HasProprietaireId;

    public function __construct(
        protected DodoVroumApiService $apiService,
        protected StatsService $statsService,
        protected BookingOwnerScopeService $bookingOwnerScopeService
    ) {
    }

    /**
     * Afficher la page des revenus avec statistiques détaillées
     */
    public function index(): Response
    {
        $user = auth()->user();

        try {
            // Récupérer le proprietaireId réel depuis les données utilisateur
            $proprietaireId = $this->getProprietaireId($user);
            
            if (!$proprietaireId) {
                Log::error('Impossible de récupérer le proprietaireId pour la page revenus', [
                    'user_id' => $user->getAuthIdentifier(),
                ]);
                
                return Inertia::render('Owner/Revenue', [
                    'stats' => $this->getDefaultStats(),
                ]);
            }
            
            // Essayer d'abord l'endpoint API dédié (NestJS). Tant que l'API n'est pas
            // garantie isolée par propriétaire, on n'accepte la réponse que si elle
            // contient explicitement l'ownerId du connecté (sinon fallback local).
            $apiStats = $this->statsService->getOwnerStats($proprietaireId);

            $hasApiPayload = $apiStats !== null
                && (isset($apiStats['totalRevenue']) || isset($apiStats['chartData']));
            $apiOwnerMatches = $hasApiPayload
                && $this->apiOwnerStatsExplicitlyMatchConnectedOwner($apiStats, $proprietaireId);

            if ($hasApiPayload && $apiOwnerMatches) {
                Log::info('Utilisation des stats depuis l\'API NestJS (ownerId explicite conforme)', [
                    'ownerId' => $proprietaireId,
                ]);

                $stats = $this->adaptApiStatsToFrontend($apiStats);

                return Inertia::render('Owner/Revenue', [
                    'stats' => $stats,
                ]);
            }

            if ($hasApiPayload && ! $apiOwnerMatches) {
                Log::warning('Stats API NestJS ignorées : ownerId absent ou non conforme au propriétaire connecté, calcul local.', [
                    'expectedOwnerId' => $proprietaireId,
                    'responseKeys' => array_keys($apiStats),
                ]);
            }

            Log::info('Endpoint API stats indisponible ou non fiable, utilisation du calcul local', [
                'ownerId' => $proprietaireId,
            ]);
            
            // Fallback : Calculer les stats localement depuis les données de l'API
            // (moins performant mais fonctionne même si l'endpoint NestJS n'est pas encore implémenté)
            $apiFilters = [];
            if (is_numeric($proprietaireId)) {
                $apiFilters['proprietaireId'] = (int) $proprietaireId;
            } else {
                $apiFilters['proprietaireId'] = $proprietaireId;
            }
            
            // Récupérer les données nécessaires
            $allResidences = $this->apiService->getResidences($apiFilters);
            $allVehicles = $this->apiService->getVehicles($apiFilters);
            $allBookings = $this->apiService->getBookings($apiFilters);
            
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
            
            // Double vérification côté serveur pour les réservations
            $bookings = [];
            foreach ($allBookings as $booking) {
                $bookingProprietaireId = $this->bookingOwnerScopeService->resolveOwnerIdForBooking($booking);
                if ($this->bookingOwnerScopeService->matchesProprietaire($bookingProprietaireId, $proprietaireId)) {
                    $bookings[] = $booking;
                }
            }
            
            // Calculer les statistiques de revenus
            $stats = $this->calculateRevenueStats($residences, $vehicles, $bookings);
            
        } catch (\Exception $e) {
            Log::error('Erreur récupération données revenus propriétaire', ['error' => $e->getMessage()]);
            
            $stats = $this->getDefaultStats();
        }

        return Inertia::render('Owner/Revenue', [
            'stats' => $stats,
        ]);
    }

    /**
     * Calculer les statistiques de revenus avec évolution temporelle
     */
    private function calculateRevenueStats(array $residences, array $vehicles, array $bookings): array
    {
        $now = new \DateTime();
        $lastMonth = (clone $now)->modify('-1 month');
        
        $validBookings = array_filter($bookings, fn (array $b) => $this->isEligibleForRevenue($b));
        
        $totalRevenue = 0;
        $totalBookings = count($validBookings); // Compter uniquement les réservations non annulées
        $activeProperties = 0;
        
        // Compter les biens actifs
        foreach ($residences as $residence) {
            $isActive = $residence['isActive'] ?? $residence['is_active'] ?? $residence['available'] ?? true;
            if ($isActive === true || $isActive === 'true' || $isActive === 1) {
                $activeProperties++;
            }
        }
        foreach ($vehicles as $vehicle) {
            $isAvailable = $vehicle['available'] ?? $vehicle['isAvailable'] ?? $vehicle['is_available'] ?? true;
            if ($isAvailable === true || $isAvailable === 'true' || $isAvailable === 1) {
                $activeProperties++;
            }
        }
        
        // Calculer les revenus et le taux d'occupation
        $revenueThisMonth = 0;
        $revenueLastMonth = 0;
        $bookingsThisMonth = 0;
        $bookingsLastMonth = 0;
        $totalNights = 0;
        $totalAvailableNights = 0;
        
        // Données pour le graphique (6 derniers mois)
        $chartData = [];
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = (clone $now)->modify("-$i months");
            $months[] = $monthDate->format('M');
            $chartData[$monthDate->format('Y-m')] = 0;
        }
        
        foreach ($validBookings as $booking) {
            $totalPrice = (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0);
            // Montant à verser au propriétaire = 90% du prix total
            $ownerPayment = round($totalPrice * 0.9);
            $totalRevenue += $ownerPayment;
            
            // Calculer les revenus par mois
            $bookingDate = $booking['createdAt'] ?? $booking['created_at'] ?? null;
            if ($bookingDate) {
                try {
                    $bookingDateTime = new \DateTime($bookingDate);
                    $monthKey = $bookingDateTime->format('Y-m');
                    
                    if (isset($chartData[$monthKey])) {
                        $chartData[$monthKey] += $ownerPayment;
                    }
                    
                    // Revenus du mois actuel vs mois dernier
                    if ($bookingDateTime->format('Y-m') === $now->format('Y-m')) {
                        $revenueThisMonth += $ownerPayment;
                        $bookingsThisMonth++;
                    } elseif ($bookingDateTime->format('Y-m') === $lastMonth->format('Y-m')) {
                        $revenueLastMonth += $ownerPayment;
                        $bookingsLastMonth++;
                    }
                } catch (\Exception $e) {
                    // Ignorer les erreurs de date
                }
            }
            
            // Calculer les nuits pour le taux d'occupation
            $startDate = $booking['startDate'] ?? $booking['start_date'] ?? null;
            $endDate = $booking['endDate'] ?? $booking['end_date'] ?? null;
            if ($startDate && $endDate) {
                try {
                    $start = new \DateTime($startDate);
                    $end = new \DateTime($endDate);
                    $nights = $start->diff($end)->days;
                    $totalNights += $nights;
                } catch (\Exception $e) {
                    // Ignorer les erreurs de date
                }
            }
        }
        
        // Calculer le taux d'occupation (simplifié)
        // On suppose qu'un bien peut être réservé 30 jours par mois en moyenne
        $occupationRate = 0;
        if ($activeProperties > 0) {
            $maxNights = $activeProperties * 30; // 30 jours par bien par mois
            if ($maxNights > 0) {
                $occupationRate = round(($totalNights / $maxNights) * 100);
            }
        }
        
        // Calculer les tendances (pourcentage de variation)
        $revenueTrend = 0;
        if ($revenueLastMonth > 0) {
            $revenueTrend = round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1);
        }
        
        $bookingsTrend = 0;
        if ($bookingsLastMonth > 0) {
            $bookingsTrend = round((($bookingsThisMonth - $bookingsLastMonth) / $bookingsLastMonth) * 100, 1);
        }
        
        // Préparer les données du graphique
        $chartDataArray = [];
        foreach ($months as $index => $month) {
            $monthKey = (clone $now)->modify('-' . (5 - $index) . ' months')->format('Y-m');
            $chartDataArray[] = [
                'month' => $month,
                'total' => (int) ($chartData[$monthKey] ?? 0),
            ];
        }
        
        return [
            'totalRevenue' => (int) $totalRevenue,
            'totalBookings' => $totalBookings,
            'occupationRate' => $occupationRate,
            'activeProperties' => $activeProperties,
            'trends' => [
                'totalRevenue' => $revenueTrend,
                'bookings' => $bookingsTrend,
                'occupation' => 0, // À calculer si nécessaire
                'properties' => 0, // À calculer si nécessaire
            ],
            'chartData' => $chartDataArray,
        ];
    }

    /**
     * La réponse NestJS doit inclure explicitement l'identifiant du propriétaire
     * (ownerId / proprietaireId ou équivalent dans meta) et il doit correspondre
     * au propriétaire connecté. Sinon on refuse la réponse (fuite cross-owner possible).
     */
    private function apiOwnerStatsExplicitlyMatchConnectedOwner(array $apiStats, string|int $proprietaireId): bool
    {
        $candidates = [
            $apiStats['ownerId'] ?? null,
            $apiStats['owner_id'] ?? null,
            $apiStats['proprietaireId'] ?? null,
            $apiStats['proprietaire_id'] ?? null,
        ];

        if (isset($apiStats['meta']) && is_array($apiStats['meta'])) {
            $meta = $apiStats['meta'];
            $candidates[] = $meta['ownerId'] ?? null;
            $candidates[] = $meta['owner_id'] ?? null;
            $candidates[] = $meta['proprietaireId'] ?? null;
            $candidates[] = $meta['proprietaire_id'] ?? null;
        }

        $explicit = null;
        foreach ($candidates as $value) {
            if ($value !== null && $value !== '') {
                $explicit = $value;
                break;
            }
        }

        if ($explicit === null) {
            return false;
        }

        if ((string) $explicit === (string) $proprietaireId) {
            return true;
        }

        return is_numeric($explicit) && is_numeric($proprietaireId)
            && (int) $explicit === (int) $proprietaireId;
    }

    /**
     * Adapter les stats de l'API NestJS au format attendu par le frontend
     */
    private function adaptApiStatsToFrontend(array $apiStats): array
    {
        // L'API NestJS doit retourner :
        // {
        //   totalRevenue: number,
        //   revenueTrend: number,
        //   totalBookings: number,
        //   bookingsTrend: number,
        //   occupationRate: number,
        //   occupationTrend: number,
        //   activeProperties: number,
        //   propertiesTrend: number,
        //   chartData: Array<{ month: string, total: number }>
        // }
        
        return [
            'totalRevenue' => (int) ($apiStats['totalRevenue'] ?? 0),
            'totalBookings' => (int) ($apiStats['totalBookings'] ?? 0),
            'occupationRate' => (int) ($apiStats['occupationRate'] ?? 0),
            'activeProperties' => (int) ($apiStats['activeProperties'] ?? $apiStats['activeAssets'] ?? 0),
            'trends' => [
                'totalRevenue' => (float) ($apiStats['revenueTrend'] ?? 0),
                'bookings' => (float) ($apiStats['bookingsTrend'] ?? 0),
                'occupation' => (float) ($apiStats['occupationTrend'] ?? 0),
                'properties' => (float) ($apiStats['propertiesTrend'] ?? 0),
            ],
            'chartData' => $this->normalizeChartData($apiStats['chartData'] ?? []),
        ];
    }

    /**
     * Normaliser les données du graphique
     */
    private function normalizeChartData(array $chartData): array
    {
        // S'assurer que tous les mois ont une valeur (même 0)
        $normalized = [];
        $now = new \DateTime();
        $months = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = (clone $now)->modify("-$i months");
            $monthKey = $monthDate->format('M');
            $months[] = $monthKey;
            
            // Chercher dans les données de l'API
            $found = false;
            foreach ($chartData as $data) {
                $dataMonth = $data['month'] ?? $data['monthName'] ?? null;
                if ($dataMonth === $monthKey || $dataMonth === $monthDate->format('F')) {
                    $normalized[] = [
                        'month' => $monthKey,
                        'total' => (int) ($data['total'] ?? $data['revenue'] ?? 0),
                    ];
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $normalized[] = [
                    'month' => $monthKey,
                    'total' => 0,
                ];
            }
        }
        
        return $normalized;
    }

    /**
     * Retourner des statistiques par défaut en cas d'erreur
     */
    private function getDefaultStats(): array
    {
        return [
            'totalRevenue' => 0,
            'totalBookings' => 0,
            'occupationRate' => 0,
            'activeProperties' => 0,
            'trends' => [
                'totalRevenue' => 0,
                'bookings' => 0,
                'occupation' => 0,
                'properties' => 0,
            ],
            'chartData' => [],
        ];
    }
}

