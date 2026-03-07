<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Store;
use App\Models\Product;
use App\Services\WalmartService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncWalmartProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'walmart:sync {--store= : Specific store to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Walmart stores to OMS';

    protected $walmartService;
    protected $categories = ['T-Shirts', 'Sweatshirts', 'Posters', 'Hoodies'];

    /**
     * Create a new command instance.
     *
     * @return void
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
        $this->info('Starting Walmart Product Sync...');
        
        $specifiedStore = $this->option('store');
        
        $query = Store::where('platform', 'Walmart')->where('active', true);
        
        if ($specifiedStore) {
            $query->where('store_id', $specifiedStore);
        }
        
        $stores = $query->get();
        
        if ($stores->isEmpty()) {
            $this->warn('No active Walmart stores found to sync.');
            return;
        }

        foreach ($stores as $store) {
            $this->syncStore($store);
        }

        $this->info('Walmart Product Sync Completed.');
    }

    protected function syncStore($store)
    {
        $this->info("Syncing store: {$store->store_name} ({$store->store_id})");
        
        $stats = [
            'new' => 0,
            'updated' => 0,
            'skipped' => 0
        ];

        foreach ($this->categories as $category) {
            $this->line("  Fetching category: {$category}");
            $items = $this->walmartService->fetchProductsByCategory($store->store_id, $category, 1000);
            
            $this->line("  Found " . count($items) . " items.");

            foreach ($items as $item) {
                $this->processItem($item, $store, $stats);
            }
            
            // Rate limiting between categories
            sleep(1);
        }

        $this->info("✅ Store {$store->store_id} sync complete: {$stats['new']} new, {$stats['updated']} updated, {$stats['skipped']} skipped.");
    }

    protected function processItem($item, $store, &$stats)
    {
        try {
            $sku = $item['sku'] ?? '';
            if (!$sku) return;

            $baseSku = $this->extractBaseSKU($sku);
            
            // Check existing by SKU first (most reliable)
            $product = Product::where('sku', $sku)->first();
            
            // Fallback to base_sku if not found by exact SKU
            if (!$product) {
                $product = Product::where('base_sku', $baseSku)->first();
            }
            
            if ($product) {
                // Check recent sync
                $walmartData = $product->walmart_data ?? [];
                $lastSynced = $walmartData['lastSyncedAt'] ?? null;
                $availableStores = $walmartData['availableStores'] ?? [];
                
                // If it was synced recently AND this store is already tracked, skip.
                if ($lastSynced && Carbon::parse($lastSynced)->gt(Carbon::now()->subDays(1)) && in_array($store->store_id, $availableStores)) {
                    $stats['skipped']++;
                    return;
                }

                $hasImages = !empty($walmartData['mainMockupURL']); 
                
                if (!$hasImages) {
                    $details = $this->walmartService->fetchProductDetails($store->store_id, $sku);
                    if ($details) {
                        $item = array_merge($item, $details);
                    }
                    usleep(100000); // 100ms
                }

                $parsed = $this->parseWalmartProduct($item, $store->store_id);
                
                // Update
                $walmartData['uploaded'] = true;
                $walmartData['lastSyncedAt'] = now()->toIso8601String();
                $walmartData['mainMockupURL'] = $parsed['mainMockupURL'];
                
                if (!in_array($store->store_id, $availableStores)) {
                    $availableStores[] = $store->store_id;
                }
                $walmartData['availableStores'] = $availableStores;

                $product->walmart_data = $walmartData;
                
                // Also ensure base_sku is filled
                if (empty($product->base_sku)) {
                    $product->base_sku = $baseSku;
                }
                
                $product->save();
                $stats['updated']++;

            } else {
                // New Product
                // Always fetch details for new products to get images
                $details = $this->walmartService->fetchProductDetails($store->store_id, $sku);
                if ($details) {
                    $item = array_merge($item, $details);
                }
                usleep(100000); // 100ms

                $parsed = $this->parseWalmartProduct($item, $store->store_id);

                Product::create([
                    'base_sku' => $parsed['baseSKU'],
                    'sku' => $parsed['sku'],
                    'title' => $parsed['productName'],
                    'seller_code' => 'WALMART', // Default
                    'walmart_data' => [
                        'uploaded' => true,
                        'availableStores' => [$store->store_id],
                        'walmartItemId' => $parsed['walmartItemId'],
                        'publishedStatus' => $parsed['publishedStatus'],
                        'mainMockupURL' => $parsed['mainMockupURL'],
                        'lastSyncedAt' => now()->toIso8601String()
                    ],
                    'upload_status' => [
                        'walmart' => ['uploaded' => true]
                    ],
                    'status' => 'active'
                ]);
                $stats['new']++;
            }
        } catch (\Exception $e) {
            $this->error("Error processing item {$sku}: " . $e->getMessage());
            $stats['errors'] = ($stats['errors'] ?? 0) + 1;
        }
    }

    protected function extractBaseSKU($sku)
    {
        $parts = preg_split('/[-_]/', $sku);
        if (count($parts) >= 4) {
            return implode('-', array_slice($parts, 0, 4));
        }
        return $sku;
    }

    protected function parseWalmartProduct($item, $storeId)
    {
        $sku = $item['sku'] ?? '';
        $baseSKU = $this->extractBaseSKU($sku);
        
        $mainMockupURL = '';
        if (!empty($item['mainMockupURL'])) {
            $mainMockupURL = $item['mainMockupURL'];
        } elseif (!empty($item['images']['mainMockupURL'])) {
            $mainMockupURL = $item['images']['mainMockupURL'];
        } elseif (!empty($item['primaryImageUrl'])) {
            $mainMockupURL = $item['primaryImageUrl'];
        }

        return [
            'sku' => $sku,
            'baseSKU' => $baseSKU,
            'productName' => $item['productName'] ?? '',
            'category' => $item['productType'] ?? '',
            'price' => $item['price']['amount'] ?? 0,
            'walmartItemId' => $item['wpid'] ?? ($item['itemId'] ?? ''),
            'publishedStatus' => $item['publishedStatus'] ?? 'PUBLISHED',
            'mainMockupURL' => $mainMockupURL,
            'storeId' => $storeId
        ];
    }
}
