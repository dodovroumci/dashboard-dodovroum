<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Model User - Adapté au schéma Prisma de DodoVroum
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Prisma utilise des UUID (strings) pour l'id
    protected $keyType = 'string';
    public $incrementing = false;

    // On désactive la gestion automatique pour mapper sur createdAt/updatedAt
    public $timestamps = false;

    protected $fillable = [
        'id',
        'firstName',
        'lastName',
        'email',
        'password',
        'phone',
        'role',
        'isActive',
        'isVerified',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts des types Prisma vers Laravel
     */
    protected function casts(): array
    {
        return [
           
            'is_admin' => 'boolean',
            'isActive' => 'boolean',
            'isVerified' => 'boolean',
        ];
    }

    /**
     * Helper pour récupérer le nom complet (Laravel attend souvent 'name')
     */
    public function getNameAttribute(): string
    {
        return "{$this->firstName} {$this->lastName}";
    }

    /**
     * Vérification Admin - Basée sur ton Enum SQL ('ADMIN')
     */
    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN' || $this->is_admin;
    }

    /**
     * Vérification Propriétaire
     */
    public function isOwner(): bool
    {
        return $this->role === 'PROPRIETAIRE';
    }
    /**
 * Retourne le mot de passe pour l'authentification.
 */
public function getAuthPassword()
{
    return $this->password;
}
}
