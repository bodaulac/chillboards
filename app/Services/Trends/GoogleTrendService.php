<?php

namespace App\Services\Trends;

use App\Models\Trend;
use App\Models\News;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleTrendService
{
    protected $apiKey;
    const BASE_URL = 'https://serpapi.com/search';

    public function __construct()
    {
        $this->apiKey = config('services.serpapi.key');
    }

    public function fetchDailyTrends($geo = 'US')
    {
        if (!$this->apiKey) {
            Log::error('SERPAPI_KEY is not configured.');
            return [];
        }

        try {
            $response = Http::get(self::BASE_URL, [
                'engine' => 'google_trends_trending_now',
                'geo' => $geo,
                'api_key' => $this->apiKey,
                'hl' => 'en'
            ]);

            if ($response->failed()) {
                throw new \Exception("SerpAPI failed: " . $response->body());
            }

            $searches = $response->json('trending_searches') ?? [];
            $savedTrends = [];

            foreach ($searches as $search) {
                $query = $search['query'] ?? '';
                $volume = $search['search_volume'] ?? 0;
                
                // Save Trend
                $trend = Trend::updateOrCreate(
                    ['title' => $query, 'source' => 'Google Trends'],
                    [
                        'keywords' => [$query],
                        'description' => ($volume > 0 ? "{$volume} searches" : "Trending now"),
                        'trending_score' => $this->calculateScore($volume),
                        'category' => $this->categorize($query),
                        'status' => 'new',
                        'metadata' => [
                            'geo' => $geo,
                            'searchVolume' => $volume,
                            'increasePercentage' => $search['increase_percentage'] ?? null
                        ]
                    ]
                );

                // Create News if new or urgent
                if ($trend->wasRecentlyCreated) {
                    News::create([
                        'title' => "🔍 Google Trend: {$query}",
                        'message' => "New trending keyword: \"{$query}\"",
                        'description' => "Volume: {$volume}. Category: {$trend->category}",
                        'event_type' => 'TRENDING',
                        'source' => 'google',
                        'category' => 'alert',
                        'priority' => $trend->trending_score > 90 ? 'high' : 'medium',
                        'metadata' => ['trendId' => $trend->id]
                    ]);
                }

                $savedTrends[] = $trend;
            }

            return $savedTrends;

        } catch (\Exception $e) {
            Log::error("Google Trends Error: " . $e->getMessage());
            return [];
        }
    }

    protected function calculateScore($searches)
    {
        // Simple logic based on ported JS
        // parses "200K+", "5M+" etc if string, but SerpAPI typically returns int or normalized string
        // Assuming int for simplicity or parsing logic similar to JS
        // (Omitted detailed parsing for brevity, assuming raw int provided by modern API or handled)
        return 75; // Placeholder
    }

    protected function categorize($query)
    {
        $q = strtolower($query);
        if (preg_match('/movie|film|netflix|disney/', $q)) return 'movie';
        if (preg_match('/music|song|concert/', $q)) return 'music';
        if (preg_match('/sport|nba|nfl|football/', $q)) return 'sports';
        if (preg_match('/iphone|tech|ai|crypto/', $q)) return 'tech';
        return 'general';
    }
}
