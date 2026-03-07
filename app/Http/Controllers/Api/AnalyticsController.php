<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Returns order status counts for the quick-stat bar.
     * Reuses calcStatusCounts() to avoid duplicate logic.
     */
    public function statistics()
    {
        return response()->json([
            'success' => true,
            'data' => $this->calcStatusCounts(),
        ]);
    }

    /**
     * Enhanced dashboard analytics:
     * - periodStats: revenue, profit, orders, avg, margin for selected period
     * - sellerBreakdown: grouped by seller_code with resolved names
     * - topProducts: top 10 by revenue within the selected period, grouped by true base SKU
     * - ordersByStatus: global status counts (all-time)
     * - periodOrdersByStatus: status counts within the selected period
     *
     * Query params:
     *   period: today|yesterday|last7Days|thisMonth|lastMonth (default: today)
     *   startDate, endDate: custom date range (YYYY-MM-DD)
     */
    public function dashboard(Request $request)
    {
        $period    = $request->get('period', 'today');
        $startDate = $request->get('startDate');
        $endDate   = $request->get('endDate');

        [$start, $end] = $this->resolveDates($period, $startDate, $endDate);

        // Load seller name map once
        $sellerNameMap = $this->getSellerNameMap();

        $periodStats          = $this->calcPeriodStats($start, $end);
        $sellerBreakdown      = $this->calcSellerBreakdown($start, $end, $sellerNameMap);
        $topProducts          = $this->calcTopProducts($start, $end, $sellerNameMap);
        $statusCounts         = $this->calcStatusCounts();
        $periodStatusCounts   = $this->calcStatusCounts($start, $end);

        return response()->json([
            'success' => true,
            'data' => [
                'period'              => $period,
                'dateRange'           => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                'periodStats'         => $periodStats,
                'sellerBreakdown'     => $sellerBreakdown,
                'topProducts'         => $topProducts,
                'ordersByStatus'      => $statusCounts,
                'periodOrdersByStatus' => $periodStatusCounts,
            ]
        ]);
    }

    // ---------- Helpers ----------

    /**
     * Build a seller_code => seller_name map from the Seller model.
     */
    private function getSellerNameMap(): array
    {
        $map = [];
        try {
            $sellers = Seller::all(['seller_code', 'seller_name']);
            foreach ($sellers as $s) {
                $map[$s->seller_code] = $s->seller_name;
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }
        return $map;
    }

    private function resolveDates(string $period, ?string $startDate, ?string $endDate): array
    {
        $now = Carbon::now();

        if ($startDate && $endDate) {
            return [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()];
        }

        return match ($period) {
            'yesterday'  => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
            'last7Days'  => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'thisMonth'  => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'lastMonth'  => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth()
            ],
            default      => [$now->copy()->startOfDay(), $now->copy()->endOfDay()], // today
        };
    }

    private function calcPeriodStats(Carbon $start, Carbon $end): array
    {
        $orders = Order::whereBetween('order_date', [$start, $end])->get();

        $totalOrders  = $orders->count();
        $totalRevenue = $orders->sum(fn($o) => $this->getRevenue($o));
        $totalCost    = $orders->sum(fn($o) => $this->getCost($o));
        $totalProfit  = $totalRevenue - $totalCost;
        $avgOrder     = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        $margin       = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return [
            'totalOrders'   => $totalOrders,
            'totalRevenue'  => round($totalRevenue, 2),
            'totalCost'     => round($totalCost, 2),
            'totalProfit'   => round($totalProfit, 2),
            'avgOrderValue' => round($avgOrder, 2),
            'profitMargin'  => round($margin, 2),
        ];
    }

    private function calcSellerBreakdown(Carbon $start, Carbon $end, array $sellerNameMap): array
    {
        $orders = Order::whereBetween('order_date', [$start, $end])
            ->whereNotNull('seller_code')
            ->get();

        $grouped = $orders->groupBy('seller_code');

        $sellers = [];
        foreach ($grouped as $code => $sellerOrders) {
            $revenue = $sellerOrders->sum(fn($o) => $this->getRevenue($o));
            $cost    = $sellerOrders->sum(fn($o) => $this->getCost($o));
            $profit  = $revenue - $cost;
            $cnt     = $sellerOrders->count();
            $avg     = $cnt > 0 ? $revenue / $cnt : 0;
            $margin  = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

            $sellers[] = [
                'sellerCode'    => $code,
                'sellerName'    => $sellerNameMap[$code] ?? $code,
                'totalOrders'   => $cnt,
                'totalRevenue'  => round($revenue, 2),
                'totalCost'     => round($cost, 2),
                'totalProfit'   => round($profit, 2),
                'avgOrderValue' => round($avg, 2),
                'profitMargin'  => round($margin, 2),
            ];
        }

        usort($sellers, fn($a, $b) => $b['totalRevenue'] <=> $a['totalRevenue']);

        return $sellers;
    }

    /**
     * Top 10 products by revenue within the selected period.
     * Groups by true base SKU (strips color-size suffix).
     *
     * SKU format: PREFIX-TYPE-DATE-HASH-COLOR-SIZE
     * Base SKU:   PREFIX-TYPE-DATE-HASH
     */
    private function calcTopProducts(Carbon $start, Carbon $end, array $sellerNameMap): array
    {
        $orders = Order::whereBetween('order_date', [$start, $end])
            ->whereNotNull('product_details')
            ->get();

        $products = [];
        foreach ($orders as $order) {
            $details  = $order->product_details ?? [];
            $fullSku  = $details['sku'] ?? 'UNKNOWN';
            $baseSku  = $this->extractBaseSku($fullSku);
            $name     = $details['name'] ?? $details['title'] ?? $baseSku;
            $revenue  = $this->getRevenue($order);
            $cost     = $this->getCost($order);
            $quantity = (int) ($details['quantity'] ?? 1);

            if (!isset($products[$baseSku])) {
                $products[$baseSku] = [
                    'baseSKU'       => $baseSku,
                    'productName'   => $name,
                    'sellerCode'    => $order->seller_code ?? 'DEFAULT',
                    'sellerName'    => $sellerNameMap[$order->seller_code ?? ''] ?? ($order->seller_code ?? 'DEFAULT'),
                    'totalOrders'   => 0,
                    'totalQuantity' => 0,
                    'totalRevenue'  => 0,
                    'totalCost'     => 0,
                ];
            }

            $products[$baseSku]['totalOrders']++;
            $products[$baseSku]['totalQuantity'] += $quantity;
            $products[$baseSku]['totalRevenue']  += $revenue;
            $products[$baseSku]['totalCost']     += $cost;
        }

        // Finalize
        $result = array_values($products);
        foreach ($result as &$p) {
            $p['totalRevenue']  = round($p['totalRevenue'], 2);
            $p['totalCost']     = round($p['totalCost'], 2);
            $p['totalProfit']   = round($p['totalRevenue'] - $p['totalCost'], 2);
            $p['avgPrice']      = $p['totalOrders'] > 0 ? round($p['totalRevenue'] / $p['totalQuantity'], 2) : 0;
            $p['profitMargin']  = $p['totalRevenue'] > 0 ? round(($p['totalProfit'] / $p['totalRevenue']) * 100, 2) : 0;
        }

        usort($result, fn($a, $b) => $b['totalRevenue'] <=> $a['totalRevenue']);
        return array_slice($result, 0, 10);
    }

    /**
     * Count orders by status. Optionally within a date range.
     * Case-insensitive: normalizes all statuses to lowercase.
     */
    private function calcStatusCounts(?Carbon $start = null, ?Carbon $end = null): array
    {
        $query = Order::query();
        if ($start && $end) {
            $query->whereBetween('order_date', [$start, $end]);
        }

        $statuses = [
            'pending', 'designing', 'review', 'production',
            'shipped', 'delivered', 'cancelled', 'refunded', 'returned',
        ];

        $cases = collect($statuses)->map(function ($s) {
            return "SUM(CASE WHEN LOWER(status) = '{$s}' THEN 1 ELSE 0 END) as `{$s}`";
        })->join(', ');

        $counts = $query->selectRaw("{$cases}, COUNT(*) as total")->first();

        $result = ['total' => (int) $counts->total];
        foreach ($statuses as $s) {
            $result[$s] = (int) $counts->{$s};
        }

        return $result;
    }

    // ---------- Revenue/Cost extractors ----------

    /**
     * Extract revenue from an order's financials or product_details.
     */
    private function getRevenue(Order $order): float
    {
        $f = $order->financials ?? [];
        return (float) ($f['total_price'] ?? $f['revenue'] ?? $order->product_details['price'] ?? 0);
    }

    /**
     * Extract cost from an order's financials.
     * Falls back to 0 if no cost data.
     */
    private function getCost(Order $order): float
    {
        $f = $order->financials ?? [];
        return (float) ($f['cost'] ?? $f['production_cost'] ?? $f['fulfillment_cost'] ?? 0);
    }

    /**
     * Extract true base SKU from a full SKU.
     * Format: PREFIX-TYPE-DATE-HASH-COLOR-SIZE → PREFIX-TYPE-DATE-HASH
     *
     * Examples:
     *   PH1-TEE-260307-19D523-BLA-L   → PH1-TEE-260307-19D523
     *   HHT030-TEE-260113-43A73F-SAN-L → HHT030-TEE-260113-43A73F
     *   PH1-SW-260209-D47B35-PUR-2XL  → PH1-SW-260209-D47B35
     */
    private function extractBaseSku(string $sku): string
    {
        $parts = explode('-', $sku);

        // Standard format has 6+ parts: PREFIX-TYPE-DATE-HASH-COLOR-SIZE
        // Base SKU is everything except the last 2 parts (color + size)
        if (count($parts) >= 6) {
            return implode('-', array_slice($parts, 0, -2));
        }

        // If fewer parts, return as-is (already a base SKU or non-standard format)
        return $sku;
    }
}
