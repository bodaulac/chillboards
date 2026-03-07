<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Product;
use App\Services\ProductSheetSyncService;

// Create a dummy team for testing if none exists or use an existing one
$team = Team::first();
if (!$team) {
    echo "No team found. Creating dummy team...\n";
    $team = Team::create(['name' => 'Test Team', 'leader_id' => 1]);
}

// Set a test sheet URL (Need a real publicly accessible one or mock the service)
// For this test, we will temporarily mock the GoogleSheetService to avoid real API calls
$team->product_sheet_url = 'https://docs.google.com/spreadsheets/d/mock-id/edit';
$team->save();

echo "Verifying Product Sheet Sync for Team: {$team->name}\n";

try {
    // Mocking the GoogleSheetService for local testing
    $mockRows = [
        ['SKU', 'Title', 'Design URL', 'Mockup URL'],
        ['HHT001', 'Test Product 1', 'http://design1.com', 'http://mockup1.com'],
        ['HHT002', 'Test Product 2', 'http://design2.com', 'http://mockup2.com'],
    ];

    // Create a mock of GoogleSheetService
    $googleSheetService = new class($mockRows) extends \App\Services\GoogleSheetService {
        private $mockData;
        public function __construct($data) { $this->mockData = $data; }
        public function readSheet($id, $range) { return $this->mockData; }
    };

    $syncService = new ProductSheetSyncService($googleSheetService);
    $result = $syncService->syncTeamProducts($team);

    echo "Sync Result:\n";
    print_r($result);

    // Check if products were created/updated
    $products = Product::whereIn('sku', ['HHT001', 'HHT002'])->get();
    echo "Products in DB:\n";
    foreach ($products as $p) {
        echo "- SKU: {$p->sku}, Title: {$p->title}, Design URL: " . ($p->design_assignment['design_url'] ?? 'N/A') . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
