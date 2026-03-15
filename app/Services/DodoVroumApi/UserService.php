<?php

namespace App\Services\DodoVroumApi;

class UserService extends BaseApiService
{
    /**
     * Récupérer tous les utilisateurs
     */
    public function all(array $filters = []): array
    {
        return $this->get('users', $filters);
    }

    /**
     * Récupérer un utilisateur par ID
     */
    public function find(string $id): ?array
    {
        return $this->getSingle("users/{$id}");
    }

    /**
     * Récupérer uniquement les propriétaires
     */
    public function getOwners(): array
    {
        // Essayer plusieurs variantes de filtre
        $filters = [
            ['role' => 'proprietaire'],
            ['type' => 'owner'],
            ['isOwner' => true],
        ];

        foreach ($filters as $filter) {
            $users = $this->all($filter);
            if (!empty($users)) {
                return $this->filterOwners($users);
            }
        }

        // Si aucun filtre ne fonctionne, récupérer tous les utilisateurs et filtrer
        $allUsers = $this->all();
        return $this->filterOwners($allUsers);
    }

    /**
     * Filtrer les propriétaires depuis un tableau d'utilisateurs
     */
    protected function filterOwners(array $users): array
    {
        $owners = [];

        foreach ($users as $user) {
            if ($this->isOwner($user)) {
                $owners[] = $user;
            }
        }

        return $owners;
    }

    /**
     * Vérifier si un utilisateur est un propriétaire
     */
    protected function isOwner(array $user): bool
    {
        // Vérifier le rôle
        if (isset($user['role'])) {
            $role = strtolower($user['role']);
            if (in_array($role, ['proprietaire', 'owner', 'propriétaire'])) {
                return true;
            }
        }

        // Vérifier le type
        if (isset($user['type'])) {
            $type = strtolower($user['type']);
            if (in_array($type, ['proprietaire', 'owner', 'propriétaire'])) {
                return true;
            }
        }

        // Vérifier isOwner
        if (isset($user['isOwner']) && $user['isOwner']) {
            return true;
        }

        // Vérifier isProprietaire
        if (isset($user['isProprietaire']) && $user['isProprietaire']) {
            return true;
        }

        return false;
    }
}

