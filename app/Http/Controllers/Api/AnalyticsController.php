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
     * Returns order status counts for the quick-stat bar on the dashboard.
     */
    public function statistics()
    {
        $counts = Order::selectRaw("
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'designing' THEN 1 ELSE 0 END) as designing,
            SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) as review,
            SUM(CASE WHEN status = 'production' THEN 1 ELSE 0 END) as production,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
            COUNT(*) as total
        ")->first();

        return response()->json([
            'success' => true,
            'data' => [
                'pending'    => (int) $counts->pending,
                'designing'  => (int) $counts->designing,
                'review'     => (int) $counts->review,
                'production' => (int) $counts->production,
                'shipped'    => (int) $counts->shipped,
                'total'      => (int) $counts->total,
            ]
        ]);
    }

    /**
     * Returns enhanced analytics data:
     * - periodStats: revenue, profit, orders, avg, margin for the selected period
     * - sellerBreakdown: grouped by seller_code
     * - topProducts: top 10 by revenue (all-time)
     * - ordersByStatus: same as /statistics
     *
     * Query params:
     *   period: today|yesterday|last7Days|thisMonth|lastMonth  (default: today)
     *   startDate, endDate: for custom date ranges (YYYY-MM-DD)
     */
    public function dashboard(Request $request)
    {
        $period    = $request->get('period', 'today');
        $startDate = $request->get('startDate');
        $endDate   = $request->get('endDate');

        [$start, $end] = $this->resolveDates($period, $startDate, $endDate);

        $periodStats     = $this->calcPeriodStats($start, $end);
        $sellerBreakdown = $this->calcSellerBreakdown($start, $end);
        $topProducts     = $this->calcTopProducts();
        $statusCounts    = $this->calcStatusCounts();

        return response()->json([
            'success' => true,
            'data' => [
                'period'          => $period,
                'dateRange'       => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                'periodStats'     => $periodStats,
                'sellerBreakdown' => $sellerBreakdown,
                'topProducts'     => $topProducts,
                'ordersByStatus'  => $statusCounts,
            ]
        ]);
    }

    // ---------- Helpers ----------

    private function resolveDates(string $period, ?string $startDate, ?string $endDate): array
    {
        $now = Carbon::now();

        if ($startDate && $endDate) {
            return [Carbon::parse($startDate)->startOfDay(), Carbon::parse($endDate)->endOfDay()];
        }

        return match ($period) {
            'yesterday'  => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
            'last7Days'  => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'thisMonth'  => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
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
        $totalRevenue = $orders->sum(fn($o) => (float) ($o->financials['total_price'] ?? $o->financials['revenue'] ?? 0));
        $totalProfit  = $orders->sum(fn($o) => (float) ($o->financials['profit'] ?? 0));
        $avgOrder     = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        $margin       = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return [
            'totalOrders'   => $totalOrders,
            'totalRevenue'  => round($totalRevenue, 2),
            'totalProfit'   => round($totalProfit, 2),
            'avgOrderValue' => round($avgOrder, 2),
            'profitMargin'  => round($margin, 2),
        ];
    }

    private function calcSellerBreakdown(Carbon $start, Carbon $end): array
    {
        $orders = Order::whereBetween('order_date', [$start, $end])
            ->whereNotNull('seller_code')
            ->get();

        // Group by seller_code
        $grouped = $orders->groupBy('seller_code');

        $sellers = [];
        foreach ($grouped as $code => $sellerOrders) {
            $revenue = $sellerOrders->sum(fn($o) => (float) ($o->financials['total_price'] ?? $o->financials['revenue'] ?? 0));
            $profit  = $sellerOrders->sum(fn($o) => (float) ($o->financials['profit'] ?? 0));
            $cnt     = $sellerOrders->count();
            $avg     = $cnt > 0 ? $revenue / $cnt : 0;
            $margin  = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

            $sellers[] = [
                'sellerCode'    => $code,
                'sellerName'    => $sellerOrders->first()->seller_code ?? $code,
                'totalOrders'   => $cnt,
                'totalRevenue'  => round($revenue, 2),
                'totalProfit'   => round($profit, 2),
                'avgOrderValue' => round($avg, 2),
                'profitMargin'  => round($margin, 2),
            ];
        }

        // Sort by revenue descending
        usort($sellers, fn($a, $b) => $b['totalRevenue'] <=> $a['totalRevenue']);

        return $sellers;
    }

    private function calcTopProducts(): array
    {
        $orders = Order::whereNotNull('product_details')->get();

        $products = [];
        foreach ($orders as $order) {
            $details  = $order->product_details ?? [];
            $baseSku  = $details['base_sku'] ?? $details['sku'] ?? 'UNKNOWN';
            $name     = $details['name'] ?? $details['title'] ?? $baseSku;
            $revenue  = (float) ($order->financials['total_price'] ?? $order->financials['revenue'] ?? 0);
            $profit   = (float) ($order->financials['profit'] ?? 0);
            $price    = (float) ($details['price'] ?? 0);

            if (!isset($products[$baseSku])) {
                $products[$baseSku] = [
                    'baseSKU'      => $baseSku,
                    'productName'  => $name,
                    'sellerCode'   => $order->seller_code ?? '—',
                    'totalOrders'  => 0,
                    'totalRevenue' => 0,
                    'totalProfit'  => 0,
                    'totalPrice'   => 0,
                ];
            }

            $products[$baseSku]['totalOrders']++;
            $products[$baseSku]['totalRevenue'] += $revenue;
            $products[$baseSku]['totalProfit']  += $profit;
            $products[$baseSku]['totalPrice']   += $price;
        }

        // Finalize calculations
        $result = array_values($products);
        foreach ($result as &$p) {
            $p['totalRevenue']  = round($p['totalRevenue'], 2);
            $p['totalProfit']   = round($p['totalProfit'], 2);
            $p['avgPrice']      = $p['totalOrders'] > 0 ? round($p['totalPrice'] / $p['totalOrders'], 2) : 0;
            $p['profitMargin']  = $p['totalRevenue'] > 0 ? round(($p['totalProfit'] / $p['totalRevenue']) * 100, 2) : 0;
            unset($p['totalPrice']);
        }

        // Sort by revenue, take top 10
        usort($result, fn($a, $b) => $b['totalRevenue'] <=> $a['totalRevenue']);
        return array_slice($result, 0, 10);
    }

    private function calcStatusCounts(): array
    {
        $counts = Order::selectRaw("
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'designing' THEN 1 ELSE 0 END) as designing,
            SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) as review,
            SUM(CASE WHEN status = 'production' THEN 1 ELSE 0 END) as production,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
            COUNT(*) as total
        ")->first();

        return [
            'pending'    => (int) $counts->pending,
            'designing'  => (int) $counts->designing,
            'review'     => (int) $counts->review,
            'production' => (int) $counts->production,
            'shipped'    => (int) $counts->shipped,
            'total'      => (int) $counts->total,
        ];
    }
}
