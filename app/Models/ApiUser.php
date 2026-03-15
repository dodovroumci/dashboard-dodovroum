<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * Modèle utilisateur basé sur l'API (pas de base de données)
 * Les données sont stockées en session après authentification API
 */
class ApiUser implements Authenticatable
{
    protected array $attributes;

    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): ?string
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string
    {
        // Pas de mot de passe stocké localement
        return '';
    }

    /**
     * Get the name of the password attribute for the user.
     */
    public function getAuthPasswordName(): string
    {
        // Pas de mot de passe stocké localement
        return 'password';
    }

    /**
     * Get the token value for the "remember me" session.
     */
    public function getRememberToken(): ?string
    {
        return $this->attributes['remember_token'] ?? null;
    }

    /**
     * Set the token value for the "remember me" session.
     */
    public function setRememberToken($value): void
    {
        $this->attributes['remember_token'] = $value;
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /**
     * Magic method to access attributes
     */
    public function __get(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Magic method to set attributes
     */
    public function __set(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Magic method to check if attribute exists
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Check if the user is an admin
     */
    public function isAdmin(): bool
    {
        // Normaliser le rôle en minuscules pour la comparaison
        $role = strtolower($this->attributes['role'] ?? 'owner');
        $email = strtolower($this->attributes['email'] ?? '');
        $adminEmail = strtolower(config('services.dodovroum.admin_email', ''));
        
        // Vérifier le rôle (normalisé en minuscules)
        $isAdmin = in_array($role, ['admin', 'administrator', 'superadmin', 'super_admin']);
        
        // Fallback: Vérifier l'email admin configuré
        if (!$isAdmin && $email === $adminEmail && !empty($adminEmail)) {
            \Illuminate\Support\Facades\Log::info('Admin détecté par email configuré dans ApiUser::isAdmin()', [
                'email' => $email,
            ]);
            $isAdmin = true;
        }

        return $isAdmin;
    }

    /**
     * Check if the user is an owner
     */
    public function isOwner(): bool
    {
        // Normaliser le rôle en minuscules pour la comparaison
        $role = strtolower($this->attributes['role'] ?? 'owner');
        return in_array($role, ['owner', 'proprietaire', 'propriétaire']);
    }

    /**
     * Get the API token
     */
    public function getApiToken(): ?string
    {
        // Essayer d'abord les attributs
        if (isset($this->attributes['token']) && !empty($this->attributes['token'])) {
            return $this->attributes['token'];
        }
        
        // Fallback: récupérer depuis la session
        $sessionToken = \Illuminate\Support\Facades\Session::get('api_token');
        if ($sessionToken) {
            return $sessionToken;
        }
        
        return null;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Serialize the user for session storage
     */
    public function __serialize(): array
    {
        return ['attributes' => $this->attributes];
    }

    /**
     * Unserialize the user from session storage
     */
    public function __unserialize(array $data): void
    {
        $this->attributes = $data['attributes'] ?? [];
    }
}

