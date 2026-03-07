<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Store;

$stores = Store::all();
foreach ($stores as $store) {
    echo "ID: {$store->store_id} | Name: {$store->store_name} | Platform: {$store->platform}\n";
}
