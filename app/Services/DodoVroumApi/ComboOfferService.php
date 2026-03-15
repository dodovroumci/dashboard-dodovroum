<?php

namespace App\Services\DodoVroumApi;

use App\Services\DodoVroumApiService;
use Illuminate\Support\Facades\Log;

class ComboOfferService
{
    public function __construct(
        protected DodoVroumApiService $apiService
    ) {
    }

    /**
     * Actualiser toutes les offres combinées qui utilisent un véhicule donné
     */
    public function refreshOffersForVehicle(string $vehicleId): void
    {
        try {
            Log::info('🔄 Actualisation des offres combinées pour véhicule', [
                'vehicle_id' => $vehicleId,
            ]);

            // Récupérer toutes les offres combinées
            $allOffers = $this->apiService->getComboOffers([]);
            
            $offersToRefresh = [];
            foreach ($allOffers as $offer) {
                // L'API retourne 'voiture' au lieu de 'vehicle'
                $vehicle = $offer['voiture'] ?? $offer['vehicle'] ?? null;
                $offerVehicleId = $vehicle['id'] ?? $offer['vehicleId'] ?? $offer['vehicle_id'] ?? null;
                
                if ($offerVehicleId === $vehicleId) {
                    $offersToRefresh[] = $offer['id'] ?? null;
                }
            }

            if (empty($offersToRefresh)) {
                Log::info('Aucune offre combinée trouvée pour ce véhicule', [
                    'vehicle_id' => $vehicleId,
                ]);
                return;
            }

            Log::info('Offres combinées à actualiser trouvées', [
                'vehicle_id' => $vehicleId,
                'offers_count' => count($offersToRefresh),
                'offer_ids' => $offersToRefresh,
            ]);

            // Actualiser chaque offre combinée en appelant l'API
            // L'API NestJS devrait automatiquement récupérer les nouvelles données du véhicule
            foreach ($offersToRefresh as $offerId) {
                if (!$offerId) {
                    continue;
                }

                try {
                    // Récupérer d'abord le véhicule mis à jour depuis l'API
                    $updatedVehicle = null;
                    try {
                        $updatedVehicle = $this->apiService->getVehicle($vehicleId);
                        if ($updatedVehicle) {
                            Log::info('Véhicule mis à jour récupéré pour actualisation offre combinée', [
                                'vehicle_id' => $vehicleId,
                                'vehicle_title' => $updatedVehicle['title'] ?? $updatedVehicle['titre'] ?? $updatedVehicle['name'] ?? null,
                            ]);
                        }
                    } catch (\Exception $vehicleError) {
                        Log::warning('Impossible de récupérer le véhicule mis à jour', [
                            'vehicle_id' => $vehicleId,
                            'error' => $vehicleError->getMessage(),
                        ]);
                    }
                    
                    // Tenter un PATCH minimal pour forcer la mise à jour des relations
                    // Si l'API NestJS utilise des relations Prisma, cela devrait déclencher le rechargement
                    try {
                        // PATCH avec un champ minimal (isActive par exemple) pour forcer la mise à jour
                        $this->apiService->updateComboOffer($offerId, ['isActive' => true]);
                        Log::info('Offre combinée actualisée via PATCH', [
                            'offer_id' => $offerId,
                            'vehicle_id' => $vehicleId,
                        ]);
                    } catch (\Exception $patchError) {
                        // Si le PATCH échoue, récupérer l'offre pour forcer le rechargement
                        Log::debug('PATCH échoué, tentative de récupération directe', [
                            'offer_id' => $offerId,
                            'error' => $patchError->getMessage(),
                        ]);
                        $updatedOffer = $this->apiService->getComboOffer($offerId);
                        
                        if ($updatedOffer) {
                            Log::info('Offre combinée actualisée via GET', [
                                'offer_id' => $offerId,
                                'vehicle_id' => $vehicleId,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur lors de l\'actualisation de l\'offre combinée', [
                        'offer_id' => $offerId,
                        'vehicle_id' => $vehicleId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'actualisation des offres combinées pour véhicule', [
                'vehicle_id' => $vehicleId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Actualiser toutes les offres combinées qui utilisent une résidence donnée
     */
    public function refreshOffersForResidence(string $residenceId): void
    {
        try {
            Log::info('🔄 Actualisation des offres combinées pour résidence', [
                'residence_id' => $residenceId,
            ]);

            // Récupérer toutes les offres combinées
            $allOffers = $this->apiService->getComboOffers([]);
            
            $offersToRefresh = [];
            foreach ($allOffers as $offer) {
                $residence = $offer['residence'] ?? null;
                $offerResidenceId = $residence['id'] ?? $offer['residenceId'] ?? $offer['residence_id'] ?? null;
                
                if ($offerResidenceId === $residenceId) {
                    $offersToRefresh[] = $offer['id'] ?? null;
                }
            }

            if (empty($offersToRefresh)) {
                Log::info('Aucune offre combinée trouvée pour cette résidence', [
                    'residence_id' => $residenceId,
                ]);
                return;
            }

            Log::info('Offres combinées à actualiser trouvées', [
                'residence_id' => $residenceId,
                'offers_count' => count($offersToRefresh),
                'offer_ids' => $offersToRefresh,
            ]);

            // Actualiser chaque offre combinée en appelant l'API
            // L'API NestJS devrait automatiquement récupérer les nouvelles données de la résidence
            foreach ($offersToRefresh as $offerId) {
                if (!$offerId) {
                    continue;
                }

                try {
                    // Tenter un PATCH minimal pour forcer la mise à jour des relations
                    // Si l'API NestJS utilise des relations Prisma, cela devrait déclencher le rechargement
                    try {
                        // PATCH avec un champ minimal (isActive par exemple) pour forcer la mise à jour
                        $this->apiService->updateComboOffer($offerId, ['isActive' => true]);
                        Log::info('Offre combinée actualisée via PATCH', [
                            'offer_id' => $offerId,
                            'residence_id' => $residenceId,
                        ]);
                    } catch (\Exception $patchError) {
                        // Si le PATCH échoue, récupérer l'offre pour forcer le rechargement
                        Log::debug('PATCH échoué, tentative de récupération directe', [
                            'offer_id' => $offerId,
                            'error' => $patchError->getMessage(),
                        ]);
                        $updatedOffer = $this->apiService->getComboOffer($offerId);
                        
                        if ($updatedOffer) {
                            Log::info('Offre combinée actualisée via GET', [
                                'offer_id' => $offerId,
                                'residence_id' => $residenceId,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur lors de l\'actualisation de l\'offre combinée', [
                        'offer_id' => $offerId,
                        'residence_id' => $residenceId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'actualisation des offres combinées pour résidence', [
                'residence_id' => $residenceId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

