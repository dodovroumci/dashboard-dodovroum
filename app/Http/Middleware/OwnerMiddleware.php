<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            \Illuminate\Support\Facades\Log::warning('Middleware Owner: Aucun utilisateur authentifié');
            abort(403, 'Accès réservé aux propriétaires.');
        }

        // Vérifier le rôle (support ApiUser et User)
        $isOwner = false;
        $role = null;
        
        // Méthode 1: Vérifier la méthode isOwner() si elle existe
        if (method_exists($user, 'isOwner')) {
            $isOwner = $user->isOwner();
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
            
            // Un utilisateur est owner s'il n'est pas admin
            $isAdmin = ($role === 'admin' || $role === 'administrator' || $role === 'superadmin');
            $isOwner = !$isAdmin;
        }

        if (!$isOwner) {
            \Illuminate\Support\Facades\Log::warning('Accès owner refusé', [
                'user_email' => $user->email ?? null,
                'user_role' => $role,
            ]);
            abort(403, 'Accès réservé aux propriétaires.');
        }

        return $next($request);
    }
}
