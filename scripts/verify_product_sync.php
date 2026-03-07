<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Store;
use App\Services\WalmartService;

$storeId = 'WM-CrushTee'; // Use the known store
echo "Verifying Product Sync for: {$storeId}\n";

try {
    $service = app(WalmartService::class);
    $result = $service->syncProducts($storeId);
    
    echo "Sync Result:\n";
    print_r($result);
    
    $products = \App\Models\Product::orderBy('created_at', 'desc')->limit(5)->get();
    echo "Recent Products in DB:\n";
    foreach ($products as $p) {
        echo "- SKU: {$p->sku}, Title: {$p->title}, Image: " . ($p->walmart_data['image_url'] ?? 'N/A') . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
