<?php

namespace App\Services\DodoVroumApi;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\DodoVroumApiException;

class AuthService
{
    protected string $baseUrl;
    protected string $adminEmail;
    protected string $adminPassword;

    public function __construct()
    {
        $this->baseUrl = config('services.dodovroum.api_url', 'http://localhost:3000/api');
        $this->adminEmail = config('services.dodovroum.admin_email', 'admin@dodovroum.com');
        $this->adminPassword = config('services.dodovroum.admin_password', 'admin123');

        // Valider la configuration
        if (empty($this->baseUrl) || empty($this->adminEmail) || empty($this->adminPassword)) {
            throw new \RuntimeException('Configuration API DodoVroum incomplète. Vérifiez votre fichier .env');
        }
    }

    /**
     * Obtenir le token d'authentification (avec cache)
     */
    public function getAccessToken(?string $email = null, ?string $password = null): ?string
    {
        $email = $email ?? $this->adminEmail;
        $password = $password ?? $this->adminPassword;
        
        $cacheKey = $this->getCacheKey($email);
        
        // Vérifier le cache (token valide pendant 55 minutes)
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken) {
            return $cachedToken;
        }

        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/auth/login", [
                'email' => $email,
                'password' => $password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $token = $data['data']['access_token'] 
                    ?? $data['access_token'] 
                    ?? $data['token'] 
                    ?? null;

                if ($token) {
                    // Mettre en cache pendant 55 minutes
                    Cache::put($cacheKey, $token, now()->addMinutes(55));
                    Log::debug('Token d\'authentification obtenu avec succès', ['email' => $email]);
                    return $token;
                }
            }

            // Extraire le message d'erreur de la réponse
            $errorBody = $response->json();
            $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? 'Erreur d\'authentification';
            
            Log::error('Échec authentification API DodoVroum', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
            ]);

            // Message d'erreur plus spécifique selon le code de statut
            if ($response->status() === 401) {
                $message = $errorMessage === 'Identifiants invalides' 
                    ? 'Identifiants invalides. Vérifiez que DODOVROUM_ADMIN_EMAIL et DODOVROUM_ADMIN_PASSWORD dans votre fichier .env sont corrects.'
                    : $errorMessage;
                throw DodoVroumApiException::authenticationFailed($message);
            }

            throw DodoVroumApiException::authenticationFailed($errorMessage ?: 'Impossible de s\'authentifier auprès de l\'API');
        } catch (DodoVroumApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Exception lors de l\'authentification API DodoVroum', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw DodoVroumApiException::authenticationFailed('Erreur lors de l\'authentification : ' . $e->getMessage());
        }
    }

    /**
     * Effacer le token du cache (utile pour forcer un renouvellement)
     */
    public function clearToken(?string $email = null): void
    {
        $email = $email ?? $this->adminEmail;
        $cacheKey = $this->getCacheKey($email);
        Cache::forget($cacheKey);
    }

    /**
     * Obtenir la clé de cache pour un email
     */
    protected function getCacheKey(string $email): string
    {
        return 'dodovroum_api_token_' . md5($email);
    }
}

