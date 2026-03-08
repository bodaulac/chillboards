<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    Route::post('login', [App\Http\Controllers\Api\AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function() {
        Route::apiResource('users', App\Http\Controllers\Api\UserController::class);
        Route::apiResource('stores', StoreController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('orders', OrderController::class);
        Route::post('orders/{id}/fulfill', [OrderController::class, 'fulfill']);
        Route::post('orders/{id}/fulfill-bulk', [OrderController::class, 'fulfillBulk']);
        Route::get('fulfillment/fjpod/skus', [OrderController::class, 'getFJPODSKUs']);
        Route::get('fulfillment/flashship/variants', [OrderController::class, 'getFlashshipVariants']);
        Route::get('fulfillment/flashship/orders', [OrderController::class, 'getFlashshipOrders']);
        Route::post('fulfillment/flashship/sync-tracking', [OrderController::class, 'syncFlashshipTracking']);
        Route::post('fulfillment/fjpod/sync-tracking', [OrderController::class, 'syncFJPODTracking']);
        Route::post('fulfillment/printway/sync-tracking', [OrderController::class, 'syncPrintwayTracking']);
        Route::post('fulfillment/walmart/sync-tracking', [OrderController::class, 'syncWalmartTracking']);
        Route::get('orders/{id}/notes', [App\Http\Controllers\Api\NoteController::class, 'index']);
        Route::post('orders/{id}/notes', [App\Http\Controllers\Api\NoteController::class, 'store']);
        Route::delete('notes/{id}', [App\Http\Controllers\Api\NoteController::class, 'destroy']);

        // Integrations
        Route::post('shopify/sync', [App\Http\Controllers\Api\ProductIntegrationController::class, 'syncShopify']);
        Route::post('walmart/sync', [App\Http\Controllers\Api\ProductIntegrationController::class, 'syncWalmart']);
        Route::post('walmart/sync-products', [App\Http\Controllers\Api\ProductIntegrationController::class, 'syncWalmartProducts']);
        Route::post('walmart/upload', [App\Http\Controllers\Api\ProductIntegrationController::class, 'uploadWalmart']);
        Route::post('templates/walmart', [App\Http\Controllers\Api\ProductIntegrationController::class, 'createWalmartTemplate']);

        // Trends & News
        Route::get('trends', [App\Http\Controllers\Api\TrendController::class, 'index']);
        Route::get('news', [App\Http\Controllers\Api\TrendController::class, 'getNews']);
        Route::post('news/{id}/read', [App\Http\Controllers\Api\TrendController::class, 'markNewsRead']);

        // Team Management
        Route::apiResource('teams', App\Http\Controllers\Api\TeamController::class);
        Route::post('teams/{id}/assign-store', [App\Http\Controllers\Api\TeamController::class, 'assignStore']);
        Route::post('teams/{id}/members', [App\Http\Controllers\Api\TeamController::class, 'addMember']);
        Route::post('teams/{id}/sync-products', [App\Http\Controllers\Api\TeamController::class, 'syncProducts']);
        Route::post('teams/{id}/delegate', [App\Http\Controllers\Api\TeamController::class, 'delegateStore']);
        // Analytics & Statistics
        Route::get('analytics/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('statistics', [AnalyticsController::class, 'statistics']);

        // File Upload
        Route::post('upload', [App\Http\Controllers\Api\UploadController::class, 'upload']);

        // Design Mappings
        Route::get('design-mappings/{sku}', [App\Http\Controllers\Api\DesignMappingController::class, 'show']);
        Route::post('design-mappings', [App\Http\Controllers\Api\DesignMappingController::class, 'store']);
    });
});

// Test route
Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok', 
        'message' => 'OMS API Operational',
        'timestamp' => now()->toIso8601String()
    ]);
});
