<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopifyService;
use App\Models\Store;

class SyncShopifyOrders extends Command
{
    protected $signature = 'shopify:sync {store? : Optional Store ID to sync specific store}';
    protected $description = 'Sync orders from Shopify stores';

    protected $service;

    public function __construct(ShopifyService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        $storeId = $this->argument('store');

        if ($storeId) {
            $stores = Store::where('storeId', $storeId)->where('platform', 'Shopify')->get();
        } else {
            $stores = Store::where('platform', 'Shopify')->get();
        }

        if ($stores->isEmpty()) {
            $this->error('No Shopify stores found.');
            return 0;
        }

        foreach ($stores as $store) {
            $this->info("Syncing Store: {$store->storeName} ({$store->storeId})...");
            
            try {
                $result = $this->service->syncStore($store->storeId, 7); // Default 7 days
                
                if ($result['success']) {
                    $this->info("  Success! Total: {$result['total']}, New: {$result['new']}, Updated: {$result['updated']}, Errors: {$result['errors']}");
                } else {
                    $this->error("  Failed: " . ($result['error'] ?? 'Unknown error'));
                }
            } catch (\Exception $e) {
                $this->error("  Exception: " . $e->getMessage());
            }
        }

        $this->info('Shopify Sync Complete.');
    }
}
