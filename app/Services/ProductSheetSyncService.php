<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ProductSheetSyncService
{
    protected $googleSheetService;

    // =====================================================================
    // ETSY SHEET COLUMN MAP (fixed, 0-indexed)
    // Matches exactly the structure used in sheetImportService.js (backend-v2)
    // Col A(0):ID | B(1):ImportDate | C(2):BaseSKU | D(3):Title | E(4):MainImage
    // F(5):PromptAI | G-L(6-11):SecImg1-6 | M(12):Desc | N(13):Platform
    // O(14):SellerCode | P(15):Source | Q(16):Type
    // R(17):DesignURL1 | S(18):MockupURL1 | T(19):DesignURL2 | U(20):MockupURL2
    // V(21):DesignStatus
    // =====================================================================
    const COL_ID           = 0;
    const COL_IMPORT_DATE  = 1;
    const COL_BASE_SKU     = 2;
    const COL_TITLE        = 3;
    const COL_MAIN_IMAGE   = 4;
    const COL_PROMPT_AI    = 5;
    const COL_SEC_IMG_1    = 6;
    const COL_SEC_IMG_2    = 7;
    const COL_SEC_IMG_3    = 8;
    const COL_SEC_IMG_4    = 9;
    const COL_SEC_IMG_5    = 10;
    const COL_SEC_IMG_6    = 11;
    const COL_DESC         = 12;
    const COL_PLATFORM     = 13;
    const COL_SELLER_CODE  = 14;
    const COL_SOURCE       = 15;
    const COL_TYPE         = 16;
    const COL_DESIGN_URL_1 = 17;
    const COL_MOCKUP_URL_1 = 18;
    const COL_DESIGN_URL_2 = 19;
    const COL_MOCKUP_URL_2 = 20;
    const COL_DESIGN_STATUS = 21;

    const ETSY_SHEET_NAME = 'Etsy';
    const ETSY_RANGE      = 'A2:Z'; // Skip header row

    public function __construct(GoogleSheetService $googleSheetService)
    {
        $this->googleSheetService = $googleSheetService;
    }

    /**
     * Sync products for a team from its configured Google Sheet.
     * Reads the 'Etsy' tab using the fixed column map.
     */
    public function syncTeamProducts(Team $team)
    {
        $url = $team->product_sheet_url;
        if (!$url) {
            throw new \Exception("Team '{$team->name}' does not have a product sheet URL configured.");
        }

        Log::info("Starting product sync for Team: {$team->name} | URL: {$url}");

        // Extract Spreadsheet ID from URL
        if (!preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $url, $matches)) {
            throw new \Exception("Invalid Google Sheet URL format. Must contain /d/SPREADSHEET_ID/");
        }
        $spreadsheetId = $matches[1];

        // Read the Etsy tab with fixed range
        $range = self::ETSY_SHEET_NAME . '!' . self::ETSY_RANGE;
        $rows  = $this->googleSheetService->readSheet($spreadsheetId, $range);

        if (empty($rows)) {
            Log::warning("No data found in Etsy sheet for Team: {$team->name}");
            return [
                'success' => true,
                'updated' => 0,
                'created' => 0,
                'message' => 'Etsy sheet has no data.'
            ];
        }

        Log::info("Read " . count($rows) . " rows from Etsy sheet for Team: {$team->name}");

        $stats = ['updated' => 0, 'created' => 0, 'errors' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            try {
                $this->processEtsyRow($row, $stats);
            } catch (\Exception $e) {
                Log::error("Error processing Etsy row: " . $e->getMessage());
                $stats['errors']++;
            }
        }

        Log::info("Sync completed for Team: {$team->name}. Stats: " . json_encode($stats));

        return array_merge(['success' => true], $stats);
    }

    /**
     * Process a single row using the FIXED Etsy column map.
     */
    protected function processEtsyRow(array $row, array &$stats): void
    {
        $baseSku = trim($row[self::COL_BASE_SKU] ?? '');
        $title   = trim($row[self::COL_TITLE] ?? '');

        // Skip if neither BaseSKU nor Title
        if (!$baseSku && !$title) {
            $stats['skipped']++;
            return;
        }

        // Generate BaseSKU from title if missing
        if (!$baseSku && $title) {
            $baseSku = strtoupper(preg_replace('/[^A-Z0-9\s]/i', '', $title));
            $baseSku = preg_replace('/\s+/', '-', trim($baseSku));
            $baseSku = substr($baseSku, 0, 30);
        }

        // Read all fields from fixed column positions
        $mainImage   = trim($row[self::COL_MAIN_IMAGE]   ?? '');
        $sellerCode  = trim($row[self::COL_SELLER_CODE]  ?? 'SYSTEM');
        $designUrl1  = trim($row[self::COL_DESIGN_URL_1] ?? '');
        $mockupUrl1  = trim($row[self::COL_MOCKUP_URL_1] ?? '');
        $designUrl2  = trim($row[self::COL_DESIGN_URL_2] ?? '');
        $mockupUrl2  = trim($row[self::COL_MOCKUP_URL_2] ?? '');
        $designStatus = trim($row[self::COL_DESIGN_STATUS] ?? '');
        $promptAi    = trim($row[self::COL_PROMPT_AI]    ?? '');
        $desc        = trim($row[self::COL_DESC]         ?? '');
        $productType = trim($row[self::COL_TYPE]          ?? 'T-Shirt');

        // Collect secondary images (filter empty)
        $secondaryImages = array_values(array_filter([
            $row[self::COL_SEC_IMG_1] ?? '',
            $row[self::COL_SEC_IMG_2] ?? '',
            $row[self::COL_SEC_IMG_3] ?? '',
            $row[self::COL_SEC_IMG_4] ?? '',
            $row[self::COL_SEC_IMG_5] ?? '',
            $row[self::COL_SEC_IMG_6] ?? '',
        ], fn($v) => trim($v) !== ''));

        // Find existing product by BaseSKU or SKU
        $product = Product::where('base_sku', $baseSku)->first()
                ?? Product::where('sku', $baseSku)->first();

        // --- Build data for upsert ---

        // 1. design_assignment: design/mockup URLs for order detail view
        $designAssignment = $product ? ($product->design_assignment ?? []) : [];
        if ($designUrl1)   $designAssignment['design_url']   = $designUrl1;
        if ($mockupUrl1)   $designAssignment['mockup_url']   = $mockupUrl1;
        if ($designUrl2)   $designAssignment['design_url_2'] = $designUrl2;
        if ($mockupUrl2)   $designAssignment['mockup_url_2'] = $mockupUrl2;
        if ($designStatus) $designAssignment['status']       = $designStatus;
        if ($promptAi)     $designAssignment['ai_prompt']    = $promptAi;

        // 2. walmart_data: image shown in the Products tab list
        // Priority: MockupURL1 > MainImage > DesignURL1
        $displayImage = $mockupUrl1 ?: $mainImage ?: $designUrl1;
        $walmartData  = $product ? ($product->walmart_data ?? []) : [];
        if ($displayImage) {
            $walmartData['mainMockupURL'] = $displayImage;
        }
        // Store all secondary images
        if (!empty($secondaryImages)) {
            $walmartData['secondaryImages'] = $secondaryImages;
        }
        if ($mainImage) {
            $walmartData['image_url'] = $mainImage;
        }

        $data = [
            'design_assignment' => $designAssignment,
            'walmart_data'      => $walmartData,
        ];

        if ($title)      $data['title']       = $title;
        if ($sellerCode) $data['seller_code']  = $sellerCode;
        if ($productType) {
            $uploadStatus = $product ? ($product->upload_status ?? []) : [];
            $uploadStatus['product_type'] = $productType;
            if ($desc) $uploadStatus['description'] = $desc;
            $data['upload_status'] = $uploadStatus;
        }

        if ($product) {
            $product->update($data);
            Log::debug("Updated product BaseSKU={$baseSku}: image=" . ($displayImage ?: 'none'));
            $stats['updated']++;
        } else {
            Product::create(array_merge($data, [
                'sku'      => $baseSku,
                'base_sku' => $baseSku,
            ]));
            Log::debug("Created product BaseSKU={$baseSku}");
            $stats['created']++;
        }
    }
}
