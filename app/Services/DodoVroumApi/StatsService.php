<?php

namespace App\Services\DodoVroumApi;

use App\Services\DodoVroumApi\BaseApiService;
use Illuminate\Support\Facades\Log;

/**
 * Service pour récupérer les statistiques depuis l'API NestJS
 * 
 * Ce service appelle l'endpoint /api/owner/stats de l'API NestJS
 * qui doit être implémenté avec des agrégations SQL performantes.
 */
class StatsService extends BaseApiService
{
    /**
     * Récupérer les statistiques d'un propriétaire
     * 
     * L'API NestJS doit utiliser l'ownerId du token JWT (sécurité critique)
     * et retourner des données agrégées pour éviter de charger des milliers de lignes.
     * 
     * @param string|null $ownerId Optionnel, mais l'API doit ignorer ce paramètre
     *                             et utiliser l'ownerId du token JWT
     * @return array|null Retourne null si l'endpoint n'existe pas (404), pour permettre le fallback
     */
    public function getOwnerStats(?string $ownerId = null): ?array
    {
        try {
            // L'API NestJS doit utiliser l'ownerId du token JWT, pas du paramètre
            // On ne passe pas ownerId dans l'URL pour forcer l'utilisation du token
            $response = $this->get('owner/stats');
            
            // Si l'endpoint retourne un tableau vide (404 géré par BaseApiService),
            // on retourne null pour indiquer que l'endpoint n'existe pas encore
            if (empty($response)) {
                Log::info('Endpoint /api/owner/stats non disponible (404), fallback sur calcul local', [
                    'ownerId' => $ownerId,
                ]);
                return null;
            }
            
            if ($response && is_array($response)) {
                Log::info('Stats récupérées depuis l\'API NestJS', [
                    'has_data' => !empty($response),
                    'keys' => array_keys($response),
                ]);
                return $response;
            }
            
            Log::warning('Réponse API stats invalide', [
                'response' => $response,
                'response_type' => gettype($response),
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            // Si c'est une erreur 404, c'est normal (endpoint pas encore implémenté)
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'Not Found')) {
                Log::info('Endpoint /api/owner/stats non trouvé, fallback sur calcul local', [
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
            
            Log::error('Erreur lors de la récupération des stats depuis l\'API', [
                'error' => $e->getMessage(),
                'ownerId' => $ownerId,
            ]);
            
            return null;
        }
    }

    /**
     * Retourner des statistiques par défaut en cas d'erreur
     */
    private function getDefaultStats(): array
    {
        return [
            'totalRevenue' => 0,
            'revenueTrend' => 0,
            'totalBookings' => 0,
            'bookingsTrend' => 0,
            'occupationRate' => 0,
            'occupationTrend' => 0,
            'activeProperties' => 0,
            'propertiesTrend' => 0,
            'chartData' => [],
        ];
    }
}

