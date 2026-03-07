<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

$count = Order::count();
echo "Total Orders: " . $count . "\n";

if ($count > 0) {
    $lastOrder = Order::orderBy('created_at', 'desc')->first();
    echo "Last Order:\n";
    print_r($lastOrder->toArray());
}
