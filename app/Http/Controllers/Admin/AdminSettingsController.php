<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DodoVroumApi\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {
    }

    /**
     * Afficher la page des paramètres
     */
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'api_url' => config('services.dodovroum.api_url', 'http://localhost:3000/api'),
                'admin_email' => config('services.dodovroum.admin_email', 'admin@dodovroum.com'),
            ],
        ]);
    }

    /**
     * Tester la connexion à l'API avec de nouveaux identifiants
     */
    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        try {
            // Tester la connexion avec les nouveaux identifiants
            $token = $this->authService->getAccessToken($validated['email'], $validated['password']);

            if ($token) {
                return back()->with('success', 'Connexion réussie ! Les identifiants sont corrects. N\'oubliez pas de mettre à jour le fichier .env avec ces identifiants.');
            }

            return back()->withErrors([
                'error' => 'Impossible de se connecter avec ces identifiants.',
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du test de connexion API', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Erreur lors du test de connexion : ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Vider le cache
     */
    public function clearCache()
    {
        try {
            // Vider le cache de l'application
            \Artisan::call('cache:clear');
            \Artisan::call('config:clear');
            
            // Vider le cache des tokens API
            $this->authService->clearToken();

            return back()->with('success', 'Cache vidé avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur lors du vidage du cache', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Erreur lors du vidage du cache : ' . $e->getMessage(),
            ]);
        }
    }
}

