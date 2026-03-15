<?php

namespace App\Console\Commands;

use App\Services\DodoVroumApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeleteOrphanResidence extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'residence:delete-orphan {id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Supprimer une résidence orpheline (sans propriétaire)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');
        
        $this->info("Suppression de la résidence {$id}...");
        
        try {
            $apiService = new DodoVroumApiService();
            $success = $apiService->deleteResidence($id);
            
            if ($success) {
                $this->info("✅ Résidence supprimée avec succès !");
                Log::info('Résidence orpheline supprimée via commande', ['id' => $id]);
                return 0;
            } else {
                $this->error("❌ Échec de la suppression de la résidence");
                Log::warning('Échec de la suppression de la résidence orpheline', ['id' => $id]);
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Erreur : " . $e->getMessage());
            Log::error('Erreur lors de la suppression de la résidence orpheline', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return 1;
        }
    }
}

