<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        // Search by SKU, base_sku, title, or seller_code
        if ($request->has('search') && trim($request->search) !== '') {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', $search)
                  ->orWhere('base_sku', 'like', $search)
                  ->orWhere('title', 'like', $search)
                  ->orWhere('seller_code', 'like', $search);
            });
        }

        // Filter by seller_code
        if ($request->has('seller_code') && $request->seller_code !== '') {
            $query->where('seller_code', $request->seller_code);
        }

        // Filter by store_id (via walmart_data JSON)
        if ($request->has('store_id') && $request->store_id !== '') {
            $query->where('walmart_data->availableStores', 'like', '%' . $request->store_id . '%');
        }

        $perPage = min((int)($request->per_page ?? 50), 200);
        $products = $query->latest()->paginate($perPage);

        $mapped = collect($products->items())->map(function ($product) {
            $walmartData      = $product->walmart_data ?? [];
            $designAssignment = $product->design_assignment ?? [];
            $uploadStatus     = $product->upload_status ?? [];
            $availableStores  = $walmartData['availableStores'] ?? [];

            // Image priority: MockupURL1 > mainMockupURL > image_url
            $image = $designAssignment['mockup_url']
                  ?? $walmartData['mainMockupURL']
                  ?? $walmartData['image_url']
                  ?? null;

            return [
                'id'        => $product->id,
                'SKU'       => $product->sku,
                'BaseSKU'   => $product->base_sku,
                'Title'     => $product->title,
                'Seller'    => $product->seller_code,
                'store_id'  => !empty($availableStores) ? implode(', ', $availableStores) : ($product->base_sku ? substr($product->base_sku, 0, 3) : '---'),
                'Image'     => $image,
                'Price'     => $walmartData['price'] ?? 0,
                'Status'    => $uploadStatus['status'] ?? ($walmartData['status'] ?? 'Draft'),
                'DesignURL' => $designAssignment['design_url'] ?? null,
                'MockupURL' => $designAssignment['mockup_url'] ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $mapped,
            'meta'    => [
                'total'        => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'per_page'     => $products->perPage(),
                'from'         => $products->firstItem(),
                'to'           => $products->lastItem(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku'               => 'required|unique:products',
            'title'             => 'required',
            'seller_code'       => 'required',
            'base_sku'          => 'nullable|string',
            'platform_skus'     => 'nullable|array',
            'walmart_data'      => 'nullable|array',
            'upload_status'     => 'nullable|array',
            'design_assignment' => 'nullable|array',
        ]);

        $product = Product::create($validated);
        return response()->json($product, 201);
    }

    public function show($id)
    {
        return response()->json(Product::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'sku'               => 'nullable|string',
            'base_sku'          => 'nullable|string',
            'title'             => 'nullable|string',
            'seller_code'       => 'nullable|string',
            'platform_skus'     => 'nullable|array',
            'walmart_data'      => 'nullable|array',
            'upload_status'     => 'nullable|array',
            'design_assignment' => 'nullable|array',
        ]);

        $product->update($validated);
        return response()->json($product);
    }

    public function destroy($id)
    {
        Product::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
