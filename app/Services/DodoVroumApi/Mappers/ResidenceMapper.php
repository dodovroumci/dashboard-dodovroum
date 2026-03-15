<?php

namespace App\Services\DodoVroumApi\Mappers;

use Illuminate\Support\Facades\Log;

class ResidenceMapper
{
    /**
     * Mapper les données d'une résidence depuis l'API vers le format frontend
     */
    public static function fromApi(array $residence): array
    {
        $available = $residence['available']
            ?? $residence['disponible']
            ?? $residence['isAvailable']
            ?? null;

        $isAvailable = $residence['isAvailable'] ?? $available;
        $apiStatus = strtolower(trim($residence['status'] ?? ''));
        
        // Déterminer le statut pour le frontend
        $frontendStatus = 'unavailable'; // Default
        if (!empty($apiStatus)) {
            $frontendStatus = $apiStatus;
        } elseif ($isAvailable !== null) {
            $frontendStatus = $isAvailable ? 'available' : 'occupied';
        } else {
            $isVerified = $residence['isVerified'] ?? null;
            if ($isVerified !== null) {
                $frontendStatus = $isVerified ? 'available' : 'unavailable';
            }
        }

        return [
            'id' => $residence['id'] ?? null,
            'title' => $residence['nom'] ?? $residence['title'] ?? $residence['name'] ?? 'Résidence sans nom',
            'name' => $residence['nom'] ?? $residence['name'] ?? $residence['title'] ?? 'Résidence sans nom',
            'type' => $residence['typeResidence'] ?? $residence['type'] ?? null,
            'typeResidence' => $residence['typeResidence'] ?? $residence['type'] ?? null,
            'address' => $residence['adresse'] ?? $residence['address'] ?? null,
            'adresse' => $residence['adresse'] ?? $residence['address'] ?? null,
            'city' => $residence['ville'] ?? $residence['city'] ?? null,
            'ville' => $residence['ville'] ?? $residence['city'] ?? null,
            'country' => $residence['pays'] ?? $residence['country'] ?? null,
            'pricePerNight' => $residence['prixParNuit'] ?? $residence['pricePerNight'] ?? $residence['price'] ?? 0,
            'price' => $residence['prixParNuit'] ?? $residence['pricePerNight'] ?? $residence['price'] ?? 0,
            'bedrooms' => $residence['nombreChambres'] ?? $residence['bedrooms'] ?? $residence['chambres'] ?? $residence['nbChambres'] ?? $residence['rooms'] ?? 0,
            'nombreChambres' => $residence['nombreChambres'] ?? $residence['bedrooms'] ?? $residence['chambres'] ?? $residence['nbChambres'] ?? $residence['rooms'] ?? 0,
            'bathrooms' => $residence['nombreSallesBain'] ?? $residence['bathrooms'] ?? $residence['sallesBain'] ?? $residence['nbSallesBain'] ?? 0,
            'nombreSallesBain' => $residence['nombreSallesBain'] ?? $residence['bathrooms'] ?? $residence['sallesBain'] ?? $residence['nbSallesBain'] ?? 0,
            'capacity' => $residence['capacite'] ?? $residence['capacity'] ?? $residence['personnes'] ?? $residence['nbPersonnes'] ?? $residence['guests'] ?? 0,
            'capacite' => $residence['capacite'] ?? $residence['capacity'] ?? $residence['personnes'] ?? $residence['nbPersonnes'] ?? $residence['guests'] ?? 0,
            'description' => $residence['description'] ?? null,
            'images' => self::normalizeImages($residence),
            'amenities' => self::normalizeAmenities($residence),
            'commodites' => $residence['commodites'] ?? $residence['amenities'] ?? [],
            'latitude' => $residence['localisation']['latitude'] ?? $residence['latitude'] ?? null,
            'longitude' => $residence['localisation']['longitude'] ?? $residence['longitude'] ?? null,
            'isActive' => $residence['isVerified'] ?? $residence['isActive'] ?? true,
            'isVerified' => $residence['isVerified'] ?? false,
            'isAvailable' => $isAvailable ?? ($residence['isVerified'] ?? true),
            'available' => $isAvailable ?? ($residence['isVerified'] ?? true),
            'status' => $frontendStatus,
            'notation' => $residence['notation'] ?? null,
            'owner' => $residence['proprietaire'] ?? $residence['owner'] ?? $residence['user'] ?? null,
            'proprietaire' => $residence['proprietaire'] ?? $residence['owner'] ?? $residence['user'] ?? null,
            'proprietaireId' => self::extractOwnerId($residence),
        ];
    }

