<?php

namespace App\Console\Commands;

use App\Services\DodoVroumApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password {email} {password}';
    protected $description = 'Réinitialiser le mot de passe d\'un utilisateur (via mise à jour)';

    public function handle()
    {
        $email = $this->argument('email');
        $newPassword = $this->argument('password');
        
        $this->info("Réinitialisation du mot de passe pour : {$email}");
        
        try {
            $apiService = new DodoVroumApiService();
            
            // Récupérer l'utilisateur
            $users = $apiService->getUsers();
            $user = null;
            $userId = null;
            
            foreach ($users as $u) {
                if (strtolower($u['email'] ?? '') === strtolower($email)) {
                    $user = $u;
                    $userId = $u['id'] ?? null;
                    break;
                }
            }
            
            if (!$userId) {
                $this->error("❌ Utilisateur non trouvé avec l'email : {$email}");
                return 1;
            }
            
            $this->info("✅ Utilisateur trouvé (ID: {$userId})");
            $this->info("⚠️  Tentative de mise à jour du mot de passe...");
            
            // Essayer de mettre à jour le mot de passe
            // Note: L'API attend le mot de passe hashé en bcrypt avec 12 rounds (comme Node.js)
            $result = $apiService->updateUser($userId, [
                'password' => password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
            ]);
            
            if (!empty($result)) {
                $this->info("✅ Mot de passe mis à jour avec succès !");
                $this->line("Vous pouvez maintenant vous connecter avec :");
                $this->line("  Email: {$email}");
                $this->line("  Mot de passe: {$newPassword}");
                return 0;
            } else {
                $this->error("❌ Échec de la mise à jour du mot de passe");
                $this->line("L'API ne permet peut-être pas de mettre à jour le mot de passe via cette méthode.");
                $this->line("Vous devrez peut-être supprimer et recréer l'utilisateur.");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Erreur : " . $e->getMessage());
            Log::error('Erreur lors de la réinitialisation du mot de passe', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return 1;
        }
    }
}

