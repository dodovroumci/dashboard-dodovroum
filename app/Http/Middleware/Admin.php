<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Admin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Log pour debug
        \Illuminate\Support\Facades\Log::debug('Middleware Admin - Vérification', [
            'has_user' => $user !== null,
            'user_id' => $user ? ($user->getAuthIdentifier() ?? 'N/A') : 'N/A',
            'user_email' => $user ? ($user->email ?? 'N/A') : 'N/A',
            'auth_check' => \Illuminate\Support\Facades\Auth::check(),
            'auth_id' => \Illuminate\Support\Facades\Auth::id(),
            'session_id' => $request->session()->getId(),
            'url' => $request->url(),
        ]);
        
        if (!$user) {
            \Illuminate\Support\Facades\Log::warning('Middleware Admin: Aucun utilisateur authentifié', [
                'url' => $request->url(),
                'session_id' => $request->session()->getId(),
                'auth_check' => \Illuminate\Support\Facades\Auth::check(),
            ]);
            abort(403, 'Accès réservé aux administrateurs.');
        }

        // Vérifier le rôle (support ApiUser et User)
        $isAdmin = false;
        $role = null;
        
        // Méthode 1: Vérifier la méthode isAdmin() si elle existe
        if (method_exists($user, 'isAdmin')) {
            $isAdmin = $user->isAdmin();
            $role = $user->role ?? 'non défini';
        } 
        // Méthode 2: Vérifier directement l'attribut role
        else {
            // Pour ApiUser, utiliser __get() qui accède à $attributes
            $role = $user->role ?? null;
            
            // Vérifier aussi les attributs directs si c'est ApiUser
            if ($user instanceof \App\Models\ApiUser) {
                $userArray = $user->toArray();
                $role = $userArray['role'] ?? $role;
            }
            
            $isAdmin = ($role === 'admin' || $role === 'administrator' || $role === 'superadmin');
        }

        // Méthode 3: Fallback - Vérifier l'email admin configuré
        if (!$isAdmin) {
            $userEmail = strtolower($user->email ?? '');
            $adminEmail = strtolower(config('services.dodovroum.admin_email', ''));
            
            if ($userEmail === $adminEmail && !empty($adminEmail)) {
                \Illuminate\Support\Facades\Log::info('Admin détecté par email configuré (fallback)', [
                    'email' => $userEmail,
                ]);
                $isAdmin = true;
                $role = 'admin';
            }
        }

        // Log pour debug
        \Illuminate\Support\Facades\Log::debug('Vérification accès admin', [
            'user_id' => $user->id ?? $user->getAuthIdentifier(),
            'user_email' => $user->email ?? null,
            'user_role' => $role,
            'user_class' => get_class($user),
            'is_admin_method' => method_exists($user, 'isAdmin') ? $user->isAdmin() : 'N/A',
            'is_admin_result' => $isAdmin,
            'admin_email_config' => config('services.dodovroum.admin_email'),
        ]);

        if (!$isAdmin) {
            \Illuminate\Support\Facades\Log::warning('Accès admin refusé', [
                'user_email' => $user->email ?? null,
                'user_role' => $role,
            ]);
            
            // Si c'est un propriétaire qui essaie d'accéder à une route admin, rediriger vers son dashboard
            if ($role === 'owner' || (method_exists($user, 'isOwner') && $user->isOwner())) {
                \Illuminate\Support\Facades\Log::info('Redirection propriétaire vers owner.dashboard', [
                    'user_email' => $user->email ?? null,
                    'requested_url' => $request->url(),
                ]);
                return redirect()->route('owner.dashboard');
            }
            
            abort(403, 'Accès réservé aux administrateurs.');
        }

        return $next($request);
    }
}
