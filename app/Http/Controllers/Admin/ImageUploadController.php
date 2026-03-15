<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DodoVroumApi\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    protected AuthService $authService;
    protected string $baseUrl;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
        $this->baseUrl = config('services.dodovroum.api_url', 'http://localhost:3000/api');
    }

    /**
     * Upload une image et retourne son URL
     * Proxy vers l'API NestJS /api/upload/single
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        try {
            $file = $request->file('image');
            
            // Déterminer la catégorie depuis le paramètre ou l'URL de la requête
            // Par défaut, utiliser 'residences' pour la compatibilité
            $category = $request->input('category', 'residences');
            
            // Valider la catégorie
            $allowedCategories = ['residences', 'vehicles', 'users', 'combo-offers'];
            if (!in_array($category, $allowedCategories)) {
                $category = 'residences'; // Fallback
            }
            
            // Obtenir le token d'authentification
            $token = $this->authService->getAccessToken();
            
            if (!$token) {
                Log::error('Impossible d\'obtenir le token pour l\'upload d\'image');
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur d\'authentification',
                ], 401);
            }
            
            // Préparer la requête multipart/form-data pour NestJS
            // Essayer plusieurs routes possibles selon la configuration NestJS
            // Routes possibles : /upload, /upload/single, /upload/image, /files/upload
            $uploadRoute = config('services.dodovroum.upload_route', 'upload/single');
            
            // Utiliser l'URL locale si disponible, sinon l'URL publique
            // Pour la communication inter-serveur, l'IP locale est plus fiable
            $apiBaseUrl = config('services.dodovroum.api_url_local', $this->baseUrl);
            $uploadUrl = "{$apiBaseUrl}/{$uploadRoute}";
            
            $fileContent = file_get_contents($file->getRealPath());
            $fileName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            
            Log::debug('Upload image vers NestJS - Préparation', [
                'url' => $uploadUrl,
                'base_url' => $apiBaseUrl,
                'route_config' => $uploadRoute,
                'category' => $category,
                'filename' => $fileName,
                'size' => $file->getSize(),
                'size_mb' => round($file->getSize() / 1024 / 1024, 2),
                'mime_type' => $mimeType,
                'field_name' => 'file', // NestJS attend 'file'
                'has_token' => !empty($token),
                'token_preview' => $token ? substr($token, 0, 20) . '...' : null,
            ]);
            
            // Envoyer le fichier à NestJS avec la catégorie
            // Laravel Http::attach() attend : nom du champ, contenu du fichier, nom du fichier
            // IMPORTANT : Le nom du champ doit être 'file' pour correspondre à @FileInterceptor('file') dans NestJS
            $response = Http::timeout(60) // Timeout plus long pour les uploads
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Accept' => 'application/json',
                ])
                ->attach('file', $fileContent, $fileName, [
                    'Content-Type' => $mimeType,
                ])
                ->post($uploadUrl, [
                    'category' => $category,
                ]);
            
            Log::debug('Upload image vers NestJS - Réponse reçue', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'response_preview' => substr($response->body(), 0, 500),
            ]);
            
            // Si 401, le token est peut-être expiré, on réessaie une fois
            if ($response->status() === 401) {
                Log::warning('Token expiré lors de l\'upload, tentative de renouvellement');
                $this->authService->clearToken();
                $token = $this->authService->getAccessToken();
                
                if ($token) {
                    $response = Http::timeout(60)
                        ->withHeaders([
                            'Authorization' => "Bearer {$token}",
                            'Accept' => 'application/json',
                        ])
                        ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName(), [
                            'Content-Type' => $file->getMimeType(),
                        ])
                        ->post($uploadUrl, [
                            'category' => $category,
                        ]);
                } else {
                    Log::error('Impossible de renouveler le token après 401');
                    return response()->json([
                        'success' => false,
                        'message' => 'Erreur d\'authentification',
                    ], 401);
                }
            }
            
            if ($response->successful()) {
                $data = $response->json();
                
                // NestJS retourne probablement { url: "...", path: "..." } ou { data: { url: "..." } }
                $url = $data['url'] 
                    ?? $data['data']['url'] 
                    ?? $data['data']['path'] 
                    ?? null;
                
                if ($url) {
                    // S'assurer que l'URL est complète
                    if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                        // Si c'est un chemin relatif, construire l'URL complète
                        $basePublicUrl = config('services.dodovroum.public_url', 'https://dodovroum.com');
                        $url = rtrim($basePublicUrl, '/') . '/' . ltrim($url, '/');
                    }
                    
                    Log::info('Image uploadée avec succès vers NestJS', [
                        'category' => $category,
                        'url' => $url,
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'url' => $url,
                        'path' => $data['path'] ?? $data['data']['path'] ?? null,
                    ]);
                } else {
                    Log::error('Réponse NestJS invalide (pas d\'URL)', [
                        'response' => $data,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Réponse invalide de l\'API',
                    ], 500);
                }
            } else {
                // Récupérer les données d'erreur (peut être null pour les erreurs 413)
                $errorData = null;
                try {
                    $errorData = $response->json();
                } catch (\Exception $e) {
                    // La réponse n'est pas du JSON (cas fréquent pour 413)
                    Log::debug('Réponse NestJS non-JSON', [
                        'status' => $response->status(),
                        'body_preview' => substr($response->body(), 0, 200),
                    ]);
                }
                
                $errorMessage = $errorData['message'] 
                    ?? $errorData['error'] 
                    ?? null;
                
                // Message spécifique pour 404 (Route non trouvée)
                if ($response->status() === 404) {
                    $errorMessage = "La route d'upload n'existe pas dans NestJS. Route essayée : {$uploadRoute}";
                    
                    Log::error('Erreur 404 - Route d\'upload non trouvée dans NestJS', [
                        'status' => $response->status(),
                        'upload_url' => $uploadUrl,
                        'upload_route' => $uploadRoute,
                        'error' => $errorMessage,
                        'response_body' => $response->body(),
                        'response_json' => $errorData,
                        'suggestion' => [
                            'Option 1' => 'Vérifier que la route d\'upload existe dans NestJS (ex: /api/upload, /api/upload/single, /api/files/upload)',
                            'Option 2' => 'Configurer la route dans .env : DODOVROUM_UPLOAD_ROUTE=upload (ou la route correcte)',
                            'Option 3' => 'Revenir au stockage local Laravel si NestJS n\'a pas encore cette route configurée',
                        ],
                    ]);
                    
                    // Fallback : stocker localement si NestJS n'a pas la route
                    Log::info('Fallback : stockage local Laravel activé car route NestJS non disponible');
                    try {
                        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                        $path = $file->storeAs('residences', $filename, 'public');
                        $baseUrl = $request->getSchemeAndHttpHost();
                        $url = $baseUrl . '/storage/' . $path;
                        
                        Log::info('Image stockée localement (fallback)', [
                            'path' => $path,
                            'url' => $url,
                        ]);
                        
                        return response()->json([
                            'success' => true,
                            'url' => $url,
                            'path' => $path,
                            'warning' => 'Image stockée localement car la route NestJS n\'est pas disponible',
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Erreur lors du stockage local (fallback)', [
                            'error' => $e->getMessage(),
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => $errorMessage,
                        ], 404);
                    }
                }
                // Message spécifique pour 413 (Payload Too Large)
                elseif ($response->status() === 413) {
                    $fileSizeMB = round($file->getSize() / 1024 / 1024, 2);
                    $errorMessage = $errorMessage 
                        ?? "Le fichier est trop volumineux ({$fileSizeMB} MB). La limite maximale côté serveur NestJS/Nginx est probablement inférieure à 5 MB.";
                    
                    Log::error('Erreur 413 - Fichier trop volumineux pour NestJS', [
                        'status' => $response->status(),
                        'file_size_bytes' => $file->getSize(),
                        'file_size_mb' => $fileSizeMB,
                        'filename' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'error' => $errorMessage,
                        'response_body' => $response->body(),
                        'response_json' => $errorData,
                        'suggestion' => [
                            'NestJS' => 'Vérifier main.ts : app.use(json({ limit: "10mb" })) et app.use(urlencoded({ limit: "10mb", extended: true }))',
                            'Nginx' => 'Vérifier client_max_body_size dans la configuration (ex: client_max_body_size 10M;)',
                            'Alternative' => 'Réduire la limite côté Laravel pour correspondre à celle de NestJS',
                        ],
                    ]);
                } else {
                    $errorMessage = $errorMessage ?? 'Erreur lors de l\'upload de l\'image';
                    
                    Log::error('Erreur upload image vers NestJS', [
                        'status' => $response->status(),
                        'error' => $errorMessage,
                        'response' => $errorData,
                        'response_body' => $response->body(),
                        'file_size_bytes' => $file->getSize(),
                        'file_size_mb' => round($file->getSize() / 1024 / 1024, 2),
                    ]);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                ], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('Erreur upload image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload de l\'image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer une image
     */
    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        try {
            // Supprimer l'image du storage
            if (Storage::disk('public')->exists($request->path)) {
                Storage::disk('public')->delete($request->path);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Image supprimée avec succès',
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur suppression image', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'image',
            ], 500);
        }
    }
}

