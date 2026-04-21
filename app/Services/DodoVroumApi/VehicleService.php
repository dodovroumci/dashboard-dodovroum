<?php

namespace App\Services\DodoVroumApi;

use App\Services\DodoVroumApi\Mappers\VehicleMapper;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class VehicleService extends BaseApiService
{
    /**
     * Récupérer tous les véhicules avec pagination automatique
     */
    public function all(array $filters = []): array
    {
        $limit = 100;
        $page = 1;
        $allVehicles = [];
        $maxPages = 10; // Limite de sécurité
        
        do {
            $queryParams = array_merge($filters, [
                'limit' => $limit,
                'page' => $page,
            ]);
            
            $response = $this->get('vehicles', $queryParams);
            
            if (empty($response)) {
                break;
            }
            
            $allVehicles = array_merge($allVehicles, $response);
            
            // Si on a moins de résultats que le limit, on a atteint la fin
            if (count($response) < $limit) {
                break;
            }
            
            $page++;
        } while ($page <= $maxPages);
        
        Log::debug('Véhicules récupérés', [
            'count' => count($allVehicles),
            'filters' => $filters,
        ]);
        
        return $allVehicles;
    }

    /**
     * Récupérer un véhicule par ID
     */
    public function find(string $id): ?array
    {
        Log::debug('VehicleService::find appelé', [
            'vehicle_id' => $id,
            'endpoint' => "vehicles/{$id}",
        ]);
        
        // L'API NestJS n'a pas d'endpoint GET /api/vehicles/:id
        // On récupère tous les véhicules et on filtre par ID
        $allVehicles = $this->all([]);
        
        Log::debug('VehicleService::find - Véhicules récupérés depuis all()', [
            'vehicle_id' => $id,
            'total_vehicles' => count($allVehicles),
        ]);
        
        // Chercher le véhicule par ID (support id et _id)
        $vehicle = null;
        foreach ($allVehicles as $v) {
            $vehicleId = $v['id'] ?? $v['_id'] ?? null;
            if ($vehicleId && (string) $vehicleId === (string) $id) {
                $vehicle = $v;
                break;
            }
        }
        
        Log::debug('VehicleService::find - Recherche par ID', [
            'vehicle_id' => $id,
            'vehicle_found' => !empty($vehicle),
            'vehicle_keys' => $vehicle ? array_keys($vehicle) : null,
            'vehicle_id_in_response' => $vehicle['id'] ?? $vehicle['_id'] ?? null,
            'raw_images' => $vehicle['images'] ?? null,
            'raw_images_type' => isset($vehicle['images']) ? gettype($vehicle['images']) : 'not_set',
            'raw_images_count' => isset($vehicle['images']) && is_array($vehicle['images']) ? count($vehicle['images']) : 0,
        ]);
        
        if (!$vehicle) {
            Log::warning('VehicleService::find - Véhicule non trouvé dans la liste', [
                'vehicle_id' => $id,
                'total_vehicles_checked' => count($allVehicles),
            ]);
            return null;
        }
        
        $mappedVehicle = VehicleMapper::fromApi($vehicle);
        
        Log::debug('VehicleService::find - Véhicule mappé', [
            'vehicle_id' => $id,
            'mapped_keys' => $mappedVehicle ? array_keys($mappedVehicle) : null,
            'mapped_images' => $mappedVehicle['images'] ?? null,
            'mapped_images_count' => isset($mappedVehicle['images']) && is_array($mappedVehicle['images']) ? count($mappedVehicle['images']) : 0,
        ]);
        
        return $mappedVehicle;
    }

    /**
     * Récupérer tous les véhicules mappés pour le frontend
     */
    public function allMapped(array $filters = []): array
    {
        $vehicles = $this->all($filters);
        
        return array_map(function($vehicle) {
            return VehicleMapper::fromApi($vehicle);
        }, $vehicles);
    }

    /**
     * Créer un nouveau véhicule
     */
    public function create(array $data, bool $isAdmin = false): array
    {
        if (isset($data['description']) && is_string($data['description']) && mb_strlen($data['description']) > 500) {
            $data['description'] = mb_substr($data['description'], 0, 500);
        }

        if (!$isAdmin) {
            $authId = Auth::id();
            if ($authId) {
                $data['ownerId'] = (string) $authId;
                $data['proprietaireId'] = (string) $authId;
            }
        }

        $dataForApi = VehicleMapper::toApi($data);

        if (!$isAdmin) {
            $authId = Auth::id();
            if ($authId) {
                $dataForApi['ownerId'] = (string) $authId;
                $dataForApi['proprietaireId'] = (string) $authId;
            }
        }

        if (isset($dataForApi['description']) && is_string($dataForApi['description']) && mb_strlen($dataForApi['description']) > 500) {
            $dataForApi['description'] = mb_substr($dataForApi['description'], 0, 500);
        }

        return $this->post('vehicles', $dataForApi, false);
    }

    /**
     * Mettre à jour un véhicule
     * Utilise la même logique que create() pour garantir la cohérence
     */
    public function update(string $id, array $data): array
    {
        Log::info('🔵 VehicleService::update appelé', [
            'vehicle_id' => $id,
            'data_keys' => array_keys($data),
            'data_preview' => array_intersect_key($data, array_flip(['brand', 'model', 'type', 'year', 'seats', 'pricePerDay'])),
        ]);
        
        // Tronquer la description à 500 caractères si nécessaire (même logique que create)
        if (isset($data['description']) && is_string($data['description'])) {
            $originalLength = mb_strlen($data['description']);
            if ($originalLength > 500) {
                Log::warning('Description trop longue lors de la mise à jour véhicule, troncature appliquée', [
                    'vehicle_id' => $id,
                    'original_length' => $originalLength,
                    'truncated_length' => 500,
                ]);
                $data['description'] = mb_substr($data['description'], 0, 500);
            }
        }
        
        // Utiliser le même mapper que create() pour garantir la cohérence
        $dataForApi = VehicleMapper::toApi($data);
        
        // Double vérification après le mapping (même logique que create)
        if (isset($dataForApi['description']) && is_string($dataForApi['description']) && mb_strlen($dataForApi['description']) > 500) {
            Log::warning('Description encore trop longue après mapping véhicule (update), troncature finale', [
                'vehicle_id' => $id,
                'length' => mb_strlen($dataForApi['description']),
            ]);
            $dataForApi['description'] = mb_substr($dataForApi['description'], 0, 500);
        }
        
        // Gestion de proprietaireId/ownerId pour l'update
        // Pour éviter l'erreur 403, on doit récupérer le propriétaire actuel du véhicule
        // et l'envoyer dans la requête si c'est un admin qui modifie
        $user = Auth::user();
        $isAdmin = $user && (
            (method_exists($user, 'isAdmin') && $user->isAdmin()) ||
            ($user->role ?? 'owner') === 'admin' ||
            ($user->is_admin ?? false)
        );
        
        // Récupérer le véhicule actuel pour obtenir son propriétaire
        $currentVehicle = null;
        try {
            $allVehicles = $this->all([]);
            foreach ($allVehicles as $v) {
                $vehicleId = $v['id'] ?? $v['_id'] ?? null;
                if ($vehicleId && (string) $vehicleId === (string) $id) {
                    $currentVehicle = $v;
                    break;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Impossible de récupérer le véhicule actuel pour obtenir le propriétaire', [
                'vehicle_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
        
        // Extraire le ownerId actuel du véhicule
        $currentOwnerId = null;
        if ($currentVehicle) {
            // Vérifier dans ownerId direct
            if (isset($currentVehicle['ownerId']) && !empty($currentVehicle['ownerId'])) {
                $currentOwnerId = (string) $currentVehicle['ownerId'];
            }
            // Vérifier dans owner.id (relation Prisma)
            elseif (isset($currentVehicle['owner']) && is_array($currentVehicle['owner'])) {
                $currentOwnerId = (string) ($currentVehicle['owner']['id'] ?? $currentVehicle['owner']['_id'] ?? null);
            }
            // Vérifier dans proprietaireId
            elseif (isset($currentVehicle['proprietaireId']) && !empty($currentVehicle['proprietaireId'])) {
                $currentOwnerId = (string) $currentVehicle['proprietaireId'];
            }
        }
        
        Log::debug('Propriétaire actuel du véhicule', [
            'vehicle_id' => $id,
            'current_ownerId' => $currentOwnerId,
            'isAdmin' => $isAdmin,
            'proprietaireId_in_data' => $data['proprietaireId'] ?? null,
        ]);
        
        // ⚠️ IMPORTANT : Pour l'update, ne JAMAIS envoyer ownerId dans le payload
        // L'API NestJS vérifie que le sub du JWT correspond au ownerId du véhicule
        // Même si on envoie ownerId dans le payload, l'API vérifie toujours jwt.sub === vehicle.ownerId
        // Pour les admins, cela cause un 403 car jwt.sub (ID admin) ne correspond pas au ownerId du véhicule
        // 
        // SOLUTION : Ne pas envoyer ownerId et laisser l'API utiliser le sub du JWT
        // Si l'API refuse toujours, il faut modifier l'API NestJS pour permettre aux admins de modifier n'importe quel véhicule
        // en vérifiant le rôle dans le JWT plutôt que seulement le sub
        
        // 🔍 DEBUG : Décoder le token JWT pour voir ce qu'il contient
        $jwtUserId = 'N/A';
        $jwtRole = 'N/A';
        $isAdminInJwt = false;
        try {
            $token = $this->getAuthToken();
            if ($token) {
                // Décoder le JWT manuellement (même logique que ApiAuthService)
                $parts = explode('.', $token);
                if (count($parts) === 3) {
                    $payload = $parts[1];
                    $padding = strlen($payload) % 4;
                    if ($padding !== 0) {
                        $payload .= str_repeat('=', 4 - $padding);
                    }
                    $decoded = base64_decode($payload, true);
                    if ($decoded !== false) {
                        $jwtData = json_decode($decoded, true);
                        if (is_array($jwtData)) {
                            // Vérifier le rôle admin de manière insensible à la casse
                            $jwtRole = strtolower($jwtData['role'] ?? '');
                            $isAdminInJwt = in_array($jwtRole, ['admin', 'administrator', 'superadmin', 'super_admin']);
                            $jwtUserId = $jwtData['sub'] ?? $jwtData['id'] ?? $jwtData['userId'] ?? 'N/A';
                            
                            Log::info('🔍 Token JWT décodé avant PATCH', [
                                'vehicle_id' => $id,
                                'jwt_user_id' => $jwtUserId,
                                'jwt_email' => $jwtData['email'] ?? 'N/A',
                                'jwt_role' => $jwtData['role'] ?? 'N/A',
                                'jwt_role_normalized' => $jwtRole,
                                'vehicle_ownerId' => $currentOwnerId,
                                'match' => $jwtUserId === $currentOwnerId,
                                'isAdmin_in_jwt' => $isAdminInJwt,
                                'isAdmin_in_laravel' => $isAdmin,
                                'note' => $isAdminInJwt ? '✅ Le token contient bien le rôle admin' : '❌ Le token ne contient pas le rôle admin',
                            ]);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Impossible de décoder le token JWT pour debug', [
                'error' => $e->getMessage(),
            ]);
        }
        
        // ⚠️ PROBLÈME IDENTIFIÉ : L'API NestJS vérifie que jwt.sub === vehicle.ownerId
        // Même si on envoie ownerId dans le payload, l'API vérifie toujours jwt.sub === vehicle.ownerId
        // Pour les admins, cela cause un 403 car jwt.sub (ID admin) ne correspond pas au ownerId du véhicule
        // 
        // SOLUTION TEMPORAIRE : Ne pas envoyer ownerId et laisser l'API utiliser le sub du JWT
        // ⚠️ ATTENTION : Cela ne fonctionnera que si l'API NestJS permet aux admins de modifier n'importe quel véhicule
        // Si l'API refuse toujours, il faut modifier l'API NestJS pour permettre aux admins de modifier n'importe quel véhicule
        // en vérifiant le rôle dans le JWT plutôt que seulement le sub
        
        // Supprimer ownerId du payload pour éviter l'erreur 403
        unset($dataForApi['ownerId']);
        unset($dataForApi['proprietaireId']);
        unset($dataForApi['owner_id']);
        unset($dataForApi['proprietaire_id']);
        
        Log::info('Mise à jour véhicule - ownerId non envoyé (l\'API utilise le sub du JWT)', [
            'vehicle_id' => $id,
            'current_ownerId' => $currentOwnerId,
            'isAdmin' => $isAdmin,
            'jwt_user_id' => $jwtUserId,
            'jwt_role' => $jwtRole,
            'isAdmin_in_jwt' => $isAdminInJwt,
            'match' => $jwtUserId === $currentOwnerId,
            'note' => 'L\'API vérifie jwt.sub === vehicle.ownerId. Pour les admins, cela peut causer un 403 si les IDs ne correspondent pas. Si l\'erreur persiste, il faut modifier l\'API NestJS pour permettre aux admins de modifier n\'importe quel véhicule.',
        ]);
        
        
        // Supprimer les champs obsolètes qui ne doivent pas être envoyés à l'API (même logique que create)
        // ⚠️ NE PAS supprimer 'seats' car l'API NestJS l'attend dans le DTO de validation
        unset($dataForApi['places']);
        unset($dataForApi['capacity']); // capacity n'est pas attendu par le DTO, seulement seats
        unset($dataForApi['fuel']); // fuel est mappé vers fuelType
        
        // Filtrer les images vides (même logique que create)
        if (isset($dataForApi['images']) && is_array($dataForApi['images'])) {
            $dataForApi['images'] = array_filter($dataForApi['images'], function($img) {
                return !empty(trim($img));
            });
            $dataForApi['images'] = array_values($dataForApi['images']);
        }
        
        // Filtrer les features vides (même logique que create)
        if (isset($dataForApi['features']) && is_array($dataForApi['features'])) {
            $dataForApi['features'] = array_filter($dataForApi['features'], function($feature) {
                return !empty(trim($feature));
            });
            $dataForApi['features'] = array_values($dataForApi['features']);
        }
        
        // Filtrer les valeurs null (même logique que create)
        // ⚠️ IMPORTANT : fuelType n'est plus dans allowedNullFields car l'API NestJS exige une chaîne non vide
        // Si fuelType est null ou vide, il sera supprimé du payload par le mapper
        $allowedNullFields = ['color', 'transmission', 'mileage', 'description', 'images', 'features'];
        $dataForApi = array_filter($dataForApi, function($value, $key) use ($allowedNullFields) {
            // Garder les champs null autorisés et les tableaux vides
            if (in_array($key, $allowedNullFields)) {
                return true;
            }
            // Filtrer les valeurs null pour les autres champs
            return $value !== null;
        }, ARRAY_FILTER_USE_BOTH);
        
        // 🛡️ Debugging : Vérifier les types dans les logs Laravel avant l'envoi
        Log::debug('Payload formaté pour NestJS (update)', [
            'vehicle_id' => $id,
            'payload' => $dataForApi,
            'types_check' => [
                'year' => isset($dataForApi['year']) ? gettype($dataForApi['year']) . ' (' . $dataForApi['year'] . ')' : 'not_set',
                'seats' => isset($dataForApi['seats']) ? gettype($dataForApi['seats']) . ' (' . $dataForApi['seats'] . ')' : 'not_set',
                'pricePerDay' => isset($dataForApi['pricePerDay']) ? gettype($dataForApi['pricePerDay']) . ' (' . $dataForApi['pricePerDay'] . ')' : 'not_set',
                'mileage' => isset($dataForApi['mileage']) ? gettype($dataForApi['mileage']) . ' (' . $dataForApi['mileage'] . ')' : 'not_set',
                'isActive' => isset($dataForApi['isActive']) ? gettype($dataForApi['isActive']) . ' (' . ($dataForApi['isActive'] ? 'true' : 'false') . ')' : 'not_set',
                'licensePlate' => isset($dataForApi['licensePlate']) ? gettype($dataForApi['licensePlate']) . ' (' . $dataForApi['licensePlate'] . ')' : 'not_set',
            ],
        ]);
        
        Log::info('Mise à jour de véhicule - données envoyées à l\'API', [
            'vehicle_id' => $id,
            'data_keys' => array_keys($dataForApi),
            'type' => $dataForApi['type'] ?? null,
            'seats' => $dataForApi['seats'] ?? null,
            'fuelType' => $dataForApi['fuelType'] ?? null,
            'has_ownerId' => isset($dataForApi['ownerId']),
        ]);
        
        try {
            $result = $this->patch("vehicles/{$id}", $dataForApi);
            
            // 🔍 DEBUG : Vérifier si le title est bien retourné par l'API
            // La réponse peut être un objet ou un tableau selon la normalisation
            $returnedTitle = null;
            if (is_array($result)) {
                // Si c'est un tableau (normalisé), prendre le premier élément
                if (isset($result[0]) && is_array($result[0])) {
                    $returnedTitle = $result[0]['title'] ?? $result[0]['titre'] ?? $result[0]['name'] ?? null;
                } else {
                    // Si c'est un objet direct
                    $returnedTitle = $result['title'] ?? $result['titre'] ?? $result['name'] ?? null;
                }
            }
            
            Log::info('Véhicule mis à jour avec succès', [
                'vehicle_id' => $id,
                'title_sent' => $dataForApi['title'] ?? null,
                'title_returned' => $returnedTitle,
                'title_match' => ($dataForApi['title'] ?? null) === $returnedTitle,
                'result_type' => gettype($result),
                'result_keys' => is_array($result) ? array_keys($result) : 'not_array',
                'result_preview' => is_array($result) ? array_intersect_key($result, array_flip(['id', 'title', 'name', 'brand', 'model'])) : 'not_array',
            ]);
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du véhicule', [
                'vehicle_id' => $id,
                'error' => $e->getMessage(),
                'data_sent' => $dataForApi,
                'original_data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Supprimer un véhicule
     */
    public function delete(string $id): bool
    {
        Log::info('VehicleService::delete appelé', [
            'vehicle_id' => $id,
            'endpoint' => "vehicles/{$id}",
        ]);
        
        try {
            $result = parent::delete("vehicles/{$id}");
            
            Log::info('VehicleService::delete - Résultat', [
                'vehicle_id' => $id,
                'success' => $result,
            ]);
            
            return $result;
        } catch (\Exception $e) {
            Log::error('VehicleService::delete - Erreur', [
                'vehicle_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Récupérer les types de véhicules disponibles depuis l'API
     */
    public function getTypes(): array
    {
        // Essayer plusieurs endpoints possibles (ordre de priorité)
        $endpoints = [
            'vehicles/types',
            'vehicles/categories',
            'vehicle-types',
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $response = $this->get($endpoint);
                
                if (!empty($response)) {
                    // Log des types bruts retournés par l'API pour debug
                    Log::debug('Types bruts retournés par l\'API', [
                        'endpoint' => $endpoint,
                        'raw_response' => $response,
                        'response_count' => count($response),
                    ]);
                    
                    // Normaliser les types pour avoir un format uniforme
                    // Types acceptés par la validation - alignés avec l'API NestJS
                    $acceptedTypes = ['berline', 'suv', '4x4', 'utilitaire', 'moto', 'citadine', 'luxe'];
                    
                    $normalizedTypes = [];
                    $typeMap = [
                        // Types directs (acceptés nativement)
                        'berline' => 'berline',
                        'suv' => 'suv',
                        '4x4' => '4x4',
                        '4 x 4' => '4x4',
                        'utilitaire' => 'utilitaire',
                        'moto' => 'moto',
                        'motorcycle' => 'moto',
                        'citadine' => 'citadine', // ✅ Accepté directement
                        'luxe' => 'luxe', // ✅ Accepté directement
                        // Mappings vers types acceptés (variantes)
                        'sedan' => 'berline',
                        'car' => 'berline',
                    ];
                    
                    foreach ($response as $type) {
                        if (is_string($type)) {
                            $normalizedValue = strtolower(trim($type));
                            $normalizedValue = $typeMap[$normalizedValue] ?? $normalizedValue;
                            
                            // Ne garder que les types acceptés
                            if (in_array($normalizedValue, $acceptedTypes)) {
                                $normalizedTypes[] = [
                                    'value' => $normalizedValue,
                                    'label' => ucfirst($normalizedValue),
                                ];
                            }
                        } elseif (is_array($type)) {
                            $value = $type['value'] ?? $type['id'] ?? $type['name'] ?? $type['type'] ?? null;
                            $label = $type['label'] ?? $type['name'] ?? $type['title'] ?? $value ?? null;
                            
                            if ($value && $label) {
                                $normalizedValue = strtolower(trim($value));
                                $normalizedValue = $typeMap[$normalizedValue] ?? $normalizedValue;
                                
                                // Ne garder que les types acceptés
                                if (in_array($normalizedValue, $acceptedTypes)) {
                                    // Utiliser le label original de l'API si disponible, sinon générer
                                    $finalLabel = ($normalizedValue === strtolower(trim($value))) ? $label : ucfirst($normalizedValue);
                                    $normalizedTypes[] = [
                                        'value' => $normalizedValue,
                                        'label' => $finalLabel,
                                    ];
                                }
                            }
                        }
                    }
                    
                    // Dédupliquer les types normalisés (plusieurs types API peuvent mapper vers le même type accepté)
                    $uniqueTypes = [];
                    $seenValues = [];
                    foreach ($normalizedTypes as $type) {
                        if (!in_array($type['value'], $seenValues)) {
                            $uniqueTypes[] = $type;
                            $seenValues[] = $type['value'];
                        }
                    }
                    $normalizedTypes = $uniqueTypes;
                    
                    // Ajouter les types acceptés qui ne sont pas retournés par l'API
                    // (4x4 et moto peuvent ne pas être dans la réponse API)
                    $existingValues = array_column($normalizedTypes, 'value');
                    $missingTypes = [
                        ['value' => '4x4', 'label' => '4x4'],
                        ['value' => 'moto', 'label' => 'Moto'],
                    ];
                    
                    foreach ($missingTypes as $missingType) {
                        if (!in_array($missingType['value'], $existingValues)) {
                            $normalizedTypes[] = $missingType;
                        }
                    }
                    
                    // Trier par ordre alphabétique pour cohérence
                    usort($normalizedTypes, function($a, $b) {
                        return strcmp($a['value'], $b['value']);
                    });
                    
                    Log::debug('Types normalisés et filtrés depuis l\'API', [
                        'endpoint' => $endpoint,
                        'normalized_types' => $normalizedTypes,
                        'accepted_types' => $acceptedTypes,
                    ]);

                    if (!empty($normalizedTypes)) {
                        Log::debug('Types de véhicules récupérés depuis l\'API', [
                            'endpoint' => $endpoint,
                            'count' => count($normalizedTypes),
                        ]);
                        return $normalizedTypes;
                    }
                }
            } catch (\Exception $e) {
                Log::debug('Tentative endpoint échouée pour getTypes', [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        // Si aucun endpoint ne fonctionne, retourner les types par défaut
        // Alignés avec l'API NestJS
        Log::warning('Aucun endpoint API ne fonctionne pour getTypes, utilisation des types par défaut');
        return [
            ['value' => 'berline', 'label' => 'Berline'],
            ['value' => 'suv', 'label' => 'SUV'],
            ['value' => '4x4', 'label' => '4x4'],
            ['value' => 'utilitaire', 'label' => 'Utilitaire'],
            ['value' => 'moto', 'label' => 'Moto'],
            ['value' => 'citadine', 'label' => 'Citadine'],
            ['value' => 'luxe', 'label' => 'Luxe'],
        ];
    }

    /**
     * Récupérer les véhicules pour un utilisateur (filtrés selon le rôle)
     * 
     * @param Authenticatable $user L'utilisateur connecté
     * @param array $filters Filtres additionnels
     * @return array
     */
    public function getVehiclesForUser(Authenticatable $user, array $filters = []): array
    {
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->role ?? 'owner') === 'admin';
        
        if ($isAdmin) {
            // Admin voit tout
            return $this->allMapped($filters);
        }

        // Propriétaire : filtrer par proprietaireId
        $ownerFilters = array_merge($filters, [
            'proprietaireId' => $user->getAuthIdentifier(),
        ]);

        return $this->allMapped($ownerFilters);
    }

    /**
     * Récupérer un véhicule pour un utilisateur (avec vérification de propriété)
     * 
     * @param string $id ID du véhicule
     * @param Authenticatable $user L'utilisateur connecté
     * @return array|null
     */
    public function findForUser(string $id, Authenticatable $user): ?array
    {
        Log::info('VehicleService::findForUser appelé', [
            'vehicle_id' => $id,
            'user_id' => $user->getAuthIdentifier(),
            'user_role' => $user->role ?? 'unknown',
        ]);

        // Si propriétaire, vérifier d'abord dans la liste filtrée
        $isOwner = method_exists($user, 'isOwner') ? $user->isOwner() : ($user->role ?? 'owner') === 'owner';
        
        if ($isOwner) {
            // Récupérer la liste filtrée des véhicules de l'utilisateur
            $userVehicles = $this->getVehiclesForUser($user);
            $vehicleInList = collect($userVehicles)->firstWhere('id', $id);
            
            if ($vehicleInList) {
                // Si le véhicule est dans la liste filtrée, il appartient à l'utilisateur
                // On fait confiance à la liste filtrée de l'API
                // Récupérer les détails complets
                $vehicle = $this->find($id);
                if ($vehicle) {
                    // Vérifier si le proprietaireId correspond (pour logging uniquement)
                    $userId = (string) $user->getAuthIdentifier();
                    $vehicleOwnerId = (string) ($vehicle['proprietaireId'] ?? $vehicle['ownerId'] ?? null);
                    
                    if ($vehicleOwnerId && $vehicleOwnerId !== $userId) {
                        // Loguer un avertissement mais autoriser l'accès car le véhicule est dans la liste filtrée
                        Log::warning('Véhicule trouvé dans liste filtrée mais proprietaireId ne correspond pas - autorisation basée sur liste filtrée', [
                            'vehicle_id' => $id,
                            'user_id' => $userId,
                            'vehicle_proprietaireId' => $vehicleOwnerId,
                        ]);
                    }
                    
                    Log::info('Véhicule trouvé dans la liste filtrée, propriété confirmée', [
                        'vehicle_id' => $id,
                        'user_id' => $user->getAuthIdentifier(),
                    ]);
                    return $vehicle;
                }
            } else {
                Log::warning('Véhicule non trouvé dans la liste filtrée', [
                    'vehicle_id' => $id,
                    'user_id' => $user->getAuthIdentifier(),
                    'user_vehicles_count' => count($userVehicles),
                ]);
            }
        }

        $vehicle = $this->find($id);

        if (!$vehicle) {
            Log::warning('Véhicule non trouvé dans findForUser', [
                'vehicle_id' => $id,
            ]);
            return null;
        }

        Log::info('Véhicule trouvé, vérification propriétaire', [
            'vehicle_id' => $id,
            'vehicle_keys' => array_keys($vehicle),
            'proprietaireId' => $vehicle['proprietaireId'] ?? 'not_set',
            'ownerId' => $vehicle['ownerId'] ?? 'not_set',
        ]);

        // Si propriétaire et pas trouvé dans la liste, vérifier par proprietaireId
        if ($isOwner) {
            $ownerId = $vehicle['proprietaireId'] 
                ?? $vehicle['ownerId'] 
                ?? ($vehicle['proprietaire']['id'] ?? null)
                ?? ($vehicle['owner']['id'] ?? null);

            $userId = (string) $user->getAuthIdentifier();

            Log::info('Vérification propriétaire', [
                'vehicle_id' => $id,
                'ownerId_from_vehicle' => $ownerId,
                'user_id' => $userId,
                'match' => $ownerId === $userId,
                'ownerId_type' => gettype($ownerId),
                'userId_type' => gettype($userId),
            ]);

            if ($ownerId !== $userId) {
                Log::warning('Véhicule n\'appartient pas au propriétaire', [
                    'vehicle_id' => $id,
                    'ownerId_from_vehicle' => $ownerId,
                    'user_id' => $userId,
                ]);
                return null; // Ne pas retourner le véhicule s'il n'appartient pas au propriétaire
            }
        }

        return $vehicle;
    }
}

