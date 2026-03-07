<?php

namespace App\Helpers;

use App\Models\Seller;

class SellerMapper
{
    /**
     * Determine Seller Code from SKU or Store
     */
    public static function getSellerCode(?string $sku, string $storeId): string
    {
        if (!$sku) return 'DEFAULT';

        // 1. Check Product database for direct SKU or BaseSKU match
        $product = \App\Models\Product::where('sku', $sku)->orWhere('base_sku', $sku)->first();
        if ($product && $product->seller_code) {
            return $product->seller_code;
        }

        // 2. Try prefix lookup (mapping BaseSKU if SKU contains it)
        $parts = explode('-', $sku);
        $prefix = strtoupper($parts[0]);
        
        $baseProduct = \App\Models\Product::where('base_sku', $prefix)->first();
        if ($baseProduct && $baseProduct->seller_code) {
            return $baseProduct->seller_code;
        }

        // 3. Fallback to prefix if it looks like a code (2-5 chars)
        if (strlen($prefix) >= 2 && strlen($prefix) <= 5) {
            return $prefix;
        }

        return 'DEFAULT';
    }
}
