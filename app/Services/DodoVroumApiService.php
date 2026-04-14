<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class DodoVroumApiService
{
    protected string $baseUrl;
    protected string $adminEmail;
    protected string $adminPassword;

    public function __construct()
    {
        $this->baseUrl = config('services.dodovroum.api_url', 'http://localhost:3000/api');
        $this->adminEmail = config('services.dodovroum.admin_email', 'admin@dodovroum.com');
        $this->adminPassword = config('services.dodovroum.admin_password', 'admin123');
    }

    /**
     * Oublier le token en cache pour un email donné
     */
    protected function forgetTokenCache(?string $email = null): void
    {
        $email = $email ?? $this->adminEmail;
        $cacheKey = 'dodovroum_api_token_' . md5($email);
        // Utiliser le store 'file' pour éviter les problèmes avec MySQL
        try {
            Cache::store('file')->forget($cacheKey);
        } catch (\Exception $e) {
            // Si le cache file échoue, essayer le cache par défaut
            try {
                Cache::forget($cacheKey);
            } catch (\Exception $e2) {
                // Ignorer les erreurs de cache
            }
        }
    }

    /**
     * Créer un client HTTP avec les options appropriées
     */
    protected function createHttpClient(int $timeout = 30)
    {
        $client = Http::timeout($timeout);
        
        // Désactiver la vérification SSL en développement si nécessaire
        if (config('app.debug') && str_starts_with($this->baseUrl, 'https://')) {
            $client = $client->withoutVerifying();
        }
        
        return $client;
    }

    /**
     * Obtenir le token d'authentification (avec cache)
     */
    protected function getAccessToken(?string $email = null, ?string $password = null): ?string
    {
        // Utiliser les credentials fournis ou ceux de l'admin par défaut
        $email = $email ?? $this->adminEmail;
        $password = $password ?? $this->adminPassword;
        
        // Clé de cache basée sur l'email pour permettre plusieurs tokens
        $cacheKey = 'dodovroum_api_token_' . md5($email);
        
        // Vérifier le cache (token valide pendant 1 heure)
        // Utiliser le store 'file' pour éviter les problèmes avec MySQL
        try {
            $cachedToken = Cache::store('file')->get($cacheKey);
            if ($cachedToken) {
                return $cachedToken;
            }
        } catch (\Exception $e) {
            // Si le cache file échoue, essayer le cache par défaut
            try {
                $cachedToken = Cache::get($cacheKey);
                if ($cachedToken) {
                    return $cachedToken;
                }
            } catch (\Exception $e2) {
                // Ignorer les erreurs de cache et continuer
            }
        }

        try {
            $loginUrl = "{$this->baseUrl}/auth/login";
            
            Log::info('Tentative de connexion à l\'API DodoVroum', [
                'url' => $loginUrl,
                'email' => $email,
            ]);
            
            $response = $this->createHttpClient()->post($loginUrl, [
                'email' => $email,
                'password' => $password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // L'API retourne { success: true, data: { access_token: ... } }
                $token = $data['data']['access_token'] 
                    ?? $data['access_token'] 
                    ?? $data['token'] 
                    ?? null;

                if ($token) {
                    // Mettre en cache pendant 55 minutes (un peu moins que la durée de vie du token)
                    // Utiliser le store 'file' pour éviter les problèmes avec MySQL
                    try {
                        Cache::store('file')->put($cacheKey, $token, now()->addMinutes(55));
                    } catch (\Exception $e) {
                        // Si le cache file échoue, essayer le cache par défaut
                        try {
                            Cache::put($cacheKey, $token, now()->addMinutes(55));
                        } catch (\Exception $e2) {
                            // Ignorer les erreurs de cache mais continuer
                            Log::warning('Impossible de mettre le token en cache', ['error' => $e2->getMessage()]);
                        }
                    }
                    Log::info('Token d\'authentification obtenu avec succès', ['email' => $email]);
                    return $token;
                }
            }

            $errorData = $response->json();
            $errorMessage = $errorData['message'] ?? $errorData['error'] ?? 'Erreur inconnue';
            
            Log::error('Échec authentification API DodoVroum', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
                'data_structure' => $errorData,
                'error_message' => $errorMessage,
                'api_url' => $loginUrl,
            ]);

            // Si c'est une erreur 401, donner un message plus clair
            if ($response->status() === 401) {
                Log::warning('Identifiants invalides pour l\'API', [
                    'email' => $email,
                    'admin_email_config' => config('services.dodovroum.admin_email'),
                    'suggestion' => 'Vérifiez que DODOVROUM_ADMIN_EMAIL et DODOVROUM_ADMIN_PASSWORD dans le .env sont corrects',
                ]);
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Exception lors de l\'authentification API DodoVroum', [
                'email' => $email,
                'url' => $loginUrl ?? "{$this->baseUrl}/auth/login",
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Obtenir un token pour un utilisateur spécifique (propriétaire)
     */
    protected function getOwnerToken(string $ownerId): ?string
    {
        // Récupérer les informations du propriétaire pour obtenir son email
        $owner = $this->getUser($ownerId);
        if (!$owner) {
            Log::error('Impossible de récupérer les informations du propriétaire', ['ownerId' => $ownerId]);
            return null;
        }

        $ownerEmail = $owner['email'] ?? null;
        if (!$ownerEmail) {
            Log::error('Le propriétaire n\'a pas d\'email', ['ownerId' => $ownerId, 'owner' => $owner]);
            return null;
        }

        // Note: On ne peut pas obtenir le mot de passe du propriétaire depuis l'API
        // Cette approche ne fonctionnera que si on a les credentials du propriétaire
        // Pour l'instant, on retourne null et on utilisera l'admin token
        Log::warning('Impossible d\'obtenir un token pour le propriétaire sans son mot de passe', [
            'ownerId' => $ownerId,
            'ownerEmail' => $ownerEmail,
        ]);

        return null;
    }

    /**
     * JWT NestJS stocké en session après login (prioritaire pour les appels « utilisateur »).
     */
    protected function getSessionJwt(): ?string
    {
        try {
            if (! app()->bound('session')) {
                return null;
            }
            $session = session();
            if (method_exists($session, 'isStarted') && ! $session->isStarted()) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        foreach (['nest_jwt_token', 'api_token'] as $key) {
            $t = Session::get($key);
            if (is_string($t) && $t !== '') {
                return $t;
            }
        }

        return null;
    }

    /**
     * Token pour la requête HTTP : session JWT (nest_jwt_token / api_token), puis ApiUser, puis compte admin (cache).
     */
    protected function resolveRequestToken(): ?string
    {
        $jwt = $this->getSessionJwt();
        if ($jwt !== null) {
            return $jwt;
        }

        if (Auth::check()) {
            $user = Auth::user();
            if ($user && method_exists($user, 'getApiToken')) {
                $t = $user->getApiToken();
                if (is_string($t) && $t !== '') {
                    return $t;
                }
            }
        }

        return $this->getAccessToken();
    }

    /**
     * Si false : erreur 401 traitée sans renouveler le token admin (JWT utilisateur expiré / invalide).
     */
    protected function shouldRefreshAdminTokenOn401(): bool
    {
        if ($this->getSessionJwt()) {
            return false;
        }

        if (Auth::check()) {
            $user = Auth::user();
            if ($user && method_exists($user, 'getApiToken')) {
                $t = $user->getApiToken();
                if (is_string($t) && $t !== '') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Faire une requête GET à l'API avec authentification
     */
    protected function get(string $endpoint, array $query = []): array
    {
        $token = $this->resolveRequestToken();

        if (! $token) {
            $this->forgetTokenCache();
            $token = $this->getAccessToken();
        }

        if (! $token) {
            Log::error('Impossible d\'obtenir le token d\'authentification');
            return [];
        }

        try {
            $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');

            $response = $this->createHttpClient()
                ->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get($url, $query);

            // Si 401 : ne pas substituer le compte admin quand un JWT utilisateur était utilisé
            if ($response->status() === 401) {
                if (! $this->shouldRefreshAdminTokenOn401()) {
                    Log::error("Erreur API sur {$endpoint}", [
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ]);

                    return [];
                }

                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = $this->createHttpClient()
                        ->withToken($token)
                        ->withHeaders([
                            'Accept' => 'application/json',
                        ])
                        ->get($url, $query);
                }
            }

            if ($response->successful()) {
                $data = $response->json();

                // Gérer les réponses avec structure { success: true, data: [...] }
                if (isset($data['data']) && is_array($data['data'])) {
                    $items = $data['data'];
                    
                    // Aplatir les tableaux imbriqués
                    $flattened = [];
                    foreach ($items as $item) {
                        if (is_array($item)) {
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
                                // C'est un objet/tableau associatif, on l'ajoute directement
                                if (isset($item['id'])) {
                                    $flattened[] = $item;
                                }
                            }
                        }
                    }
                    
                    $items = $flattened;
                    return $items;
                }
                
                // Si c'est directement un tableau
                if (is_array($data)) {
                    return $data;
                }
                
                // Si c'est un objet avec des propriétés
                if (is_object($data)) {
                    return (array) $data;
                }
                
                return [];
            }

            Log::error("Erreur API sur {$endpoint}", [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('API DodoVroum exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Faire une requête POST à l'API avec authentification
     */
    protected function post(string $endpoint, array $data = []): array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            // Essayer une dernière fois en vidant le cache
            $this->forgetTokenCache();
            $token = $this->getAccessToken();
            
            if (!$token) {
                Log::error('Impossible d\'obtenir le token d\'authentification après plusieurs tentatives', [
                    'endpoint' => $endpoint,
                    'base_url' => $this->baseUrl,
                    'admin_email' => $this->adminEmail,
                    'api_url' => config('services.dodovroum.api_url'),
                ]);
                throw new \Exception('Impossible de se connecter à l\'API. Vérifiez vos identifiants dans le fichier .env (DODOVROUM_ADMIN_EMAIL et DODOVROUM_ADMIN_PASSWORD). Assurez-vous que l\'API est accessible et que les identifiants sont corrects.');
            }
        }

        try {
            $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');

            $response = $this->createHttpClient()
                ->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->post($url, $data);

            if ($response->status() === 401) {
                if (! $this->shouldRefreshAdminTokenOn401()) {
                    Log::error("Erreur API sur {$endpoint}", [
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ]);
                    throw new \Exception('Session API expirée ou non autorisée.');
                }

                Log::warning('Token admin expiré, tentative de renouvellement', [
                    'endpoint' => $endpoint,
                ]);

                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = $this->createHttpClient()
                        ->withToken($token)
                        ->withHeaders([
                            'Accept' => 'application/json',
                        ])
                        ->asJson()
                        ->post($url, $data);
                } else {
                    Log::error('Impossible de renouveler le token après 401');
                    return [];
                }
            }

            if ($response->successful()) {
                $result = $response->json();
                return $result['data'] ?? $result ?? [];
            }

            // Extraire le message d'erreur de la réponse
            $errorBody = $response->json();
            $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? $response->body();
            
            // Si c'est un tableau de messages (validation), les joindre
            if (is_array($errorMessage)) {
                $errorMessage = implode(', ', $errorMessage);
            }

            Log::warning('API DodoVroum POST error', [
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
            ]);

            // Lancer une exception avec le message d'erreur pour que le contrôleur puisse l'afficher
            throw new \Exception($errorMessage ?: 'Erreur lors de la requête à l\'API');
        } catch (\Exception $e) {
            Log::error('API DodoVroum POST exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Faire une requête PUT à l'API avec authentification
     */
    protected function put(string $endpoint, array $data = []): array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification');
            return [];
        }

        try {
            $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');

            $response = $this->createHttpClient(10)
                ->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->put($url, $data);

            if ($response->status() === 401) {
                if (! $this->shouldRefreshAdminTokenOn401()) {
                    Log::error("Erreur API sur {$endpoint}", [
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ]);

                    return [];
                }

                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = $this->createHttpClient(10)
                        ->withToken($token)
                        ->withHeaders([
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->put($url, $data);
                }
            }

            if ($response->successful()) {
                $result = $response->json();
                return $result['data'] ?? $result ?? [];
            }

            Log::error("Erreur API sur {$endpoint}", [
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('API DodoVroum PUT exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Faire une requête PATCH à l'API avec authentification
     */
    protected function patch(string $endpoint, array $data = []): array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification');
            return [];
        }

        try {
            $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');

            $response = $this->createHttpClient()
                ->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->asJson()
                ->patch($url, $data);

            if ($response->status() === 401) {
                if (! $this->shouldRefreshAdminTokenOn401()) {
                    Log::error("Erreur API sur {$endpoint}", [
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ]);
                    throw new \Exception('Session API expirée ou non autorisée.');
                }

                Log::warning('Token admin expiré, tentative de renouvellement', [
                    'endpoint' => $endpoint,
                ]);

                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = $this->createHttpClient()
                        ->withToken($token)
                        ->withHeaders([
                            'Accept' => 'application/json',
                        ])
                        ->asJson()
                        ->patch($url, $data);
                } else {
                    Log::error('Impossible de renouveler le token après 401');
                    return [];
                }
            }

            if ($response->successful()) {
                $result = $response->json();
                return $result['data'] ?? $result ?? [];
            }

            // Extraire le message d'erreur de la réponse
            $errorBody = $response->json();
            $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? $response->body();
            
            // Si c'est un tableau de messages (validation), les joindre
            if (is_array($errorMessage)) {
                $errorMessage = implode(', ', $errorMessage);
            }

            Log::error("Erreur API sur {$endpoint}", [
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
                'data_sent' => $data,
            ]);

            // Lancer une exception avec le message d'erreur pour que le contrôleur puisse l'afficher
            throw new \Exception($errorMessage ?: 'Erreur lors de la requête à l\'API');
        } catch (\Exception $e) {
            Log::error('API DodoVroum PATCH exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-lancer l'exception pour que le contrôleur puisse la gérer
            throw $e;
        }
    }

    /**
     * Faire une requête DELETE à l'API avec authentification
     */
    protected function delete(string $endpoint): bool
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification');
            return false;
        }

        try {
            $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');

            $response = $this->createHttpClient(10)
                ->withToken($token)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->delete($url);

            if ($response->status() === 401) {
                if (! $this->shouldRefreshAdminTokenOn401()) {
                    Log::error("Erreur API sur {$endpoint}", [
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ]);

                    return false;
                }

                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = $this->createHttpClient(10)
                        ->withToken($token)
                        ->withHeaders([
                            'Accept' => 'application/json',
                        ])
                        ->delete($url);
                }
            }

            if ($response->successful()) {
                return true;
            }

            Log::error("Erreur API sur {$endpoint}", [
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('API DodoVroum DELETE exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Récupérer les statistiques du dashboard
     * Note: L'endpoint admin/stats n'existe peut-être pas, on calcule les stats depuis les données
     */
    public function getDashboardStats(): array
    {
        // Essayer d'abord admin/stats, sinon calculer depuis les données
        $stats = $this->get('admin/stats');
        
        // Si vide, calculer depuis les données disponibles
        if (empty($stats)) {
            $bookings = $this->getBookings();
            $residences = $this->getResidences();
            $vehicles = $this->getVehicles();
            
            $stats = [
                'total_bookings' => count($bookings),
                'total_residences' => count($residences),
                'total_vehicles' => count($vehicles),
                'occupation_rate' => 0,
                'revenue' => 0,
            ];
        }

        // Extraire les valeurs numériques proprement
        $bookings = $this->extractNumericValue(
            $stats['total_bookings'] ?? $stats['bookings'] ?? $stats['reservations'] ?? 0
        );
        
        $occupation = $this->extractNumericValue(
            $stats['occupation_rate'] ?? $stats['occupation'] ?? $stats['taux_occupation'] ?? 0
        );
        
        $vehicleUsage = $this->extractVehicleUsage(
            $stats['vehicle_usage'] ?? $stats['vehicleUsage'] ?? $stats['locations_vehicules'] ?? '0 courses'
        );
        
        $revenue = $this->extractNumericValue(
            $stats['revenue'] ?? $stats['total_revenue'] ?? $stats['revenus'] ?? 0
        );

        return [
            'bookings' => (int) $bookings,
            'occupation' => (int) $occupation,
            'vehicleUsage' => $vehicleUsage,
            'revenue' => $this->formatRevenue($revenue),
        ];
    }

    /**
     * Extraire une valeur numérique d'une chaîne ou d'un objet
     */
    protected function extractNumericValue($value): int|float
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value)) {
            // Extraire le premier nombre trouvé dans la chaîne
            if (preg_match('/\d+/', $value, $matches)) {
                return (int) $matches[0];
            }
        }

        if (is_array($value) && isset($value['value'])) {
            return $this->extractNumericValue($value['value']);
        }

        return 0;
    }

    /**
     * Extraire et formater l'usage des véhicules
     */
    protected function extractVehicleUsage($value): string
    {
        if (is_string($value)) {
            // Si c'est déjà une chaîne formatée, essayer d'extraire le nombre
            if (preg_match('/(\d+)\s*(courses?|locations?)?/i', $value, $matches)) {
                return $matches[1] . ' courses';
            }
            // Si ça contient du texte bizarre, retourner juste le nombre
            if (preg_match('/\d+/', $value, $matches)) {
                return $matches[0] . ' courses';
            }
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value . ' courses';
        }

        return '0 courses';
    }

    /**
     * Formater les revenus avec séparateurs de milliers
     */
    protected function formatRevenue($value): string
    {
        $numeric = $this->extractNumericValue($value);
        return number_format($numeric, 0, ',', ' ');
    }

    /**
     * Récupérer les réservations récentes
     * Note: L'endpoint admin/bookings/recent n'existe peut-être pas, on prend les premières bookings
     */
    public function getRecentBookings(int $limit = 10): array
    {
        // Essayer d'abord admin/bookings/recent, sinon prendre les premières bookings
        $bookings = $this->get('admin/bookings/recent', ['limit' => $limit]);
        
        // Si vide, récupérer toutes les bookings et prendre les premières
        if (empty($bookings)) {
            $allBookings = $this->getBookings();
            $bookings = array_slice($allBookings, 0, $limit);
        }

        // Si la réponse est un tableau avec une clé 'data', extraire les données
        if (isset($bookings['data']) && is_array($bookings['data'])) {
            $bookings = $bookings['data'];
        }

        // S'assurer que $bookings est un tableau
        if (!is_array($bookings)) {
            return [];
        }

        return array_map(function ($booking) {
            if (!is_array($booking)) {
                return null;
            }

            return [
                'id' => $booking['id'] ?? null,
                'customer' => $booking['customer_name'] ?? $booking['user']['name'] ?? $booking['customer'] ?? 'Client inconnu',
                'property' => $booking['property_name'] ?? $booking['residence']['title'] ?? $booking['property'] ?? 'Propriété inconnue',
                'dates' => $this->formatDates($booking['start_date'] ?? $booking['startDate'] ?? null, $booking['end_date'] ?? $booking['endDate'] ?? null),
                'status' => $this->formatStatus($booking['status'] ?? 'pending'),
            ];
        }, array_filter($bookings));
    }

    /**
     * Récupérer toutes les réservations
     * Endpoint: GET /api/bookings
     */
    public function getBookings(array $filters = []): array
    {
        return $this->get('bookings', $filters);
    }

    /**
     * Approuver une réservation
     * Endpoint: PATCH /api/bookings/:id/approve
     * @return array{success: bool, message?: string}
     */
    public function approveBooking(string $id): array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification pour approveBooking');
            return ['success' => false, 'message' => 'Impossible d\'obtenir le token d\'authentification'];
        }

        try {
            $url = "{$this->baseUrl}/bookings/{$id}/approve";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->patch($url, []);

            if ($response->status() === 401) {
                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->patch($url, []);
                } else {
                    return ['success' => false, 'message' => 'Impossible de renouveler le token d\'authentification'];
                }
            }

            if ($response->successful()) {
                Log::info('Réservation approuvée avec succès', ['id' => $id]);
                return ['success' => true];
            }

            $responseBody = $response->json();
            $errorMessage = $responseBody['message'] ?? $responseBody['error'] ?? 'Erreur inconnue lors de l\'approbation';

            Log::warning('API DodoVroum PATCH approve booking error', [
                'id' => $id,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
            ]);

            return ['success' => false, 'message' => $errorMessage];
        } catch (\Exception $e) {
            Log::error('API DodoVroum PATCH approve booking exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Une erreur est survenue lors de l\'approbation : ' . $e->getMessage()];
        }
    }

    /**
     * Rejeter une réservation
     * Endpoint: PATCH /api/bookings/:id/reject
     * @param string $id ID de la réservation
     * @param string|null $reason Raison du rejet (optionnel)
     * @return array{success: bool, message?: string}
     */
    public function rejectBooking(string $id, ?string $reason = null): array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification pour rejectBooking');
            return ['success' => false, 'message' => 'Impossible d\'obtenir le token d\'authentification'];
        }

        try {
            $url = "{$this->baseUrl}/bookings/{$id}/reject";
            $data = [];
            if ($reason) {
                $data['reason'] = $reason;
            }
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->patch($url, $data);

            if ($response->status() === 401) {
                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->patch($url, $data);
                } else {
                    return ['success' => false, 'message' => 'Impossible de renouveler le token d\'authentification'];
                }
            }

            if ($response->successful()) {
                Log::info('Réservation rejetée avec succès', ['id' => $id, 'reason' => $reason]);
                return ['success' => true];
            }

            $responseBody = $response->json();
            $errorMessage = $responseBody['message'] ?? $responseBody['error'] ?? 'Erreur inconnue lors du rejet';

            Log::warning('API DodoVroum PATCH reject booking error', [
                'id' => $id,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
            ]);

            return ['success' => false, 'message' => $errorMessage];
        } catch (\Exception $e) {
            Log::error('API DodoVroum PATCH reject booking exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Une erreur est survenue lors du rejet : ' . $e->getMessage()];
        }
    }

    /**
     * Confirmer la récupération de clé par le client
     * Endpoint: PATCH /api/bookings/:id/confirm-key-retrieval
     * Transition: CONFIRMEE → CHECKIN_CLIENT
     * @return array{success: bool, message?: string}
     */
    public function confirmKeyRetrieval(string $id): array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification pour confirmKeyRetrieval');
            return ['success' => false, 'message' => 'Impossible d\'obtenir le token d\'authentification'];
        }

        try {
            $url = "{$this->baseUrl}/bookings/{$id}/confirm-key-retrieval";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->patch($url, []);

            if ($response->status() === 401) {
                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->patch($url, []);
                } else {
                    return ['success' => false, 'message' => 'Impossible de renouveler le token d\'authentification'];
                }
            }

            if ($response->successful()) {
                Log::info('Récupération de clé confirmée avec succès', ['id' => $id]);
                return ['success' => true];
            }

            $responseBody = $response->json();
            $errorMessage = $responseBody['message'] ?? $responseBody['error'] ?? 'Erreur inconnue lors de la confirmation de récupération de clé';

            Log::warning('API DodoVroum PATCH confirm-key-retrieval error', [
                'id' => $id,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
            ]);

            return ['success' => false, 'message' => $errorMessage];
        } catch (\Exception $e) {
            Log::error('API DodoVroum PATCH confirm-key-retrieval exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Une erreur est survenue lors de la confirmation de récupération de clé : ' . $e->getMessage()];
        }
    }

    /**
     * Confirmer la remise de clé par le propriétaire
     * Endpoint: PATCH /api/bookings/:id/confirm-owner-key-handover
     * Transition: CHECKIN_CLIENT → CHECKIN_PROPRIO (ou auto → EN_COURS_SEJOUR si les deux ont confirmé)
     * @return array{success: bool, message?: string}
     */
    public function confirmOwnerKeyHandover(string $id): array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification pour confirmOwnerKeyHandover');
            return ['success' => false, 'message' => 'Impossible d\'obtenir le token d\'authentification'];
        }

        try {
            $url = "{$this->baseUrl}/bookings/{$id}/confirm-owner-key-handover";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->patch($url, []);

            if ($response->status() === 401) {
                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->patch($url, []);
                } else {
                    return ['success' => false, 'message' => 'Impossible de renouveler le token d\'authentification'];
                }
            }

            if ($response->successful()) {
                Log::info('Remise de clé par le propriétaire confirmée avec succès', ['id' => $id]);
                return ['success' => true];
            }

            $responseBody = $response->json();
            $errorMessage = $responseBody['message'] ?? $responseBody['error'] ?? 'Erreur inconnue lors de la confirmation de remise de clé';

            Log::warning('API DodoVroum PATCH confirm-owner-key-handover error', [
                'id' => $id,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
            ]);

            return ['success' => false, 'message' => $errorMessage];
        } catch (\Exception $e) {
            Log::error('API DodoVroum PATCH confirm-owner-key-handover exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Une erreur est survenue lors de la confirmation de remise de clé : ' . $e->getMessage()];
        }
    }

    /**
     * Confirmer le checkout
     * Endpoint: PATCH /api/bookings/:id/confirm-checkout
     * Transition: EN_COURS_SEJOUR → TERMINEE
     * @return array{success: bool, message?: string}
     */
    public function confirmCheckOut(string $id): array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification pour confirmCheckOut');
            return ['success' => false, 'message' => 'Impossible d\'obtenir le token d\'authentification'];
        }

        try {
            $url = "{$this->baseUrl}/bookings/{$id}/confirm-checkout";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->patch($url, []);

            if ($response->status() === 401) {
                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->patch($url, []);
                } else {
                    return ['success' => false, 'message' => 'Impossible de renouveler le token d\'authentification'];
                }
            }

            if ($response->successful()) {
                Log::info('Checkout confirmé avec succès', ['id' => $id]);
                return ['success' => true];
            }

            $responseBody = $response->json();
            $errorMessage = $responseBody['message'] ?? $responseBody['error'] ?? 'Erreur inconnue lors de la confirmation du checkout';

            Log::warning('API DodoVroum PATCH confirm-checkout error', [
                'id' => $id,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
            ]);

            return ['success' => false, 'message' => $errorMessage];
        } catch (\Exception $e) {
            Log::error('API DodoVroum PATCH confirm-checkout exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Une erreur est survenue lors de la confirmation du checkout : ' . $e->getMessage()];
        }
    }

    /**
     * Supprimer une réservation
     * Endpoint: DELETE /api/bookings/:id
     * L'API NestJS supprime physiquement la réservation et toutes ses dépendances (payments, reviews) en cascade
     * @return array{success: bool, message?: string}
     */
    public function deleteBooking(string $id): array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification pour deleteBooking');
            return ['success' => false, 'message' => 'Impossible d\'obtenir le token d\'authentification'];
        }

        try {
            $url = "{$this->baseUrl}/bookings/{$id}";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->delete($url);

            if ($response->status() === 401) {
                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                        ])
                        ->delete($url);
                } else {
                    return ['success' => false, 'message' => 'Impossible de renouveler le token d\'authentification'];
                }
            }

            if ($response->successful()) {
                Log::info('Réservation supprimée avec succès (suppression physique avec cascade)', [
                    'id' => $id,
                ]);
                
                return ['success' => true];
            }

            // Extraire le message d'erreur de la réponse
            $responseBody = $response->json();
            $errorMessage = $responseBody['message'] ?? $responseBody['error'] ?? 'Erreur inconnue lors de la suppression';
            
            // Si c'est une erreur de contrainte de clé étrangère, message plus clair
            if (str_contains($errorMessage, 'Foreign key constraint') || str_contains($errorMessage, 'bookingId')) {
                $errorMessage = 'Impossible de supprimer cette réservation car elle est liée à d\'autres données.';
            }

            Log::warning('API DodoVroum DELETE booking error', [
                'id' => $id,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
            ]);

            return ['success' => false, 'message' => $errorMessage];
        } catch (\Exception $e) {
            Log::error('API DodoVroum DELETE booking exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Une erreur est survenue lors de la suppression : ' . $e->getMessage()];
        }
    }

    /**
     * Récupérer toutes les résidences
     * Endpoint: GET /api/residences
     */
    public function getResidences(array $filters = []): array
    {
        // L'API limite à 100 résultats par page, on doit paginer
        $limit = 100;
        $page = 1;
        $allResidences = [];
        
        do {
            $queryParams = array_merge($filters, [
                'limit' => $limit,
                'page' => $page,
            ]);
            
            try {
                $response = $this->get('residences', $queryParams);
            } catch (\Exception $e) {
                Log::error('Erreur lors de la récupération des résidences', [
                    'filters' => $filters,
                    'error' => $e->getMessage()
                ]);
                // Si c'est une erreur 500 de l'API, retourner un tableau vide mais logger l'erreur
                return [];
            }
            
            // Si l'API retourne une structure paginée
            if (isset($response['data']) && is_array($response['data'])) {
                $residences = $response['data'];
                $total = $response['total'] ?? count($residences);
                $currentLimit = $response['limit'] ?? $limit;
            } elseif (is_array($response)) {
                // Si c'est directement un tableau
                $residences = $response;
                $total = count($residences);
                $currentLimit = $limit;
            } else {
                $residences = [];
                $total = 0;
                $currentLimit = $limit;
            }
            
            // Aplatir les tableaux imbriqués
            foreach ($residences as $item) {
                if (is_array($item)) {
                    $keys = array_keys($item);
                    $isNumericArray = !empty($keys) && $keys === range(0, count($item) - 1);
                    
                    if ($isNumericArray) {
                        // C'est un tableau de tableaux, on aplatit
                        foreach ($item as $subItem) {
                            if (is_array($subItem) && isset($subItem['id'])) {
                                $allResidences[] = $subItem;
                            }
                        }
                    } else {
                        // C'est un objet/tableau associatif
                        if (isset($item['id'])) {
                            $allResidences[] = $item;
                        }
                    }
                }
            }
            
            // Vérifier s'il y a plus de pages
            // Si on a récupéré moins que le limit, on a atteint la fin
            // Ou si on a déjà récupéré toutes les résidences (total connu)
            $hasMore = count($residences) >= $currentLimit;
            
            // Si on connaît le total et qu'on a tout récupéré, arrêter
            if (isset($total) && $total > 0 && count($allResidences) >= $total) {
                $hasMore = false;
            }
            
            $page++;
            
        } while ($hasMore && $page <= 10); // Limite de sécurité : max 10 pages
        
        return $allResidences;
    }

    /**
     * Récupérer une résidence par ID
     * Endpoint: GET /api/residences/:id
     */
    public function getResidence(string $id): ?array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification');
            return null;
        }

        try {
            $url = "{$this->baseUrl}/residences/{$id}";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get($url);

            // Si 401, le token est peut-être expiré, on réessaie une fois
            if ($response->status() === 401) {
                Log::warning('Token expiré, tentative de renouvellement', [
                    'endpoint' => "residences/{$id}",
                ]);
                
                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                        ])
                        ->get($url);
                } else {
                    Log::error('Impossible de renouveler le token après 401');
                    return null;
                }
            }

            if ($response->successful()) {
                $data = $response->json();

                // Gérer les réponses avec structure { success: true, data: {...} }
                if (isset($data['data'])) {
                    // Si data est un objet unique (pas un tableau)
                    if (is_array($data['data']) && isset($data['data']['id'])) {
                        return $data['data'];
                    }
                    // Si data est un tableau (structure inattendue)
                    if (is_array($data['data']) && isset($data['data'][0])) {
                        return $data['data'][0];
                    }
                }

                // Si c'est directement l'objet résidence
                if (is_array($data) && isset($data['id'])) {
                    return $data;
                }
                
                Log::warning('Structure de réponse inattendue pour getResidence', [
                    'id' => $id,
                    'data' => $data,
                ]);
                
                return null;
            }

            Log::warning('API DodoVroum GET residence error', [
                'id' => $id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('API DodoVroum getResidence exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Créer une nouvelle résidence
     * Endpoint: POST /api/residences
     */
    public function createResidence(array $data): array
    {
        return $this->post('residences', $data);
    }

    /**
     * Mettre à jour une résidence
     * Endpoint: PATCH /api/residences/:id
     */
    public function updateResidence(string $id, array $data): array
    {
        // L'API utilise PATCH, pas PUT
        return $this->patch("residences/{$id}", $data);
    }

    /**
     * Bloquer des dates pour une résidence
     * Endpoint: POST /api/residences/:id/blocked-dates
     */
    public function blockResidenceDates(string $id, array $data): array
    {
        try {
            return $this->post("residences/{$id}/blocked-dates", $data);
        } catch (\Exception $e) {
            Log::error('Erreur lors du blocage des dates pour la résidence', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Récupérer les dates bloquées d'une résidence
     * Endpoint: GET /api/residences/:id/blocked-dates
     */
    public function getResidenceBlockedDates(string $id): array
    {
        try {
            $result = $this->get("residences/{$id}/blocked-dates");
            // Normaliser la réponse
            if (isset($result['data']) && is_array($result['data'])) {
                return $result['data'];
            }
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            // Si l'endpoint n'existe pas encore, retourner un tableau vide
            Log::warning('Endpoint blocked-dates non disponible pour la résidence', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Débloquer des dates pour une résidence
     * Endpoint: DELETE /api/residences/:id/blocked-dates/:blockedDateId
     */
    public function unblockResidenceDates(string $id, string $blockedDateId): bool
    {
        try {
            return $this->delete("residences/{$id}/blocked-dates/{$blockedDateId}");
        } catch (\Exception $e) {
            Log::error('Erreur lors du déblocage des dates pour la résidence', [
                'id' => $id,
                'blockedDateId' => $blockedDateId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Supprimer une résidence
     * Endpoint: DELETE /api/residences/:id
     */
    public function deleteResidence(string $id): bool
    {
        return $this->delete("residences/{$id}");
    }

    /**
     * Récupérer tous les véhicules
     * Endpoint: GET /api/vehicles
     */
    public function getVehicles(array $filters = []): array
    {
        // L'API limite à 100 résultats par page, on doit paginer
        $limit = 100;
        $page = 1;
        $allVehicles = [];
        
        do {
            $queryParams = array_merge($filters, [
                'limit' => $limit,
                'page' => $page,
            ]);
            
            $response = $this->get('vehicles', $queryParams);
            
            // Si l'API retourne une structure paginée
            if (isset($response['data']) && is_array($response['data'])) {
                $vehicles = $response['data'];
                $total = $response['total'] ?? count($vehicles);
                $currentLimit = $response['limit'] ?? $limit;
            } elseif (is_array($response)) {
                // Si c'est directement un tableau
                $vehicles = $response;
                $total = count($vehicles);
                $currentLimit = $limit;
            } else {
                $vehicles = [];
                $total = 0;
                $currentLimit = $limit;
            }
            
            // Aplatir les tableaux imbriqués
            foreach ($vehicles as $item) {
                if (is_array($item)) {
                    $keys = array_keys($item);
                    $isNumericArray = !empty($keys) && $keys === range(0, count($item) - 1);
                    
                    if ($isNumericArray) {
                        // C'est un tableau de tableaux, on aplatit
                        foreach ($item as $subItem) {
                            if (is_array($subItem) && isset($subItem['id'])) {
                                $allVehicles[] = $subItem;
                            }
                        }
                    } else {
                        // C'est un objet/tableau associatif
                        if (isset($item['id'])) {
                            $allVehicles[] = $item;
                        }
                    }
                }
            }
            
            // Vérifier s'il y a plus de pages
            // Si on a récupéré moins que le limit, on a atteint la fin
            // Ou si on a déjà récupéré tous les véhicules (total connu)
            $hasMore = count($vehicles) >= $currentLimit;
            
            // Si on connaît le total et qu'on a tout récupéré, arrêter
            if (isset($total) && $total > 0 && count($allVehicles) >= $total) {
                $hasMore = false;
            }
            
            $page++;
            
        } while ($hasMore && $page <= 10); // Limite de sécurité : max 10 pages
        
        return $allVehicles;
    }

    /**
     * Récupérer un véhicule par ID
     * Endpoint: GET /api/vehicles/:id
     */
    public function getVehicle(string $id): ?array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification pour getVehicle');
            return null;
        }

        try {
            $url = "{$this->baseUrl}/vehicles/{$id}";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get($url);

            // Si 401, le token est peut-être expiré, on réessaie une fois
            if ($response->status() === 401) {
                Log::warning('Token expiré pour getVehicle, tentative de renouvellement', ['id' => $id]);
                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                        ])
                        ->get($url);
                } else {
                    Log::error('Impossible de renouveler le token après 401 pour getVehicle');
                    return null;
                }
            }

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('API DodoVroum GET vehicle success', [
                    'id' => $id,
                    'data_type' => gettype($data),
                    'data_keys' => is_array($data) ? array_keys($data) : null,
                ]);
                
                // Gérer les réponses avec structure { success: true, data: {...} }
                if (isset($data['data'])) {
                    // Si data est un objet unique (pas un tableau)
                    if (is_array($data['data']) && isset($data['data']['id'])) {
                        // Vérifier si c'est bien un véhicule (a des champs de véhicule)
                        if (isset($data['data']['marque']) || isset($data['data']['brand']) || isset($data['data']['titre'])) {
                            Log::info('Véhicule trouvé dans data', [
                                'id' => $data['data']['id'],
                                'all_keys' => array_keys($data['data']),
                            ]);
                            return $data['data'];
                        }
                        // Si data.data existe et contient le véhicule
                        if (isset($data['data']['data']) && is_array($data['data']['data'])) {
                            if (isset($data['data']['data']['id']) && (isset($data['data']['data']['marque']) || isset($data['data']['data']['brand']))) {
                                Log::info('Véhicule trouvé dans data.data', ['id' => $data['data']['data']['id']]);
                                return $data['data']['data'];
                            }
                        }
                    }
                    // Si data est un tableau (structure inattendue)
                    if (is_array($data['data']) && isset($data['data'][0])) {
                        Log::info('Véhicule trouvé dans data[0]', ['id' => $data['data'][0]['id'] ?? null]);
                        return $data['data'][0];
                    }
                }
                
                // Si c'est directement l'objet véhicule
                if (is_array($data) && isset($data['id']) && (isset($data['marque']) || isset($data['brand']) || isset($data['titre']))) {
                    Log::info('Véhicule trouvé directement', ['id' => $data['id']]);
                    return $data;
                }
                
                Log::warning('Structure de réponse inattendue pour getVehicle', [
                    'id' => $id,
                    'data_keys' => is_array($data) ? array_keys($data) : null,
                ]);
                
                return null;
            }

            Log::warning('API DodoVroum GET vehicle error', [
                'id' => $id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('API DodoVroum getVehicle exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Créer un nouveau véhicule
     * Endpoint: POST /api/vehicles
     */
    public function createVehicle(array $data): array
    {
        return $this->post('vehicles', $data);
    }

    /**
     * Mettre à jour un véhicule
     * Endpoint: PATCH /api/vehicles/:id
     */
    public function updateVehicle(string $id, array $data): array
    {
        // L'API utilise PATCH, pas PUT
        return $this->patch("vehicles/{$id}", $data);
    }

    /**
     * Bloquer des dates pour un véhicule
     * Endpoint: POST /api/vehicles/:id/blocked-dates
     */
    public function blockVehicleDates(string $id, array $data): array
    {
        return $this->post("vehicles/{$id}/blocked-dates", $data);
    }

    /**
     * Récupérer les dates bloquées d'un véhicule
     * Endpoint: GET /api/vehicles/:id/blocked-dates
     */
    public function getVehicleBlockedDates(string $id): array
    {
        try {
            $result = $this->get("vehicles/{$id}/blocked-dates");
            if (isset($result['data']) && is_array($result['data'])) {
                return $result['data'];
            }
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            Log::warning('Endpoint blocked-dates non disponible pour le véhicule', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Débloquer des dates pour un véhicule
     * Endpoint: DELETE /api/vehicles/:id/blocked-dates/:blockedDateId
     */
    public function unblockVehicleDates(string $id, string $blockedDateId): bool
    {
        return $this->delete("vehicles/{$id}/blocked-dates/{$blockedDateId}");
    }

    /**
     * Supprimer un véhicule
     * Endpoint: DELETE /api/vehicles/:id
     */
    public function deleteVehicle(string $id): bool
    {
        return $this->delete("vehicles/{$id}");
    }

    /**
     * Récupérer les offres combinées avec pagination
     * Endpoint: GET /api/offers
     */
    public function getComboOffers(array $filters = []): array
    {
        $allOffers = [];
        $page = 1;
        $maxPages = 10;
        
        while ($page <= $maxPages) {
            $params = array_merge($filters, [
                'page' => $page,
                'limit' => 100, // Limite de l'API
            ]);
            
            $response = $this->get('offers', $params);
            
            if (empty($response)) {
                break;
            }
            
            // Gérer différentes structures de réponse
            $items = [];
            if (isset($response['data']) && is_array($response['data'])) {
                if (isset($response['data']['data']) && is_array($response['data']['data'])) {
                    $items = $response['data']['data'];
                } elseif (isset($response['data'][0])) {
                    $items = $response['data'];
                } else {
                    $items = [$response['data']];
                }
            } elseif (is_array($response) && isset($response[0])) {
                $items = $response;
            }
            
            if (empty($items)) {
                break;
            }
            
            $allOffers = array_merge($allOffers, $items);
            
            // Si on a moins de 100 items, on a récupéré toutes les pages
            if (count($items) < 100) {
                break;
            }
            
            $page++;
        }
        
        return $allOffers;
    }

    /**
     * Récupérer une offre combinée par ID
     * Endpoint: GET /api/offers/:id
     */
    public function getComboOffer(string $id): ?array
    {
        try {
            $token = $this->resolveRequestToken();

            if (!$token) {
                Log::error('Impossible d\'obtenir le token d\'authentification pour getComboOffer');
                return null;
            }

            $url = "{$this->baseUrl}/offers/{$id}";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->status() === 401) {
                Log::warning('Token expiré pour getComboOffer, tentative de renouvellement', ['id' => $id]);
                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                        ])
                        ->get($url);
                } else {
                    Log::error('Impossible de renouveler le token après 401 pour getComboOffer');
                    return null;
                }
            }

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('API DodoVroum GET combo offer success', [
                    'id' => $id,
                    'url' => $url,
                    'data_type' => gettype($data),
                    'data_keys' => is_array($data) ? array_keys($data) : null,
                ]);

                $offerData = null;

                // Gérer les réponses avec structure { success: true, data: {...} }
                if (isset($data['data'])) {
                    // Si data est un objet unique (pas un tableau)
                    if (is_array($data['data']) && isset($data['data']['id'])) {
                        $offerData = $data['data'];
                    }
                    // Si data est un tableau (structure inattendue pour un single item, mais on gère)
                    elseif (is_array($data['data']) && isset($data['data'][0])) {
                        $offerData = $data['data'][0];
                    }
                }
                // Si c'est directement l'objet offre
                elseif (is_array($data) && isset($data['id'])) {
                    $offerData = $data;
                }
                
                if ($offerData) {
                    Log::info('Offre combinée trouvée', ['id' => $offerData['id'] ?? null]);
                    
                    // Log détaillé de la structure de l'offre
                    Log::info('Structure complète de l\'offre combinée depuis l\'API', [
                        'offer_id' => $offerData['id'] ?? null,
                        'offer_keys' => array_keys($offerData),
                        'has_residence' => isset($offerData['residence']),
                        'has_voiture' => isset($offerData['voiture']),
                        'has_vehicle' => isset($offerData['vehicle']),
                        'residence_structure' => isset($offerData['residence']) ? [
                            'keys' => array_keys($offerData['residence']),
                            'id' => $offerData['residence']['id'] ?? null,
                            'has_proprietaire' => isset($offerData['residence']['proprietaire']),
                            'has_owner' => isset($offerData['residence']['owner']),
                            'proprietaire_keys' => isset($offerData['residence']['proprietaire']) && is_array($offerData['residence']['proprietaire']) ? array_keys($offerData['residence']['proprietaire']) : null,
                        ] : null,
                        'vehicle_structure' => isset($offerData['voiture']) ? [
                            'keys' => array_keys($offerData['voiture']),
                            'id' => $offerData['voiture']['id'] ?? null,
                            'has_proprietaire' => isset($offerData['voiture']['proprietaire']),
                            'has_owner' => isset($offerData['voiture']['owner']),
                            'proprietaire_keys' => isset($offerData['voiture']['proprietaire']) && is_array($offerData['voiture']['proprietaire']) ? array_keys($offerData['voiture']['proprietaire']) : null,
                        ] : (isset($offerData['vehicle']) ? [
                            'keys' => array_keys($offerData['vehicle']),
                            'id' => $offerData['vehicle']['id'] ?? null,
                            'has_proprietaire' => isset($offerData['vehicle']['proprietaire']),
                            'has_owner' => isset($offerData['vehicle']['owner']),
                            'proprietaire_keys' => isset($offerData['vehicle']['proprietaire']) && is_array($offerData['vehicle']['proprietaire']) ? array_keys($offerData['vehicle']['proprietaire']) : null,
                        ] : null),
                    ]);
                    
                    return $offerData;
                }
                
                Log::warning('Structure de réponse inattendue pour getComboOffer', [
                    'id' => $id,
                    'data' => $data,
                    'extracted_offer_data' => $offerData,
                ]);
                
                return null;
            }

            Log::warning('API DodoVroum GET error for single combo offer', [
                'id' => $id,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('API DodoVroum getComboOffer exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Créer une nouvelle offre combinée
     * Endpoint: POST /api/offers
     */
    public function createComboOffer(array $data): array
    {
        return $this->post('offers', $data);
    }

    /**
     * Mettre à jour une offre combinée
     * Endpoint: PATCH /api/offers/:id
     */
    public function updateComboOffer(string $id, array $data): array
    {
        return $this->patch("offers/{$id}", $data);
    }

    /**
     * Bloquer des dates pour une offre combinée
     * Endpoint: POST /api/offers/:id/blocked-dates
     */
    public function blockComboOfferDates(string $id, array $data): array
    {
        return $this->post("offers/{$id}/blocked-dates", $data);
    }

    /**
     * Récupérer les dates bloquées d'une offre combinée
     * Endpoint: GET /api/offers/:id/blocked-dates
     */
    public function getComboOfferBlockedDates(string $id): array
    {
        try {
            $result = $this->get("offers/{$id}/blocked-dates");
            if (isset($result['data']) && is_array($result['data'])) {
                return $result['data'];
            }
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            Log::warning('Endpoint blocked-dates non disponible pour l\'offre combinée', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Débloquer des dates pour une offre combinée
     * Endpoint: DELETE /api/offers/:id/blocked-dates/:blockedDateId
     */
    public function unblockComboOfferDates(string $id, string $blockedDateId): bool
    {
        return $this->delete("offers/{$id}/blocked-dates/{$blockedDateId}");
    }

    /**
     * Supprimer une offre combinée
     * Endpoint: DELETE /api/offers/:id
     */
    public function deleteComboOffer(string $id): bool
    {
        return $this->delete("offers/{$id}");
    }

    /**
     * Récupérer tous les utilisateurs
     * Endpoint: GET /api/users
     */
    public function getUsers(array $filters = []): array
    {
        return $this->get('users', $filters);
    }

    /**
     * Récupérer un utilisateur par ID
     * Endpoint: GET /api/users/:id
     */
    public function getUser(string $id): ?array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification pour getUser');
            return null;
        }

        try {
            $url = "{$this->baseUrl}/users/{$id}";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->status() === 401) {
                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                        ])
                        ->get($url);
                } else {
                    return null;
                }
            }

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data'])) {
                    return $data['data'];
                }
                
                if (is_array($data) && isset($data['id'])) {
                    return $data;
                }
                
                return null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('API DodoVroum getUser exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Créer un nouvel utilisateur
     * Endpoint: POST /api/users
     */
    public function createUser(array $data): array
    {
        Log::info('DodoVroumApiService::createUser - Données à envoyer', [
            'endpoint' => 'users',
            'data' => $data,
        ]);
        
        return $this->post('users', $data);
    }

    /**
     * Mettre à jour un utilisateur
     * Endpoint: PATCH /api/users/:id
     */
    public function updateUser(string $id, array $data): array
    {
        return $this->patch("users/{$id}", $data);
    }

    /**
     * Supprimer un utilisateur
     * Endpoint: DELETE /api/users/:id
     */
    public function deleteUser(string $id): bool
    {
        return $this->delete("users/{$id}");
    }

    /**
     * Approuver la vérification d'identité d'un utilisateur
     * Endpoint: PATCH /api/users/:id/identity-verification/approve
     */
    public function approveIdentityVerification(string $userId): array
    {
        return $this->patch("users/{$userId}/identity-verification/approve", []);
    }

    /**
     * Rejeter la vérification d'identité d'un utilisateur
     * Endpoint: PATCH /api/users/:id/identity-verification/reject
     */
    public function rejectIdentityVerification(string $userId, string $reason): array
    {
        return $this->patch("users/{$userId}/identity-verification/reject", [
            'rejectionReason' => $reason,
        ]);
    }

    /**
     * Récupérer les types de véhicules disponibles depuis l'API
     */
    public function getVehicleTypes(): array
    {
        try {
            $token = $this->resolveRequestToken();

            if (!$token) {
                Log::error('Impossible d\'obtenir le token d\'authentification pour getVehicleTypes');
                return [];
            }

            // Essayer plusieurs endpoints possibles (ordre de priorité)
            $endpoints = [
                'vehicles/types',        // Endpoint principal
                'vehicles/categories',   // Alias
                'vehicle-types',         // Endpoint alternatif
            ];

            foreach ($endpoints as $endpoint) {
                try {
                    $url = "{$this->baseUrl}/{$endpoint}";
                    
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                        ])
                        ->get($url);

                    if ($response->successful()) {
                        $data = $response->json();
                        
                        Log::info('API DodoVroum GET vehicle types success', [
                            'endpoint' => $endpoint,
                            'data_type' => gettype($data),
                            'data_keys' => is_array($data) ? array_keys($data) : null,
                        ]);

                        // Gérer différentes structures de réponse
                        $types = [];
                        
                        if (isset($data['data']) && is_array($data['data'])) {
                            $types = $data['data'];
                        } elseif (is_array($data) && isset($data[0])) {
                            $types = $data;
                        } elseif (is_array($data) && !empty($data)) {
                            // Si c'est un objet associatif, le convertir en tableau
                            $types = array_values($data);
                        }

                        // Normaliser les types pour avoir un format uniforme
                        $normalizedTypes = [];
                        foreach ($types as $type) {
                            if (is_string($type)) {
                                // Si c'est juste une string, créer un objet avec value et label
                                $normalizedTypes[] = [
                                    'value' => strtolower($type),
                                    'label' => ucfirst($type),
                                ];
                            } elseif (is_array($type)) {
                                // Si c'est déjà un objet, extraire value et label
                                $value = $type['value'] ?? $type['id'] ?? $type['name'] ?? $type['type'] ?? null;
                                $label = $type['label'] ?? $type['name'] ?? $type['title'] ?? $value ?? null;
                                
                                if ($value && $label) {
                                    // Préserver la casse originale de la value (l'API peut retourner "CAR", "SUV", etc.)
                                    // mais normaliser en minuscules pour la compatibilité avec le frontend
                                    $normalizedTypes[] = [
                                        'value' => strtolower($value),
                                        'label' => $label,
                                    ];
                                }
                            }
                        }

                        if (!empty($normalizedTypes)) {
                            Log::info('Types de véhicules récupérés depuis l\'API', [
                                'endpoint' => $endpoint,
                                'count' => count($normalizedTypes),
                            ]);
                            return $normalizedTypes;
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug('Tentative endpoint échouée pour getVehicleTypes', [
                        'endpoint' => $endpoint,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            // Si aucun endpoint ne fonctionne, retourner les types par défaut
            Log::warning('Aucun endpoint API ne fonctionne pour getVehicleTypes, utilisation des types par défaut');
            return [
                ['value' => 'berline', 'label' => 'Berline'],
                ['value' => 'suv', 'label' => 'SUV'],
                ['value' => '4x4', 'label' => '4x4'],
                ['value' => 'utilitaire', 'label' => 'Utilitaire'],
                ['value' => 'moto', 'label' => 'Moto'],
            ];
        } catch (\Exception $e) {
            Log::error('API DodoVroum getVehicleTypes exception', [
                'error' => $e->getMessage(),
            ]);

            // Retourner les types par défaut en cas d'erreur
            return [
                ['value' => 'berline', 'label' => 'Berline'],
                ['value' => 'suv', 'label' => 'SUV'],
                ['value' => '4x4', 'label' => '4x4'],
                ['value' => 'utilitaire', 'label' => 'Utilitaire'],
                ['value' => 'moto', 'label' => 'Moto'],
            ];
        }
    }

    /**
     * Récupérer tous les avis
     * Endpoint: GET /api/reviews
     */
    public function getReviews(array $filters = []): array
    {
        return $this->get('reviews', $filters);
    }

    /**
     * Récupérer un avis par ID
     * Endpoint: GET /api/reviews/:id
     */
    public function getReview(string $id): ?array
    {
        $token = $this->resolveRequestToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification pour getReview');
            return null;
        }

        try {
            $url = "{$this->baseUrl}/reviews/{$id}";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->status() === 401) {
                Log::warning('Token expiré pour getReview, tentative de renouvellement', ['id' => $id]);
                $this->forgetTokenCache();
                $token = $this->getAccessToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                        ])
                        ->get($url);
                } else {
                    Log::error('Impossible de renouveler le token après 401 pour getReview');
                    return null;
                }
            }

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('API DodoVroum GET review success', [
                    'id' => $id,
                    'data_type' => gettype($data),
                    'data_keys' => is_array($data) ? array_keys($data) : null,
                ]);
                
                // Gérer les réponses avec structure { success: true, data: {...} }
                if (isset($data['data'])) {
                    // Si data est un objet unique (pas un tableau)
                    if (is_array($data['data']) && isset($data['data']['id'])) {
                        Log::info('Avis trouvé dans data', [
                            'id' => $data['data']['id'],
                            'all_keys' => array_keys($data['data']),
                        ]);
                        return $data['data'];
                    }
                    // Si data est un tableau (structure inattendue)
                    if (is_array($data['data']) && isset($data['data'][0])) {
                        Log::info('Avis trouvé dans data[0]', ['id' => $data['data'][0]['id'] ?? null]);
                        return $data['data'][0];
                    }
                }
                
                // Si c'est directement l'objet avis
                if (is_array($data) && isset($data['id'])) {
                    Log::info('Avis trouvé directement', ['id' => $data['id']]);
                    return $data;
                }
                
                Log::warning('Structure de réponse inattendue pour getReview', [
                    'id' => $id,
                    'data' => $data,
                ]);
                
                return null;
            }

            Log::warning('API DodoVroum GET review error', [
                'id' => $id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('API DodoVroum getReview exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Récupérer les avis d'une réservation spécifique
     * Endpoint: GET /api/reviews?bookingId=:bookingId
     */
    public function getReviewsByBooking(string $bookingId): array
    {
        return $this->getReviews(['bookingId' => $bookingId]);
    }

    /**
     * Formater les dates pour l'affichage
     */
    protected function formatDates(?string $startDate, ?string $endDate): string
    {
        if (!$startDate || !$endDate) {
            return 'Dates non définies';
        }

        try {
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);

            $startFormatted = $start->format('d M');
            $endFormatted = $end->format('d M');

            return "{$startFormatted} - {$endFormatted}";
        } catch (\Exception $e) {
            return "{$startDate} - {$endDate}";
        }
    }

    /**
     * Formater le statut pour l'affichage
     */
    protected function formatStatus(string $status): string
    {
        $statusMap = [
            'confirmed' => 'Confirmée',
            'pending' => 'En attente',
            'cancelled' => 'Annulée',
            'completed' => 'Terminée',
        ];

        return $statusMap[strtolower($status)] ?? ucfirst($status);
    }
}

