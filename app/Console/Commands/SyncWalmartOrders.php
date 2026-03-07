<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WalmartService;
use App\Models\Store;

class SyncWalmartOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walmart:orders:sync {store? : Optional specific store ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync orders from Walmart stores to OMS';

    protected $walmartService;

    /**
     * Create a new command instance.
     */
    public function __construct(WalmartService $walmartService)
    {
        parent::__construct();
        $this->walmartService = $walmartService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Walmart Order Sync...');
        
        $specifiedStore = $this->argument('store');
        $query = Store::where('platform', 'walmart')->where('active', true);
        
        if ($specifiedStore) {
            $query->where('store_id', $specifiedStore);
        }
        
        $stores = $query->get();
        
        if ($stores->isEmpty()) {
            $this->warn('No active Walmart stores found to sync.');
            return 0;
        }

        foreach ($stores as $store) {
            $this->info("Syncing store orders: {$store->store_name} ({$store->store_id})");
            
            try {
                $result = $this->walmartService->syncStore($store->store_id, 7);
                
                if ($result['success']) {
                    $this->info("  ✅ Success: Total: {$result['total']}, New: {$result['new']}, Updated: {$result['updated']}, Errors: {$result['errors']}");
                } else {
                    $this->error("  ❌ Failed: " . ($result['error'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Exception: " . $e->getMessage());
            }
        }

        $this->info('Walmart Order Sync Completed.');
        return 0;
    }
}
