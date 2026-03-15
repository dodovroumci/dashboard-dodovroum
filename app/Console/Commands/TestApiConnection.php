<?php

namespace App\Console\Commands;

use App\Services\DodoVroumApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TestApiConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:test-connection 
                            {--email= : Email admin à utiliser}
                            {--password= : Mot de passe admin à utiliser}
                            {--show-token : Afficher le token complet (par défaut, seulement les 50 premiers caractères)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tester la connexion à l\'API DodoVroum et obtenir un token d\'authentification';

    /**
     * Execute the console command.
     */
    public function handle(DodoVroumApiService $apiService): int
    {
        $this->info('🔐 Test de connexion à l\'API DodoVroum');
        $this->newLine();

        // Récupérer les credentials
        $email = $this->option('email') ?? config('services.dodovroum.admin_email');
        $password = $this->option('password') ?? config('services.dodovroum.admin_password');
        $apiUrl = config('services.dodovroum.api_url');

        $this->line("📡 URL de l'API: <fg=cyan>{$apiUrl}</>");
        $this->line("📧 Email: <fg=cyan>{$email}</>");
        $this->line("🔑 Mot de passe: <fg=cyan>" . str_repeat('*', strlen($password)) . "</>");
        $this->newLine();

        // Vider le cache du token pour forcer une nouvelle authentification
        $cacheKey = 'dodovroum_api_token_' . md5($email);
        try {
            Cache::store('file')->forget($cacheKey);
        } catch (\Exception $e) {
            try {
                Cache::forget($cacheKey);
            } catch (\Exception $e2) {
                // Ignorer les erreurs de cache
            }
        }
        $this->info('🗑️  Cache du token vidé');
        $this->newLine();

        // Tester la connexion en faisant une requête simple qui nécessite l'authentification
        try {
            $this->info('⏳ Tentative d\'authentification...');
            
            // Si des credentials personnalisés sont fournis, on doit les utiliser
            // Pour cela, on va créer une instance temporaire du service avec les bons credentials
            if ($this->option('email') || $this->option('password')) {
                // Modifier temporairement la config
                config(['services.dodovroum.admin_email' => $email]);
                config(['services.dodovroum.admin_password' => $password]);
                $apiService = app(DodoVroumApiService::class);
            }
            
            // Tester en faisant une requête simple qui nécessite l'authentification
            $this->info('🧪 Test d\'une requête API (cela va authentifier automatiquement)...');
            $users = $apiService->getUsers();
            
            if (is_array($users)) {
                $this->newLine();
                $this->info('✅ Authentification réussie !');
                $this->newLine();
                $this->line("📊 Nombre d'utilisateurs récupérés: <fg=green>" . count($users) . "</>");
                
                // Afficher le token depuis le cache
                $cacheKey = 'dodovroum_api_token_' . md5($email);
                try {
                    $token = Cache::store('file')->get($cacheKey);
                } catch (\Exception $e) {
                    try {
                        $token = Cache::get($cacheKey);
                    } catch (\Exception $e2) {
                        $token = null;
                    }
                }
                if ($token) {
                    if ($this->option('show-token')) {
                        $this->newLine();
                        $this->line("🎫 Token complet:");
                        $this->line("<fg=cyan>{$token}</>");
                    } else {
                        $this->line("🎫 Token (tronqué): <fg=green>" . substr($token, 0, 50) . "...</>");
                        $this->line("💡 Utilisez <fg=yellow>--show-token</> pour afficher le token complet");
                    }
                    
                    // Afficher les informations du token (décodage JWT basique)
                    $parts = explode('.', $token);
                    if (count($parts) === 3) {
                        try {
                            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
                            if ($payload) {
                                $this->newLine();
                                $this->line("📋 Informations du token:");
                                if (isset($payload['exp'])) {
                                    $expDate = date('Y-m-d H:i:s', $payload['exp']);
                                    $this->line("   ⏰ Expire le: <fg=cyan>{$expDate}</>");
                                }
                                if (isset($payload['email'])) {
                                    $this->line("   📧 Email: <fg=cyan>{$payload['email']}</>");
                                }
                                if (isset($payload['role'])) {
                                    $this->line("   👤 Rôle: <fg=cyan>{$payload['role']}</>");
                                }
                            }
                        } catch (\Exception $e) {
                            // Ignorer les erreurs de décodage
                        }
                    }
                }
                
                return Command::SUCCESS;
            } else {
                $this->newLine();
                $this->error('❌ Échec de l\'authentification');
                $this->newLine();
                $this->warn('💡 Vérifiez que:');
                $this->line('   - L\'URL de l\'API est correcte dans votre fichier .env');
                $this->line('   - Les identifiants (email/mot de passe) sont valides');
                $this->line('   - L\'API est accessible depuis votre serveur');
                $this->newLine();
                $this->line('📝 Commandes utiles:');
                $this->line('   php artisan config:clear');
                $this->line('   php artisan cache:clear');
                
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Erreur lors du test:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('💡 Vérifiez les logs dans storage/logs/laravel.log pour plus de détails');
            
            return Command::FAILURE;
        }
    }
}

