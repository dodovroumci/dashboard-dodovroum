<?php

namespace App\Services\DodoVroumApi;

use App\Exceptions\DodoVroumApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiAuthService
{
    protected string $baseUrl;
    protected string $adminEmail;

    public function __construct()
    {
        $this->baseUrl = config('services.dodovroum.api_url', 'http://localhost:3000/api');
        $this->adminEmail = config('services.dodovroum.admin_email', 'admin@dodovroum.com');
    }

    /**
     * Authentifier un utilisateur via l'API
     * 
     * @param string $email
     * @param string $password
     * @return array|null Retourne les données utilisateur avec le token, ou null si échec
     */
    public function authenticate(string $email, string $password): ?array
    {
        try {
            // Normaliser l'email (minuscules, sans espaces)
            $email = strtolower(trim($email));
            $password = trim($password);
            
            // Logger les données envoyées (sans le mot de passe complet pour la sécurité)
            Log::info('Tentative d\'authentification', [
                'email' => $email,
                'password_length' => strlen($password),
                'password_preview' => substr($password, 0, 2) . '***',
                'url' => "{$this->baseUrl}/auth/login",
            ]);
            
            // Créer le client HTTP avec les options appropriées
            $client = Http::timeout(10)
                ->acceptJson()
                ->asJson();
            
            // Désactiver la vérification SSL en développement si nécessaire
            if (config('app.debug') && str_starts_with($this->baseUrl, 'https://')) {
                $client = $client->withoutVerifying();
            }
            
            $response = $client->post("{$this->baseUrl}/auth/login", [
                'email' => $email,
                'password' => $password,
            ]);
            
            // Logger la réponse complète pour le débogage
            Log::debug('Réponse API authentification', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body_preview' => substr($response->body(), 0, 200),
            ]);

            if (!$response->successful()) {
                $errorBody = $response->json();
                $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? 'Erreur d\'authentification';
                
                Log::warning('Échec authentification API', [
                    'email' => $email,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'error_message' => $errorMessage,
                ]);
                
                // Si c'est une erreur 401, donner plus de détails
                if ($response->status() === 401) {
                    Log::info('Authentification refusée - Identifiants invalides', [
                        'email' => $email,
                        'suggestion' => 'Vérifiez que l\'email et le mot de passe sont corrects',
                    ]);
                }
                
                return null;
            }

            $data = $response->json();
            
            // Extraire le token
            $token = $data['data']['access_token'] 
                ?? $data['access_token'] 
                ?? $data['token'] 
                ?? null;

            if (!$token) {
                Log::error('Token non trouvé dans la réponse API', ['response' => $data]);
                return null;
            }

            // Récupérer les infos utilisateur depuis l'API
            $userData = $this->getUserFromToken($token);

            // Si l'API ne retourne pas les infos, essayer de décoder le JWT token
            if (!$userData) {
                Log::warning('Impossible de récupérer les infos utilisateur depuis /auth/me, tentative de décodage JWT', [
                    'email' => $email,
                    'admin_email' => $this->adminEmail,
                ]);
                
                // Essayer de décoder le JWT token pour extraire les infos utilisateur
                $userData = $this->decodeJwtToken($token);
                
                // Si le décodage JWT échoue aussi, créer un utilisateur basique
                if (!$userData) {
                    Log::warning('Impossible de décoder le JWT, utilisation des données par défaut', [
                        'email' => $email,
                        'admin_email' => $this->adminEmail,
                    ]);
                    $userData = [
                        'email' => $email,
                        'id' => md5($email), // ID temporaire basé sur l'email
                    ];
                } else {
                    Log::info('Données utilisateur récupérées depuis le JWT', [
                        'email' => $userData['email'] ?? $email,
                        'role' => $userData['role'] ?? 'non défini',
                    ]);
                }
            }
            
            // S'assurer que l'email est toujours présent (utiliser celui de la connexion si manquant)
            if (empty($userData['email'])) {
                $userData['email'] = $email;
            }
            
            // Log pour debug
            Log::debug('Données utilisateur récupérées', [
                'email' => $userData['email'] ?? $email,
                'role_detecte' => $userData['role'] ?? $userData['type'] ?? 'non défini',
            ]);

            // Normaliser le rôle
            $role = $this->normalizeRole($userData, $email);

            Log::info('Rôle normalisé après authentification', [
                'email' => $email,
                'admin_email_config' => $this->adminEmail,
                'role_detecte' => $role,
                'user_data_role' => $userData['role'] ?? $userData['type'] ?? 'non défini',
            ]);

            // Extraire l'ID - essayer plusieurs sources
            $userId = $userData['id'] ?? $userData['_id'] ?? null;
            
            // Si l'ID est null, essayer de l'extraire depuis le JWT décodé
            if ($userId === null) {
                $jwtData = $this->decodeJwtToken($token);
                if ($jwtData && isset($jwtData['id'])) {
                    $userId = $jwtData['id'];
                    Log::debug('ID extrait depuis le JWT', ['id' => $userId]);
                }
            }
            
            $finalUserData = [
                'id' => $userId,
                'email' => $userData['email'] ?? $email,
                'name' => $this->getUserName($userData),
                'firstName' => $userData['firstName'] ?? $userData['prenom'] ?? null,
                'lastName' => $userData['lastName'] ?? $userData['nom'] ?? null,
                'role' => $role,
                'token' => $token,
                'raw' => $userData, // Données brutes de l'API
            ];
            
            Log::debug('Données utilisateur finales retournées par ApiAuthService', [
                'id' => $finalUserData['id'],
                'email' => $finalUserData['email'],
                'role' => $finalUserData['role'],
            ]);
            
            return $finalUserData;
        } catch (\Exception $e) {
            Log::error('Exception lors de l\'authentification API', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Récupérer les infos utilisateur depuis l'API avec le token
     */
    protected function getUserFromToken(string $token): ?array
    {
        try {
            // Essayer plusieurs endpoints possibles
            $endpoints = [
                'auth/me',
                'users/me',
                'auth/profile',
            ];

            foreach ($endpoints as $endpoint) {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'Authorization' => "Bearer {$token}",
                        'Accept' => 'application/json',
                    ])
                    ->get("{$this->baseUrl}/{$endpoint}");

                if ($response->successful()) {
                    $data = $response->json();
                    return ApiResponseNormalizer::data($data);
                }
            }

            // Si aucun endpoint ne fonctionne, essayer de déduire depuis le token
            // (certaines APIs incluent les infos utilisateur dans le payload JWT)
            Log::warning('Aucun endpoint /me disponible, utilisation des données par défaut');
            return null;
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des infos utilisateur', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Normaliser le rôle utilisateur
     * 
     * Priorité :
     * 1. Rôle envoyé par l'API (role, type, isAdmin, is_admin)
     * 2. Email admin configuré (fallback si l'API ne fournit pas de rôle)
     * 3. Owner par défaut
     */
    protected function normalizeRole(array $userData, ?string $loginEmail = null): string
    {
        // Utiliser l'email de connexion en priorité pour la détection admin
        $email = strtolower($loginEmail ?? $userData['email'] ?? '');
        $adminEmail = strtolower($this->adminEmail ?? '');
        
        Log::debug('Normalisation du rôle - Données reçues', [
            'email_connexion' => $loginEmail,
            'email_userData' => $userData['email'] ?? 'non défini',
            'email_utilisateur_final' => $email,
            'admin_email_config' => $adminEmail,
            'userData_keys' => array_keys($userData),
            'userData_role' => $userData['role'] ?? 'non défini',
            'userData_type' => $userData['type'] ?? 'non défini',
            'userData_isAdmin' => $userData['isAdmin'] ?? 'non défini',
            'userData_is_admin' => $userData['is_admin'] ?? 'non défini',
        ]);
        
        // PRIORITÉ 1 : Vérifier le rôle envoyé par l'API (champ role ou type)
        $role = strtolower($userData['role'] ?? $userData['type'] ?? '');
        
        if (!empty($role)) {
            // Mapper les différents formats de rôle admin
            if (in_array($role, ['admin', 'administrator', 'superadmin', 'super_admin'])) {
                Log::info('Rôle admin détecté par champ role/type de l\'API', [
                    'role' => $role,
                    'email' => $email,
                ]);
                return 'admin';
            }
            
            // Mapper les différents formats de rôle owner
            if (in_array($role, ['proprietaire', 'owner', 'propriétaire'])) {
                Log::info('Rôle owner détecté par champ role/type de l\'API', [
                    'role' => $role,
                    'email' => $email,
                ]);
                return 'owner';
            }
        }

        // PRIORITÉ 2 : Vérifier les champs isAdmin ou is_admin
        if (isset($userData['isAdmin']) && $userData['isAdmin']) {
            Log::info('Rôle admin détecté par isAdmin de l\'API', [
                'isAdmin' => $userData['isAdmin'],
                'email' => $email,
            ]);
            return 'admin';
        }
        if (isset($userData['is_admin']) && $userData['is_admin']) {
            Log::info('Rôle admin détecté par is_admin de l\'API', [
                'is_admin' => $userData['is_admin'],
                'email' => $email,
            ]);
            return 'admin';
        }

        // PRIORITÉ 3 : Fallback - Si l'email correspond à l'email admin configuré
        // Utiliser l'email de connexion pour cette vérification
        if (!empty($email) && $email === $adminEmail && !empty($adminEmail)) {
            Log::info('Rôle admin détecté par email configuré (fallback)', [
                'email' => $email,
                'admin_email_config' => $adminEmail,
                'email_connexion' => $loginEmail,
            ]);
            return 'admin';
        }

        // Par défaut, considérer comme owner
        Log::debug('Rôle par défaut: owner', [
            'email' => $email,
            'email_connexion' => $loginEmail,
            'admin_email_config' => $adminEmail,
            'raison' => 'Aucun rôle détecté dans les données de l\'API et email ne correspond pas à l\'admin configuré',
        ]);
        return 'owner';
    }

    /**
     * Obtenir le nom complet de l'utilisateur
     */
    protected function getUserName(array $userData): string
    {
        $firstName = $userData['firstName'] ?? $userData['prenom'] ?? '';
        $lastName = $userData['lastName'] ?? $userData['nom'] ?? '';
        
        $fullName = trim($firstName . ' ' . $lastName);
        
        if (empty($fullName)) {
            $fullName = $userData['name'] ?? $userData['email'] ?? 'Utilisateur';
        }
        
        return $fullName;
    }

    /**
     * Vérifier si un token est valide
     */
    public function validateToken(string $token): bool
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/auth/me");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Décoder un JWT token pour extraire les informations utilisateur
     * 
     * @param string $token Le token JWT
     * @return array|null Les données utilisateur extraites du token, ou null si échec
     */
    protected function decodeJwtToken(string $token): ?array
    {
        try {
            // Un JWT est composé de 3 parties séparées par des points : header.payload.signature
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                Log::warning('Token JWT invalide (format incorrect)', [
                    'parts_count' => count($parts),
                ]);
                return null;
            }
            
            // Décoder le payload (2ème partie)
            $payload = $parts[1];
            
            // Ajouter le padding nécessaire pour base64_decode
            $padding = strlen($payload) % 4;
            if ($padding !== 0) {
                $payload .= str_repeat('=', 4 - $padding);
            }
            
            // Décoder le payload
            $decoded = base64_decode($payload, true);
            
            if ($decoded === false) {
                Log::warning('Impossible de décoder le payload JWT');
                return null;
            }
            
            $data = json_decode($decoded, true);
            
            if (!is_array($data)) {
                Log::warning('Payload JWT invalide (pas un JSON valide)');
                return null;
            }
            
            // Extraire les informations utilisateur du payload
            // PRIORITÉ ABSOLUE : Le claim 'sub' (subject) du JWT est la source de vérité pour l'ID
            $userId = $data['sub'] ?? $data['id'] ?? $data['userId'] ?? null;
            
            // Convertir l'ID en string pour garantir la cohérence (même si c'est "1")
            if ($userId !== null) {
                $userId = (string) $userId;
            }
            
            $userData = [
                'id' => $userId,
                'email' => $data['email'] ?? null,
                'role' => $data['role'] ?? null,
                'firstName' => $data['firstName'] ?? $data['first_name'] ?? $data['prenom'] ?? null,
                'lastName' => $data['lastName'] ?? $data['last_name'] ?? $data['nom'] ?? null,
            ];
            
            // Si on a au moins un ID ou un email, c'est valide
            if ($userData['id'] || $userData['email']) {
                Log::debug('JWT décodé avec succès', [
                    'id' => $userData['id'],
                    'id_type' => gettype($userData['id']),
                    'id_source' => isset($data['sub']) ? 'sub' : (isset($data['id']) ? 'id' : (isset($data['userId']) ? 'userId' : 'none')),
                    'email' => $userData['email'],
                    'role' => $userData['role'],
                    'raw_sub' => $data['sub'] ?? 'N/A',
                ]);
                return $userData;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Erreur lors du décodage JWT', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

