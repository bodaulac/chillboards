<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrintwayService
{
    protected string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = env('PRINTWAY_API_URL', 'https://apis.printway.io/v3');
        $this->apiKey = env('PRINTWAY_API_KEY');
    }

    // ... createOrder ...

    /**
     * Create order on Printway
     */
    public function createOrder(array $orderData): array
    {
        try {
            if (!$this->apiKey) throw new \Exception('Printway API key not configured');

            // Support both bulk-fulfillment payload and single Order model array
            $shippingAddress = $orderData['shippingAddress'] ?? [];
            if (empty($shippingAddress) && isset($orderData['customer_details']['address'])) {
                $cust = $orderData['customer_details'];
                $shippingAddress = [
                    'name' => ($cust['firstName'] ?? 'Customer') . ' ' . ($cust['lastName'] ?? 'Name'),
                    'email' => $cust['email'] ?? '',
                    'phone' => $cust['phone'] ?? '',
                    'address1' => $cust['address']['line1'] ?? '',
                    'address2' => $cust['address']['line2'] ?? '',
                    'city' => $cust['address']['city'] ?? '',
                    'province' => $cust['address']['state'] ?? '',
                    'zip' => $cust['address']['zip'] ?? '',
                    'countryCode' => $cust['address']['country'] ?? 'US',
                ];
            }

            $nameParts = explode(' ', trim($shippingAddress['name'] ?? ''));
            $firstName = $nameParts[0] ?? 'Customer';
            $lastName = implode(' ', array_slice($nameParts, 1)) ?: 'Name';
            
            $countryCode = $shippingAddress['countryCode'] ?? 'US';
            if ($countryCode === 'USA') $countryCode = 'US';

            // Support both 'lineItems' (bulk) and single-order fields
            $lineItems = $orderData['lineItems'] ?? null;
            if (!$lineItems) {
                // Single order fallback
                $lineItems = [[
                    'sku' => $orderData['variant_id'] ?? $orderData['product_details']['sku'] ?? '',
                    'quantity' => $orderData['product_details']['quantity'] ?? 1,
                    'designUrl' => $orderData['design_url'] ?? '',
                    'mockupUrl' => $orderData['mockup_url'] ?? '',
                    'printLocation' => $orderData['print_location'] ?? 'Front',
                    'specialAreas' => $orderData['special_print_areas'] ?? []
                ]];
            }

            $orderItems = collect($lineItems)->map(function ($item) {
                // Initialize artwork fields
                $artworkFields = [
                    'mockup_url' => $item['mockupUrl'] ?? '',
                    'artwork_front' => '',
                    'artwork_back' => '',
                    'artwork_left' => '',
                    'artwork_right' => '',
                    'artwork_hood' => '',
                    'artwork_left_chest' => '',
                    'artwork_right_chest' => '',
                    'artwork_left_upper_sleeves' => '',
                    'artwork_right_upper_sleeves' => '',
                    'artwork_left_lower_sleeves' => '',
                    'artwork_right_lower_sleeves' => ''
                ];

                // Map from printAreas array
                if (!empty($item['printAreas'])) {
                    foreach ($item['printAreas'] as $area) {
                        $position = strtolower($area['position'] ?? 'front');
                        $key = "artwork_{$position}";
                        $artworkFields[$key] = $area['design_url'];
                    }
                }
                
                // Direct field mapping (higher priority)
                $designUrl = $item['designUrl'] ?? '';
                if ($designUrl) {
                    $loc = strtolower($item['printLocation'] ?? 'front');
                    if ($loc === 'front' || $loc === 'both') $artworkFields['artwork_front'] = $designUrl;
                    if ($loc === 'back' || $loc === 'both') $artworkFields['artwork_back'] = $designUrl;
                    
                    // Special areas
                    $special = $item['specialAreas'] ?? [];
                    if ($special['leftSleeve'] ?? false) $artworkFields['artwork_left'] = $designUrl;
                    if ($special['rightSleeve'] ?? false) $artworkFields['artwork_right'] = $designUrl;
                    if ($special['hood'] ?? false) $artworkFields['artwork_hood'] = $designUrl;
                    // Note: Pocket and external neck mapping for Printway might need specific field knowledge
                }

                if (!empty($item['backDesignUrl'])) $artworkFields['artwork_back'] = $item['backDesignUrl'];
                if (!empty($item['leftSleeveUrl'])) $artworkFields['artwork_left'] = $item['leftSleeveUrl'];
                if (!empty($item['rightSleeveUrl'])) $artworkFields['artwork_right'] = $item['rightSleeveUrl'];
                if (!empty($item['hoodUrl'])) $artworkFields['artwork_hood'] = $item['hoodUrl'];
                
                // Ensure at least front artwork exists
                if (empty($artworkFields['artwork_front']) && !empty($item['printAreas'][0]['design_url'])) {
                    $artworkFields['artwork_front'] = $item['printAreas'][0]['design_url'];
                }

                return array_merge([
                    'item_sku' => $item['sku'],
                    'quantity' => $item['quantity'] ?? 1,
                ], $artworkFields);
            })->toArray();

            $payload = [
                'order_id' => $orderData['externalId'] ?? $orderData['orderId'] ?? $orderData['order_id'] ?? 'ORD-'.time(),
                'store_code' => '',
                'shipping_email' => $shippingAddress['email'] ?? 'noreply@printway.io',
                'firstName' => $firstName,
                'lastName' => $lastName,
                'shipping_phone' => $shippingAddress['phone'] ?? '',
                'shipping_address1' => $shippingAddress['address1'],
                'shipping_address2' => $shippingAddress['address2'] ?? '',
                'shipping_city' => $shippingAddress['city'],
                'shipping_province' => $shippingAddress['province'] ?? $shippingAddress['state'] ?? '',
                'shipping_province_code' => $shippingAddress['province'] ?? $shippingAddress['state'] ?? '',
                'shipping_zip' => $shippingAddress['zip'],
                'shipping_country_code' => $countryCode,
                'shipping_country' => $this->getCountryName($countryCode),
                'order_items' => $orderItems
            ];

            Log::info("📦 Creating Printway order: {$payload['order_id']}");

            $response = Http::withHeaders([
                'pw-access-token' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl}/order/create-new-order", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $orderData = $data['data'] ?? $data;

                if ($orderData && (isset($orderData['id']) || isset($orderData['order_id']))) {
                    return [
                        'success' => true,
                        'orderId' => $orderData['id'] ?? $orderData['order_id'],
                        'externalId' => $orderData['order_id'] ?? $orderData['external_id'],
                        'status' => $orderData['status'] ?? 'pending',
                        'trackingNumber' => $orderData['tracking_number'] ?? $orderData['tracking_code'] ?? '',
                        'data' => $orderData
                    ];
                }
            }

            return [
                'success' => false,
                'error' => $response->json('message') ?? 'Unknown Printway error or no data',
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error("❌ Printway order creation failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncTracking(string $printwayOrderId): array
    {
        try {
            if (!$this->apiKey) throw new \Exception('Printway API key not configured');

            $response = Http::withHeaders(['pw-access-token' => $this->apiKey])
                ->get("{$this->baseUrl}/orders/{$printwayOrderId}/tracking");

            if ($response->successful()) {
                $data = $response->json();
                $tracking = $data['data'] ?? $data;

                if (!empty($tracking['tracking_number']) || !empty($tracking['tracking_code'])) {
                    return [
                        'success' => true,
                        'shipped' => true,
                        'tracking_number' => $tracking['tracking_number'] ?? $tracking['tracking_code'],
                        'carrier' => $tracking['carrier'] ?? 'DHL',
                        'status' => 'SHIPPED'
                    ];
                }

                return [
                    'success' => true,
                    'shipped' => false,
                    'status' => $tracking['status'] ?? 'PENDING'
                ];
            }
            return ['success' => false, 'error' => 'No tracking data'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function getCountryName(string $code): string
    {
        $map = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France'
        ];
        return $map[$code] ?? $code;
    }
}
