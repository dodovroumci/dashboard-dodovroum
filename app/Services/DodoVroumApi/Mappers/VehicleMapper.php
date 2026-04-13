<?php

namespace App\Services\DodoVroumApi\Mappers;

class VehicleMapper
{
    /**
     * Mapper les données d'un véhicule depuis l'API vers le format frontend
     */
    public static function fromApi(array $vehicle): array
    {
        // Inférer le type de véhicule
        $type = self::inferVehicleType($vehicle);
        
        // Déterminer le statut
        $status = self::resolveVehicleStatus($vehicle);
        
        // Construire le nom du véhicule (avec fallback garanti)
        $name = self::buildVehicleName($vehicle);
        
        return [
            'id' => $vehicle['id'] ?? null,
            'name' => $name,
            'brand' => $vehicle['marque'] ?? $vehicle['brand'] ?? null,
            'marque' => $vehicle['marque'] ?? $vehicle['brand'] ?? null,
            'model' => $vehicle['modele'] ?? $vehicle['model'] ?? null,
            'modele' => $vehicle['modele'] ?? $vehicle['model'] ?? null,
            'year' => $vehicle['annee'] ?? $vehicle['year'] ?? null,
            'annee' => $vehicle['annee'] ?? $vehicle['year'] ?? null,
            'type' => $type,
            'typeVehicule' => $type,
            'seats' => $vehicle['places'] ?? $vehicle['seats'] ?? $vehicle['capacity'] ?? 0,
            'places' => $vehicle['places'] ?? $vehicle['seats'] ?? $vehicle['capacity'] ?? 0,
            'plateNumber' => $vehicle['plateNumber'] ?? $vehicle['plate_number'] ?? $vehicle['plaque'] ?? $vehicle['plaqueImmatriculation'] ?? 'N/A',
            'plate_number' => $vehicle['plateNumber'] ?? $vehicle['plate_number'] ?? $vehicle['plaque'] ?? $vehicle['plaqueImmatriculation'] ?? 'N/A',
            'pricePerDay' => $vehicle['prixParJour'] ?? $vehicle['pricePerDay'] ?? $vehicle['price_per_day'] ?? $vehicle['price'] ?? 0,
            'price_per_day' => $vehicle['prixParJour'] ?? $vehicle['pricePerDay'] ?? $vehicle['price_per_day'] ?? $vehicle['price'] ?? 0,
            'price' => $vehicle['prixParJour'] ?? $vehicle['pricePerDay'] ?? $vehicle['price_per_day'] ?? $vehicle['price'] ?? 0,
            'color' => $vehicle['couleur'] ?? $vehicle['color'] ?? null,
            'couleur' => $vehicle['couleur'] ?? $vehicle['color'] ?? null,
            'transmission' => $vehicle['transmission'] ?? null,
            // L'API NestJS expose surtout fuelType ; carburant/fuel sont des alias historiques
            'fuel' => $vehicle['carburant']
                ?? $vehicle['fuel']
                ?? $vehicle['fuelType']
                ?? $vehicle['fuel_type']
                ?? null,
            'carburant' => $vehicle['carburant']
                ?? $vehicle['fuel']
                ?? $vehicle['fuelType']
                ?? $vehicle['fuel_type']
                ?? null,
            'mileage' => $vehicle['kilometrage'] ?? $vehicle['mileage'] ?? null,
            'kilometrage' => $vehicle['kilometrage'] ?? $vehicle['mileage'] ?? null,
            'description' => $vehicle['description'] ?? null,
            'images' => self::normalizeImages($vehicle),
            'features' => $vehicle['commodites'] ?? $vehicle['features'] ?? [],
            'commodites' => $vehicle['commodites'] ?? $vehicle['features'] ?? [],
            'isAvailable' => $vehicle['isAvailable'] ?? $vehicle['available'] ?? $vehicle['disponible'] ?? true,
            'available' => $vehicle['isAvailable'] ?? $vehicle['available'] ?? $vehicle['disponible'] ?? true,
            'disponible' => $vehicle['isAvailable'] ?? $vehicle['available'] ?? $vehicle['disponible'] ?? true,
            'status' => $status,
            'owner' => $vehicle['proprietaire'] ?? $vehicle['owner'] ?? $vehicle['user'] ?? null,
            'proprietaire' => $vehicle['proprietaire'] ?? $vehicle['owner'] ?? $vehicle['user'] ?? null,
            'proprietaireId' => self::extractOwnerId($vehicle),
        ];
    }

