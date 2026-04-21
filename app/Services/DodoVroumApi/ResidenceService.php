<?php

namespace App\Services\DodoVroumApi;

use App\Services\DodoVroumApi\Mappers\ResidenceMapper;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ResidenceService extends BaseApiService
{
    /**
     * Récupérer toutes les résidences avec pagination automatique
     */
    public function all(array $filters = []): array
    {
        $limit = 100;
        $page = 1;
        $allResidences = [];
        $maxPages = 10; // Limite de sécurité
        
        do {
            $queryParams = array_merge($filters, [
                'limit' => $limit,
                'page' => $page,
            ]);
            
            $response = $this->get('residences', $queryParams);
            
            if (empty($response)) {
                break;
            }
            
            $allResidences = array_merge($allResidences, $response);
            
            // Si on a moins de résultats que le limit, on a atteint la fin
            if (count($response) < $limit) {
                break;
            }
            
            $page++;
        } while ($page <= $maxPages);

        return $allResidences;
    }

    /**
     * Récupérer une résidence par ID
     */
    public function find(string $id): ?array
    {
        $residence = $this->getSingle("residences/{$id}");
        
        if (!$residence) {
            return null;
        }
        
        return ResidenceMapper::fromApi($residence);
    }

    /**
     * Récupérer toutes les résidences mappées pour le frontend
     */
    public function allMapped(array $filters = []): array
    {
        $residences = $this->all($filters);
        
        return array_map(function($residence) {
            return ResidenceMapper::fromApi($residence);
        }, $residences);
    }

    /**
     * Créer une nouvelle résidence
     */
    public function create(array $data): array
    {
        // Tronquer la description à 500 caractères avant le mapping
        if (isset($data['description']) && is_string($data['description'])) {
            $originalLength = mb_strlen($data['description']);
            if ($originalLength > 500) {
                Log::warning('Description trop longue, troncature appliquée', [
                    'original_length' => $originalLength,
                    'truncated_length' => 500,
                ]);
                $data['description'] = mb_substr($data['description'], 0, 500);
            }
            Log::debug('Description avant envoi API', [
                'length' => mb_strlen($data['description']),
                'preview' => mb_substr($data['description'], 0, 100) . '...',
            ]);
        }
        
        // 1. Préparation des données pour Prisma @db.LongText
        // Prisma attend des chaînes JSON (String @db.LongText), pas des tableaux natifs
        $rawImages = $data['images'] ?? [];
        $rawAmenities = $data['amenities'] ?? $data['commodites'] ?? [];
        
        // S'assurer que ce sont des tableaux
        if (is_string($rawImages)) {
            $decoded = json_decode($rawImages, true);
            $rawImages = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        }
        if (!is_array($rawImages)) {
            $rawImages = [];
        }
        
        if (is_string($rawAmenities)) {
            $decoded = json_decode($rawAmenities, true);
            $rawAmenities = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        }
        if (!is_array($rawAmenities)) {
            $rawAmenities = [];
        }
        
        // Normaliser les images (remplacer URLs locales par URLs publiques)
        $normalizedImages = $this->normalizeImages($rawImages);
        
        // Nettoyer les amenities
        $cleanedAmenities = array_values(array_filter($rawAmenities, function($amenity) {
            return !empty(trim($amenity));
        }));
        
        // Mapper les données françaises vers le format anglais attendu par l'API NestJS
        // On passe les images normalisées et amenities nettoyées directement au mapper
        $data['images'] = $normalizedImages;
        $data['amenities'] = $cleanedAmenities;
        $dataForApi = ResidenceMapper::toApi($data);
        
        // 2. S'assurer que images et amenities sont des tableaux natifs (NestJS DTO attend des arrays)
        // Prisma se chargera de convertir en JSON string lors de l'enregistrement (@db.LongText)
        if (!isset($dataForApi['images']) || !is_array($dataForApi['images'])) {
            $dataForApi['images'] = [];
        }
        
        if (!isset($dataForApi['amenities']) || !is_array($dataForApi['amenities'])) {
            $dataForApi['amenities'] = [];
        }
        
        // 3. S'assurer que les types sont corrects pour Prisma
        // SUPPRIMER pricePerNight (Prisma ne l'accepte pas)
        unset($dataForApi['pricePerNight']);
        
        // Garder uniquement pricePerDay (Float)
        if (isset($dataForApi['pricePerDay'])) {
            $dataForApi['pricePerDay'] = (float) $dataForApi['pricePerDay'];
        }
        
        if (isset($dataForApi['bedrooms'])) {
            $dataForApi['bedrooms'] = (int) $dataForApi['bedrooms'];
        }
        if (isset($dataForApi['bathrooms'])) {
            $dataForApi['bathrooms'] = (int) $dataForApi['bathrooms'];
        }
        if (isset($dataForApi['capacity'])) {
            $dataForApi['capacity'] = (int) $dataForApi['capacity'];
        }
        
        // Envoi du propriétaire quand il est renseigné (admin crée pour un propriétaire).
        // On n'envoie que proprietaireId à l'API NestJS (pas ownerId) pour respecter le DTO strict.
        $proprietaireIdToSend = $dataForApi['proprietaireId'] ?? $dataForApi['ownerId'] ?? $data['proprietaireId'] ?? $data['ownerId'] ?? null;
        $proprietaireIdToSend = trim((string) $proprietaireIdToSend);
        if ($proprietaireIdToSend !== '') {
            $dataForApi['proprietaireId'] = $proprietaireIdToSend;
        } else {
            unset($dataForApi['proprietaireId']);
        }
        unset($dataForApi['ownerId']);
        unset($dataForApi['owner_id']);
        unset($dataForApi['proprietaire_id']);
        
        // Log pour déboguer les données envoyées
        Log::debug('Données mappées pour création résidence (format NestJS DTO - tableaux natifs)', [
            'data_keys' => array_keys($dataForApi),
            'has_images' => isset($dataForApi['images']),
            'images_type' => isset($dataForApi['images']) ? gettype($dataForApi['images']) : null,
            'images_is_array' => isset($dataForApi['images']) && is_array($dataForApi['images']),
            'images_count' => isset($dataForApi['images']) && is_array($dataForApi['images']) ? count($dataForApi['images']) : 0,
            'has_amenities' => isset($dataForApi['amenities']),
            'amenities_type' => isset($dataForApi['amenities']) ? gettype($dataForApi['amenities']) : null,
            'amenities_is_array' => isset($dataForApi['amenities']) && is_array($dataForApi['amenities']),
            'amenities_count' => isset($dataForApi['amenities']) && is_array($dataForApi['amenities']) ? count($dataForApi['amenities']) : 0,
            'has_pricePerDay' => isset($dataForApi['pricePerDay']),
            'pricePerDay_value' => $dataForApi['pricePerDay'] ?? null,
            'has_pricePerNight' => isset($dataForApi['pricePerNight']),
            'proprietaireId' => $dataForApi['proprietaireId'] ?? null,
            'proprietaireId_type' => isset($dataForApi['proprietaireId']) ? gettype($dataForApi['proprietaireId']) : null,
            'note' => 'images et amenities envoyés en tableaux natifs (NestJS DTO), proprietaireId seul (pas ownerId)',
        ]);
        
        // Ajouter la localisation si les coordonnées sont fournies
        if (isset($data['latitude']) && isset($data['longitude'])) {
            $dataForApi['localisation'] = [
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
            ];
        }
        
        $result = $this->post('residences', $dataForApi);
        
        Log::info('Résidence créée avec succès', [
            'residenceId' => $result[0]['id'] ?? null,
        ]);
        
        return $result;
    }

    /**
     * Mettre à jour une résidence
     */
    public function update(string $id, array $data): array
    {
        Log::info('🔵 ResidenceService::update appelé', [
            'residence_id' => $id,
            'data_keys' => array_keys($data),
        ]);
        
        // 1. Préparation des données pour Prisma @db.LongText
        // Décoder les chaînes JSON si nécessaire
        $rawImages = $data['images'] ?? [];
        $rawAmenities = $data['amenities'] ?? $data['commodites'] ?? [];
        
        // S'assurer que ce sont des tableaux
        if (is_string($rawImages)) {
            $decoded = json_decode($rawImages, true);
            $rawImages = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        }
        if (!is_array($rawImages)) {
            $rawImages = [];
        }
        
        if (is_string($rawAmenities)) {
            $decoded = json_decode($rawAmenities, true);
            $rawAmenities = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
        }
        if (!is_array($rawAmenities)) {
            $rawAmenities = [];
        }
        
        // Normaliser les images (remplacer URLs locales par URLs publiques)
        $normalizedImages = $this->normalizeImages($rawImages);
        
        // Nettoyer les amenities
        $cleanedAmenities = array_values(array_filter($rawAmenities, function($amenity) {
            return !empty(trim($amenity));
        }));
        
        // Passer les images et amenities normalisées au mapper
        $data['images'] = $normalizedImages;
        $data['amenities'] = $cleanedAmenities;
        
        // Mapper les données françaises vers le format anglais attendu par l'API NestJS
        $dataForApi = ResidenceMapper::toApi($data);
        
        // ⚠️ IMPORTANT : Ne JAMAIS envoyer ownerId ou proprietaireId dans le payload de mise à jour
        // L'API NestJS vérifie que le token JWT correspond au propriétaire du véhicule
        // Si on envoie ownerId, l'API peut rejeter la requête avec 403
        // La solution : ne pas modifier le propriétaire lors de l'update, l'API le garde tel quel
        unset($dataForApi['ownerId']);
        unset($dataForApi['proprietaireId']);
        unset($dataForApi['owner_id']);
        unset($dataForApi['proprietaire_id']);
        
        // Ajouter la localisation si les coordonnées sont fournies
        if (isset($data['latitude']) && isset($data['longitude'])) {
            $dataForApi['location'] = [
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
            ];
        }
        
        // Ajouter blockedDates si fourni
        if (isset($data['blockedDates'])) {
            $dataForApi['blockedDates'] = $data['blockedDates'];
        }
        
        // 2. S'assurer que images et amenities sont des tableaux natifs (NestJS DTO attend des arrays)
        // Prisma se chargera de convertir en JSON string lors de l'enregistrement (@db.LongText)
        if (!isset($dataForApi['images']) || !is_array($dataForApi['images'])) {
            $dataForApi['images'] = [];
        }
        
        if (!isset($dataForApi['amenities']) || !is_array($dataForApi['amenities'])) {
            $dataForApi['amenities'] = [];
        }
        
        // 3. S'assurer que les types sont corrects pour Prisma
        // SUPPRIMER pricePerNight (Prisma ne l'accepte pas)
        unset($dataForApi['pricePerNight']);
        
        // Garder uniquement pricePerDay (Float)
        if (isset($dataForApi['pricePerDay'])) {
            $dataForApi['pricePerDay'] = (float) $dataForApi['pricePerDay'];
        }
        
        if (isset($dataForApi['bedrooms'])) {
            $dataForApi['bedrooms'] = (int) $dataForApi['bedrooms'];
        }
        if (isset($dataForApi['bathrooms'])) {
            $dataForApi['bathrooms'] = (int) $dataForApi['bathrooms'];
        }
        if (isset($dataForApi['capacity'])) {
            $dataForApi['capacity'] = (int) $dataForApi['capacity'];
        }
        
        // Log pour déboguer les données envoyées
        Log::debug('Données mappées pour mise à jour résidence (format NestJS DTO - tableaux natifs)', [
            'residence_id' => $id,
            'data_keys' => array_keys($dataForApi),
            'has_images' => isset($dataForApi['images']),
            'images_type' => isset($dataForApi['images']) ? gettype($dataForApi['images']) : null,
            'images_is_array' => isset($dataForApi['images']) && is_array($dataForApi['images']),
            'images_count' => isset($dataForApi['images']) && is_array($dataForApi['images']) ? count($dataForApi['images']) : 0,
            'has_amenities' => isset($dataForApi['amenities']),
            'amenities_type' => isset($dataForApi['amenities']) ? gettype($dataForApi['amenities']) : null,
            'amenities_is_array' => isset($dataForApi['amenities']) && is_array($dataForApi['amenities']),
            'amenities_count' => isset($dataForApi['amenities']) && is_array($dataForApi['amenities']) ? count($dataForApi['amenities']) : 0,
            'has_pricePerDay' => isset($dataForApi['pricePerDay']),
            'pricePerDay_value' => $dataForApi['pricePerDay'] ?? null,
            'has_pricePerNight' => isset($dataForApi['pricePerNight']),
            'note' => 'images et amenities envoyés en tableaux natifs (NestJS DTO), Prisma convertira en JSON string',
        ]);
        
        Log::info('Mise à jour résidence - données envoyées à l\'API', [
            'residence_id' => $id,
            'data_keys' => array_keys($dataForApi),
            'has_ownerId' => false,
            'note' => 'ownerId non envoyé (conservation du propriétaire actuel)',
        ]);
        
        return $this->patch("residences/{$id}", $dataForApi);
    }

    /**
     * Supprimer une résidence
     */
    public function delete(string $id): bool
    {
        return parent::delete("residences/{$id}");
    }

    /**
     * Récupérer les résidences pour un utilisateur (filtrées selon le rôle)
     * 
     * @param Authenticatable $user L'utilisateur connecté
     * @param array $filters Filtres additionnels
     * @return array
     */
    public function getResidencesForUser(Authenticatable $user, array $filters = []): array
    {
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role ?? 'owner') === 'admin';
        
        if ($isAdmin) {
            // Admin voit tout
            return $this->allMapped($filters);
        }

        // Propriétaire : filtrer par proprietaireId
        // L'API NestJS accepte proprietaireId dans les filtres
        $ownerFilters = array_merge($filters, [
            'proprietaireId' => $user->getAuthIdentifier(),
        ]);

        return $this->allMapped($ownerFilters);
    }

    /**
     * Récupérer une résidence pour un utilisateur (avec vérification de propriété)
     * 
     * @param string $id ID de la résidence
     * @param Authenticatable $user L'utilisateur connecté
     * @return array|null
     */
    public function findForUser(string $id, Authenticatable $user): ?array
    {
        Log::info('ResidenceService::findForUser appelé', [
            'residence_id' => $id,
            'user_id' => $user->getAuthIdentifier(),
            'user_role' => $user->role ?? 'unknown',
        ]);

        // Si propriétaire, vérifier d'abord dans la liste filtrée
        $isOwner = method_exists($user, 'isOwner') ? $user->isOwner() : ($user->role ?? 'owner') === 'owner';
        
        if ($isOwner) {
            // Récupérer la liste filtrée des résidences de l'utilisateur
            $userResidences = $this->getResidencesForUser($user);
            $residenceInList = collect($userResidences)->firstWhere('id', $id);
            
            if ($residenceInList) {
                // Si la résidence est dans la liste filtrée, elle appartient à l'utilisateur
                // Récupérer les détails complets
                $residence = $this->find($id);
                if ($residence) {
                    Log::info('Résidence trouvée dans la liste filtrée, propriété confirmée', [
                        'residence_id' => $id,
                        'user_id' => $user->getAuthIdentifier(),
                    ]);
                    return $residence;
                }
            } else {
                Log::warning('Résidence non trouvée dans la liste filtrée', [
                    'residence_id' => $id,
                    'user_id' => $user->getAuthIdentifier(),
                    'user_residences_count' => count($userResidences),
                ]);
            }
        }

        $residence = $this->find($id);

        if (!$residence) {
            Log::warning('Résidence non trouvée dans findForUser', [
                'residence_id' => $id,
            ]);
            return null;
        }

        Log::info('Résidence trouvée, vérification propriétaire', [
            'residence_id' => $id,
            'residence_keys' => array_keys($residence),
            'proprietaireId' => $residence['proprietaireId'] ?? 'not_set',
            'ownerId' => $residence['ownerId'] ?? 'not_set',
        ]);

        // Si propriétaire et pas trouvée dans la liste, vérifier par proprietaireId
        if ($isOwner) {
            $ownerId = $residence['proprietaireId'] 
                ?? $residence['ownerId'] 
                ?? ($residence['proprietaire']['id'] ?? null)
                ?? ($residence['owner']['id'] ?? null);

            $userId = (string) $user->getAuthIdentifier();

            Log::info('Vérification propriétaire', [
                'residence_id' => $id,
                'ownerId_from_residence' => $ownerId,
                'user_id' => $userId,
                'match' => $ownerId === $userId,
                'ownerId_type' => gettype($ownerId),
                'userId_type' => gettype($userId),
            ]);

            if ($ownerId !== $userId) {
                Log::warning('Résidence n\'appartient pas au propriétaire', [
                    'residence_id' => $id,
                    'ownerId_from_residence' => $ownerId,
                    'user_id' => $userId,
                ]);
                return null; // Ne pas retourner la résidence si elle n'appartient pas au propriétaire
            }
        }

        return $residence;
    }

    /**
     * Normaliser les images : remplacer les URLs locales par l'URL publique
     * NE PAS envoyer de Base64 (trop lourd pour l'API)
     * Retourne un tableau d'URLs propres et accessibles
     */
    protected function normalizeImages(array $images): array
    {
        $publicBaseUrl = config('services.dodovroum.api_url', 'https://dodovroum.com/api');
        // Extraire le domaine de base (sans /api)
        $publicBaseUrl = str_replace('/api', '', $publicBaseUrl);
        
        $normalized = array_map(function($image) use ($publicBaseUrl) {
            // SÉCURITÉ : Si c'est du Base64, on ne l'envoie PAS
            if (str_contains($image, 'data:image')) {
                Log::error("Tentative d'envoi de Base64 détectée et bloquée", [
                    'image_preview' => substr($image, 0, 100) . '...',
                ]);
                return null; 
            }
            
            // Si c'est déjà une URL valide, on nettoie juste l'hôte local
            if (filter_var($image, FILTER_VALIDATE_URL)) {
                // Remplacer les URLs locales par l'URL publique
                $cleaned = str_replace(
                    ['http://127.0.0.1:8000', 'http://localhost:8000', 'https://127.0.0.1:8000', 'https://localhost:8000'],
                    $publicBaseUrl,
                    $image
                );
                
                // S'assurer que l'URL est complète et accessible
                // Si l'URL contient /storage/, s'assurer qu'elle pointe vers le bon domaine
                if (str_contains($cleaned, '/storage/')) {
                    return $cleaned;
                }
                
                // Si c'est déjà une URL publique complète, la retourner telle quelle
                if (str_starts_with($cleaned, 'http://') || str_starts_with($cleaned, 'https://')) {
                    return $cleaned;
                }
                
                return $cleaned;
            }
            
            // Si ce n'est pas une URL valide, retourner null pour filtrer
            Log::warning('URL d\'image invalide', [
                'image' => substr($image, 0, 100),
            ]);
            
            return null;
        }, array_filter($images, function($img) {
            // Filtrer les éléments vides
            return !empty(trim($img));
        }));
        
        // Filtrer les valeurs null et retourner un tableau propre
        return array_values(array_filter($normalized, function($img) {
            return $img !== null && !empty(trim($img));
        }));
    }
    
    /**
     * Convertir une URL d'image locale en URL publique accessible depuis l'API externe
     * REMPLACÉ : On ne convertit plus en Base64, on remplace juste l'URL locale par l'URL publique
     */
    protected function convertLocalImageUrlToPublic(string $imageUrl): string
    {
        // SÉCURITÉ : Si c'est du Base64, on ne l'envoie PAS
        if (str_contains($imageUrl, 'data:image')) {
            Log::error("Tentative d'envoi de Base64 détectée et bloquée dans convertLocalImageUrlToPublic");
            return "";
        }
        
        // Si l'URL est déjà publique (https:// avec un domaine externe), on la retourne telle quelle
        if (preg_match('/^https?:\/\/(?!127\.0\.0\.1|localhost)/', $imageUrl)) {
            return $imageUrl;
        }
        
        // Si c'est une URL locale, remplacer par l'URL publique
        $publicBaseUrl = config('services.dodovroum.api_url', 'https://dodovroum.com/api');
        // Extraire le domaine de base (sans /api)
        $publicBaseUrl = str_replace('/api', '', $publicBaseUrl);
        
        // Remplacer les URLs locales par l'URL publique
        $cleaned = str_replace(
            ['http://127.0.0.1:8000', 'http://localhost:8000', 'https://127.0.0.1:8000', 'https://localhost:8000'],
            $publicBaseUrl,
            $imageUrl
        );
        
        return $cleaned;
    }
}