    /**
     * Extraire l'ID du propriétaire depuis différentes structures possibles
     */
    protected static function extractOwnerId(array $residence): ?string
    {
        // Essayer d'abord les champs directs
        if (isset($residence['proprietaireId']) && !empty($residence['proprietaireId'])) {
            return (string) $residence['proprietaireId'];
        }
        if (isset($residence['ownerId']) && !empty($residence['ownerId'])) {
            return (string) $residence['ownerId'];
        }
        if (isset($residence['userId']) && !empty($residence['userId'])) {
            return (string) $residence['userId'];
        }

        // Essayer depuis l'objet proprietaire
        $proprietaire = $residence['proprietaire'] ?? $residence['owner'] ?? $residence['user'] ?? null;
        if ($proprietaire && is_array($proprietaire)) {
            if (isset($proprietaire['id']) && !empty($proprietaire['id'])) {
                return (string) $proprietaire['id'];
            }
            if (isset($proprietaire['_id']) && !empty($proprietaire['_id'])) {
                return (string) $proprietaire['_id'];
            }
        }

        return null;
    }

    /**
     * Normaliser les images d'une résidence depuis les données de l'API
     * Version Senior - Aligné sur l'API NestJS centralisée
     * Gère les tableaux natifs (format NestJS) et les chaînes JSON (fallback Prisma @db.LongText)
     */
    protected static function normalizeImages(array $residence): array
    {
        $images = $residence['images'] ?? [];

        // 1. Si NestJS nous envoie déjà un tableau propre (format actuel après nos corrections)
        if (is_array($images) && !empty($images)) {
            $normalized = array_values(array_filter(array_map(function($img) {
                if (is_string($img) && !empty(trim($img))) {
                    return self::ensureFullUrl($img);
                }
                if (is_array($img)) {
                    // Gérer les objets avec 'url' ou 'src'
                    $url = $img['url'] ?? $img['src'] ?? null;
                    if ($url && is_string($url) && !empty(trim($url))) {
                        return self::ensureFullUrl($url);
                    }
                }
                return null;
            }, $images), function($img) {
                return $img !== null && !empty(trim($img));
            }));

            return $normalized;
        }
        
        // 2. Fallback : Si c'est encore une string JSON de Prisma (ancien format)
        if (is_string($images) && !empty($images)) {
            $decoded = json_decode($images, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map(function($img) {
                    return is_string($img) && !empty(trim($img)) ? self::ensureFullUrl($img) : null;
                }, $decoded), function($img) {
                    return $img !== null;
                }));
            }
            // Si c'est une string simple (nom de fichier ou URL)
            return [self::ensureFullUrl($images)];
        }
        
        // 3. Fallback : Autres formats possibles (imageUrl, image_url, etc.)
        if (isset($residence['imageUrl']) && !empty($residence['imageUrl'])) {
            $img = is_array($residence['imageUrl']) ? $residence['imageUrl'][0] : $residence['imageUrl'];
            return [self::ensureFullUrl($img)];
        }
        if (isset($residence['image_url']) && !empty($residence['image_url'])) {
            $img = is_array($residence['image_url']) ? $residence['image_url'][0] : $residence['image_url'];
            return [self::ensureFullUrl($img)];
        }
        if (isset($residence['imageUrls']) && is_array($residence['imageUrls']) && !empty($residence['imageUrls'])) {
            return array_values(array_filter(array_map(function($img) {
                return is_string($img) && !empty(trim($img)) ? self::ensureFullUrl($img) : null;
            }, $residence['imageUrls']), function($img) {
                return $img !== null;
            }));
        }
        if (isset($residence['image']) && !empty($residence['image'])) {
            $img = is_array($residence['image']) ? $residence['image'][0] : $residence['image'];
            return [self::ensureFullUrl($img)];
        }
        
        // 4. Image par défaut si aucune image trouvée
        return [];
    }
    
    /**
     * S'assurer que l'URL est complète et accessible
     * Convertit les chemins relatifs en URLs absolues
     */
    private static function ensureFullUrl(string $path): string
    {
        // Si c'est déjà une URL complète (http:// ou https://), la retourner telle quelle
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            // Remplacer les URLs locales par l'URL publique si nécessaire
            return str_replace(
                ['http://127.0.0.1:8000', 'http://localhost:8000', 'https://127.0.0.1:8000', 'https://localhost:8000'],
                'https://dodovroum.com',
                $path
            );
        }
        
        // Si c'est un chemin relatif, construire l'URL complète
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            return "https://dodovroum.com/{$path}";
        }
        
        // Par défaut, supposer que c'est dans storage/residences/
        return "https://dodovroum.com/storage/residences/" . $path;
    }

    /**
     * Normaliser les amenities d'une résidence depuis les données de l'API
     * Gère les chaînes JSON (Prisma @db.LongText) et les tableaux natifs
     */
    protected static function normalizeAmenities(array $residence): array
    {
        $amenities = [];
        
        // 1. Si 'amenities' est une chaîne JSON (Prisma @db.LongText)
        if (isset($residence['amenities']) && is_string($residence['amenities']) && !empty($residence['amenities'])) {
            $decoded = json_decode($residence['amenities'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $amenities = array_filter($decoded, function($amenity) {
                    return !empty(trim($amenity));
                });
            }
        }
        // 2. Si 'amenities' est déjà un tableau
        elseif (isset($residence['amenities']) && is_array($residence['amenities']) && !empty($residence['amenities'])) {
            $amenities = array_filter($residence['amenities'], function($amenity) {
                return !empty(trim($amenity));
            });
        }
        // 3. Si 'commodites' est une chaîne JSON (format français)
        elseif (isset($residence['commodites']) && is_string($residence['commodites']) && !empty($residence['commodites'])) {
            $decoded = json_decode($residence['commodites'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $amenities = array_filter($decoded, function($amenity) {
                    return !empty(trim($amenity));
                });
            }
        }
        // 4. Si 'commodites' est déjà un tableau (format français)
        elseif (isset($residence['commodites']) && is_array($residence['commodites']) && !empty($residence['commodites'])) {
            $amenities = array_filter($residence['commodites'], function($amenity) {
                return !empty(trim($amenity));
            });
        }
        
        return array_values($amenities);
    }

    /**
     * Mapper les données pour l'API NestJS (format anglais)
     * Convertit les clés françaises en clés anglaises attendues par l'API
     */
    public static function toApi(array $data): array
    {
        // Nettoyer et normaliser les images
        $images = self::cleanImageUrls($data['images'] ?? []);
        
        // Nettoyer les commodités/amenities
        $amenities = self::cleanAmenities($data['amenities'] ?? $data['commodites'] ?? []);
        
        // Tronquer la description si nécessaire
        $description = self::truncateDescription($data['description'] ?? null);
        
        // Mapper vers le format anglais attendu par l'API NestJS
        $mapped = [
            // Titre/Nom - priorité: title > nom > name
            'title' => $data['title'] ?? $data['nom'] ?? $data['name'] ?? null,
            
            // Type de résidence
            'typeResidence' => $data['typeResidence'] ?? $data['type'] ?? null,
            
            // Adresse - priorité: address > adresse
            'address' => $data['address'] ?? $data['adresse'] ?? null,
            
            // Ville - priorité: city > ville
            'city' => $data['city'] ?? $data['ville'] ?? null,
            
            // Pays - priorité: country > pays
            'country' => $data['country'] ?? $data['pays'] ?? null,
            
            // Prix par jour - OBLIGATOIRE pour Prisma (seul champ prix accepté)
            'pricePerDay' => self::extractPrice($data),
            
            // Chambres - priorité: bedrooms > nombreChambres
            'bedrooms' => $data['bedrooms'] ?? $data['nombreChambres'] ?? null,
            
            // Salles de bain - priorité: bathrooms > nombreSallesBain
            'bathrooms' => $data['bathrooms'] ?? $data['nombreSallesBain'] ?? null,
            
            // Capacité - priorité: capacity > capacite
            'capacity' => $data['capacity'] ?? $data['capacite'] ?? null,
            
            // Description (tronquée à 500 caractères)
            'description' => $description,
            
            // Images nettoyées - ENVOYER EN TABLEAU NATIF (NestJS DTO attend un array, Prisma convertira en JSON string)
            'images' => !empty($images) ? $images : [],
            
            // Commodités/Amenities nettoyées - ENVOYER EN TABLEAU NATIF (NestJS DTO attend un array, Prisma convertira en JSON string)
            'amenities' => !empty($amenities) ? $amenities : [],
            
            // Statut de vérification
            'isVerified' => $data['isVerified'] ?? $data['isActive'] ?? false,
            'isActive' => $data['isActive'] ?? $data['isVerified'] ?? false,
            
            // ID du propriétaire (uniquement proprietaireId pour l'API NestJS, pas d'alias ownerId)
            'proprietaireId' => $data['proprietaireId'] ?? $data['ownerId'] ?? null,
        ];
        
        // Filtrer les valeurs null pour ne pas les envoyer à l'API
        return array_filter($mapped, function($value) {
            return $value !== null;
        });
    }
    
    /**
     * Extraire le prix depuis différentes clés possibles
     */
    protected static function extractPrice(array $data): ?float
    {
        $price = $data['pricePerNight'] 
            ?? $data['prixParNuit'] 
            ?? $data['price'] 
            ?? $data['prix'] 
            ?? null;
        
        if ($price === null) {
            return null;
        }
        
        return (float) $price;
    }
    
    /**
     * Nettoyer et normaliser les URLs d'images
     * - Filtre les URLs vides
     * - Retourne un tableau propre d'URLs (SANS Base64)
     * - Gère les chaînes JSON stringifiées
     */
    protected static function cleanImageUrls($images): array
    {
        if (empty($images)) {
            return [];
        }
        
        // Si c'est une chaîne JSON, la décoder
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $images = $decoded;
            } else {
                // Si ce n'est pas du JSON valide, traiter comme une seule URL
                $images = [$images];
            }
        }
        
        // S'assurer que c'est un tableau
        if (!is_array($images)) {
            $images = [$images];
        }
        
        // Filtrer et nettoyer chaque URL
        $cleaned = array_filter(array_map(function($img) {
            if (empty($img) || !is_string($img)) {
                return null;
            }
            
            $img = trim($img);
            
            // Ignorer les URLs vides
            if (empty($img)) {
                return null;
            }
            
            // SÉCURITÉ : Si c'est du Base64, on ne l'envoie PAS
            if (str_contains($img, 'data:image')) {
                \Illuminate\Support\Facades\Log::error("Tentative d'envoi de Base64 détectée dans ResidenceMapper::cleanImageUrls");
                return null; // On filtre les Base64
            }
            
            // Si c'est une URL publique (https://), on la garde telle quelle
            if (preg_match('/^https?:\/\/(?!127\.0\.0\.1|localhost)/', $img)) {
                return $img;
            }
            
            // Pour les URLs locales, on les retourne telles quelles
            // Le remplacement par l'URL publique sera fait dans ResidenceService::normalizeImages()
            return $img;
        }, $images), function($img) {
            return $img !== null;
        });
        
        return array_values($cleaned);
    }
    
    /**
     * Nettoyer les commodités/amenities
     * - Gère les chaînes JSON stringifiées
     */
    protected static function cleanAmenities($amenities): array
    {
        if (empty($amenities)) {
            return [];
        }
        
        // Si c'est une chaîne JSON, la décoder
        if (is_string($amenities)) {
            $decoded = json_decode($amenities, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $amenities = $decoded;
            } else {
                // Si ce n'est pas du JSON valide, traiter comme une seule valeur
                $amenities = [$amenities];
            }
        }
        
        if (!is_array($amenities)) {
            $amenities = [$amenities];
        }
        
        // Filtrer les valeurs vides et nettoyer
        $cleaned = array_filter(array_map(function($amenity) {
            if (is_string($amenity)) {
                $amenity = trim($amenity);
                return !empty($amenity) ? $amenity : null;
            }
            return null;
        }, $amenities), function($amenity) {
            return $amenity !== null;
        });
        
        return array_values($cleaned);
    }
    
    /**
     * Tronquer la description à 500 caractères si nécessaire
     */
    protected static function truncateDescription(?string $description): ?string
    {
        if (empty($description) || !is_string($description)) {
            return null;
        }
        
        if (mb_strlen($description) > 500) {
            \Illuminate\Support\Facades\Log::warning('Description trop longue dans ResidenceMapper::toApi, troncature', [
                'original_length' => mb_strlen($description),
            ]);
            return mb_substr($description, 0, 500);
        }
        
        return $description;
    }
}

