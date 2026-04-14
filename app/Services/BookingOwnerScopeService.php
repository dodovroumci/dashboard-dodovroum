<?php

namespace App\Services;

/**
 * Résolution du propriétaire « métier » d'une réservation, alignée sur OwnerBookingController::index
 * (résidence, véhicule, offre combinée, IDs seuls + enrichissement API).
 */
class BookingOwnerScopeService
{
    public function __construct(
        protected DodoVroumApiService $apiService
    ) {
    }

    public function matchesProprietaire(?string $bookingOwnerId, string|int $proprietaireId): bool
    {
        if ($bookingOwnerId === null || $bookingOwnerId === '') {
            return false;
        }

        return (string) $bookingOwnerId === (string) $proprietaireId
            || (is_numeric($bookingOwnerId) && is_numeric($proprietaireId)
                && (int) $bookingOwnerId === (int) $proprietaireId);
    }

    /**
     * Même ordre de priorité et mêmes fallbacks API que OwnerBookingController::index.
     */
    public function resolveOwnerIdForBooking(array $booking): ?string
    {
        $bookingOwnerId = null;

        if (isset($booking['residence']) && is_array($booking['residence'])) {
            $bookingOwnerId = $booking['residence']['proprietaireId'] ?? $booking['residence']['proprietaire_id'] ?? $booking['residence']['ownerId'] ?? $booking['residence']['owner_id'] ?? null;
        }
        if (! $bookingOwnerId && isset($booking['vehicle']) && is_array($booking['vehicle'])) {
            $bookingOwnerId = $booking['vehicle']['proprietaireId'] ?? $booking['vehicle']['proprietaire_id'] ?? $booking['vehicle']['ownerId'] ?? $booking['vehicle']['owner_id'] ?? null;
        }
        if (! $bookingOwnerId && isset($booking['voiture']) && is_array($booking['voiture'])) {
            $bookingOwnerId = $booking['voiture']['proprietaireId'] ?? $booking['voiture']['proprietaire_id'] ?? $booking['voiture']['ownerId'] ?? $booking['voiture']['owner_id'] ?? null;
        }

        if (! $bookingOwnerId && isset($booking['ownerId']) && ! empty($booking['ownerId'])) {
            $bookingOwnerId = $booking['ownerId'];
        }
        if (! $bookingOwnerId && isset($booking['proprietaireId']) && ! empty($booking['proprietaireId'])) {
            $bookingOwnerId = $booking['proprietaireId'];
        }
        if (! $bookingOwnerId && isset($booking['owner_id']) && ! empty($booking['owner_id'])) {
            $bookingOwnerId = $booking['owner_id'];
        }
        if (! $bookingOwnerId && isset($booking['proprietaire_id']) && ! empty($booking['proprietaire_id'])) {
            $bookingOwnerId = $booking['proprietaire_id'];
        }

        if (! $bookingOwnerId && isset($booking['offer']) && is_array($booking['offer'])) {
            $offer = $booking['offer'];
            $bookingOwnerId = $offer['proprietaireId'] ?? $offer['ownerId'] ?? null;

            if (! $bookingOwnerId && isset($offer['residence']) && is_array($offer['residence'])) {
                $bookingOwnerId = $offer['residence']['proprietaireId'] ?? $offer['residence']['proprietaire_id'] ?? $offer['residence']['ownerId'] ?? $offer['residence']['owner_id'] ?? null;
            }
            if (! $bookingOwnerId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                $bookingOwnerId = $offer['vehicle']['proprietaireId'] ?? $offer['vehicle']['proprietaire_id'] ?? $offer['vehicle']['ownerId'] ?? $offer['vehicle']['owner_id'] ?? null;
            }
            if (! $bookingOwnerId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                $bookingOwnerId = $offer['voiture']['proprietaireId'] ?? $offer['voiture']['proprietaire_id'] ?? $offer['voiture']['ownerId'] ?? $offer['voiture']['owner_id'] ?? null;
            }
        }

        if (! $bookingOwnerId && isset($booking['residenceId']) && ! empty($booking['residenceId'])) {
            try {
                $residence = $this->apiService->getResidence($booking['residenceId']);
                if ($residence && is_array($residence)) {
                    $bookingOwnerId = $residence['proprietaireId'] ?? $residence['proprietaire_id'] ?? $residence['ownerId'] ?? $residence['owner_id'] ?? null;
                }
            } catch (\Exception $e) {
                // Ignorer
            }
        }

        if (! $bookingOwnerId && isset($booking['vehicleId']) && ! empty($booking['vehicleId'])) {
            try {
                $vehicle = $this->apiService->getVehicle($booking['vehicleId']);
                if ($vehicle && is_array($vehicle)) {
                    $bookingOwnerId = $vehicle['proprietaireId'] ?? $vehicle['proprietaire_id'] ?? $vehicle['ownerId'] ?? $vehicle['owner_id'] ?? null;
                }
            } catch (\Exception $e) {
                // Ignorer
            }
        }

        if (! $bookingOwnerId && isset($booking['offerId']) && ! empty($booking['offerId'])) {
            try {
                $offer = $this->apiService->getComboOffer($booking['offerId']);
                if ($offer && is_array($offer)) {
                    $bookingOwnerId = $offer['proprietaireId'] ?? $offer['proprietaire_id'] ?? $offer['ownerId'] ?? $offer['owner_id'] ?? null;
                    if (! $bookingOwnerId && isset($offer['residence']) && is_array($offer['residence'])) {
                        $bookingOwnerId = $offer['residence']['proprietaireId'] ?? $offer['residence']['proprietaire_id'] ?? $offer['residence']['ownerId'] ?? $offer['residence']['owner_id'] ?? null;
                    }
                    if (! $bookingOwnerId && isset($offer['vehicle']) && is_array($offer['vehicle'])) {
                        $bookingOwnerId = $offer['vehicle']['proprietaireId'] ?? $offer['vehicle']['proprietaire_id'] ?? $offer['vehicle']['ownerId'] ?? $offer['vehicle']['owner_id'] ?? null;
                    }
                    if (! $bookingOwnerId && isset($offer['voiture']) && is_array($offer['voiture'])) {
                        $bookingOwnerId = $offer['voiture']['proprietaireId'] ?? $offer['voiture']['proprietaire_id'] ?? $offer['voiture']['ownerId'] ?? $offer['voiture']['owner_id'] ?? null;
                    }
                }
            } catch (\Exception $e) {
                // Ignorer
            }
        }

        if ($bookingOwnerId === null || $bookingOwnerId === '') {
            return null;
        }

        return (string) $bookingOwnerId;
    }
}
