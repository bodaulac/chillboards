<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DesignMapping;
use Illuminate\Http\Request;

class DesignMappingController extends Controller
{
    /**
     * Lookup design mapping by SKU.
     * Tries exact match first, then base_sku prefix match.
     */
    public function show(string $sku)
    {
        $mapping = DesignMapping::where('base_sku', $sku)->first();

        if (!$mapping) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $mapping,
        ]);
    }

    /**
     * Create or update a design mapping by base_sku.
     * Only updates fields that are provided (non-null).
     */
    public function store(Request $request)
    {
        $request->validate([
            'base_sku' => 'required|string|max:255',
            'design_url' => 'nullable|string',
            'mockup_url' => 'nullable|string',
            'design_url_2' => 'nullable|string',
            'mockup_url_2' => 'nullable|string',
        ]);

        $baseSku = $request->input('base_sku');

        // Build only the fields that were sent
        $data = array_filter($request->only([
            'design_url', 'mockup_url', 'design_url_2', 'mockup_url_2',
        ]), fn($v) => $v !== null);

        $mapping = DesignMapping::updateOrCreate(
            ['base_sku' => $baseSku],
            $data
        );

        return response()->json([
            'success' => true,
            'data' => $mapping,
        ]);
    }
}
