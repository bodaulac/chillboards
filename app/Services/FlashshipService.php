<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlashshipService
{
    protected string $baseUrl;
    protected ?string $apiToken;

    public function __construct()
    {
        $this->baseUrl = env('FLASHSHIP_API_URL', 'https://api.flashship.net/seller-api-v2');
        $this->apiToken = env('FLASHSHIP_API_TOKEN');
    }

    /**
     * Create order on Flashship (supports both single and bulk orders)
     */
    public function createOrder(array $orderData): array
    {
        try {
            if (!$this->apiToken) {
                throw new \Exception('Flashship API token not configured');
            }

            $orderId = $orderData['orderId'];
            $customer = $orderData['customer'];
            $design = $orderData['design'] ?? [];
            $shipment = $orderData['shipment'] ?? 1;
            $printType = $orderData['printType'] ?? 2; // Default: DTG
            $specialPrint = $orderData['specialPrint'] ?? null;
            
            // Check if this is a bulk order
            $products = $orderData['products'] ?? null;
            
            if ($products && is_array($products) && count($products) > 0) {
                // Bulk order - multiple items
                return $this->createBulkOrder($orderId, $customer, $products, $design, $shipment, $printType, $specialPrint);
            } else {
                // Single item order
                return $this->createSingleOrder($orderId, $customer, $orderData['product'] ?? [], $design, $shipment, $printType, $specialPrint, $orderData);
            }

        } catch (\Exception $e) {
            Log::error("❌ Flashship order creation failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create bulk order with multiple items
     */
    protected function createBulkOrder(string $orderId, array $customer, array $items, array $globalDesign, int $shipment, int $printType, $specialPrint): array
    {
        $products = [];

        foreach ($items as $item) {
            // Map design URLs for this item (item-specific > global)
            $designUrls = $this->mapItemDesignUrls($item, $globalDesign);
            
            // Determine special print flag
            $itemSpecialPrint = $this->determineSpecialPrint($designUrls, $item['special_print'] ?? $specialPrint);
            
            $products[] = [
                'variant_id' => (int)($item['variant_id'] ?? $item['variantId']),
                'quantity' => $item['quantity'] ?? 1,
                'printType' => (int)($item['printType'] ?? $printType),
                'special_print' => $itemSpecialPrint,
                ...$designUrls,
                'note' => $item['note'] ?? ''
            ];
        }

        $payload = [
            'order_id' => $orderId,
            'buyer_first_name' => $customer['firstName'],
            'buyer_last_name' => $customer['lastName'],
            'buyer_email' => $customer['email'] ?? '',
            'buyer_phone' => $customer['phone'] ?? '',
            'buyer_address1' => $customer['address']['line1'],
            'buyer_address2' => $customer['address']['line2'] ?? '',
            'buyer_city' => $customer['address']['city'],
            'buyer_province_code' => $customer['address']['state'],
            'buyer_zip' => $customer['address']['zip'],
            'buyer_country_code' => ($customer['address']['country'] === 'USA' || empty($customer['address']['country'])) ? 'US' : $customer['address']['country'],
            'shipment' => (string)$shipment,
            'link_label' => $globalDesign['labelUrl'] ?? null,
            'products' => $products
        ];

        return $this->sendFlashshipRequest($orderId, $payload);
    }

    /**
     * Create single item order
     */
    protected function createSingleOrder(string $orderId, array $customer, array $product, array $design, int $shipment, $printType, $specialPrint, array $orderData): array
    {
        // Handle new payload structure if present (design_url, mockup_url, etc.)
        $designUrl = $orderData['design_url'] ?? null;
        $mockupUrl = $orderData['mockup_url'] ?? null;
        $printLocation = $orderData['print_location'] ?? 'Front';
        $specialAreas = $orderData['special_print_areas'] ?? [];
        
        // Map print types to Flashship codes (2=DTG, 1=DTF, 3=Basic DTF)
        if (is_string($printType)) {
            $printType = match($printType) {
                'DTF' => 1,
                'Basic DTF' => 3,
                default => 2 // Default: DTG
            };
        }

        $urls = [];
        
        // Map primary designs based on location
        if ($designUrl) {
            $loc = strtolower($printLocation);
            if ($loc === 'front' || $loc === 'both') $urls['printer_design_front_url'] = $designUrl;
            if ($loc === 'back' || $loc === 'both') $urls['printer_design_back_url'] = $designUrl;
        }
        
        if ($mockupUrl) {
            $loc = strtolower($printLocation);
            if ($loc === 'front' || $loc === 'both') $urls['mockup_front_url'] = $mockupUrl;
            if ($loc === 'back' || $loc === 'both') $urls['mockup_back_url'] = $mockupUrl;
        }

        // Map special print areas if designUrl is provided (assuming same URL for now as per UI)
        if ($designUrl) {
            if ($specialAreas['leftSleeve'] ?? false) $urls['printer_design_left_url'] = $designUrl;
            if ($specialAreas['rightSleeve'] ?? false) $urls['printer_design_right_url'] = $designUrl;
            if ($specialAreas['neckLabel'] ?? false) $urls['printer_design_neck_url'] = $designUrl;
            if ($specialAreas['neck'] ?? false) $urls['printer_design_neck_url'] = $designUrl; // Flashship might use same field or we need to clarify
        }

        // Fallback to old mapping if new fields are empty
        if (empty($urls)) {
            $designUrls = $this->mapGlobalDesignUrls($design, $orderData['printLocations'] ?? [$printLocation]);
            $mockupUrls = $this->mapMockupUrls($design, $orderData['printLocations'] ?? [$printLocation]);
            $urls = array_merge($designUrls, $mockupUrls);
        }
        
        // Determine special print flag
        $hasSpecial = !empty($urls['printer_design_left_url']) || 
                      !empty($urls['printer_design_right_url']) || 
                      !empty($urls['printer_design_neck_url']) || 
                      ($specialPrint == 1);

        $payload = [
            'order_id' => $orderId,
            'buyer_first_name' => $customer['firstName'] ?? $customer['name'] ?? 'Customer',
            'buyer_last_name' => $customer['lastName'] ?? '',
            'buyer_email' => $customer['email'] ?? '',
            'buyer_phone' => $customer['phone'] ?? '',
            'buyer_address1' => $customer['address']['line1'] ?? $customer['address1'] ?? '',
            'buyer_address2' => $customer['address']['line2'] ?? $customer['address2'] ?? '',
            'buyer_city' => $customer['address']['city'] ?? $customer['city'] ?? '',
            'buyer_province_code' => $customer['address']['state'] ?? $customer['state'] ?? '',
            'buyer_zip' => $customer['address']['zip'] ?? $customer['zip'] ?? '',
            'buyer_country_code' => ($customer['address']['country'] ?? $customer['country'] ?? 'US') === 'USA' ? 'US' : ($customer['address']['country'] ?? $customer['country'] ?? 'US'),
            'shipment' => (string)$shipment,
            'link_label' => $design['labelUrl'] ?? null,
            'products' => [[
                'variant_id' => (int)($product['variantId'] ?? $orderData['variant_id'] ?? 0),
                'quantity' => $product['quantity'] ?? 1,
                'printType' => (int)$printType,
                'special_print' => $hasSpecial ? 1 : 0,
                ...$urls,
                'note' => $product['note'] ?? ''
            ]]
        ];

        return $this->sendFlashshipRequest($orderId, $payload);
    }

    /**
     * Map design URLs for individual item (item override > global)
     */
    protected function mapItemDesignUrls(array $item, array $globalDesign): array
    {
        $mapped = [];

        // Front Design (Item > Global)
        if (!empty($item['designUrl'])) {
            $mapped['printer_design_front_url'] = $item['designUrl'];
        } elseif (!empty($globalDesign['designUrl'])) {
            $mapped['printer_design_front_url'] = $globalDesign['designUrl'];
        } elseif (!empty($globalDesign['designUrls'])) {
            $frontDesign = collect($globalDesign['designUrls'])->firstWhere('location', 'front');
            if ($frontDesign) $mapped['printer_design_front_url'] = $frontDesign['url'];
        }

        // Back Design
        if (!empty($item['backDesignUrl'])) {
            $mapped['printer_design_back_url'] = $item['backDesignUrl'];
        } elseif (!empty($globalDesign['backDesignUrl'])) {
            $mapped['printer_design_back_url'] = $globalDesign['backDesignUrl'];
        } elseif (!empty($globalDesign['designUrls'])) {
            $backDesign = collect($globalDesign['designUrls'])->firstWhere('location', 'back');
            if ($backDesign) $mapped['printer_design_back_url'] = $backDesign['url'];
        }

        // Left Sleeve
        if (!empty($item['leftSleeveUrl'])) {
            $mapped['printer_design_left_url'] = $item['leftSleeveUrl'];
        } elseif (!empty($globalDesign['leftSleeveUrl'])) {
            $mapped['printer_design_left_url'] = $globalDesign['leftSleeveUrl'];
        }

        // Right Sleeve
        if (!empty($item['rightSleeveUrl'])) {
            $mapped['printer_design_right_url'] = $item['rightSleeveUrl'];
        } elseif (!empty($globalDesign['rightSleeveUrl'])) {
            $mapped['printer_design_right_url'] = $globalDesign['rightSleeveUrl'];
        }

        // Neck Label
        if (!empty($item['neckLabelUrl'])) {
            $mapped['printer_design_neck_url'] = $item['neckLabelUrl'];
        } elseif (!empty($globalDesign['neckLabelUrl'])) {
            $mapped['printer_design_neck_url'] = $globalDesign['neckLabelUrl'];
        }

        // Mockup URLs
        if (!empty($item['mockupUrl'])) {
            $mapped['mockup_front_url'] = $item['mockupUrl'];
        } elseif (!empty($globalDesign['mockupUrl'])) {
            $mapped['mockup_front_url'] = $globalDesign['mockupUrl'];
        }

        if (!empty($item['backMockupUrl'])) {
            $mapped['mockup_back_url'] = $item['backMockupUrl'];
        } elseif (!empty($globalDesign['backMockupUrl'])) {
            $mapped['mockup_back_url'] = $globalDesign['backMockupUrl'];
        }

        if (!empty($item['leftSleeveMockupUrl'])) {
            $mapped['mockup_left_url'] = $item['leftSleeveMockupUrl'];
        }

        if (!empty($item['rightSleeveMockupUrl'])) {
            $mapped['mockup_right_url'] = $item['rightSleeveMockupUrl'];
        }

        return $mapped;
    }

    /**
     * Map global design URLs (for single orders)
     */
    protected function mapGlobalDesignUrls(array $design, array $locations): array
    {
        $urls = [];
        
        // 1. High priority: designUrls array from complex designs
        if (!empty($design['designUrls']) && is_array($design['designUrls'])) {
             foreach ($design['designUrls'] as $item) {
                if (empty($item['url'])) continue;
                
                $loc = strtolower($item['location'] ?? 'front');
                
                if ($loc === 'front') {
                    $urls['printer_design_front_url'] = $item['url'];
                } elseif ($loc === 'back') {
                    $urls['printer_design_back_url'] = $item['url'];
                } elseif ($loc === 'left') {
                    $urls['printer_design_left_url'] = $item['url'];
                } elseif ($loc === 'right') {
                    $urls['printer_design_right_url'] = $item['url'];
                } elseif ($loc === 'neck') {
                    $urls['printer_design_neck_url'] = $item['url'];
                }
            }
        }
        // 2. Fallback: single designUrl + locations array
        elseif (!empty($design['designUrl'])) {
            foreach ($locations as $location) {
                $loc = strtolower($location);
                
                if ($loc === 'front') {
                    $urls['printer_design_front_url'] = $design['designUrl'];
                } elseif ($loc === 'back') {
                    $urls['printer_design_back_url'] = $design['designUrl'];
                }
            }
        }

        // 3. Direct field mapping (highest priority if present)
        if (!empty($design['designUrlLeft'])) $urls['printer_design_left_url'] = $design['designUrlLeft'];
        if (!empty($design['designUrlRight'])) $urls['printer_design_right_url'] = $design['designUrlRight'];
        if (!empty($design['designUrlNeck'])) $urls['printer_design_neck_url'] = $design['designUrlNeck'];
        
        return $urls;
    }

    /**
     * Map mockup URLs
     */
    protected function mapMockupUrls(array $design, array $locations): array
    {
        $urls = [];
        
        if (!empty($design['mockupUrls']) && is_array($design['mockupUrls'])) {
            foreach ($design['mockupUrls'] as $item) {
                if (empty($item['url'])) continue;
                
                $loc = strtolower($item['location'] ?? 'front');
                
                if ($loc === 'front') {
                    $urls['mockup_front_url'] = $item['url'];
                } elseif ($loc === 'back') {
                    $urls['mockup_back_url'] = $item['url'];
                } elseif ($loc === 'left') {
                    $urls['mockup_left_url'] = $item['url'];
                } elseif ($loc === 'right') {
                    $urls['mockup_right_url'] = $item['url'];
                } elseif ($loc === 'neck') {
                    $urls['mockup_neck_url'] = $item['url'];
                }
            }
        } elseif (!empty($design['mockupUrl'])) {
            $loc = strtolower($locations[0] ?? 'front');
            
            if ($loc === 'front') {
                $urls['mockup_front_url'] = $design['mockupUrl'];
            } elseif ($loc === 'back') {
                $urls['mockup_back_url'] = $design['mockupUrl'];
            }
        }

        // Direct field mapping
        if (!empty($design['mockupUrlLeft'])) $urls['mockup_left_url'] = $design['mockupUrlLeft'];
        if (!empty($design['mockupUrlRight'])) $urls['mockup_right_url'] = $design['mockupUrlRight'];
        if (!empty($design['mockupUrlNeck'])) $urls['mockup_neck_url'] = $design['mockupUrlNeck'];
        
        return $urls;
    }

    /**
     * Determine special print flag
     * Auto-enable if sleeves or neck designs are present
     */
    protected function determineSpecialPrint(array $designUrls, $explicitFlag = null): ?int
    {
        // If explicitly set, use that
        if ($explicitFlag !== null) {
            return $explicitFlag;
        }
        
        // Auto-detect: if sleeves or neck designs exist, enable special print
        $hasSpecialAreas = !empty($designUrls['printer_design_left_url']) ||
                           !empty($designUrls['printer_design_right_url']) ||
                           !empty($designUrls['printer_design_neck_url']);
        
        return $hasSpecialAreas ? 1 : null;
    }

    /**
     * Send request to Flashship API
     */
    protected function sendFlashshipRequest(string $orderId, array $payload): array
    {
        Log::info("📦 Creating Flashship order: {$orderId}", [
            'endpoint' => "{$this->baseUrl}/orders/shirt-add",
            'itemCount' => count($payload['products'])
        ]);

        $response = Http::withToken($this->apiToken)
            ->timeout(30)
            ->post("{$this->baseUrl}/orders/shirt-add", $payload);

        if ($response->successful()) {
            $data = $response->json();
            if (($data['code'] ?? '') === 'FLS_200' || ($data['code'] ?? '') === 'FLS_201') {
                return [
                    'success' => true,
                    'orderId' => $data['data'],
                    'flashshipOrderId' => $data['data'],
                    'status' => 'pending',
                    'rawResponse' => $data
                ];
            }
            
            return [
                'success' => false,
                'error' => $data['msg'] ?? $data['err'] ?? 'Unknown Flashship error',
                'details' => $data
            ];
        }

        return [
            'success' => false,
            'error' => $response->json('message') ?? $response->body(),
            'httpStatus' => $response->status()
        ];
    }

    public function getVariants(): array
    {
        try {
            if (!$this->apiToken) throw new \Exception('Flashship API token not configured');

            $response = Http::withToken($this->apiToken)
                ->timeout(30)
                ->get("{$this->baseUrl}/orders/list-variant-sku");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json('data') ?? []
                ];
            }

            return ['success' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncTracking(string $flashshipOrderId): array
    {
        try {
            if (!$this->apiToken) throw new \Exception('Flashship API token not configured');

            $response = Http::withToken($this->apiToken)
                ->timeout(30)
                ->get("{$this->baseUrl}/orders/order-detail", [
                    'order_id' => $flashshipOrderId
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $order = $data['data'] ?? null;

                if ($order && !empty($order['tracking_number'])) {
                    return [
                        'success' => true,
                        'shipped' => true,
                        'tracking_number' => $order['tracking_number'],
                        'carrier' => $order['tracking_company'] ?? 'UPS',
                        'status' => 'SHIPPED'
                    ];
                }

                return [
                    'success' => true,
                    'shipped' => false,
                    'status' => $order['status'] ?? 'PENDING'
                ];
            }

            return ['success' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
