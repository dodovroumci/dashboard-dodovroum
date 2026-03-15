<?php

namespace App\Http\Controllers\Owner\Concerns;

use App\Services\DodoVroumApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait HasProprietaireId
{
    /**
     * Extraire le proprietaireId réel depuis les données utilisateur
     * 
     * @param \App\Models\ApiUser $user
     * @return int|string|null Le proprietaireId (int si numérique, sinon string ou null)
     */
    protected function getProprietaireId($user)
    {
        $userId = (string) $user->getAuthIdentifier();
        $userEmail = $user->email ?? null;
        
        // Cache le proprietaireId pour éviter de le rechercher à chaque requête
        $cacheKey = "proprietaire_id_{$userId}";
        $cachedProprietaireId = Cache::get($cacheKey);
        
        if ($cachedProprietaireId !== null) {
            return $cachedProprietaireId;
        }
        
        $apiService = $this->apiService ?? app(DodoVroumApiService::class);
        
        // Méthode 1 : Essayer de récupérer les données utilisateur complètes par ID
        $fullUserData = $apiService->getUser($userId);
        
        if ($fullUserData) {
            // Essayer d'abord proprietaireId direct
            $proprietaireId = $fullUserData['proprietaireId'] ?? $fullUserData['proprietaire_id'] ?? $fullUserData['ownerId'] ?? $fullUserData['owner_id'] ?? null;
            
            // Si pas trouvé, essayer dans un objet proprietaire
            if (!$proprietaireId && isset($fullUserData['proprietaire']) && is_array($fullUserData['proprietaire'])) {
                $proprietaireId = $fullUserData['proprietaire']['id'] ?? $fullUserData['proprietaire']['_id'] ?? null;
            }
            
            // Si pas trouvé, essayer dans un objet owner
            if (!$proprietaireId && isset($fullUserData['owner']) && is_array($fullUserData['owner'])) {
                $proprietaireId = $fullUserData['owner']['id'] ?? $fullUserData['owner']['_id'] ?? null;
            }
            
            if ($proprietaireId) {
                Cache::put($cacheKey, $proprietaireId, now()->addHours(1));
                return $proprietaireId;
            }
        }
        
        // Méthode 2 : Si getUser() ne fonctionne pas, chercher par email dans la liste des utilisateurs
        if ($userEmail) {
            $usersData = $apiService->getUsers();
            $allUsers = [];
            
            // Normaliser la structure des données
            if (is_array($usersData)) {
                if (isset($usersData['data']) && is_array($usersData['data'])) {
                    if (isset($usersData['data']['data']) && is_array($usersData['data']['data'])) {
                        $allUsers = $usersData['data']['data'];
                    } elseif (isset($usersData['data'][0])) {
                        $allUsers = $usersData['data'];
                    } else {
                        $allUsers = [$usersData['data']];
                    }
                } elseif (isset($usersData[0])) {
                    $allUsers = $usersData;
                }
            }
            
            // Trouver l'utilisateur par email
            $foundUserByEmail = null;
            foreach ($allUsers as $u) {
                $email = strtolower($u['email'] ?? '');
                if ($email === strtolower($userEmail)) {
                    $foundUserByEmail = $u;
                    break;
                }
            }
            
            if ($foundUserByEmail) {
                // Essayer d'extraire le proprietaireId depuis cet utilisateur
                $proprietaireId = $foundUserByEmail['proprietaireId'] ?? $foundUserByEmail['proprietaire_id'] ?? $foundUserByEmail['ownerId'] ?? $foundUserByEmail['owner_id'] ?? null;
                
                if (!$proprietaireId && isset($foundUserByEmail['proprietaire']) && is_array($foundUserByEmail['proprietaire'])) {
                    $proprietaireId = $foundUserByEmail['proprietaire']['id'] ?? $foundUserByEmail['proprietaire']['_id'] ?? null;
                }
                
                if (!$proprietaireId && isset($foundUserByEmail['owner']) && is_array($foundUserByEmail['owner'])) {
                    $proprietaireId = $foundUserByEmail['owner']['id'] ?? $foundUserByEmail['owner']['_id'] ?? null;
                }
                
                // Si on a trouvé un proprietaireId, le retourner
                if ($proprietaireId) {
                    Cache::put($cacheKey, $proprietaireId, now()->addHours(1));
                    return $proprietaireId;
                }
                
                // Si pas de proprietaireId dans l'utilisateur, utiliser son ID comme proprietaireId
                // (dans certains cas, user.id = proprietaireId, notamment pour les propriétaires)
                $foundUserId = $foundUserByEmail['id'] ?? $foundUserByEmail['_id'] ?? null;
                if ($foundUserId) {
                    // Vérifier dans les résidences si ce userId correspond à un proprietaireId
                    $allResidences = $apiService->getResidences([]);
                    foreach ($allResidences as $residence) {
                        $residenceProprietaireId = $residence['proprietaireId'] ?? $residence['proprietaire_id'] ?? $residence['ownerId'] ?? $residence['owner_id'] ?? null;
                        if ($residenceProprietaireId && (string) $residenceProprietaireId === (string) $foundUserId) {
                            Cache::put($cacheKey, $residenceProprietaireId, now()->addHours(1));
                            return $residenceProprietaireId;
                        }
                    }
                    
                    // Si aucune résidence ne correspond, utiliser quand même l'ID utilisateur comme proprietaireId
                    Cache::put($cacheKey, $foundUserId, now()->addHours(1));
                    return $foundUserId;
                }
            }
        }
        
        // Méthode 3 : Si toujours pas trouvé, chercher dans les résidences/véhicules
        // Récupérer quelques résidences et extraire le proprietaireId depuis celles qui correspondent à l'utilisateur
        $allResidences = $apiService->getResidences([]);
        
        foreach ($allResidences as $residence) {
            $residenceProprietaireId = $residence['proprietaireId'] ?? $residence['proprietaire_id'] ?? $residence['ownerId'] ?? $residence['owner_id'] ?? null;
            
            // Si la résidence a un proprietaire avec email, comparer
            if (isset($residence['proprietaire']) && is_array($residence['proprietaire'])) {
                $proprietaireEmail = strtolower($residence['proprietaire']['email'] ?? '');
                if ($proprietaireEmail === strtolower($userEmail ?? '')) {
                    $proprietaireId = $residence['proprietaire']['id'] ?? $residence['proprietaire']['_id'] ?? $residenceProprietaireId;
                    if ($proprietaireId) {
                        Cache::put($cacheKey, $proprietaireId, now()->addHours(1));
                        return $proprietaireId;
                    }
                }
            }
        }
        
        // Méthode 4 : Chercher dans les véhicules
        $allVehicles = $apiService->getVehicles([]);
        
        foreach ($allVehicles as $vehicle) {
            $vehicleProprietaireId = $vehicle['proprietaireId'] ?? $vehicle['proprietaire_id'] ?? $vehicle['ownerId'] ?? $vehicle['owner_id'] ?? null;
            
            // Si le véhicule a un proprietaire avec email, comparer
            if (isset($vehicle['proprietaire']) && is_array($vehicle['proprietaire'])) {
                $proprietaireEmail = strtolower($vehicle['proprietaire']['email'] ?? '');
                if ($proprietaireEmail === strtolower($userEmail ?? '')) {
                    $proprietaireId = $vehicle['proprietaire']['id'] ?? $vehicle['proprietaire']['_id'] ?? $vehicleProprietaireId;
                    if ($proprietaireId) {
                        Cache::put($cacheKey, $proprietaireId, now()->addHours(1));
                        return $proprietaireId;
                    }
                }
            }
        }
        
        Log::warning('proprietaireId non trouvé après toutes les méthodes', [
            'user_id' => $userId,
            'user_email' => $userEmail,
        ]);
        
        return null;
    }
}

