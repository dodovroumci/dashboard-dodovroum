<?php

namespace App\Services\DodoVroumApi;

use Illuminate\Support\Facades\Log;

/**
 * Service pour les offres combinées, utilise le token du contexte (propriétaire ou admin).
 * Quand un propriétaire est connecté, GET /offers renvoie uniquement ses offres.
 */
class OfferService extends BaseApiService
{
    /**
     * Récupérer toutes les offres avec pagination (token propriétaire = ses offres, token admin = toutes).
     */
    public function all(array $filters = []): array
    {
        $limit = 100;
        $page = 1;
        $allOffers = [];
        $maxPages = 10;

        do {
            $queryParams = array_merge($filters, [
                'limit' => $limit,
                'page' => $page,
            ]);

            $response = $this->get('offers', $queryParams);

            if (empty($response)) {
                break;
            }

            $allOffers = array_merge($allOffers, $response);

            if (count($response) < $limit) {
                break;
            }

            $page++;
        } while ($page <= $maxPages);

        Log::debug('Offres combinées récupérées', [
            'count' => count($allOffers),
            'filters' => $filters,
        ]);

        return $allOffers;
    }

    /**
     * Créer une offre combinée (avec le token du propriétaire connecté pour l'associer à son compte).
     */
    public function create(array $data): array
    {
        $result = $this->post('offers', $data);
        return is_array($result) ? $result : [];
    }
}
