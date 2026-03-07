<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Store;
use App\Services\WalmartService;
use App\Services\ShopifyService;

$storeId = $_GET['storeId'] ?? $argv[1] ?? null;

if (!$storeId) {
    echo "Usage: php verify_sync.php <store_id>\n";
    $stores = Store::all(['store_id', 'platform', 'active']);
    echo "Available stores:\n";
    foreach ($stores as $s) {
        echo "- {$s->store_id} ({$s->platform}) [" . ($s->active ? 'Active' : 'Inactive') . "]\n";
    }
    exit;
}

$store = Store::where('store_id', $storeId)->first();
if (!$store) {
    echo "Store not found: {$storeId}\n";
    exit;
}

echo "Syncing store: {$store->store_name} ({$store->platform})\n";

try {
    if (strtolower($store->platform) === 'walmart') {
        $service = app(WalmartService::class);
        $result = $service->syncStore($storeId, 7);
    } else if (strtolower($store->platform) === 'shopify') {
        $service = app(ShopifyService::class);
        $result = $service->syncStore($storeId, 7);
    } else {
        echo "Unsupported platform: {$store->platform}\n";
        exit;
    }

    echo "Result:\n";
    print_r($result);
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