    /**
     * Inférer le type de véhicule depuis les données de l'API
     */
    protected static function inferVehicleType(array $vehicle): string
    {
        // Vérifier d'abord le champ typeVehicule ou type
        $type = strtolower(trim($vehicle['typeVehicule'] ?? $vehicle['type'] ?? ''));
        
        if (!empty($type)) {
            // Normaliser les valeurs - aligné avec l'API NestJS
            $typeMap = [
                'berline' => 'Berline',
                'suv' => 'SUV',
                '4x4' => '4x4',
                'utilitaire' => 'Utilitaire',
                'moto' => 'Moto',
                'citadine' => 'Citadine', // ✅ Accepté directement
                'luxe' => 'Luxe', // ✅ Accepté directement
                'car' => 'Berline',
                'sedan' => 'Berline',
            ];
            
            return $typeMap[$type] ?? ucfirst($type);
        }
        
        // Si pas de type explicite, essayer de l'inférer depuis d'autres champs
        // (par exemple, le nombre de places, la marque, etc.)
        $seats = $vehicle['places'] ?? $vehicle['seats'] ?? 0;
        
        if ($seats >= 7) {
            return 'SUV';
        } elseif ($seats <= 2) {
            return 'Moto';
        }
        
        return 'Berline'; // Par défaut
    }

    /**
     * Résoudre le statut du véhicule
     */
    protected static function resolveVehicleStatus(array $vehicle): string
    {
        $apiStatus = strtolower(trim($vehicle['status'] ?? ''));
        $isAvailable = $vehicle['isAvailable'] ?? $vehicle['available'] ?? $vehicle['disponible'] ?? null;
        
        if (!empty($apiStatus)) {
            return $apiStatus;
        }
        
        if ($isAvailable !== null) {
            return $isAvailable ? 'available' : 'unavailable';
        }
        
        return 'available'; // Par défaut
    }

