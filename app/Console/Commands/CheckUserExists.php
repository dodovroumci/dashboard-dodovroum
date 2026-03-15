<?php

namespace App\Console\Commands;

use App\Services\DodoVroumApiService;
use Illuminate\Console\Command;

class CheckUserExists extends Command
{
    protected $signature = 'user:check {email}';
    protected $description = 'Vérifier si un utilisateur existe dans l\'API';

    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Recherche de l'utilisateur avec l'email : {$email}");
        
        try {
            $apiService = new DodoVroumApiService();
            $users = $apiService->getUsers();
            
            // Normaliser l'email recherché (minuscules)
            $emailLower = strtolower($email);
            
            $found = false;
            foreach ($users as $user) {
                $userEmail = strtolower($user['email'] ?? '');
                if ($userEmail === $emailLower) {
                    $found = true;
                    $this->info("✅ Utilisateur trouvé !");
                    $this->line("ID: " . ($user['id'] ?? 'N/A'));
                    $this->line("Email: " . ($user['email'] ?? 'N/A'));
                    $this->line("Prénom: " . ($user['firstName'] ?? 'N/A'));
                    $this->line("Nom: " . ($user['lastName'] ?? 'N/A'));
                    $this->line("Rôle: " . ($user['role'] ?? 'N/A'));
                    $this->line("Actif: " . (($user['isActive'] ?? false) ? 'Oui' : 'Non'));
                    return 0;
                }
            }
            
            if (!$found) {
                $this->error("❌ Utilisateur non trouvé avec l'email : {$email}");
                $this->line("Utilisateurs existants :");
                foreach ($users as $user) {
                    $this->line("  - " . ($user['email'] ?? 'N/A') . " (" . ($user['role'] ?? 'N/A') . ")");
                }
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Erreur : " . $e->getMessage());
            return 1;
        }
    }
}

