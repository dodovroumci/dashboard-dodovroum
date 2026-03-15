<?php

namespace App\Services\DodoVroumApi;

class ApiResponseNormalizer
{
    /**
     * Normalise une réponse API pour extraire les données
     * Gère les différentes structures possibles :
     * - { data: { data: [...] } }
     * - { data: [...] }
     * - { data: {...} }
     * - [...]
     * - {...}
     */
    public static function data(array $response): array
    {
        // Cas 1: Structure imbriquée { data: { data: [...] } }
        if (isset($response['data']['data']) && is_array($response['data']['data'])) {
            return self::flattenArray($response['data']['data']);
        }

        // Cas 2: Structure simple { data: [...] } ou { data: {...} }
        if (isset($response['data'])) {
            $data = $response['data'];
            
            // Si c'est un tableau, l'aplatir
            if (is_array($data)) {
                return self::flattenArray($data);
            }
            
            // Si c'est un objet unique, le retourner dans un tableau
            return [$data];
        }

        // Cas 3: Tableau direct
        if (is_array($response) && isset($response[0])) {
            return self::flattenArray($response);
        }

        // Cas 4: Objet unique
        if (is_array($response) && !empty($response)) {
            return [$response];
        }

        return [];
    }

    /**
     * Normalise une réponse API pour un objet unique (pas un tableau)
     */
    public static function single(array $response): ?array
    {
        // Cas 1: Structure imbriquée { data: { data: {...} } }
        if (isset($response['data']['data']) && is_array($response['data']['data'])) {
            // Si c'est un tableau avec un seul élément
            if (isset($response['data']['data'][0])) {
                return $response['data']['data'][0];
            }
            // Si c'est un objet
            if (isset($response['data']['data']['id'])) {
                return $response['data']['data'];
            }
        }

        // Cas 2: Structure simple { data: {...} }
        if (isset($response['data'])) {
            $data = $response['data'];
            
            // Si c'est un tableau avec un seul élément
            if (is_array($data) && isset($data[0])) {
                return $data[0];
            }
            
            // Si c'est un objet (avec 'id' ou '_id')
            if (is_array($data) && (isset($data['id']) || isset($data['_id']))) {
                return $data;
            }
        }

        // Cas 3: Objet direct (avec 'id' ou '_id')
        if (is_array($response) && (isset($response['id']) || isset($response['_id']))) {
            return $response;
        }

        return null;
    }

    /**
     * Aplatit les tableaux imbriqués
     * Gère les cas où l'API retourne des tableaux de tableaux
     */
    protected static function flattenArray(array $items): array
    {
        $flattened = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Vérifier si c'est un tableau numérique (indexé 0, 1, 2...)
            $keys = array_keys($item);
            $isNumericArray = !empty($keys) && $keys === range(0, count($item) - 1);

            if ($isNumericArray) {
                // C'est un tableau de tableaux, on aplatit
                foreach ($item as $subItem) {
                    if (is_array($subItem) && isset($subItem['id'])) {
                        $flattened[] = $subItem;
                    }
                }
            } else {
                // C'est un objet/tableau associatif
                if (isset($item['id'])) {
                    $flattened[] = $item;
                }
            }
        }

        return $flattened;
    }
}

