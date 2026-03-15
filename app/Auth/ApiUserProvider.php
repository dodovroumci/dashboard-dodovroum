<?php

namespace App\Auth;

use App\Models\ApiUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Session;

/**
 * UserProvider personnalisé qui lit les utilisateurs depuis la session
 * (après authentification via l'API)
 */
class ApiUserProvider implements UserProvider
{
    /**
     * Retrieve a user by their unique identifier.
     * 
     * Cette méthode est appelée par Laravel pour récupérer l'utilisateur depuis la session.
     * L'identifier est l'ID stocké dans la session par Auth::login().
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        $userData = Session::get('api_user');
        
        if (!$userData) {
            return null;
        }
        
        $userDataId = $userData['id'] ?? null;
        
        // Normaliser les IDs en string pour la comparaison (évite les problèmes de type)
        $identifierStr = $identifier !== null ? (string) $identifier : null;
        $userDataIdStr = $userDataId !== null ? (string) $userDataId : null;
        
        // Si l'identifier est null mais qu'on a des données utilisateur,
        // c'est probablement le cas où l'ID n'a pas été correctement stocké lors de Auth::login()
        // On accepte quand même l'utilisateur si on a des données valides (fallback)
        if ($identifierStr === null && !empty($userData)) {
            // Vérifier qu'on a au moins un email pour valider l'utilisateur
            if (!empty($userData['email'])) {
                return $this->createUser($userData);
            }
            return null;
        }
        
        if ($userDataIdStr === $identifierStr) {
            return $this->createUser($userData);
        }

        return null;
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $userData = Session::get('api_user');
        
        if (!$userData || ($userData['id'] ?? null) !== $identifier) {
            return null;
        }

        if (($userData['remember_token'] ?? null) !== $token) {
            return null;
        }

        return $this->createUser($userData);
    }

    /**
     * Update the "remember me" token for the given user in storage.
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $userData = Session::get('api_user', []);
        $userData['remember_token'] = $token;
        Session::put('api_user', $userData);
    }

    /**
     * Retrieve a user by the given credentials.
     * 
     * Note: Cette méthode n'est pas utilisée car l'authentification
     * se fait directement via l'API dans le LoginController
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        // Non utilisé - l'authentification se fait via l'API
        return null;
    }

    /**
     * Validate a user against the given credentials.
     * 
     * Note: Cette méthode n'est pas utilisée car l'authentification
     * se fait directement via l'API dans le LoginController
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        // Non utilisé - l'authentification se fait via l'API
        return false;
    }

    /**
     * Rehash the user's password if required and supported.
     * 
     * Note: Non utilisé car les mots de passe sont gérés par l'API
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Non utilisé - les mots de passe sont gérés par l'API NestJS
        // Pas besoin de rehash car on n'utilise pas de mots de passe locaux
    }

    /**
     * Créer une instance ApiUser depuis les données
     */
    protected function createUser(array $userData): ApiUser
    {
        // S'assurer que le token est dans les données utilisateur
        // Si le token n'est pas dans userData, le récupérer depuis la session
        if (!isset($userData['token']) || empty($userData['token'])) {
            $sessionToken = Session::get('api_token');
            if ($sessionToken) {
                $userData['token'] = $sessionToken;
            }
        }
        
        return new ApiUser($userData);
    }
}

