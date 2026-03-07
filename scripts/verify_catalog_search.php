<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\WalmartService;
use Illuminate\Support\Facades\Http;

$storeId = 'WM-CrushTee';
$wpid = '6EGSDP68O0ZS';

$service = app(WalmartService::class);
$token = $service->getAccessToken($storeId);

$headers = [
    'WM_SEC.ACCESS_TOKEN' => $token,
    'WM_SVC.NAME' => 'Walmart Marketplace',
    'WM_QOS.CORRELATION_ID' => 'diag-' . uniqid(),
    'Accept' => 'application/json',
    'Content-Type' => 'application/json'
];

echo "--- Testing POST /v3/items/catalog/search with structured query ---\n";
$payload = [
    'query' => [
        'field' => 'wpid',
        'value' => $wpid,
        'operator' => 'EQUALS'
    ]
];

$res = Http::withHeaders($headers)->post("https://marketplace.walmartapis.com/v3/items/catalog/search", $payload);
echo "Status: " . $res->status() . "\n";
echo "Body: " . $res->body() . "\n";

echo "\n--- Testing POST /v3/catalog/search with structured query ---\n";
$res2 = Http::withHeaders($headers)->post("https://marketplace.walmartapis.com/v3/catalog/search", $payload);
echo "Status: " . $res2->status() . "\n";
echo "Body: " . $res2->body() . "\n";
