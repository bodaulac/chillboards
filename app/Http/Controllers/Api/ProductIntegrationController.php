<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ShopifyService;
use App\Services\WalmartUploadService;
use App\Services\GoogleSheetService;

class ProductIntegrationController extends Controller
{
    /**
     * Trigger Shopify Order Sync
     */
    public function syncShopify(Request $request)
    {
        $request->validate([
            'storeId' => 'required|string',
            'days' => 'integer|min:1|max:30'
        ]);

        $storeId = $request->input('storeId');
        $days = $request->input('days', 7);

        try {
            $service = app(ShopifyService::class);
            $result = $service->syncStore($storeId, $days);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Trigger Walmart Order Sync
     */
    public function syncWalmart(Request $request)
    {
        $request->validate([
            'storeId' => 'required|string',
            'days' => 'integer|min:1|max:30'
        ]);

        $storeId = $request->input('storeId');
        $days = $request->input('days', 7);

        try {
            $service = app(WalmartService::class);
            $result = $service->syncStore($storeId, $days);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload to Walmart (Multi-Item Feed)
     */
    public function uploadWalmart(Request $request)
    {
        $request->validate([
            'storeId' => 'required',
            'templateData' => 'required|array', // { headers: [], rows: [] }
            'settings' => 'array'
        ]);

        try {
            $service = app(WalmartUploadService::class);
            $result = $service->uploadToWalmart(
                $request->input('storeId'),
                $request->input('templateData'),
                $request->input('settings', [])
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create Google Sheet Template for Walmart
     */
    public function createWalmartTemplate(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'headers' => 'required|array',
            'data' => 'required|array'
        ]);

        try {
            $service = app(GoogleSheetService::class);
            $result = $service->createWalmartTemplateSheet(
                $request->input('title'),
                $request->input('headers'),
                $request->input('data')
            );

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Trigger Walmart Product Sync
     */
    public function syncWalmartProducts(Request $request)
    {
        $request->validate([
            'storeId' => 'required|string'
        ]);

        $storeId = $request->input('storeId');

        try {
            $service = app(WalmartService::class);
            $result = $service->syncProducts($storeId);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
