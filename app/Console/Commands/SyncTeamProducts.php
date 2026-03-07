<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Team;
use App\Services\ProductSheetSyncService;
use Illuminate\Support\Facades\Log;

class SyncTeamProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teams:sync-products {--team= : Specific team ID to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Team Google Sheets to OMS';

    protected $syncService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ProductSheetSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Team Product Sync...');
        
        $teamId = $this->option('team');
        
        $query = Team::query();
        if ($teamId) {
            $query->where('id', $teamId);
        } else {
            $query->whereNotNull('product_sheet_url');
        }
        
        $teams = $query->get();
        
        if ($teams->isEmpty()) {
            $this->warn('No teams found with product sheet URLs.');
            return;
        }

        foreach ($teams as $team) {
            $this->info("Syncing team: {$team->name} (ID: {$team->id})");
            try {
                $result = $this->syncService->syncTeamProducts($team);
                if ($result['success']) {
                    $this->info("✅ Success: {$result['updated']} updated, {$result['created']} created.");
                } else {
                    $this->error("❌ Failed: " . ($result['error'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                $this->error("❌ Error: " . $e->getMessage());
                Log::error("Artisan Sync Error for Team {$team->id}: " . $e->getMessage());
            }
        }

        $this->info('Team Product Sync Completed.');
    }
}
