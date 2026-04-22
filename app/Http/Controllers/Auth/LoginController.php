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

        $userData = $this->apiAuthService->authenticate(
            $credentials['email'],
            $credentials['password']
        );

        if (!$userData) {
            return back()->withErrors(['email' => 'Identifiants incorrects.'])->onlyInput('email');
        }

        // 1. On connecte d'abord l'utilisateur
        $user = new \App\Models\ApiUser($userData);
        Auth::login($user, $request->boolean('remember'));

        // 2. ON RÉGÉNÈRE LA SESSION ICI (Avant de stocker le token)
        $request->session()->regenerate();

        // 3. MAINTENANT on stocke le token dans la NOUVELLE session
        if (isset($userData['token'])) {
            // On blinde les deux clés
            $request->session()->put('api_token', $userData['token']);
            $request->session()->put('nest_jwt_token', $userData['token']);
            $request->session()->put('api_user', $userData);

            // On force la sauvegarde pour être sûr que le driver écrive sur le disque
            $request->session()->save();

            // Validation post-login
            \Illuminate\Support\Facades\Log::info('Vérification Session Finale', [
                'session_id' => $request->session()->getId(),
                'has_api_token' => $request->session()->has('api_token'),
                'has_nest_token' => $request->session()->has('nest_jwt_token'),
                'user_id' => Auth::id()
            ]);
        }

        // Redirection
        $role = $userData['role'] ?? 'owner';
        if ($role === 'admin' || (method_exists($user, 'isAdmin') && $user->isAdmin())) {
            return redirect()->intended(route('admin.dashboard'));
        }

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
