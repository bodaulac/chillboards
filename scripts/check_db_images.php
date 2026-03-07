<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Product;

$productsWithImages = Product::whereNotNull('walmart_data->image_url')->count();
echo "Total products: " . Product::count() . "\n";
echo "Products with images: " . $productsWithImages . "\n";

if ($productsWithImages > 0) {
    $p = Product::whereNotNull('walmart_data->image_url')->first();
    echo "Sample product with image: {$p->sku}\n";
    print_r($p->walmart_data);
} else {
    echo "No products have images yet.\n";
}
