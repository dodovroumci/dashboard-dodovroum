<?php

namespace App\Services\DodoVroumApi;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use App\Exceptions\DodoVroumApiException;

class BookingService extends BaseApiService
{
    /**
     * Récupérer toutes les réservations
     */
    public function all(array $filters = []): array
    {
        return $this->get('bookings', $filters);
    }

    /**
     * Récupérer les réservations pour un véhicule spécifique
     * Tente d'utiliser le filtre vehicleId de l'API, sinon récupère toutes et filtre localement
     * 
     * @param string $vehicleId ID du véhicule (CUID NestJS)
     * @return array Réservations liées au véhicule
     */
    public function getBookingsForVehicle(string $vehicleId): array
    {
        Log::info('BookingService::getBookingsForVehicle appelé', [
            'vehicle_id' => $vehicleId,
        ]);

        // Tenter d'abord avec un filtre API (si supporté)
        try {
            $filteredBookings = $this->get('bookings', ['vehicleId' => $vehicleId]);
            
            // Vérifier si les réservations retournées correspondent vraiment au véhicule
            // (l'API peut ignorer le filtre et retourner toutes les réservations)
            $validBookings = [];
            foreach ($filteredBookings as $booking) {
                $bookingVehicleId = null;
                
                // Extraire l'ID du véhicule de toutes les façons possibles
                if (isset($booking['vehicleId']) && $booking['vehicleId'] !== null) {
                    $bookingVehicleId = $booking['vehicleId'];
                } elseif (isset($booking['vehicle_id']) && $booking['vehicle_id'] !== null) {
                    $bookingVehicleId = $booking['vehicle_id'];
                } elseif (isset($booking['vehicle']) && $booking['vehicle'] !== null) {
                    if (is_array($booking['vehicle'])) {
                        $bookingVehicleId = $booking['vehicle']['id'] ?? $booking['vehicle']['_id'] ?? null;
                    } elseif (is_string($booking['vehicle'])) {
                        $bookingVehicleId = $booking['vehicle'];
                    }
                }
                
                if ($bookingVehicleId && (string) $bookingVehicleId === (string) $vehicleId) {
                    $validBookings[] = $booking;
                }
            }
            
            if (!empty($validBookings)) {
                Log::info('BookingService::getBookingsForVehicle - Réservations trouvées via filtre API', [
                    'vehicle_id' => $vehicleId,
                    'count_filtered' => count($filteredBookings),
                    'count_valid' => count($validBookings),
                ]);
                return $validBookings;
            }
            
            // Si le filtre API a retourné des résultats mais aucun ne correspond, l'API ignore le filtre
            if (!empty($filteredBookings)) {
                Log::warning('BookingService::getBookingsForVehicle - Filtre API ignoré par l\'API, fallback sur pagination', [
                    'vehicle_id' => $vehicleId,
                    'count_returned' => count($filteredBookings),
                    'count_valid' => count($validBookings),
                ]);
            }
            
            Log::debug('BookingService::getBookingsForVehicle - Aucune réservation via filtre API, tentative avec toutes les réservations', [
                'vehicle_id' => $vehicleId,
            ]);
        } catch (\Exception $e) {
            Log::warning('BookingService::getBookingsForVehicle - Filtre API non supporté, fallback sur toutes les réservations', [
                'vehicle_id' => $vehicleId,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback : récupérer toutes les réservations avec pagination et filtrer localement
        $allBookings = [];
        $limit = 100;
        $page = 1;
        $maxPages = 20; // Limite de sécurité pour éviter les boucles infinies
        
        do {
            try {
                $queryParams = [
                    'limit' => $limit,
                    'page' => $page,
                ];
                
                $response = $this->get('bookings', $queryParams);
                
                if (empty($response)) {
                    break;
                }
                
                $allBookings = array_merge($allBookings, $response);
                
                // Si on a moins de résultats que le limit, on a atteint la fin
                if (count($response) < $limit) {
                    break;
                }
                
                $page++;
            } catch (\Exception $e) {
                Log::error('BookingService::getBookingsForVehicle - Erreur lors de la pagination', [
                    'vehicle_id' => $vehicleId,
                    'page' => $page,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        } while ($page <= $maxPages);

        Log::info('BookingService::getBookingsForVehicle - Toutes les réservations récupérées', [
            'vehicle_id' => $vehicleId,
            'total_bookings' => count($allBookings),
            'pages_fetched' => $page - 1,
        ]);

        // Filtrer localement avec la méthode robuste
        $vehicleBookings = [];
        foreach ($allBookings as $booking) {
            $bookingVehicleId = null;
            
            // Extraire l'ID du véhicule de toutes les façons possibles
            if (isset($booking['vehicleId']) && $booking['vehicleId'] !== null) {
                $bookingVehicleId = $booking['vehicleId'];
            } elseif (isset($booking['vehicle_id']) && $booking['vehicle_id'] !== null) {
                $bookingVehicleId = $booking['vehicle_id'];
            } elseif (isset($booking['vehicle']) && $booking['vehicle'] !== null) {
                if (is_array($booking['vehicle'])) {
                    $bookingVehicleId = $booking['vehicle']['id'] ?? $booking['vehicle']['_id'] ?? null;
                } elseif (is_string($booking['vehicle'])) {
                    $bookingVehicleId = $booking['vehicle'];
                }
            }
            
            if ($bookingVehicleId && (string) $bookingVehicleId === (string) $vehicleId) {
                $vehicleBookings[] = $booking;
            }
        }

        Log::info('BookingService::getBookingsForVehicle - Résultat final', [
            'vehicle_id' => $vehicleId,
            'total_bookings_checked' => count($allBookings),
            'vehicle_bookings_found' => count($vehicleBookings),
        ]);

        return $vehicleBookings;
    }

    /**
     * Récupérer une réservation par ID
     */
    public function find(string $id): ?array
    {
        return $this->getSingle("bookings/{$id}");
    }

    /**
     * Récupérer les réservations récentes
     */
    public function recent(int $limit = 10): array
    {
        $bookings = $this->get('bookings/recent', ['limit' => $limit]);
        
        // Si vide, récupérer toutes les bookings et prendre les premières
        if (empty($bookings)) {
            $allBookings = $this->all();
            $bookings = array_slice($allBookings, 0, $limit);
        }

        return $bookings;
    }

    /**
     * Approuver une réservation
     * Endpoint: PATCH /api/bookings/:id/approve
     */
    public function approve(string $id): void
    {
        try {
            $this->patch("bookings/{$id}/approve", []);
            Log::info('Réservation approuvée avec succès', ['id' => $id]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur lors de l\'approbation de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Rejeter une réservation
     * Endpoint: PATCH /api/bookings/:id/reject
     */
    public function reject(string $id, ?string $reason = null): void
    {
        try {
            $data = [];
            if ($reason) {
                $data['reason'] = $reason;
            }
            
            $this->patch("bookings/{$id}/reject", $data);
            Log::info('Réservation rejetée avec succès', ['id' => $id, 'reason' => $reason]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur lors du rejet de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Confirmer la récupération de clé par le client
     * Endpoint: PATCH /api/bookings/:id/confirm-key-retrieval
     * Transition: CONFIRMEE → CHECKIN_CLIENT
     */
    public function confirmKeyRetrieval(string $id): void
    {
        try {
            $this->patch("bookings/{$id}/confirm-key-retrieval", []);
            Log::info('Récupération de clé confirmée avec succès', ['id' => $id]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur lors de la confirmation de récupération de clé', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Confirmer la remise de clé par le propriétaire
     * Endpoint: PATCH /api/bookings/:id/confirm-owner-key-handover
     * Transition: CHECKIN_CLIENT → CHECKIN_PROPRIO (ou auto → EN_COURS_SEJOUR si les deux ont confirmé)
     */
    public function confirmOwnerKeyHandover(string $id): void
    {
        try {
            $this->patch("bookings/{$id}/confirm-owner-key-handover", []);
            Log::info('Remise de clé par le propriétaire confirmée avec succès', ['id' => $id]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur lors de la confirmation de remise de clé', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Confirmer le checkout
     * Endpoint: PATCH /api/bookings/:id/confirm-checkout
     * Transition: EN_COURS_SEJOUR → TERMINEE
     */
    public function confirmCheckOut(string $id): void
    {
        try {
            $this->patch("bookings/{$id}/confirm-checkout", []);
            Log::info('Checkout confirmé avec succès', ['id' => $id]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur lors de la confirmation du checkout', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Marquer une réservation comme payée manuellement
     * Endpoint: PATCH /api/bookings/:id/mark-as-paid
     */
    public function markAsPaid(string $id, ?float $amount = null, ?string $paymentMethod = null, ?string $transactionId = null, ?string $notes = null): void
    {
        try {
            $data = [];
            if ($amount !== null) {
                $data['amount'] = $amount;
            }
            if ($paymentMethod) {
                $data['paymentMethod'] = $paymentMethod;
            }
            if ($transactionId) {
                $data['transactionId'] = $transactionId;
            }
            if ($notes) {
                $data['notes'] = $notes;
            }
            
            $this->patch("bookings/{$id}/mark-as-paid", $data);
            Log::info('Réservation marquée comme payée avec succès', [
                'id' => $id,
                'amount' => $amount,
                'paymentMethod' => $paymentMethod,
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur lors du marquage de la réservation comme payée', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Supprimer une réservation
     * Endpoint: DELETE /api/bookings/:id
     */
    public function deleteBooking(string $id): bool
    {
        try {
            $deleted = parent::delete("bookings/{$id}");
            
            if (!$deleted) {
                Log::warning('Impossible de supprimer la réservation', ['id' => $id]);
                return false;
            }
            
            Log::info('Réservation supprimée avec succès', ['id' => $id]);
            return true;
        } catch (DodoVroumApiException $e) {
            Log::error('Erreur lors de la suppression de la réservation', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Récupérer les réservations pour un utilisateur (filtrées selon le rôle)
     * 
     * @param Authenticatable $user L'utilisateur connecté
     * @param array $filters Filtres additionnels
     * @return array
     */
    public function getBookingsForUser(Authenticatable $user, array $filters = []): array
    {
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role ?? 'owner') === 'admin';
        
        if ($isAdmin) {
            // Admin voit tout
            return $this->all($filters);
        }

        // Propriétaire : filtrer par proprietaireId
        // Les réservations sont liées aux résidences/véhicules qui ont un proprietaireId
        $ownerFilters = array_merge($filters, [
            'proprietaireId' => $user->getAuthIdentifier(),
        ]);

        return $this->all($ownerFilters);
    }

    /**
     * Récupérer une réservation pour un utilisateur (avec vérification de propriété)
     * 
     * @param string $id ID de la réservation
     * @param Authenticatable $user L'utilisateur connecté
     * @return array|null
     */
    public function findForUser(string $id, Authenticatable $user): ?array
    {
        Log::info('BookingService::findForUser appelé', [
            'booking_id' => $id,
            'user_id' => $user->getAuthIdentifier(),
            'user_role' => $user->role ?? 'unknown',
        ]);

        $booking = $this->find($id);

        if (!$booking) {
            Log::warning('Réservation non trouvée dans findForUser', [
                'booking_id' => $id,
            ]);
            return null;
        }

        Log::info('Réservation trouvée, vérification propriétaire', [
            'booking_id' => $id,
            'booking_keys' => array_keys($booking),
            'has_residence' => isset($booking['residence']),
            'has_vehicle' => isset($booking['vehicle']),
        ]);

        // Si propriétaire, vérifier que la réservation concerne une de ses ressources
        $isOwner = method_exists($user, 'isOwner') ? $user->isOwner() : ($user->role ?? 'owner') === 'owner';
        
        if ($isOwner) {
            $ownerId = self::extractOwnerIdFromBooking($booking);
            $userId = (string) $user->getAuthIdentifier();

            Log::info('Vérification propriétaire réservation', [
                'booking_id' => $id,
                'ownerId_from_booking' => $ownerId,
                'user_id' => $userId,
                'match' => $ownerId === $userId,
                'ownerId_type' => gettype($ownerId),
                'userId_type' => gettype($userId),
            ]);

            if ($ownerId !== $userId) {
                Log::warning('Réservation n\'appartient pas au propriétaire', [
                    'booking_id' => $id,
                    'ownerId_from_booking' => $ownerId,
                    'user_id' => $userId,
                ]);
                return null; // Ne pas retourner la réservation si elle ne concerne pas le propriétaire
            }
        }

        return $booking;
    }

    /**
     * Extraire l'ID du propriétaire depuis une réservation
     */
    protected static function extractOwnerIdFromBooking(array $booking): ?string
    {
        // Essayer d'abord les champs directs
        if (isset($booking['ownerId']) && !empty($booking['ownerId'])) {
            return (string) $booking['ownerId'];
        }
        if (isset($booking['owner_id']) && !empty($booking['owner_id'])) {
            return (string) $booking['owner_id'];
        }
        if (isset($booking['proprietaireId']) && !empty($booking['proprietaireId'])) {
            return (string) $booking['proprietaireId'];
        }

        // Essayer depuis la résidence
        if (isset($booking['residence']) && is_array($booking['residence'])) {
            $residence = $booking['residence'];
            
            // Champs directs
            if (isset($residence['proprietaireId']) && !empty($residence['proprietaireId'])) {
                return (string) $residence['proprietaireId'];
            }
            if (isset($residence['ownerId']) && !empty($residence['ownerId'])) {
                return (string) $residence['ownerId'];
            }
            
            // Depuis l'objet proprietaire
            $proprietaire = $residence['proprietaire'] ?? $residence['owner'] ?? null;
            if ($proprietaire && is_array($proprietaire)) {
                if (isset($proprietaire['id']) && !empty($proprietaire['id'])) {
                    return (string) $proprietaire['id'];
                }
            }
        }

        // Essayer depuis le véhicule
        if (isset($booking['vehicle']) && is_array($booking['vehicle'])) {
            $vehicle = $booking['vehicle'];
            
            // Champs directs
            if (isset($vehicle['proprietaireId']) && !empty($vehicle['proprietaireId'])) {
                return (string) $vehicle['proprietaireId'];
            }
            if (isset($vehicle['ownerId']) && !empty($vehicle['ownerId'])) {
                return (string) $vehicle['ownerId'];
            }
            
            // Depuis l'objet proprietaire
            $proprietaire = $vehicle['proprietaire'] ?? $vehicle['owner'] ?? null;
            if ($proprietaire && is_array($proprietaire)) {
                if (isset($proprietaire['id']) && !empty($proprietaire['id'])) {
                    return (string) $proprietaire['id'];
                }
            }
        }

        return null;
    }
}