    /**
     * Extraire l'ID du propriétaire depuis différentes structures possibles
     */
    protected static function extractOwnerId(array $vehicle): ?string
    {
        // Essayer d'abord les champs directs
        if (isset($vehicle['proprietaireId']) && !empty($vehicle['proprietaireId'])) {
            return (string) $vehicle['proprietaireId'];
        }
        if (isset($vehicle['ownerId']) && !empty($vehicle['ownerId'])) {
            return (string) $vehicle['ownerId'];
        }
        if (isset($vehicle['userId']) && !empty($vehicle['userId'])) {
            return (string) $vehicle['userId'];
        }

        // Essayer depuis l'objet proprietaire
        $proprietaire = $vehicle['proprietaire'] ?? $vehicle['owner'] ?? $vehicle['user'] ?? null;
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
     * Construire le nom du véhicule avec fallback garanti
     * Priorité : title > name > brand + model (reconstruction pour garantir la fraîcheur)
     * 
     * Cette méthode priorise la reconstruction depuis brand + model pour éviter
     * d'utiliser des données obsolètes stockées dans les offres combinées.
     */
    protected static function buildVehicleName(array $vehicle): string
    {
        // Priorité 1 : Titre explicite de l'API (le plus fiable)
        if (!empty($vehicle['title'])) {
            $title = trim($vehicle['title']);
            \Log::debug('VehicleMapper::buildVehicleName - Title trouvé dans l\'API', [
                'title' => $title,
                'vehicle_id' => $vehicle['id'] ?? null,
            ]);
            return $title;
        }
        
        // Priorité 2 : Name explicite
        if (!empty($vehicle['name'])) {
            return trim($vehicle['name']);
        }
        
        // Priorité 3 : Reconstruction depuis brand + model pour garantir la fraîcheur
        // Cette approche évite d'utiliser des données obsolètes stockées dans les offres combinées
        $brand = $vehicle['brand'] ?? $vehicle['marque'] ?? '';
        $model = $vehicle['model'] ?? $vehicle['modele'] ?? '';
        
        if (!empty($brand) || !empty($model)) {
            $computedName = trim("$brand $model");
            if (!empty($computedName)) {
                \Log::debug('VehicleMapper::buildVehicleName - Nom reconstruit depuis brand + model', [
                    'computed_name' => $computedName,
                    'brand' => $brand,
                    'model' => $model,
                    'vehicle_id' => $vehicle['id'] ?? null,
                ]);
                return $computedName;
            }
        }
        
        // Fallback : chercher titre, nom (pour compatibilité)
        if (!empty($vehicle['titre'])) {
            return trim($vehicle['titre']);
        }
        if (!empty($vehicle['nom'])) {
            return trim($vehicle['nom']);
        }
        
        // Fallback final : toujours retourner quelque chose
        return 'Véhicule sans nom';
    }

    /**
     * Normaliser les images d'un véhicule
     * Gère les tableaux, les chaînes JSON stringifiées et les URLs uniques
     */
    protected static function normalizeImages(array $vehicle): array
    {
        $images = $vehicle['images'] ?? $vehicle['imageUrl'] ?? $vehicle['image_url'] ?? $vehicle['image'] ?? null;
        
        // Log pour déboguer
        \Log::debug('VehicleMapper::normalizeImages - Données reçues', [
            'has_images' => isset($vehicle['images']),
            'images_type' => isset($images) ? gettype($images) : 'not_set',
            'images_value_preview' => is_string($images) && strlen($images) > 100 
                ? substr($images, 0, 100) . '...' 
                : $images,
            'images_count' => isset($images) && is_array($images) ? count($images) : 0,
        ]);
        
        // 1. Si c'est vide
        if (empty($images)) {
            \Log::debug('VehicleMapper::normalizeImages - Images vides');
            return [];
        }
        
        // 2. Si c'est une chaîne JSON (Cas détecté dans les logs : API NestJS renvoie du JSON stringifié)
        if (is_string($images) && (str_starts_with($images, '[') || str_starts_with($images, '{'))) {
            try {
                $decoded = json_decode($images, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    \Log::debug('VehicleMapper::normalizeImages - JSON décodé avec succès', [
                        'decoded_count' => count($decoded),
                    ]);
                    $images = $decoded;
                } else {
                    \Log::warning('VehicleMapper::normalizeImages - Erreur de décodage JSON', [
                        'json_error' => json_last_error_msg(),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::warning('VehicleMapper::normalizeImages - Exception lors du décodage JSON', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // 3. Force en tableau si c'est une seule URL string
        if (is_string($images) && !empty($images)) {
            $validated = self::validateUrl($images);
            if (!empty($validated)) {
                \Log::debug('VehicleMapper::normalizeImages - URL unique détectée', [
                    'url' => $validated,
                ]);
                return [$validated];
            }
            return [];
        }
        
        if (!is_array($images)) {
            \Log::warning('VehicleMapper::normalizeImages - Format non supporté', [
                'type' => gettype($images),
            ]);
            return [];
        }
        
        // 4. Mapping et nettoyage des URLs
        $normalized = [];
        foreach ($images as $img) {
            $url = null;
            
            if (is_string($img) && !empty($img)) {
                $url = $img;
            } elseif (is_array($img)) {
                $url = $img['url'] ?? $img['src'] ?? $img['path'] ?? $img['link'] ?? null;
            }
            
            if ($url) {
                $validated = self::validateUrl($url);
                if (!empty($validated)) {
                    $normalized[] = $validated;
                }
            }
        }
        
        $result = array_values(array_filter($normalized));
        
        \Log::debug('VehicleMapper::normalizeImages - Images normalisées', [
            'images_count' => count($result),
            'images_preview' => array_slice($result, 0, 3),
        ]);
        
        return $result;
    }
    
    /**
     * Assure que l'URL est propre
     * Nettoie les backslashes d'échappement JSON si présents
     */
    private static function validateUrl(string $url): string
    {
        // Nettoyage des backslashes d'échappement JSON si présents
        $url = stripslashes($url);
        return trim($url);
    }

    /**
     * Mapper les données pour l'API (format anglais - l'API attend des champs en anglais)
     */
    public static function toApi(array $data): array
    {
        // Normaliser le type de véhicule - l'API attend des types en MAJUSCULES
        $type = $data['typeVehicule'] ?? $data['type'] ?? null;
        if ($type) {
            // Mapper les types français vers les types API (en majuscules)
            // Aligné avec l'API NestJS
            $typeMap = [
                'berline' => 'CAR',
                'suv' => 'SUV',
                '4x4' => 'SUV', // 4x4 est généralement un SUV
                'utilitaire' => 'VAN',
                'moto' => 'MOTORCYCLE',
                'citadine' => 'CAR', // Citadine = voiture de ville (CAR)
                'luxe' => 'CAR', // Luxe = voiture haut de gamme (CAR)
                'car' => 'CAR',
                'sedan' => 'CAR',
                'motorcycle' => 'MOTORCYCLE',
                'van' => 'VAN',
                'truck' => 'TRUCK',
            ];
            $type = strtolower(trim($type));
            $type = $typeMap[$type] ?? strtoupper($type);
        }
        
        // Normaliser la transmission (l'API attend manual/automatic)
        $transmission = $data['transmission'] ?? null;
        if ($transmission) {
            $transmissionMap = [
                'manuel' => 'manual',
                'automatique' => 'automatic',
                'manual' => 'manual',
                'automatic' => 'automatic',
            ];
            $transmission = strtolower(trim($transmission));
            $transmission = $transmissionMap[$transmission] ?? $transmission;
        }
        
        // Normaliser le carburant (l'API attend petrol/diesel/electric/hybrid)
        $fuel = $data['fuel'] ?? $data['carburant'] ?? null;
        if ($fuel) {
            $fuelMap = [
                'essence' => 'petrol',
                'gasoline' => 'petrol',
                'petrol' => 'petrol',
                'diesel' => 'diesel',
                'electrique' => 'electric',
                'electric' => 'electric',
                'hybride' => 'hybrid',
                'hybrid' => 'hybrid',
            ];
            $fuel = strtolower(trim($fuel));
            $fuel = $fuelMap[$fuel] ?? $fuel;
        }
        
        // L'API NestJS attend des champs en anglais selon le schéma Prisma
        // IMPORTANT : Pas de champ 'title', utiliser 'brand' et 'model' séparés
        // IMPORTANT : Utiliser 'fuelType' (pas 'fuel') et 'seats' (pas 'capacity')
        // 🛡️ MAPPING STRICT : Cast rigoureux pour satisfaire CreateVehicleDto / UpdateVehicleDto
        
        // Helper pour convertir en booléen strict (gère "1", "on", "true", etc.)
        $toBoolean = function($value) {
            if (is_bool($value)) {
                return $value;
            }
            if (is_string($value)) {
                $value = strtolower(trim($value));
                return in_array($value, ['1', 'true', 'on', 'yes', 'oui']);
            }
            if (is_numeric($value)) {
                return (int)$value === 1;
            }
            return (bool)$value;
        };
        
        // L'API NestJS n'accepte pas le champ 'title' (CreateVehicleDto) : utiliser uniquement brand + model
        $mapped = [
            'brand' => (string)($data['brand'] ?? $data['marque'] ?? ''),
            'model' => (string)($data['model'] ?? $data['modele'] ?? ''),
            'year' => isset($data['year']) || isset($data['annee']) 
                ? (int)($data['year'] ?? $data['annee'] ?? date('Y'))
                : (int)date('Y'),
            'type' => $type ?? 'CAR', // CAR, SUV, MOTORCYCLE, VAN, etc. (MAJUSCULES)
            // ⚠️ IMPORTANT : L'API NestJS attend 'seats' dans le DTO de validation, même si Prisma utilise 'capacity'
            // Le DTO de validation rejette 'capacity' et exige 'seats'
            'seats' => isset($data['seats']) || isset($data['places']) || isset($data['capacity'])
                ? (int)($data['seats'] ?? $data['places'] ?? $data['capacity'] ?? 5)
                : 5,
            'licensePlate' => (string)($data['plateNumber'] ?? $data['plate_number'] ?? $data['plaqueImmatriculation'] ?? $data['licensePlate'] ?? ''),
            'pricePerDay' => isset($data['pricePerDay']) || isset($data['price_per_day']) || isset($data['prixParJour']) || isset($data['price'])
                ? (float)($data['pricePerDay'] ?? $data['price_per_day'] ?? $data['prixParJour'] ?? $data['price'] ?? 0.0)
                : 0.0,
            'color' => isset($data['color']) || isset($data['couleur'])
                ? (string)($data['color'] ?? $data['couleur'] ?? 'N/A')
                : null,
            'transmission' => $transmission ?? null, // manual ou automatic
            // ⚠️ IMPORTANT : Ne pas envoyer fuelType si null ou vide (l'API NestJS exige une chaîne non vide)
            // On l'ajoutera seulement si une valeur valide est fournie
            'fuelType' => !empty($fuel) ? (string)$fuel : null, // NestJS attend 'fuelType' (pas 'fuel') : petrol, diesel, electric, hybrid
            'mileage' => isset($data['mileage']) || isset($data['kilometrage'])
                ? (int)($data['mileage'] ?? $data['kilometrage'] ?? 0)
                : 0,
            'description' => (function() use ($data) {
                if (!isset($data['description']) || !is_string($data['description'])) {
                    return $data['description'] ?? null;
                }
                $desc = $data['description'];
                if (mb_strlen($desc) > 500) {
                    \Illuminate\Support\Facades\Log::warning('Description trop longue dans VehicleMapper::toApi, troncature', [
                        'original_length' => mb_strlen($desc),
                    ]);
                    return mb_substr($desc, 0, 500);
                }
                return $desc;
            })(),
            'images' => is_array($data['images'] ?? null) ? $data['images'] : [],
            'features' => is_array($data['features'] ?? $data['commodites'] ?? null) 
                ? ($data['features'] ?? $data['commodites'])
                : [],
            // 🛡️ Cast booléen strict pour isActive/isVerified
            'isActive' => $toBoolean($data['isActive'] ?? $data['is_active'] ?? $data['isVerified'] ?? $data['is_verified'] ?? true),
        ];
        
        // Supprimer les champs obsolètes qui ne doivent pas être envoyés à l'API
        // (places est mappé vers seats, fuel est mappé vers fuelType)
        // ⚠️ NE PAS supprimer 'seats' car l'API NestJS l'attend dans le DTO de validation
        unset($mapped['places']);
        unset($mapped['capacity']); // capacity n'est pas attendu par le DTO, seulement seats
        unset($mapped['fuel']); // fuel est mappé vers fuelType
        unset($mapped['title']); // L'API rejette "property title should not exist"

        // ⚠️ IMPORTANT : Supprimer fuelType s'il est null ou vide (l'API NestJS exige une chaîne non vide)
        // Si fuelType n'est pas fourni, l'API utilisera la valeur existante lors d'une mise à jour
        if (empty($mapped['fuelType'])) {
            unset($mapped['fuelType']);
        }
        
        // Note : ownerId/proprietaireId sera géré dans VehicleService::create()
        // selon si l'utilisateur est admin ou non
        
        return $mapped;
    }
}

