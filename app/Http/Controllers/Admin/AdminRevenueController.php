<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\EvaluatesBookingRevenueEligibility;
use App\Http\Controllers\Controller;
use App\Services\DodoVroumApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminRevenueController extends Controller
{
    use EvaluatesBookingRevenueEligibility;

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
            $stats = $this->fetchAdminRevenueStats();
        } catch (\Exception $e) {
            Log::error('Erreur récupération données revenus admin', ['error' => $e->getMessage()]);

            $stats = $this->getDefaultStats();
        }

        // Log pour déboguer
        \Log::info('Admin Revenue Stats', [
            'totalRevenue' => $stats['totalRevenue'],
            'revenueThisMonth' => $stats['revenueThisMonth'] ?? 0,
            'totalBookings' => $stats['totalBookings'],
            'chartDataCount' => count($stats['chartData']),
            'chartData' => $stats['chartData'],
        ]);

        return Inertia::render('Admin/Revenue', [
            'stats' => $stats,
        ]);
    }

    /**
     * Export CSV des commissions (même périmètre que le graphique admin).
     */
    public function exportCsv(): StreamedResponse
    {
        try {
            $stats = $this->fetchAdminRevenueStats();
        } catch (\Exception $e) {
            Log::error('Erreur export CSV revenus admin', ['error' => $e->getMessage()]);
            $stats = $this->getDefaultStats();
        }

        $filename = 'commissions-dodovroum-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($stats) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Mois', 'Commissions DodoVroum (10 %) — FCFA'], ';');
            foreach ($stats['chartData'] ?? [] as $row) {
                fputcsv($out, [$row['month'] ?? '', $row['total'] ?? 0], ';');
            }
            fputcsv($out, []);
            fputcsv($out, ['Total sur la période affichée (graphique)', $stats['totalRevenue'] ?? 0], ';');
            fputcsv($out, ['Réservations comptabilisées', $stats['totalBookings'] ?? 0], ';');
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @throws \Throwable
     */
    private function fetchAdminRevenueStats(): array
    {
        $allResidences = $this->apiService->getResidences([]);
        $allVehicles = $this->apiService->getVehicles([]);
        $allBookings = $this->apiService->getBookings([]);

        return $this->calculateRevenueStats($allResidences, $allVehicles, $allBookings);
    }

    /**
     * Commissions agrégées : uniquement réservations éligibles, montants non négatifs, dates sécurisées (Carbon).
     */
    private function calculateRevenueStats(array $residences, array $vehicles, array $bookings): array
    {
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonth();
        $currentMonth = $now->month;
        $currentYear = $now->year;

        $activeProperties = 0;
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

        $chartBuckets = [];
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = $now->copy()->subMonths($i);
            $monthNames = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
            $months[] = $monthNames[$monthDate->month - 1] ?? $monthDate->format('M');
            $chartBuckets[$monthDate->format('Y-m')] = 0.0;
        }

        $totalRevenue = 0.0;
        $revenueThisMonth = 0.0;
        $revenueLastMonth = 0.0;
        $bookingsThisMonth = 0;
        $bookingsLastMonth = 0;
        $totalNights = 0;
        $eligibleCount = 0;

        foreach ($bookings as $booking) {
            if (! is_array($booking) || ! $this->isEligibleForRevenue($booking)) {
                continue;
            }

            $eligibleCount++;

            $totalPrice = max(0.0, (float) ($booking['totalPrice'] ?? $booking['total_price'] ?? 0));
            $commission = $totalPrice * 0.1;
            $totalRevenue += $commission;

            $createdRaw = $booking['createdAt'] ?? $booking['created_at'] ?? null;
            if ($createdRaw) {
                try {
                    $createdAt = Carbon::parse($createdRaw);
                    $monthKey = $createdAt->format('Y-m');
                    if (array_key_exists($monthKey, $chartBuckets)) {
                        $chartBuckets[$monthKey] += $commission;
                    }
                    if ($createdAt->month === $currentMonth && $createdAt->year === $currentYear) {
                        $revenueThisMonth += $commission;
                        $bookingsThisMonth++;
                    } elseif ($createdAt->format('Y-m') === $lastMonth->format('Y-m')) {
                        $revenueLastMonth += $commission;
                        $bookingsLastMonth++;
                    }
                } catch (\Throwable) {
                    // createdAt invalide : on garde la commission dans le total mais pas la répartition temporelle
                }
            }

            $startDate = $booking['startDate'] ?? $booking['start_date'] ?? null;
            $endDate = $booking['endDate'] ?? $booking['end_date'] ?? null;
            if ($startDate && $endDate) {
                try {
                    $start = Carbon::parse($startDate);
                    $end = Carbon::parse($endDate);
                    $totalNights += max(0, $start->diffInDays($end));
                } catch (\Throwable) {
                }
            }
        }

        Log::info('AdminRevenueController - Réservations éligibles pour CA', [
            'eligible_count' => $eligibleCount,
        ]);

        $occupationRate = 0.0;
        if ($activeProperties > 0) {
            $maxNights = $activeProperties * 30;
            if ($maxNights > 0) {
                $occupationRate = min(100.0, round(($totalNights / $maxNights) * 100, 1));
            }
        }

        $revenueTrend = 0.0;
        if ($revenueLastMonth > 0) {
            $revenueTrend = round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1);
        }

        $bookingsTrend = 0.0;
        if ($bookingsLastMonth > 0) {
            $bookingsTrend = round((($bookingsThisMonth - $bookingsLastMonth) / $bookingsLastMonth) * 100, 1);
        }

        $chartDataArray = [];
        foreach ($months as $index => $month) {
            $monthKey = $now->copy()->subMonths(5 - $index)->format('Y-m');
            $chartDataArray[] = [
                'month' => $month,
                'total' => (int) round(max(0.0, $chartBuckets[$monthKey] ?? 0.0)),
            ];
        }

        return [
            'totalRevenue' => round(max(0.0, $totalRevenue), 2),
            'revenueThisMonth' => round(max(0.0, $revenueThisMonth), 2),
            'totalBookings' => $eligibleCount,
            'occupationRate' => $occupationRate,
            'activeProperties' => $activeProperties,
            'trends' => [
                'totalRevenue' => $revenueTrend,
                'bookings' => $bookingsTrend,
                'occupation' => 0,
                'properties' => 0,
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
            'revenueThisMonth' => 0,
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

