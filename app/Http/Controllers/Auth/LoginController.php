<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\DodoVroumApi\ApiAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(
        protected ApiAuthService $apiAuthService
    ) {
    }

    /**
     * Afficher la page de connexion
     */
    public function show(): Response|\Illuminate\Http\RedirectResponse
    {
        // Si déjà connecté, rediriger selon le rôle
        if (Auth::check()) {
            $user = Auth::user();
            $role = $user->role ?? 'owner';
            
            if ($role === 'admin') {
                return redirect()->route('admin.dashboard');
            }
            if ($role === 'owner') {
                return redirect()->route('owner.dashboard');
            }
        }

        return Inertia::render('Login');
    }

    /**
     * Traiter la connexion via l'API
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Authentifier via l'API
        $userData = $this->apiAuthService->authenticate(
            $credentials['email'],
            $credentials['password']
        );

        if (!$userData) {
            // Message d'erreur plus clair
            $errorMessage = 'Les identifiants fournis ne correspondent pas à nos enregistrements.';
            $errorMessage .= ' Veuillez vérifier votre email et votre mot de passe.';
            
            \Illuminate\Support\Facades\Log::warning('Échec de connexion', [
                'email' => $credentials['email'],
                'suggestion' => 'Vérifiez que le compte existe et que le mot de passe est correct',
            ]);
            
            return back()->withErrors([
                'email' => $errorMessage,
            ])->onlyInput('email');
        }

        // Log pour debug
        \Illuminate\Support\Facades\Log::info('Authentification réussie via API', [
            'email' => $userData['email'] ?? null,
            'role' => $userData['role'] ?? null,
            'id' => $userData['id'] ?? null,
            'admin_email_config' => config('services.dodovroum.admin_email'),
        ]);

        // S'assurer que le token est dans userData
        if (isset($userData['token'])) {
            Session::put('api_token', $userData['token']);
            Session::put('nest_jwt_token', $userData['token']);
        }
        
        // Stocker les données utilisateur en session (incluant le token)
        Session::put('api_user', $userData);

        // Créer une instance ApiUser et connecter l'utilisateur
        $user = new \App\Models\ApiUser($userData);
        
        // Log pour vérifier le rôle dans ApiUser
        \Illuminate\Support\Facades\Log::debug('ApiUser créé', [
            'role' => $user->role ?? null,
            'isAdmin' => method_exists($user, 'isAdmin') ? $user->isAdmin() : 'N/A',
            'isOwner' => method_exists($user, 'isOwner') ? $user->isOwner() : 'N/A',
        ]);
        
        // Connecter l'utilisateur dans Laravel
        Auth::login($user, $request->boolean('remember'));
        
        // Log pour vérifier que l'utilisateur est bien connecté
        \Illuminate\Support\Facades\Log::debug('Utilisateur connecté via Auth::login', [
            'user_id' => $user->getAuthIdentifier(),
            'user_email' => $user->email ?? null,
            'isAdmin' => method_exists($user, 'isAdmin') ? $user->isAdmin() : 'N/A',
            'auth_check' => Auth::check(),
            'auth_user_id' => Auth::id(),
        ]);
        
        // Régénérer la session pour éviter les attaques de fixation de session
        $request->session()->regenerate();
        
        // S'assurer que la session est sauvegardée
        $request->session()->save();
        
        // Vérifier à nouveau après la régénération
        \Illuminate\Support\Facades\Log::debug('Session régénérée', [
            'auth_check_after' => Auth::check(),
            'auth_user_id_after' => Auth::id(),
            'session_id' => $request->session()->getId(),
        ]);

        // Déterminer le rôle final (utiliser isAdmin() pour être sûr)
        // Le rôle est déjà normalisé par ApiAuthService, mais vérifions avec isAdmin()
        $role = $userData['role'] ?? 'owner';
        
        // Vérifier à nouveau avec isAdmin() pour être sûr (priorité absolue)
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            $role = 'admin';
        } elseif (method_exists($user, 'isOwner') && $user->isOwner()) {
            $role = 'owner';
        }
        
        \Illuminate\Support\Facades\Log::info('Redirection après connexion', [
            'email' => $userData['email'] ?? null,
            'role_dans_userData' => $userData['role'] ?? 'non défini',
            'role_detecte' => $role,
            'isAdmin()' => method_exists($user, 'isAdmin') ? $user->isAdmin() : 'N/A',
            'isOwner()' => method_exists($user, 'isOwner') ? $user->isOwner() : 'N/A',
        ]);
        
        // Rediriger selon le rôle (utiliser Inertia::location pour les redirections externes)
        if ($role === 'admin' || (method_exists($user, 'isAdmin') && $user->isAdmin())) {
            // Utiliser redirect()->intended() pour Inertia
            return redirect()->intended(route('admin.dashboard'));
        }
        
        // Par défaut, rediriger vers le dashboard owner
        return redirect()->intended(route('owner.dashboard'));
    }

    /**
     * Déconnexion
     */
    public function destroy(Request $request)
    {
        // Nettoyer la session API
        Session::forget('api_user');
        Session::forget('api_token');
        Session::forget('nest_jwt_token');
        
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
