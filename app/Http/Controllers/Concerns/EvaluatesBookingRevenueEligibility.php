<?php

namespace App\Http\Controllers\Concerns;

/**
 * Règle « réservation comptable » : CA uniquement si paiement réel ou confirmation officielle.
 */
trait EvaluatesBookingRevenueEligibility
{
    protected function isEligibleForRevenue(array $booking): bool
    {
        $status = strtolower((string) ($booking['status'] ?? ''));

        if (in_array($status, ['cancelled', 'canceled', 'annulée', 'annulee', 'annule'], true)) {
            return false;
        }

        if (in_array($status, ['awaiting_payment', 'pending', 'draft'], true)) {
            return false;
        }

        $isPaid = $this->bookingIsPaid($booking);

        $ownerConfirmedAt = $booking['ownerConfirmedAt'] ?? $booking['owner_confirmed_at'] ?? null;
        $hasOwnerConfirmation = $ownerConfirmedAt !== null
            && $ownerConfirmedAt !== ''
            && strtolower(trim((string) $ownerConfirmedAt)) !== 'null';

        $isConfirmedStatus = $status === 'confirmed'
            || in_array($status, ['confirmee', 'confirmée'], true);

        return $isPaid || $hasOwnerConfirmation || $isConfirmedStatus;
    }

    private function bookingIsPaid(array $booking): bool
    {
        $p = $booking['isPaid'] ?? $booking['is_paid'] ?? false;
        if ($p === true || $p === 1 || $p === '1') {
            return true;
        }
        if (is_string($p) && strtolower($p) === 'true') {
            return true;
        }

        return false;
    }
}
