<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WalmartUploadService
{
    const WALMART_API_BASE = 'https://marketplace.walmartapis.com/v3';

    /**
     * Upload to Walmart Workflow
     */
    public function uploadToWalmart(string $storeId, array $templateData, array $uploadSettings = [])
    {
        try {
            Log::info("Starting Walmart upload for store: {$storeId}");

            $store = Store::where('storeId', $storeId)->where('platform', 'Walmart')->firstOrFail();
            
            $clientId = $store->credentials['clientId'] ?? null;
            $clientSecret = $store->credentials['clientSecret'] ?? null;

            if (!$clientId || !$clientSecret) {
                throw new \Exception("Walmart API credentials not configured for store {$storeId}");
            }

            // Settings
            $brandName = $uploadSettings['brandName'] ?? $store->settings['walmart']['brandName'] ?? null;
            $fulfillmentCenterId = $uploadSettings['fulfillmentCenterId'] ?? $store->settings['walmart']['fulfillmentCenterId'] ?? null;
            $taxCode = $uploadSettings['taxCode'] ?? $store->settings['walmart']['taxCode'] ?? '2038710';
            $templateType = $uploadSettings['templateType'] ?? 'tshirt';

            if (!$brandName) {
                throw new \Exception("Brand name is required.");
            }

            // 1. Get Token
            $accessToken = $this->getAccessToken($clientId, $clientSecret);

            // 2. Convert to JSON
            Log::info("Converting template ({$templateType}) to Walmart JSON...");
            $jsonContent = $this->convertToWalmartJSON($templateData, [
                'brandName' => $brandName,
                'fulfillmentCenterId' => $fulfillmentCenterId,
                'taxCode' => $taxCode
            ], $templateType);

            // 3. Upload Feed
            $uploadResult = $this->uploadFeed($jsonContent, $accessToken);

            // 4. Initial Status Check
            sleep(5);
            $statusResult = $this->checkFeedStatus($uploadResult['feedId'], $accessToken);

            Log::info("Upload completed. Feed ID: {$uploadResult['feedId']}");

            return [
                'success' => true,
                'feedId' => $uploadResult['feedId'],
                'status' => $statusResult['status'],
                'itemsReceived' => $statusResult['itemsReceived'] ?? 0,
                'store' => [
                    'storeId' => $store->storeId,
                    'storeName' => $store->storeName
                ]
            ];

        } catch (\Exception $e) {
            Log::error("Walmart upload failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function getAccessToken($clientId, $clientSecret)
    {
        // Re-implementing simplified version or reusing WalmartService if possible.
        // For independence, implementing here briefly.
        $auth = base64_encode("{$clientId}:{$clientSecret}");
        
        $response = Http::withHeaders([
            'Authorization' => "Basic {$auth}",
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'WM_QOS.CORRELATION_ID' => uniqid(),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])->post(self::WALMART_API_BASE . '/token', [
            'grant_type' => 'client_credentials'
        ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }
        
        throw new \Exception("Failed to get Walmart Access Token: " . $response->body());
    }

    protected function uploadFeed($jsonContent, $accessToken)
    {
        // Multipart/form-data upload using Http facade
        $response = Http::attach(
            'file', json_encode($jsonContent), 'feed.json'
        )->withHeaders([
            'WM_SEC.ACCESS_TOKEN' => $accessToken,
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'WM_QOS.CORRELATION_ID' => uniqid(),
            'WM_CONSUMER.CHANNEL.TYPE' => '0f3e4dd4-0513-4346-b39d-af0e00ea066d',
            'Accept' => 'application/json'
        ])->post(self::WALMART_API_BASE . '/feeds?feedType=MP_ITEM');

        if ($response->successful()) {
            return ['feedId' => $response->json('feedId')];
        }

        throw new \Exception("Failed to upload feed: " . $response->body());
    }

    protected function checkFeedStatus($feedId, $accessToken)
    {
        $response = Http::withHeaders([
            'WM_SEC.ACCESS_TOKEN' => $accessToken,
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'WM_QOS.CORRELATION_ID' => uniqid(),
            'Accept' => 'application/json'
        ])->get(self::WALMART_API_BASE . "/feeds/{$feedId}");

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception("Failed to check feed status: " . $response->body());
    }

    /**
     * Map Template Data to Item Spec 5.0
     */
    protected function convertToWalmartJSON($templateData, $settings, $templateType)
    {
        $headers = $templateData['headers'];
        $rows = $templateData['rows'];
        $items = [];

        foreach ($rows as $row) {
            $itemMap = array_combine($headers, $row); // careful if lengths differ
            if (!$itemMap) continue; // skip mismatch

            $items[] = [
                "Item" => $this->createItemJSON($itemMap, $settings, $templateType)
            ];
        }

        return [
            "MPItemFeedHeader" => [
                "processMode" => "REPLACE",
                "subset" => "EXTERNAL",
                "locale" => "en",
                "sellingChannel" => "marketplace",
                "version" => "4.2"
            ],
            "MPItem" => $items
        ];
    }

    protected function createItemJSON($item, $settings, $templateType)
    {
        // ... (Porting logic similar to node.js)
        $sku = $item['Product ID'] ?? '';
        $name = $item['Item Name'] ?? '';
        $price = (float)($item['MSRP'] ?? $item['Selling Price'] ?? 0);
        $desc = $item['Product Description'] ?? $item['Site Description'] ?? '';
        
        $mainImage = $item['Main Image URL'] ?? '';
        $additionalAssets = [];
        for ($i=1; $i<=8; $i++) {
            if (!empty($item["Additional Image URL $i"])) {
                $additionalAssets[] = [
                    "assetUrl" => $item["Additional Image URL $i"],
                    "assetType" => "SECONDARY"
                ];
            }
        }

        // Product Type Logic
        $productType = "T-Shirts";
        $type = strtolower($templateType);
        if (str_contains($type, 'hoodie') || str_contains($type, 'sweatshirt')) {
            $productType = "Sweatshirts & Hoodies";
        } elseif (str_contains($type, 'poster') || str_contains($type, 'canvas')) {
            $productType = "Art";
        }

        $productObj = [
            "productName" => $name,
            "longDescription" => $desc,
            "shortDescription" => substr($desc, 0, 1000),
            "mainImage" => ["mainImageUrl" => $mainImage, "altText" => $name],
            "additionalAssets" => $additionalAssets,
            "productTaxCode" => $settings['taxCode'],
            "brand" => $settings['brandName'],
            "sku" => $sku,
            "productType" => $productType,
            "attributes" => []
        ];

        // Variant Group
        if (!empty($item['Variant Group ID'])) {
            $productObj['variantGroupId'] = $item['Variant Group ID'];
            $productObj['isPrimaryVariant'] = ($item['Is Primary Variant'] === 'Yes') ? 'Yes' : 'No';
            $productObj['variantAttributeNames'] = ($productType === "Art") ? ["size"] : ["color", "clothingSize"];
        }

        // Attributes
        $attributes = [
            "gender" => [$this->mapGender($item['Gender'] ?? '')],
            "ageGroup" => [$this->mapAgeGroup($item['Age Group'] ?? '')],
            "material" => [$item['Material'] ?? 'Cotton'],
            "pattern" => [$item['Pattern'] ?? 'Solid'],
            "count" => ["1"]
        ];

        if ($productType !== 'Art') {
            $attributes["color"] = [$item['Color'] ?? 'Multicolor'];
            $attributes["clothingSize"] = [$item['Size'] ?? 'One Size'];
            // ... add others
        }

        $productObj['attributes'] = $attributes;

        return $productObj;
    }

    protected function mapGender($val) {
        $v = strtolower($val);
        if (str_contains($v, 'wom') || str_contains($v, 'fem')) return 'Female';
        if (str_contains($v, 'man') || str_contains($v, 'male')) return 'Male';
        return 'Unisex';
    }

    protected function mapAgeGroup($val) {
        $v = strtolower($val);
        if (str_contains($v, 'kid') || str_contains($v, 'child')) return 'Child';
        if (str_contains($v, 'baby')) return 'Infant';
        return 'Adult';
    }
}
