<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\WalmartService;

$storeId = 'WM-CrushTee';
$sku = 'HHT001-TEE-260115-AEBFF0-SAN-M';

echo "Fetching details for SKU: {$sku}\n";

$service = app(WalmartService::class);
$details = $service->fetchProductDetails($storeId, $sku);

echo "Details Response:\n";
print_r($details);
