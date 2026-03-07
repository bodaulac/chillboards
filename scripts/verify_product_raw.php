<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\WalmartService;
use Illuminate\Support\Facades\Http;

$storeId = 'WM-CrushTee';
$sku = 'HHT001-TEE-260115-AEBFF0-SAN-M';

$service = app(WalmartService::class);
$token = $service->getAccessToken($storeId);

if (!$token) {
    echo "Failed to get token.\n";
    exit(1);
}

$headers = [
    'WM_SEC.ACCESS_TOKEN' => $token,
    'WM_SVC.NAME' => 'Walmart Marketplace',
    'WM_QOS.CORRELATION_ID' => 'diag-' . uniqid(),
    'Accept' => 'application/json'
];

echo "--- Testing GET /v3/items/{$sku}?productIdType=SKU&include=listingQuality ---\n";
$res1 = Http::withHeaders($headers)->get("https://marketplace.walmartapis.com/v3/items/{$sku}", [
    'productIdType' => 'SKU',
    'include' => 'listingQuality'
]);
echo "Status: " . $res1->status() . " | Body: " . substr($res1->body(), 0, 1000) . "...\n\n";

echo "--- Testing GET /v3/items?limit=1&include=listingQuality ---\n";
$res2 = Http::withHeaders($headers)->get("https://marketplace.walmartapis.com/v3/items", [
    'limit' => 1,
    'include' => 'listingQuality'
]);
echo "Status: " . $res2->status() . " | Body: " . substr($res2->body(), 0, 1000) . "...\n\n";
