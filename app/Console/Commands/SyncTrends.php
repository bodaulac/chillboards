<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Trends\WalmartTrendService;
use App\Services\Trends\GoogleTrendService;
use Illuminate\Support\Facades\Log;

class SyncTrends extends Command
{
    protected $signature = 'trends:sync {--platform=all : Platform to sync (walmart, google, all)}';
    protected $description = 'Sync trends from external sources';

    public function handle()
    {
        $platform = $this->option('platform');
        $this->info("Starting Trend Sync (Platform: {$platform})...");

        // Walmart
        if ($platform === 'all' || $platform === 'walmart') {
            try {
                $this->info('Syncing Walmart Trends...');
                $service = app(WalmartTrendService::class);
                $result = $service->analyzeTrends();
                $this->info("  Walmart: Created {$result['newsCreated']} news items.");
            } catch (\Exception $e) {
                $this->error("  Walmart Error: " . $e->getMessage());
                Log::error("Walmart Sync Error: " . $e->getMessage());
            }
        }

        // Google
        if ($platform === 'all' || $platform === 'google') {
            try {
                $this->info('Syncing Google Trends...');
                $service = app(GoogleTrendService::class);
                $trends = $service->fetchDailyTrends('US');
                $this->info("  Google: Saved " . count($trends) . " trends.");
            } catch (\Exception $e) {
                 $this->error("  Google Error: " . $e->getMessage());
                 Log::error("Google Sync Error: " . $e->getMessage());
            }
        }

        $this->info('Trend Sync Completed.');
    }
}
