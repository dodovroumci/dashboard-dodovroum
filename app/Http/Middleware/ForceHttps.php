<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ne jamais forcer HTTPS en développement local
        if (app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        // Forcer HTTPS en production uniquement
        if (app()->environment('production')) {
            // Vérifier si on est sur localhost ou 127.0.0.1 (ne jamais forcer HTTPS)
            $host = $request->getHost();
            if (in_array($host, ['localhost', '127.0.0.1', '::1'])) {
                return $next($request);
            }

            // Vérifier si la requête est déjà en HTTPS ou si elle passe par un proxy HTTPS
            $isSecure = $request->secure() 
                || $request->header('X-Forwarded-Proto') === 'https'
                || $request->header('X-Forwarded-Ssl') === 'on';
            
            if (!$isSecure) {
                return redirect()->secure($request->getRequestUri(), 301);
            }
        }

        return $next($request);
    }
}

