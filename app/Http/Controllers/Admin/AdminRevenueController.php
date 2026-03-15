<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DodoVroumApiService;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdminRevenueController extends Controller
{
    public function __construct(
        protected DodoVroumApiService $apiService
    ) {
    }

    /**
     * Afficher la page des revenus avec statistiques détaillées pour l'admin
     */
    public function index(): Response
    {
        try {
            // Récupérer toutes les données (pas de filtre proprietaireId pour admin)
            $allResidences = $this->apiService->getResidences([]);
            $allVehicles = $this->apiService->getVehicles([]);
            $allBookings = $this->apiService->getBookings([]);
            
            // Calculer les statistiques de revenus (commission DodoVroum = 10% du total)
            $stats = $this->calculateRevenueStats($allResidences, $allVehicles, $allBookings);
            
        } catch (\Exception $e) {
            Log::error('Erreur récupération données revenus admin', ['error' => $e->getMessage()]);
            
            $stats = $this->getDefaultStats();
        }

        // Log pour déboguer
        \Log::info('Admin Revenue Stats', [
            'totalRevenue' => $stats['totalRevenue'],
            'totalBookings' => $stats['totalBookings'],
            'revenueThisMonth' => $stats['trends']['totalRevenue'] ?? 0,
            'chartDataCount' => count($stats['chartData']),
            'chartData' => $stats['chartData'],
        ]);

        return Inertia::render('Admin/Revenue', [
            'stats' => $stats,
        ]);
    }

    /**
     * Calculer les statistiques de revenus avec évolution temporelle
     * Pour l'admin : commission DodoVroum = 10% du prix total de chaque réservation
     */
    private function calculateRevenueStats(array $residences, array $vehicles, array $bookings): array
    {
        $now = new \DateTime();
        $lastMonth = (clone $now)->modify('-1 month');
        
        // Filtrer les réservations valides (confirmées ou terminées, exclure annulées et en attente)
        // Même logique que AdminBookingController : vérifier si le séjour est terminé via endDate
        $validBookings = array_filter($bookings, function($booking) {
            $status = strtolower($booking['status'] ?? 'pending');
            
            // Exclure les réservations annulées
            if (in_array($status, ['cancelled', 'canceled', 'annulée', 'annulee', 'annule'])) {
                return false;
            }
            
            // Vérifier si le séjour est terminé (date de fin passée)
            $endDate = $booking['endDate'] ?? $booking['end_date'] ?? $booking['checkOutDate'] ?? null;
            $isStayCompleted = false;
            if ($endDate) {
                try {
                    $today = new \DateTime('today');
                    $end = new \DateTime($endDate);
                    $isStayCompleted = $today > $end;
                } catch (\Exception $e) {
                    // Ignorer les erreurs de date
                }
            }
            
            // PRIORITÉ 1: Si la date de fin est passée, la réservation est terminée (valide)
            if ($isStayCompleted) {
                return true;
            }
            
            // Vérifier si le propriétaire a confirmé la réservation
            $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
            
            // PRIORITÉ 2: Si le propriétaire a confirmé (ownerConfirmedAt existe)
            if (!empty($ownerConfirmedAt)) {
                return true; // Confirmée par le propriétaire
            }
            
            // PRIORITÉ 3: Le statut est "completed" ou "terminée" (séjour terminé selon l'API)
            if (in_array($status, ['completed', 'terminee', 'terminée'])) {
                return true; // Séjour terminé
            }
            
            // PRIORITÉ 4: Le statut est "confirmed" ou "confirmee" (même sans ownerConfirmedAt, au cas où)
            if (in_array($status, ['confirmed', 'confirmee', 'confirmée'])) {
                return true; // Statut confirmé
            }
            
            // Exclure les réservations en attente (pending)
            return false;
        });
        
        // Calculer les revenus totaux (commission DodoVroum = 10% du prix total)
        $totalRevenue = 0;
        $totalBookings = count($validBookings);
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
        
        // Données pour le graphique (6 derniers mois)
        $chartData = [];
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = (clone $now)->modify("-$i months");
            // Format français des mois (3 premières lettres)
            $monthNames = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
            $monthIndex = (int) $monthDate->format('n') - 1;
            $months[] = $monthNames[$monthIndex] ?? $monthDate->format('M');
            $chartData[$monthDate->format('Y-m')] = 0;
        }
        
        Log::info('AdminRevenueController - Réservations valides pour calcul revenus', [
            'total_valid_bookings' => count($validBookings),
            'valid_bookings_details' => array_map(function($b) {
                return [
                    'id' => $b['id'] ?? $b['_id'] ?? 'N/A',
                    'status' => $b['status'] ?? 'N/A',
                    'totalPrice' => $b['totalPrice'] ?? $b['total_price'] ?? 0,
                    'endDate' => $b['endDate'] ?? $b['end_date'] ?? 'N/A',
                    'ownerConfirmedAt' => $b['ownerConfirmedAt'] ?? $b['owner_confirmed_at'] ?? null,
                    'createdAt' => $b['createdAt'] ?? $b['created_at'] ?? 'N/A',
                ];
            }, array_slice($validBookings, 0, 10)),
        ]);
        
        foreach ($validBookings as $booking) {
            $totalPrice = (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0);
            // Commission DodoVroum = 10% du prix total
            $dodoVroumCommission = round($totalPrice * 0.1);
            $totalRevenue += $dodoVroumCommission;
            
            // Calculer les revenus par mois
            $bookingDate = $booking['createdAt'] ?? $booking['created_at'] ?? null;
            if ($bookingDate) {
                try {
                    $bookingDateTime = new \DateTime($bookingDate);
                    $monthKey = $bookingDateTime->format('Y-m');
                    
                    if (isset($chartData[$monthKey])) {
                        $chartData[$monthKey] += $dodoVroumCommission;
                    }
                    
                    // Revenus du mois actuel vs mois dernier
                    if ($bookingDateTime->format('Y-m') === $now->format('Y-m')) {
                        $revenueThisMonth += $dodoVroumCommission;
                        $bookingsThisMonth++;
                    } elseif ($bookingDateTime->format('Y-m') === $lastMonth->format('Y-m')) {
                        $revenueLastMonth += $dodoVroumCommission;
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
        $occupationRate = 0;
        if ($activeProperties > 0) {
            $maxNights = $activeProperties * 30; // 30 jours par bien par mois
            if ($maxNights > 0) {
                $occupationRate = min(100, round(($totalNights / $maxNights) * 100, 1));
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

