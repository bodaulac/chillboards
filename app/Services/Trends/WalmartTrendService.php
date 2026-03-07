<?php

namespace App\Services\Trends;

use App\Models\Store;
use App\Models\News;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WalmartTrendService
{
    const BASE_URL = 'https://marketplace.walmartapis.com/v3';

    public function analyzeTrends()
    {
        Log::info('📊 Analyzing Walmart trends...');
        
        $demand = $this->getDemandTrends();
        $price = $this->getPriceTrends();
        
        $newsCreated = 0;
        
        foreach ($demand['trends'] ?? [] as $trend) {
            if ($this->isHighDemand($trend)) {
                $this->createDemandNews($trend);
                $newsCreated++;
            }
        }

        foreach ($price['trends'] ?? [] as $trend) {
            if ($this->isSignificantPriceChange($trend)) {
                $this->createPriceNews($trend);
                $newsCreated++;
            }
        }
        
        return ['success' => true, 'newsCreated' => $newsCreated];
    }

    protected function getAccessToken() {
        // ... (Reuse or inject WalmartService logic ideally, duplicating for independence now)
        $store = Store::where('platform', 'Walmart')->where('active', true)->first();
        if (!$store) return null;

        $auth = base64_encode("{$store->credentials['clientId']}:{$store->credentials['clientSecret']}");
        $response = Http::withHeaders([
            'Authorization' => "Basic {$auth}",
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'WM_QOS.CORRELATION_ID' => uniqid(),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ])->post(self::BASE_URL . '/token', ['grant_type' => 'client_credentials']);

        return $response->json('access_token');
    }

    protected function getDemandTrends() {
        $token = $this->getAccessToken();
        if (!$token) return [];

        $response = Http::withHeaders([
            'WM_SEC.ACCESS_TOKEN' => $token,
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'WM_QOS.CORRELATION_ID' => uniqid(),
            'Accept' => 'application/json'
        ])->get(self::BASE_URL . '/insights/items/trending', [
            'trendType' => 'DEMAND_TRENDS',
            'limit' => 50
        ]);

        return $response->json();
    }

    protected function getPriceTrends() {
        $token = $this->getAccessToken();
        if (!$token) return [];

        $response = Http::withHeaders([
            'WM_SEC.ACCESS_TOKEN' => $token,
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'WM_QOS.CORRELATION_ID' => uniqid(),
            'Accept' => 'application/json'
        ])->get(self::BASE_URL . '/insights/items/trending', [
            'trendType' => 'PRICE_TRENDS',
            'limit' => 50
        ]);

        return $response->json();
    }

    protected function isHighDemand($trend) {
        $weekly = $trend['weeklyTrend']['score'] ?? 0;
        $monthly = $trend['monthlyTrend']['score'] ?? 0;
        return $weekly > 0.7 || $monthly > 0.7;
    }

    protected function isSignificantPriceChange($trend) {
        $weekly = $trend['weeklyTrend']['score'] ?? 0;
        return $weekly > 0.6;
    }

    protected function createDemandNews($trend) {
        $weekly = $trend['weeklyTrend']['score'] ?? 0;
        $monthly = $trend['monthlyTrend']['score'] ?? 0;
        $maxScore = max($weekly, $monthly);
        
        $exists = News::where('source', 'walmart')
            ->where('event_type', 'TRENDING')
            ->where('metadata->itemId', $trend['itemId'])
            ->where('event_timestamp', '>=', now()->subDay())
            ->exists();

        if ($exists) return;

        News::create([
            'title' => "📈 High Demand: " . ($trend['productName'] ?? $trend['itemId']),
            'message' => "High demand detected on Walmart.",
            'description' => "Demand Score: " . round($maxScore * 100) . "%",
            'event_type' => 'TRENDING',
            'source' => 'walmart',
            'category' => 'product',
            'priority' => $maxScore > 0.85 ? 'high' : 'medium',
            'related_sku' => $trend['sku'] ?? null,
            'action_required' => $maxScore > 0.85,
            'action_type' => $maxScore > 0.85 ? 'review_product' : 'none',
            'metadata' => [
                'itemId' => $trend['itemId'],
                'trendType' => 'DEMAND',
                'weeklyScore' => $weekly,
                'monthlyScore' => $monthly
            ]
        ]);
    }

    protected function createPriceNews($trend) {
        // Similar logic for Price...
        $exists = News::where('source', 'walmart')
            ->where('event_type', 'TRENDING')
            ->where('metadata->itemId', $trend['itemId'])
            // check trendType inside metadata json if supported by DB or exact match
            // Laravel 8+ supports ->where('metadata->trendType', 'PRICE')
            ->where('event_timestamp', '>=', now()->subDay())
            ->exists();

        if ($exists) return;

        News::create([
            'title' => "💰 Price Trend: " . ($trend['productName'] ?? $trend['itemId']),
            'message' => "Significant price movement detected.",
            'description' => "Price Score: " . round(($trend['weeklyTrend']['score'] ?? 0) * 100) . "%",
            'event_type' => 'TRENDING',
            'source' => 'walmart',
            'category' => 'product',
            'priority' => 'medium',
            'related_sku' => $trend['sku'] ?? null,
            'metadata' => [
                'itemId' => $trend['itemId'],
                'trendType' => 'PRICE'
            ]
        ]);
    }
}
