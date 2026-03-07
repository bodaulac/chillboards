<?php
// Test Order Notes on VPS
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Models\Note;

try {
    $order = Order::first();
    if (!$order) {
        echo "No orders found to test with.\n";
        exit(0);
    }

    echo "Testing with Order ID: " . $order->order_id . " (Database ID: " . $order->id . ")\n";

    // Test Note Creation
    $note = Note::create([
        'order_id' => $order->id,
        'content' => 'Deployment verification test note'
    ]);

    if ($note) {
        echo "Successfully created test note. ID: " . $note->id . "\n";
        
        // Test fetching
        $fetched = Note::where('order_id', $order->id)->get();
        echo "Found " . $fetched->count() . " note(s) for this order.\n";
        
        // Clean up
        $note->delete();
        echo "Cleaned up test note.\n";
    }

    echo "OMS API Backend Verification Successful!\n";
} catch (\Exception $e) {
    echo "Verification Failed: " . $e->getMessage() . "\n";
}
