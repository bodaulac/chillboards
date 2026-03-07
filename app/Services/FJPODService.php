<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FJPODService
{
    protected string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = env('FJPOD_API_URL', 'https://pubapi.fjpod.com/api/v3');
        $this->apiKey = env('FJPOD_API_KEY');
    }

    /**
     * Get SKUs for a product
     */
    public function getSKUs(string $productCode, string $printTech = 'DTG Print'): array
    {
        try {
            if (!$this->apiKey) throw new \Exception('FJPOD API key not configured');

            $allSKUs = [];
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = Http::withHeaders(['Authorization' => $this->apiKey])
                    ->get("{$this->baseUrl}/skus/{$productCode}", [
                        'print_tech' => $printTech,
                        'page' => $page,
                        'limit' => 100
                    ]);

                if (!$response->successful()) {
                    throw new \Exception("FJPOD API Error: " . ($response->json('detail') ?? $response->body()));
                }

                $data = $response->json();
                $skus = $data['data'] ?? [];
                $allSKUs = array_merge($allSKUs, $skus);

                $pagination = $data['pagination'] ?? null;
                if ($pagination && $pagination['page'] < $pagination['total']) {
                    $page++;
                } else {
                    $hasMore = false;
                }
            }

            return ['success' => true, 'data' => $allSKUs];
        } catch (\Exception $e) {
            Log::error("FJPOD getSKUs Failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create FJPOD order
     */
    public function createOrder(array $orderData): array
    {
        try {
            if (!$this->apiKey) throw new \Exception('FJPOD API key not configured');

            $orderId = $orderData['order_id'];
            $customer = $orderData['customer'] ?? $orderData; // Fallback if direct mapping
            $design = $orderData['design'] ?? [];
            
            // Map items
            $items = [];
            if (isset($orderData['products']) && is_array($orderData['products'])) {
                foreach ($orderData['products'] as $p) {
                    $items[] = $this->mapItem($p, $orderData);
                }
            } else {
                // Single item fallback
                $items[] = $this->mapItem($orderData, $orderData);
            }

            $payload = [
                'OrderId' => $orderId,
                'FirstName' => $customer['firstName'] ?? $customer['buyer_first_name'] ?? '',
                'LastName' => $customer['lastName'] ?? $customer['buyer_last_name'] ?? '',
                'AddressLine1' => $customer['address']['line1'] ?? $customer['buyer_address1'] ?? '',
                'AddressLine2' => $customer['address']['line2'] ?? $customer['buyer_address2'] ?? '',
                'City' => $customer['address']['city'] ?? $customer['buyer_city'] ?? '',
                'StateOrRegion' => $customer['address']['state'] ?? $customer['buyer_province_code'] ?? '',
                'Zip' => $customer['address']['zip'] ?? $customer['buyer_zip'] ?? '',
                'CountryCode' => $this->normalizeCountry($customer['address']['country'] ?? $customer['buyer_country_code'] ?? 'US'),
                'Phone' => $customer['phone'] ?? $customer['buyer_phone'] ?? '',
                'shipping_method' => $orderData['shipping_method'] ?? 'Standard',
                'label_link' => $design['labelUrl'] ?? '',
                'items' => $items,
                'product_service' => 'Standard',
                'seller' => 'HHT',
                'order_source' => 'OMS'
            ];

            Log::info("📦 Creating FJPOD order: {$orderId}");

            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->timeout(30)->post("{$this->baseUrl}/orders/single_order", $payload);

            if ($response->successful()) {
                $resData = $response->json();
                if ($resData['data'] ?? false) {
                    return [
                        'success' => true,
                        'orderId' => $resData['data'],
                        'status' => 'pending'
                    ];
                }
            }

            return [
                'success' => false,
                'error' => $response->json('detail') ?? $response->body()
            ];

        } catch (\Exception $e) {
            Log::error("FJPOD createOrder Failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncTracking(string $supplierOrderId): array
    {
        try {
            if (!$this->apiKey) throw new \Exception('FJPOD API key not configured');

            $response = Http::withHeaders([
                'Authorization' => $this->apiKey
            ])->get("{$this->baseUrl}/orders/detail/{$supplierOrderId}");

            if ($response->successful()) {
                $data = $response->json();
                $orderDetail = $data['data'] ?? null;

                if ($orderDetail && isset($orderDetail['TrackingNumber']) && $orderDetail['TrackingNumber']) {
                    return [
                        'success' => true,
                        'shipped' => true,
                        'tracking_number' => $orderDetail['TrackingNumber'],
                        'carrier' => $orderDetail['Carrier'] ?? 'USPS',
                        'status' => 'SHIPPED'
                    ];
                }

                return [
                    'success' => true,
                    'shipped' => false,
                    'status' => $orderDetail['Status'] ?? 'PENDING'
                ];
            }

            return ['success' => false, 'error' => $response->body()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function mapItem(array $p, array $context): array
    {
        $sku = $p['sku'] ?? $context['variant_id'] ?? $context['sku'] ?? '';
        $design = $context['design'] ?? [];
        
        // Handle new payload structure if present (design_url, mockup_url, etc.)
        $designUrl = $context['design_url'] ?? null;
        $mockupUrl = $context['mockup_url'] ?? null;
        $printLocation = $context['print_location'] ?? 'Front';
        $specialAreas = $context['special_print_areas'] ?? [];
        
        // Auto-detect product characteristics
        $isLarge = str_contains($sku, '180') || str_contains($sku, '185') || str_contains($sku, '1717');
        $isPoster = str_contains($sku, 'POSPL');

        // Smart print size detection
        $printSize = $p['print_size_front'] ?? $context['fjpodPrintSize'] ?? ($isLarge || $isPoster ? '14x16' : '12x16');
        if ($isPoster && $printSize === '12x16') {
            $printSize = '14x16'; // Force 14x16 for posters
        }

        // Design URL mapping
        $frontUrl = $designUrl ?: ($p['front_url'] ?? $p['designUrl'] ?? $design['designUrl'] ?? '');
        $backUrl = ($printLocation === 'Back' || $printLocation === 'Both') ? ($designUrl ?: ($p['back_url'] ?? $p['backDesignUrl'] ?? $design['backDesignUrl'] ?? '')) : '';
        
        if (!$frontUrl && !empty($design['designUrls'])) {
            $frontDesign = collect($design['designUrls'])->firstWhere('location', 'front');
            if ($frontDesign) $frontUrl = $frontDesign['url'];
        }
        
        if (!$backUrl && ($printLocation === 'Back' || $printLocation === 'Both') && !empty($design['designUrls'])) {
            $backDesign = collect($design['designUrls'])->firstWhere('location', 'back');
            if ($backDesign) $backUrl = $backDesign['url'];
        }

        $leftSleeveUrl = ($specialAreas['leftSleeve'] ?? false) ? ($designUrl ?: ($p['left_sleeve'] ?? '')) : '';
        $rightSleeveUrl = ($specialAreas['rightSleeve'] ?? false) ? ($designUrl ?: ($p['right_sleeve'] ?? '')) : '';

        // Mockup URLs
        $mockupFrontUrl = $mockupUrl ?: ($p['mockup_front_url'] ?? $p['mockupUrl'] ?? $design['mockupUrl'] ?? '');
        $mockupBackUrl = ($printLocation === 'Back' || $printLocation === 'Both') ? ($mockupUrl ?: ($p['mockup_back_url'] ?? $p['backMockupUrl'] ?? $design['backMockupUrl'] ?? '')) : '';

        // Print tech auto-detection
        $printTech = $isPoster ? 'Inkjet' : ($p['print_tech'] ?? $context['printTech'] ?? 'DTG Print');

        return [
            'sku' => $sku,
            'quantity' => $p['quantity'] ?? 1,
            'front_url' => $frontUrl,
            'mockup_front_url' => $mockupFrontUrl,
            'back_url' => $backUrl,
            'mockup_back_url' => $mockupBackUrl,
            'left_sleeve' => $leftSleeveUrl,
            'mockup_left_sleeve_url' => $p['mockup_left_sleeve_url'] ?? '',
            'right_sleeve' => $rightSleeveUrl,
            'mockup_right_sleeve_url' => $p['mockup_right_sleeve_url'] ?? '',
            'print_tech' => $printTech,
            'print_size_front' => $printSize,
            'print_size_back' => $backUrl ? $printSize : '',
            'note' => $p['note'] ?? ''
        ];
    }

    protected function normalizeCountry(string $country): string
    {
        if ($country === 'USA') return 'US';
        return strlen($country) > 2 ? 'US' : $country;
    }
}
