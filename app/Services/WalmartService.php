<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalmartService
{
    /**
     * Generate UUID for Walmart API
     */
    protected function generateUUID(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Get Walmart access token for specific store
     */
    public function getAccessToken(string $storeId): ?string
    {
        try {
            // Fetch store credentials from database. Use case-insensitive search for platform.
            $store = Store::where('store_id', $storeId)
                          ->where(function($q) {
                              $q->where('platform', 'Walmart')->orWhere('platform', 'walmart');
                          })
                          ->where('active', true)
                          ->first();

            if (!$store) {
                // Fallback to env vars if not found in DB (Migration/Legacy support)
                $clientId = env('WALMART_STORE1_CLIENT_ID'); // Simplified for now
                $clientSecret = env('WALMART_STORE1_CLIENT_SECRET');
                
                if ($storeId !== 'STORE1' || !$clientId) {
                     Log::error("Store {$storeId} not found or inactive.");
                     return null;
                }
            } else {
                $credentials = $store->credentials;
                $clientId = $credentials['clientId'] ?? null;
                $clientSecret = $credentials['clientSecret'] ?? null;
            }

            if (!$clientId || !$clientSecret) {
                Log::warning("Missing credentials for store {$storeId}");
                return null;
            }

            $auth = base64_encode("{$clientId}:{$clientSecret}");

            $response = Http::withHeaders([
                'Authorization' => "Basic {$auth}",
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
                'WM_SVC.NAME' => 'Walmart Marketplace',
                'WM_QOS.CORRELATION_ID' => $this->generateUUID()
            ])->asForm()->post('https://marketplace.walmartapis.com/v3/token', [
                'grant_type' => 'client_credentials'
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error("Failed to get token for store {$storeId}: " . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error("Error getting token for store {$storeId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch products from Walmart store by category
     */
    public function fetchProductsByCategory(string $storeId, string $category, int $limit = 200): array
    {
        try {
            $token = $this->getAccessToken($storeId);
            if (!$token) {
                return [];
            }

            $response = Http::withHeaders([
                'WM_SEC.ACCESS_TOKEN' => $token,
                'WM_SVC.NAME' => 'Walmart Marketplace',
                'WM_QOS.CORRELATION_ID' => $this->generateUUID(),
                'Accept' => 'application/json'
            ])->get('https://marketplace.walmartapis.com/v3/items', [
                'limit' => $limit,
                'lifecycleStatus' => 'ACTIVE',
                'category' => $category
            ]);

            if ($response->successful()) {
                $body = $response->json();
                Log::info("Walmart Item API Response Body Sample: " . json_encode(array_slice($body['ItemResponse'] ?? [], 0, 1)));
                return $body['ItemResponse'] ?? [];
            }

            Log::error("Error fetching products for {$storeId} - {$category}: " . $response->body());
            return [];

        } catch (\Exception $e) {
            Log::error("Error fetching products for {$storeId} - {$category}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch detailed product info including images
     */
    public function fetchProductDetails(string $storeId, string $sku): ?array
    {
        try {
            $token = $this->getAccessToken($storeId);
            if (!$token) {
                return null;
            }

            $response = Http::withHeaders([
                'WM_SEC.ACCESS_TOKEN' => $token,
                'WM_SVC.NAME' => 'Walmart Marketplace',
                'WM_QOS.CORRELATION_ID' => $this->generateUUID(),
                'Accept' => 'application/json'
            ])->get("https://marketplace.walmartapis.com/v3/items/" . urlencode($sku));

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->status() !== 404) {
                 Log::error("Error fetching details for {$sku}: " . $response->body());
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Error fetching details for {$sku}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Sync orders for a specific store
     */
    public function syncStore(string $storeId, int $daysBack = 7)
    {
        try {
            $store = Store::where('store_id', $storeId)->firstOrFail();

            if (strtolower($store->platform) !== 'walmart') {
                throw new \Exception("Store {$storeId} is not a Walmart store (found: {$store->platform})");
            }

            Log::info("Starting Walmart sync for {$store->store_name} ({$storeId})");

            $orders = $this->fetchOrders($storeId, $daysBack);
            Log::info("Fetched " . count($orders) . " orders from Walmart.");

            $stats = ['new' => 0, 'updated' => 0, 'errors' => 0];

            foreach ($orders as $walmartOrder) {
                try {
                    $result = $this->processOrder($walmartOrder, $store);
                    if ($result === 'created') $stats['new']++;
                    elseif ($result === 'updated') $stats['updated']++;
                } catch (\Exception $e) {
                    Log::error("Error processing Walmart order " . ($walmartOrder['purchaseOrderId'] ?? 'unknown') . ": " . $e->getMessage());
                    $stats['errors']++;
                }
            }

            return array_merge(['success' => true, 'total' => count($orders)], $stats);

        } catch (\Exception $e) {
            Log::error("Walmart sync failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch orders from Walmart API
     */
    public function fetchOrders(string $storeId, int $daysBack): array
    {
        try {
            $token = $this->getAccessToken($storeId);
            if (!$token) return [];

            $startDate = now()->subDays($daysBack)->toIso8601String();
            $url = 'https://marketplace.walmartapis.com/v3/orders';
            
            Log::info("Fetching Walmart orders from: {$url} starting {$startDate}");

            $response = Http::withHeaders([
                'WM_SEC.ACCESS_TOKEN' => $token,
                'WM_SVC.NAME' => 'Walmart Marketplace',
                'WM_QOS.CORRELATION_ID' => $this->generateUUID(),
                'Accept' => 'application/json'
            ])->get($url, [
                'createdStartDate' => $startDate,
                'limit' => 200
            ]);

            Log::info("Walmart API Response Status: " . $response->status());
            if ($response->successful()) {
                $body = $response->json();
                return $body['list']['elements']['order'] ?? $body['elements']['order'] ?? [];
            }

            Log::error("Error fetching orders for {$storeId}: Status " . $response->status() . " - " . $response->body());
            return [];

        } catch (\Exception $e) {
            Log::error("Error fetching orders for {$storeId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Sync products for a specific store
     */
    public function syncProducts(string $storeId): array
    {
        try {
            $store = Store::where('store_id', $storeId)->firstOrFail();
            if (strtolower($store->platform) !== 'walmart') {
                throw new \Exception("Store {$storeId} is not a Walmart store");
            }

            Log::info("Starting Walmart product sync for {$store->store_name} ({$storeId})");

            // We'll fetch a batch of products. In a real scenario, we might iterate categories or paginate.
            $items = $this->fetchProductsByCategory($storeId, '', 500); // Empty category might work for general fetch in some API versions
            
            // If empty, try with a common category or log error
            if (empty($items)) {
                Log::warning("No products found for {$storeId} with empty category filter.");
                // For demonstration, we'll assume we have results if credentials are good.
            }

            $count = 0;
            foreach ($items as $item) {
                try {
                    $sku = $item['sku'] ?? null;
                    if (!$sku) continue;

                    \App\Models\Product::updateOrCreate(
                        ['sku' => $sku],
                        [
                            'title' => $item['productName'] ?? 'Unnamed Product',
                            'seller_code' => \App\Helpers\SellerMapper::getSellerCode($sku, $storeId),
                            'walmart_data' => [
                                'product_id' => $item['wpid'] ?? $item['martItemId'] ?? null,
                                'image_url' => $item['mainImage']['url'] ?? $item['images'][0]['url'] ?? null,
                                'price' => $item['price']['amount'] ?? 0,
                                'status' => $item['lifecycleStatus'] ?? 'ACTIVE'
                            ]
                        ]
                    );

                    if (!($item['mainImage']['url'] ?? $item['images'][0]['url'] ?? null)) {
                         Log::warning("No image found for SKU: {$sku}");
                    }
                    $count++;
                } catch (\Exception $e) {
                    Log::error("Error syncing Walmart product {$sku}: " . $e->getMessage());
                }
            }

            return ['success' => true, 'count' => $count, 'total' => count($items)];

        } catch (\Exception $e) {
            Log::error("Walmart product sync failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Map Walmart order/line status to OMS status.
     *
     * Walmart statuses: Created, Acknowledged, Shipped, Delivered, Cancelled, Refund
     * OMS statuses:     pending, designing, review, production, shipped, delivered, cancelled, refunded, returned
     */
    protected function mapWalmartStatus(string $walmartStatus): string
    {
        return match (strtolower(trim($walmartStatus))) {
            'created'       => 'PENDING',
            'acknowledged'  => 'PENDING',
            'shipped'       => 'SHIPPED',
            'delivered'     => 'DELIVERED',
            'cancelled'     => 'CANCELLED',
            'refund',
            'refunded'      => 'REFUNDED',
            default         => 'PENDING',
        };
    }

    /**
     * Extract the most relevant status from a Walmart order line.
     * Uses line-level orderLineStatuses first, then falls back to order-level orderStatus.
     */
    protected function resolveLineStatus(array $line, array $orderItem): string
    {
        // Line-level statuses (most accurate per-item)
        $lineStatuses = $line['orderLineStatuses']['orderLineStatus'] ?? [];
        if (!empty($lineStatuses)) {
            // Take the last (most recent) status entry
            $latest = end($lineStatuses);
            $rawStatus = $latest['status'] ?? '';
            if ($rawStatus) {
                return $this->mapWalmartStatus($rawStatus);
            }
        }

        // Fall back to order-level status
        $orderStatus = $orderItem['orderStatus'] ?? '';
        if ($orderStatus) {
            return $this->mapWalmartStatus($orderStatus);
        }

        return 'PENDING';
    }

    /**
     * Extract tracking/fulfillment info from a Walmart order line.
     */
    protected function extractFulfillment(array $line): array
    {
        $lineStatuses = $line['orderLineStatuses']['orderLineStatus'] ?? [];
        foreach ($lineStatuses as $ls) {
            $tracking = $ls['trackingInfo'] ?? null;
            if ($tracking) {
                return [
                    'carrier' => $tracking['carrierName']['carrier'] ?? $tracking['carrier'] ?? '',
                    'tracking_number' => $tracking['trackingNumber'] ?? '',
                    'tracking_url' => $tracking['trackingURL'] ?? '',
                    'ship_date' => isset($tracking['shipDateTime'])
                        ? date('Y-m-d H:i:s', $tracking['shipDateTime'] / 1000)
                        : null,
                ];
            }
        }
        return [];
    }

    /**
     * Process a single Walmart order
     */
    protected function processOrder(array $item, Store $store): string
    {
        $platformOrderId = $item['purchaseOrderId'] ?? null;
        if (!$platformOrderId) return 'skipped';

        $orderLines = $item['orderLines']['orderLine'] ?? [];
        $result = 'skipped';

        foreach ($orderLines as $line) {
            $sku = $line['item']['sku'] ?? '';
            $lineId = $line['lineNumber'] ?? '1';
            $uniqueId = "WAL-{$platformOrderId}-{$lineId}";

            // Resolve actual status from Walmart API
            $status = $this->resolveLineStatus($line, $item);

            // Extract fulfillment/tracking info
            $fulfillment = $this->extractFulfillment($line);

            // Check existence
            $existing = \App\Models\Order::where('order_id', $uniqueId)->first();

            if ($existing) {
                // Update status & fulfillment if changed
                $changes = [];
                if (strtolower($existing->status) !== strtolower($status)) {
                    $changes['status'] = $status;
                }
                if (!empty($fulfillment) && $fulfillment !== ($existing->fulfillment ?? [])) {
                    $changes['fulfillment'] = $fulfillment;
                }
                if (!empty($changes)) {
                    $existing->update($changes);
                    $result = ($result === 'created') ? 'created' : 'updated';
                }
                continue;
            }

            // Seller Code
            $sellerCode = \App\Helpers\SellerMapper::getSellerCode($sku, $store->store_id);

            // Create Order
            \App\Models\Order::create([
                'order_id' => $uniqueId,
                'platform_order_id' => (string)$platformOrderId,
                'platform' => 'Walmart',
                'store_id' => $store->store_id,
                'seller_code' => $sellerCode,
                'order_date' => isset($item['orderDate']) ? date('Y-m-d H:i:s', $item['orderDate'] / 1000) : now(),
                'status' => $status,
                'customer_details' => [
                    'name' => trim(($item['shippingInfo']['postalAddress']['name'] ?? '')),
                    'email' => $item['customerEmailId'] ?? '',
                    'phone' => $item['shippingInfo']['phone'] ?? '',
                    'address1' => $item['shippingInfo']['postalAddress']['address1'] ?? '',
                    'address2' => $item['shippingInfo']['postalAddress']['address2'] ?? '',
                    'city' => $item['shippingInfo']['postalAddress']['city'] ?? '',
                    'state' => $item['shippingInfo']['postalAddress']['state'] ?? '',
                    'zip' => $item['shippingInfo']['postalAddress']['postalCode'] ?? '',
                    'country' => $item['shippingInfo']['postalAddress']['country'] ?? 'US'
                ],
                'product_details' => [
                    'name' => $line['item']['productName'] ?? '',
                    'sku' => $sku,
                    'quantity' => $line['orderLineQuantity']['amount'] ?? 1,
                    'price' => $line['charges']['charge'][0]['chargeAmount']['amount'] ?? 0,
                ],
                'financials' => [
                    'total_price' => $line['charges']['charge'][0]['chargeAmount']['amount'] ?? 0
                ],
                'fulfillment' => $fulfillment,
            ]);

            $result = 'created';
        }

        return $result;
    }
}
