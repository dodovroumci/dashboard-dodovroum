<?php

namespace App\Services\DodoVroumApi;

use App\Exceptions\DodoVroumApiException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseApiService
{
    protected string $baseUrl;
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->baseUrl = config('services.dodovroum.api_url', 'http://localhost:3000/api');
        $this->authService = $authService;
    }

    /**
     * Obtenir le token d'authentification (utilisateur connecté ou admin)
     */
    protected function getAuthToken(): ?string
    {
        // Pour les admins, toujours utiliser le token admin pour avoir accès à toutes les données
        if (Auth::check()) {
            $user = Auth::user();
            $isAdmin = method_exists($user, 'isAdmin') 
                ? $user->isAdmin() 
                : ($user->role ?? 'owner') === 'admin';
            
            // Si c'est un admin, utiliser le token admin pour avoir accès à toutes les données
            if ($isAdmin) {
                return $this->authService->getAccessToken();
            }
            
            // Pour les propriétaires, utiliser leur token personnel
            if (method_exists($user, 'getApiToken')) {
                $userToken = $user->getApiToken();
                if ($userToken) {
                    return $userToken;
                } else {
                    Log::warning('Token utilisateur non disponible, utilisation du token admin', [
                        'user_id' => $user->getAuthIdentifier(),
                    ]);
                }
            }
        }

        // Par défaut, utiliser le token admin
        return $this->authService->getAccessToken();
    }

    /**
     * Faire une requête GET à l'API avec authentification
     */
    protected function get(string $endpoint, array $query = []): array
    {
        try {
            $token = $this->getAuthToken();

            if (!$token) {
                Log::error('Impossible d\'obtenir le token d\'authentification', [
                    'endpoint' => $endpoint,
                    'base_url' => $this->baseUrl,
                    'admin_email' => config('services.dodovroum.admin_email'),
                ]);
                throw DodoVroumApiException::authenticationFailed('Impossible d\'obtenir le token d\'authentification');
            }

            $url = "{$this->baseUrl}/{$endpoint}";

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get($url, $query);

            // Si 401, le token est peut-être expiré, on réessaie une fois
            if ($response->status() === 401) {
                Log::warning('Token expiré, tentative de renouvellement', ['endpoint' => $endpoint]);
                $this->authService->clearToken();
                $token = $this->getAuthToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                        ])
                        ->get($url, $query);
                } else {
                    Log::error('Impossible de renouveler le token après 401', [
                        'endpoint' => $endpoint,
                        'url' => $url,
                    ]);
                    throw DodoVroumApiException::authenticationFailed('Impossible de renouveler le token');
                }
            }

            if ($response->successful()) {
                $data = $response->json();
                return ApiResponseNormalizer::data($data);
            }

            // Extraire le message d'erreur pour les autres codes d'erreur
            $errorBody = $response->json();
            $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? 'Erreur inconnue';
            
            Log::warning('API DodoVroum GET error', [
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
            ]);

            // Pour les erreurs 404, retourner un tableau vide plutôt que de lancer une exception
            if ($response->status() === 404) {
                Log::info('Endpoint non trouvé (404), retour d\'un tableau vide', ['endpoint' => $endpoint]);
                return [];
            }

            // Pour les autres erreurs, lancer une exception
            throw DodoVroumApiException::requestFailed($errorMessage ?: 'Erreur lors de la requête GET', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);
        } catch (DodoVroumApiException $e) {
            // Re-lancer les exceptions API telles quelles
            throw $e;
        } catch (\Exception $e) {
            Log::error('API DodoVroum exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw DodoVroumApiException::requestFailed('Erreur lors de la requête GET : ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Faire une requête GET pour un objet unique
     */
    protected function getSingle(string $endpoint): ?array
    {
        $token = $this->getAuthToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification', ['endpoint' => $endpoint]);
            throw DodoVroumApiException::authenticationFailed();
        }

        try {
            $url = "{$this->baseUrl}/{$endpoint}";
            
            Log::debug('BaseApiService::getSingle - Requête HTTP', [
                'endpoint' => $endpoint,
                'url' => $url,
                'has_token' => !empty($token),
            ]);
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->get($url);
            
            Log::debug('BaseApiService::getSingle - Réponse HTTP reçue', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if ($response->status() === 401) {
                Log::warning('Token expiré, tentative de renouvellement', ['endpoint' => $endpoint]);
                $this->authService->clearToken();
                $token = $this->getAuthToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                        ])
                        ->get($url);
                } else {
                    throw DodoVroumApiException::authenticationFailed('Impossible de renouveler le token');
                }
            }

            if ($response->successful()) {
                $data = $response->json();
                
                Log::debug('BaseApiService::getSingle - Réponse API réussie', [
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'response_type' => gettype($data),
                    'response_keys' => is_array($data) ? array_keys($data) : null,
                    'has_id' => is_array($data) && isset($data['id']),
                    'has__id' => is_array($data) && isset($data['_id']),
                    'has_data' => is_array($data) && isset($data['data']),
                ]);
                
                $normalized = ApiResponseNormalizer::single($data);
                
                Log::debug('BaseApiService::getSingle - Données normalisées', [
                    'endpoint' => $endpoint,
                    'normalized_found' => !empty($normalized),
                    'normalized_keys' => $normalized ? array_keys($normalized) : null,
                    'normalized_id' => $normalized['id'] ?? $normalized['_id'] ?? null,
                ]);
                
                return $normalized;
            }

            if ($response->status() === 404) {
                Log::warning('BaseApiService::getSingle - 404 Not Found', [
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'response_body' => $response->body(),
                ]);
                return null;
            }

            Log::warning('API DodoVroum GET single error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (DodoVroumApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('API DodoVroum GET single exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw DodoVroumApiException::requestFailed('Erreur lors de la requête GET', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Faire une requête POST à l'API avec authentification
     */
    protected function post(string $endpoint, array $data = []): array
    {
        $token = $this->getAuthToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification', ['endpoint' => $endpoint]);
            throw DodoVroumApiException::authenticationFailed();
        }

        // Nettoyer les données avant l'envoi (tronquer les descriptions trop longues)
        $data = $this->sanitizeDataBeforeSend($data, $endpoint);
        
        // Log détaillé des données envoyées (pour debug)
        Log::debug('Requête POST API DodoVroum - Données envoyées', [
            'endpoint' => $endpoint,
            'url' => "{$this->baseUrl}/{$endpoint}",
            'data_keys' => array_keys($data),
            'has_seats' => isset($data['seats']),
            'has_capacity' => isset($data['capacity']),
            'seats_value' => $data['seats'] ?? null,
            'capacity_value' => $data['capacity'] ?? null,
            'full_data' => $data, // Log complet pour debug
        ]);

        try {
            $url = "{$this->baseUrl}/{$endpoint}";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $data);

            if ($response->status() === 401) {
                Log::warning('Token expiré, tentative de renouvellement', ['endpoint' => $endpoint]);
                $this->authService->clearToken();
                $token = $this->getAuthToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->post($url, $data);
                } else {
                    throw DodoVroumApiException::authenticationFailed('Impossible de renouveler le token');
                }
            }

            if ($response->successful()) {
                $result = $response->json();
                return ApiResponseNormalizer::data($result);
            }

            // Gérer les erreurs 403 spécifiquement
            if ($response->status() === 403) {
                $errorBody = $response->json();
                $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? 'Cette action n\'est pas autorisée';
                
                if (is_array($errorMessage)) {
                    $errorMessage = implode(', ', $errorMessage);
                }

                Log::error('API DodoVroum POST 403 Forbidden', [
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'error_message' => $errorMessage,
                    'error_body' => $errorBody,
                    'data_sent' => $data,
                ]);

                throw DodoVroumApiException::forbidden($errorMessage ?: 'Cette action n\'est pas autorisée', [
                    'endpoint' => $endpoint,
                    'url' => $url,
                ]);
            }

            // Extraire le message d'erreur pour les autres erreurs
            $errorBody = $response->json();
            $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? $response->body();
            
            if (is_array($errorMessage)) {
                $errorMessage = implode(', ', $errorMessage);
            }

            Log::warning('API DodoVroum POST error', [
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $response->status(),
                'error_message' => $errorMessage,
                'error_body' => $errorBody,
                'data_sent' => $data,
            ]);

            throw DodoVroumApiException::requestFailed($errorMessage ?: 'Erreur lors de la requête POST', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);
        } catch (DodoVroumApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('API DodoVroum POST exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw DodoVroumApiException::requestFailed('Erreur lors de la requête POST', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Nettoyer les données avant l'envoi à l'API (tronquer les descriptions trop longues)
     */
    protected function sanitizeDataBeforeSend(array $data, string $endpoint): array
    {
        // Tronquer la description à 500 caractères pour éviter les erreurs de base de données
        if (isset($data['description']) && is_string($data['description'])) {
            $originalLength = mb_strlen($data['description']);
            if ($originalLength > 500) {
                Log::warning('Description trop longue détectée avant envoi API, troncature finale', [
                    'endpoint' => $endpoint,
                    'original_length' => $originalLength,
                    'truncated_length' => 500,
                ]);
                $data['description'] = mb_substr($data['description'], 0, 500);
            }
        }
        
        // Pour les véhicules, supprimer explicitement 'places' et 'capacity' car l'API attend 'seats'
        // ⚠️ NE PAS supprimer 'seats' car l'API NestJS l'attend dans le DTO de validation
        if ($endpoint === 'vehicles' || strpos($endpoint, 'vehicles/') === 0) {
            unset($data['places']);
            unset($data['capacity']); // capacity n'est pas attendu par le DTO, seulement seats
            unset($data['fuel']); // fuel est mappé vers fuelType
        }
        
        return $data;
    }

    /**
     * Faire une requête PATCH à l'API avec authentification
     */
    protected function patch(string $endpoint, array $data = []): array
    {
        $token = $this->getAuthToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification', ['endpoint' => $endpoint]);
            throw DodoVroumApiException::authenticationFailed();
        }

        // Nettoyer les données avant l'envoi (tronquer les descriptions trop longues)
        $data = $this->sanitizeDataBeforeSend($data, $endpoint);

        try {
            $url = "{$this->baseUrl}/{$endpoint}";
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->patch($url, $data);

            if ($response->status() === 401) {
                Log::warning('Token expiré, tentative de renouvellement', ['endpoint' => $endpoint]);
                $this->authService->clearToken();
                $token = $this->getAuthToken();

                if ($token) {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                        ])
                        ->patch($url, $data);
                } else {
                    throw DodoVroumApiException::authenticationFailed('Impossible de renouveler le token');
                }
            }

            if ($response->successful()) {
                $result = $response->json();

                // Pour les réponses PATCH, utiliser single() au lieu de data() pour obtenir l'objet directement
                $normalized = ApiResponseNormalizer::single($result);
                
                // Si single() retourne null, essayer data() et prendre le premier élément
                if ($normalized === null) {
                    $dataArray = ApiResponseNormalizer::data($result);
                    $normalized = !empty($dataArray) ? $dataArray[0] : $result;
                }
                
                return $normalized ?: $result;
            }

            // Extraire le message d'erreur
            $errorBody = $response->json();
            $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? $response->body();
            
            if (is_array($errorMessage)) {
                $errorMessage = implode(', ', $errorMessage);
            }

            Log::warning('API DodoVroum PATCH error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'error_message' => $errorMessage,
            ]);

            throw DodoVroumApiException::requestFailed($errorMessage ?: 'Erreur lors de la requête PATCH', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);
        } catch (DodoVroumApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('API DodoVroum PATCH exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw DodoVroumApiException::requestFailed('Erreur lors de la requête PATCH', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Faire une requête DELETE à l'API avec authentification
     */
    protected function delete(string $endpoint): bool
    {
        $token = $this->getAuthToken();

        if (!$token) {
            Log::error('Impossible d\'obtenir le token d\'authentification', ['endpoint' => $endpoint]);
            throw DodoVroumApiException::authenticationFailed();
        }

        try {
            $url = "{$this->baseUrl}/{$endpoint}";
            
            Log::info('API DodoVroum DELETE - Requête HTTP', [
                'endpoint' => $endpoint,
                'url' => $url,
                'method' => 'DELETE',
                'token_preview' => substr($token, 0, 20) . '...',
            ]);
            
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->delete($url);

            Log::info('API DodoVroum DELETE - Réponse HTTP', [
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body_preview' => substr($response->body(), 0, 200),
            ]);

            if ($response->status() === 401) {
                Log::warning('Token expiré, tentative de renouvellement', ['endpoint' => $endpoint]);
                $this->authService->clearToken();
                $token = $this->getAuthToken();

                if ($token) {
                    Log::info('API DodoVroum DELETE - Nouvelle tentative après renouvellement token', [
                        'endpoint' => $endpoint,
                        'url' => $url,
                    ]);
                    
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                        ])
                        ->delete($url);
                    
                    Log::info('API DodoVroum DELETE - Réponse après renouvellement', [
                        'endpoint' => $endpoint,
                        'status' => $response->status(),
                        'successful' => $response->successful(),
                    ]);
                } else {
                    throw DodoVroumApiException::authenticationFailed('Impossible de renouveler le token');
                }
            }

            if ($response->successful()) {
                Log::info('API DodoVroum DELETE - Succès', [
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return true;
            }

            // Extraire le message d'erreur de la réponse
            $errorMessage = 'Erreur lors de la suppression';
            $responseBody = $response->json();
            
            if (isset($responseBody['message'])) {
                $errorMessage = $responseBody['message'];
            } elseif (isset($responseBody['error'])) {
                $errorMessage = is_string($responseBody['error']) 
                    ? $responseBody['error'] 
                    : 'Erreur lors de la suppression';
            }

            Log::warning('API DodoVroum DELETE error', [
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'error_message' => $errorMessage,
            ]);

            // Lever une exception avec le message d'erreur pour qu'il soit transmis au controller
            throw DodoVroumApiException::requestFailed($errorMessage, [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response_body' => $responseBody,
            ]);
        } catch (DodoVroumApiException $e) {
            Log::error('API DodoVroum DELETE - DodoVroumApiException', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('API DodoVroum DELETE exception', [
                'endpoint' => $endpoint,
                'url' => $url ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw DodoVroumApiException::requestFailed('Erreur lors de la requête DELETE', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

