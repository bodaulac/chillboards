<?php
require '/home/qcts/laravel_app/vendor/autoload.php';
$app = require_once '/home/qcts/laravel_app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\FJPODService;

echo "--- Testing FJPOD Service ---\n";
try {
    $service = new FJPODService();
    echo "Getting SKUs for G5000...\n";
    $result = $service->getSKUs('G5000');
    
    if ($result['success']) {
        echo "✅ Found " . count($result['data']) . " SKUs.\n";
        if (count($result['data']) > 0) {
            echo "First SKU sample: " . json_encode($result['data'][0]) . "\n";
        }
    } else {
        echo "❌ Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
} catch (Exception $e) {
    echo "🚨 Exception: " . $e->getMessage() . "\n";
}
echo "--- Test Complete ---\n";
