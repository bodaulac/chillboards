<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\FlashshipService;
use App\Services\PrintwayService;

use App\Services\FJPODService;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::query();

        // Filter by status
        if ($status = $request->get('status')) {
            $query->whereRaw('LOWER(status) = ?', [strtolower($status)]);
        }

        // Filter by platform
        if ($platform = $request->get('platform')) {
            $query->where('platform', $platform);
        }

        // Filter by seller_code
        if ($seller = $request->get('seller_code')) {
            $query->where('seller_code', $seller);
        }

        // Filter by store_id
        if ($storeId = $request->get('store_id')) {
            $query->where('store_id', $storeId);
        }

        // Filter by date range
        if ($request->get('startDate') && $request->get('endDate')) {
            $query->whereBetween('order_date', [
                Carbon::parse($request->get('startDate'))->startOfDay(),
                Carbon::parse($request->get('endDate'))->endOfDay(),
            ]);
        }

        // Search by order_id or product name
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(product_details, '$.name')) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(product_details, '$.sku')) LIKE ?", ["%{$search}%"]);
            });
        }

        $perPage = min((int) $request->get('per_page', 50), 200);
        $orders = $query->orderBy('order_date', 'desc')->paginate($perPage);

        $mapped = collect($orders->items())->map(function ($order) {
            return [
                'id'          => $order->id,
                'orderID'     => $order->order_id,
                'status'      => $order->status,
                'date'        => $order->order_date?->toIso8601String() ?? $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'productName' => $order->product_details['name'] ?? 'Unnamed Product',
                'quantity'    => (int) ($order->product_details['quantity'] ?? 1),
                'sku'         => $order->product_details['sku'] ?? 'N/A',
                'platform'    => $order->platform,
                'mockupUrl'   => $order->product_details['mockup_url'] ?? null,
                'storeId'     => $order->store_id,
                'sellerCode'  => $order->seller_code,
                'total'       => (float) ($order->financials['total_price'] ?? $order->product_details['price'] ?? 0),
                'fulfillment' => $order->fulfillment,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $mapped,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|unique:orders',
            'platform' => 'required',
            'order_date' => 'required|date',
            'status' => 'required'
        ]);

        $order = Order::create(array_merge($validated, $request->only([
            'platform_order_id', 'store_id', 'seller_code',
            'customer_details', 'product_details', 'workflow',
            'timeline', 'tracking_info', 'financials'
        ])));
        return response()->json($order, 201);
    }

    public function show($id)
    {
        return response()->json(Order::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $oldStatus = $order->status;
        $validated = $request->validate([
            'status'           => 'nullable|string',
            'customer_details' => 'nullable|array',
            'product_details'  => 'nullable|array',
            'workflow'         => 'nullable|array',
            'tracking_info'    => 'nullable|array',
            'financials'       => 'nullable|array',
            'fulfillment'      => 'nullable|array',
        ]);

        $order->update($validated);

        if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            $timeline = $order->timeline ?? [];
            $timeline[] = [
                'event' => "Status changed from {$oldStatus} to {$validated['status']}",
                'time' => now()->toIso8601String(),
                'user' => $request->user()?->name ?? 'System'
            ];
            $order->timeline = $timeline;
            $order->save();
        }

        return response()->json($order);
    }

    public function destroy($id)
    {
        Order::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    /**
     * Fulfill an order via supplier
     */
    public function fulfill(Request $request, $id)
    {
        $order = Order::where('order_id', $id)->orWhere('id', $id)->firstOrFail();

        $request->validate([
            'supplier' => 'required|in:Flashship,Printway,FJPOD'
        ]);

        $supplier = $request->input('supplier');

        // Map Order model fields to the format expected by fulfillment services
        $orderData = $this->buildFulfillmentPayload($order, $request);

        $result = match ($supplier) {
            'Flashship' => app(FlashshipService::class)->createOrder($orderData),
            'Printway'  => app(PrintwayService::class)->createOrder($orderData),
            'FJPOD'     => app(FJPODService::class)->createOrder($orderData),
        };

        if ($result['success'] ?? false) {
            $fulfillment = $order->fulfillment ?? [];
            $fulfillment['supplier'] = $supplier;
            $fulfillment['supplierOrderId'] = $result['orderId'] ?? $result['flashshipOrderId'] ?? $result['fjpodOrderId'] ?? null;
            $fulfillment['status'] = 'PENDING';
            $fulfillment['fulfilledAt'] = now()->toIso8601String();

            if ($request->has('sku')) $fulfillment['selectedSku'] = $request->input('sku');
            if ($request->has('variant_id')) $fulfillment['selectedVariantId'] = $request->input('variant_id');

            $order->fulfillment = $fulfillment;
            $order->status = 'PRODUCTION';

            $timeline = $order->timeline ?? [];
            $timeline[] = [
                'event' => "Sent to {$supplier} for fulfillment",
                'time' => now()->toIso8601String(),
                'user' => $request->user()?->name ?? 'System'
            ];
            $order->timeline = $timeline;
            $order->save();

            return response()->json([
                'success' => true,
                'message' => "Order sent to {$supplier}",
                'data' => $result
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Failed to fulfill with {$supplier}",
            'error' => $result['error'] ?? 'Unknown error'
        ], 400);
    }

    /**
     * Build the fulfillment payload from Order model + request data.
     * Maps Order model field names to what FlashshipService/FJPODService/PrintwayService expect.
     */
    private function buildFulfillmentPayload(Order $order, Request $request): array
    {
        $cust = $order->customer_details ?? [];
        $prod = $order->product_details ?? [];

        // Split customer name into first/last (Walmart stores full name)
        $nameParts = explode(' ', trim($cust['name'] ?? 'Customer'), 2);
        $firstName = $cust['firstName'] ?? $nameParts[0] ?? 'Customer';
        $lastName  = $cust['lastName'] ?? ($nameParts[1] ?? '');

        return [
            'orderId'  => $order->order_id,
            'order_id' => $order->order_id,
            'customer' => [
                'name'      => $cust['name'] ?? $firstName,
                'firstName' => $firstName,
                'lastName'  => $lastName,
                'email'     => $cust['email'] ?? '',
                'phone'     => $cust['phone'] ?? '',
                'address'   => [
                    'line1'   => $cust['address1'] ?? $cust['address']['line1'] ?? '',
                    'line2'   => $cust['address2'] ?? $cust['address']['line2'] ?? '',
                    'city'    => $cust['city'] ?? $cust['address']['city'] ?? '',
                    'state'   => $cust['state'] ?? $cust['address']['state'] ?? '',
                    'zip'     => $cust['zip'] ?? $cust['address']['zip'] ?? '',
                    'country' => $cust['country'] ?? $cust['address']['country'] ?? 'US',
                ],
                'address1' => $cust['address1'] ?? $cust['address']['line1'] ?? '',
                'address2' => $cust['address2'] ?? $cust['address']['line2'] ?? '',
                'city'     => $cust['city'] ?? $cust['address']['city'] ?? '',
                'state'    => $cust['state'] ?? $cust['address']['state'] ?? '',
                'zip'      => $cust['zip'] ?? $cust['address']['zip'] ?? '',
                'country'  => $cust['country'] ?? $cust['address']['country'] ?? 'US',
            ],
            'product' => [
                'sku'       => $prod['sku'] ?? '',
                'name'      => $prod['name'] ?? '',
                'quantity'  => (int) ($prod['quantity'] ?? 1),
                'variantId' => $request->input('variant_id'),
            ],
            'design'              => $request->input('design', []),
            'variant_id'          => $request->input('variant_id'),
            'design_url'          => $request->input('design_url'),
            'mockup_url'          => $request->input('mockup_url'),
            'print_location'      => $request->input('print_location', 'Front'),
            'printType'           => $request->input('print_type', 'DTG'),
            'specialPrint'        => $request->has('special_print_areas') ? 1 : null,
            'special_print_areas' => $request->input('special_print_areas', []),
            'printLocations'      => [$request->input('print_location', 'Front')],
            'shipment'            => 1,
        ];
    }

    /**
     * Bulk fulfill order with multiple items
     */
    public function fulfillBulk(Request $request, $id)
    {
        $order = Order::where('order_id', $id)->orWhere('id', $id)->firstOrFail();

        $request->validate([
            'supplier' => 'required|in:Flashship,Printway,FJPOD',
            'items' => 'required|array|min:1',
            'items.*.sku' => 'required',
            'items.*.quantity' => 'required|integer|min:1',
            'printType' => 'nullable|integer|in:1,2,3',
            'specialPrint' => 'nullable|integer|in:0,1'
        ]);

        $supplier = $request->input('supplier');
        $items = $request->input('items');
        $cust = $order->customer_details ?? [];

        // Split name for Walmart-style flat customer data
        $nameParts = explode(' ', trim($cust['name'] ?? 'Customer'), 2);
        $firstName = $cust['firstName'] ?? $nameParts[0] ?? 'Customer';
        $lastName  = $cust['lastName'] ?? ($nameParts[1] ?? '');

        $orderData = [
            'orderId' => $order->order_id,
            'order_id' => $order->order_id,
            'customer' => [
                'firstName' => $firstName,
                'lastName'  => $lastName,
                'email'     => $cust['email'] ?? '',
                'phone'     => $cust['phone'] ?? '',
                'address'   => [
                    'line1'   => $cust['address1'] ?? $cust['address']['line1'] ?? '',
                    'line2'   => $cust['address2'] ?? $cust['address']['line2'] ?? '',
                    'city'    => $cust['city'] ?? $cust['address']['city'] ?? '',
                    'state'   => $cust['state'] ?? $cust['address']['state'] ?? '',
                    'zip'     => $cust['zip'] ?? $cust['address']['zip'] ?? '',
                    'country' => $cust['country'] ?? $cust['address']['country'] ?? 'US',
                ]
            ],
            'products' => $items,
            'design' => $request->input('design', []),
            'printType' => $request->input('printType', 2),
            'specialPrint' => $request->input('specialPrint'),
            'fjpodPrintSize' => $request->input('fjpodPrintSize', '12x16'),
            'printTech' => $request->input('printTech', 'DTG Print')
        ];

        // For Printway, transform to lineItems format
        if ($supplier === 'Printway') {
            $orderData = [
                'externalId' => $order->order_id,
                'orderId' => $order->order_id,
                'shippingAddress' => [
                    'name'        => "{$firstName} {$lastName}",
                    'email'       => $cust['email'] ?? 'noreply@printway.io',
                    'phone'       => $cust['phone'] ?? '',
                    'address1'    => $cust['address1'] ?? $cust['address']['line1'] ?? '',
                    'address2'    => $cust['address2'] ?? $cust['address']['line2'] ?? '',
                    'city'        => $cust['city'] ?? $cust['address']['city'] ?? '',
                    'province'    => $cust['state'] ?? $cust['address']['state'] ?? '',
                    'state'       => $cust['state'] ?? $cust['address']['state'] ?? '',
                    'zip'         => $cust['zip'] ?? $cust['address']['zip'] ?? '',
                    'countryCode' => $cust['country'] ?? $cust['address']['country'] ?? 'US',
                ],
                'lineItems' => array_map(function($item) {
                    return [
                        'sku' => $item['sku'],
                        'quantity' => $item['quantity'],
                        'designUrl' => $item['designUrl'] ?? '',
                        'backDesignUrl' => $item['backDesignUrl'] ?? '',
                        'leftSleeveUrl' => $item['leftSleeveUrl'] ?? '',
                        'rightSleeveUrl' => $item['rightSleeveUrl'] ?? '',
                        'mockupUrl' => $item['mockupUrl'] ?? '',
                        'printAreas' => []
                    ];
                }, $items)
            ];
        }

        $result = match($supplier) {
            'Flashship' => app(FlashshipService::class)->createOrder($orderData),
            'FJPOD' => app(FJPODService::class)->createOrder($orderData),
            'Printway' => app(PrintwayService::class)->createOrder($orderData),
        };

        if ($result['success'] ?? false) {
            $fulfillment = $order->fulfillment ?? [];
            $fulfillment['supplier'] = $supplier;
            $fulfillment['supplierOrderId'] = $result['orderId'] ?? $result['flashshipOrderId'] ?? null;
            $fulfillment['status'] = 'PENDING';
            $fulfillment['fulfilledAt'] = now()->toIso8601String();
            $fulfillment['itemCount'] = count($items);
            $fulfillment['items'] = array_map(fn($i) => [
                'sku' => $i['sku'],
                'quantity' => $i['quantity']
            ], $items);

            $order->fulfillment = $fulfillment;
            $order->status = 'PRODUCTION';

            $timeline = $order->timeline ?? [];
            $timeline[] = [
                'event' => "Bulk order (" . count($items) . " items) sent to {$supplier}",
                'time' => now()->toIso8601String(),
                'user' => $request->user()?->name ?? 'System'
            ];
            $order->timeline = $timeline;
            $order->save();

            return response()->json([
                'success' => true,
                'message' => "Bulk order (" . count($items) . " items) sent to {$supplier}",
                'data' => $result
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Failed to fulfill bulk order with {$supplier}",
            'error' => $result['error'] ?? 'Unknown error'
        ], 400);
    }

    public function getFJPODSKUs(Request $request)
    {
        $request->validate(['productCode' => 'required']);
        $service = app(FJPODService::class);
        return response()->json($service->getSKUs($request->input('productCode'), $request->input('printTech', 'DTG Print')));
    }

    public function getFlashshipVariants()
    {
        $service = app(FlashshipService::class);
        return response()->json($service->getVariants());
    }

    public function getFlashshipOrders(Request $request)
    {
        // Return local orders fulfilled via Flashship
        $orders = Order::where('fulfillment->supplier', 'Flashship')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
            'total' => $orders->count(),
        ]);
    }

    /**
     * Lookup FlashShip order by FlashShip Order ID or by local Order ID (PO number).
     * If input looks like a PO/Walmart order ID, search local DB first for the FlashShip mapping.
     */
    public function lookupFlashshipOrder(Request $request)
    {
        $request->validate(['order_id' => 'required|string']);

        $input = trim($request->input('order_id'));
        $service = app(FlashshipService::class);

        // Check if input looks like a PO/system order ID (contains digits, dashes, or WAL- prefix)
        $isPONumber = preg_match('/^\d/', $input) || str_starts_with(strtoupper($input), 'WAL-');

        if ($isPONumber) {
            // Search local DB for this order ID → get FlashShip supplier order ID
            $order = Order::where('order_id', $input)->first();
            if (!$order && !str_starts_with(strtoupper($input), 'WAL-')) {
                // Try with WAL- prefix
                $order = Order::where('order_id', 'WAL-' . $input)->first();
            }
            if (!$order) {
                // Try partial match (order_id contains the input)
                $order = Order::where('order_id', 'LIKE', '%' . $input . '%')
                    ->where('fulfillment->supplier', 'Flashship')
                    ->first();
            }

            if ($order) {
                $fulfillment = $order->fulfillment ?? [];
                $flashshipId = $fulfillment['supplierOrderId'] ?? null;

                if ($flashshipId) {
                    // Found FlashShip ID, lookup on API
                    $result = $service->syncTracking($flashshipId);
                    $result['local_order'] = [
                        'order_id' => $order->order_id,
                        'status' => $order->status,
                        'flashship_id' => $flashshipId,
                    ];
                    // Persist cost to local order
                    $this->persistCostToOrder($order, $result);
                    return response()->json($result);
                }

                return response()->json([
                    'success' => false,
                    'error' => "Order {$order->order_id} found but has no FlashShip ID. Supplier: " . ($fulfillment['supplier'] ?? 'N/A'),
                    'local_order' => [
                        'order_id' => $order->order_id,
                        'status' => $order->status,
                        'supplier' => $fulfillment['supplier'] ?? null,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => "No order found with ID: {$input}",
            ]);
        }

        // Input is a FlashShip Order ID → lookup directly on API
        $result = $service->syncTracking($input);

        // Also check if any local order has this FlashShip ID
        $localOrder = Order::where('fulfillment->supplierOrderId', $input)->first();
        if ($localOrder) {
            $result['local_order'] = [
                'order_id' => $localOrder->order_id,
                'status' => $localOrder->status,
                'flashship_id' => $input,
            ];
            // Persist cost to local order
            $this->persistCostToOrder($localOrder, $result);
        }

        return response()->json($result);
    }

    /**
     * Persist cost data from FlashShip API result to a local order.
     */
    private function persistCostToOrder(Order $order, array $result): void
    {
        if (!($result['success'] ?? false) || empty($result['cost'])) return;

        $fulfillment = $order->fulfillment ?? [];
        $fulfillment['cost'] = $result['cost'];
        $fulfillment['costSyncedAt'] = now()->toIso8601String();
        $order->fulfillment = $fulfillment;

        $financials = $order->financials ?? [];
        $financials['fulfillment_cost'] = $result['cost']['total'];
        $financials['cost_breakdown'] = $result['cost'];
        $order->financials = $financials;
        $order->save();
    }

    public function syncFlashshipTracking()
    {
        $orders = Order::whereRaw('LOWER(status) = ?', ['production'])
            ->where('fulfillment->supplier', 'Flashship')
            ->get();

        $count = 0;
        $service = app(FlashshipService::class);

        foreach ($orders as $order) {
            $fulfillment = $order->fulfillment;
            $supplierOrderId = $fulfillment['supplierOrderId'] ?? null;

            if ($supplierOrderId) {
                $status = $service->syncTracking($supplierOrderId);

                // Always save cost data if available
                if ($status['success'] && !empty($status['cost'])) {
                    $fulfillment['cost'] = $status['cost'];
                    $fulfillment['costSyncedAt'] = now()->toIso8601String();
                    $fin = $order->financials ?? [];
                    $fin['fulfillment_cost'] = $status['cost']['total'];
                    $order->financials = $fin;
                }

                if ($status['success'] && ($status['shipped'] ?? false)) {
                    $fulfillment['status'] = 'SHIPPED';
                    $fulfillment['trackingNumber'] = $status['tracking_number'];
                    $fulfillment['carrier'] = $status['carrier'];
                    $order->fulfillment = $fulfillment;
                    $order->status = 'SHIPPED';

                    $tracking = $order->tracking_info ?? [];
                    $tracking['number'] = $status['tracking_number'];
                    $tracking['carrier'] = $status['carrier'];
                    $order->tracking_info = $tracking;

                    $timeline = $order->timeline ?? [];
                    $timeline[] = [
                        'event' => "Tracking updated: {$status['tracking_number']} ({$status['carrier']})",
                        'time' => now()->toIso8601String(),
                        'user' => 'System'
                    ];
                    $order->timeline = $timeline;
                    $order->save();
                    $count++;
                } elseif ($status['success'] && !empty($status['cost'])) {
                    // Cost was updated but not shipped yet — still save
                    $order->fulfillment = $fulfillment;
                    $order->save();
                }
            }
        }

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * Sync costs for ALL FlashShip orders (not just PRODUCTION).
     * Fetches cost data from FlashShip API and saves to fulfillment.cost.
     */
    public function syncFlashshipCosts()
    {
        $orders = Order::where('fulfillment->supplier', 'Flashship')
            ->whereNotNull('fulfillment->supplierOrderId')
            ->get();

        $count = 0;
        $service = app(FlashshipService::class);

        foreach ($orders as $order) {
            $fulfillment = $order->fulfillment;
            $supplierOrderId = $fulfillment['supplierOrderId'] ?? null;

            // Skip if already has cost data
            if (!empty($fulfillment['cost']['total']) && $fulfillment['cost']['total'] > 0) {
                continue;
            }

            if ($supplierOrderId) {
                $status = $service->syncTracking($supplierOrderId);
                if ($status['success'] && !empty($status['cost']) && $status['cost']['total'] > 0) {
                    $fulfillment['cost'] = $status['cost'];
                    $fulfillment['costSyncedAt'] = now()->toIso8601String();
                    $order->fulfillment = $fulfillment;

                    $financials = $order->financials ?? [];
                    $financials['fulfillment_cost'] = $status['cost']['total'];
                    $order->financials = $financials;
                    $order->save();
                    $count++;
                }
                usleep(200000); // 200ms delay to avoid rate limiting
            }
        }

        return response()->json(['success' => true, 'count' => $count]);
    }

    /**
     * Get finance/cost analytics for FlashShip fulfilled orders.
     */
    public function fulfillmentFinance(Request $request)
    {
        $orders = Order::where('fulfillment->supplier', 'Flashship')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        $platformFees = ['walmart' => 0.15, 'shopify' => 0.04];

        $items = [];
        $totalRevenue = 0;
        $totalCost = 0;
        $totalPlatformFee = 0;
        $totalProfit = 0;
        $ordersWithCost = 0;

        foreach ($orders as $order) {
            $f = $order->fulfillment ?? [];
            $pd = $order->product_details ?? [];
            $fin = $order->financials ?? [];

            $revenue = (float) ($fin['total_price'] ?? $pd['price'] ?? 0);
            $supplierCost = (float) ($f['cost']['total'] ?? $fin['fulfillment_cost'] ?? 0);
            $platformKey = strtolower($order->platform ?? 'walmart');
            $feeRate = $platformFees[$platformKey] ?? 0.15;
            $platformFee = $revenue * $feeRate;
            $profit = $revenue - $supplierCost - $platformFee;
            $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

            if ($supplierCost > 0) $ordersWithCost++;

            $totalRevenue += $revenue;
            $totalCost += $supplierCost;
            $totalPlatformFee += $platformFee;
            $totalProfit += $profit;

            $items[] = [
                'orderId'       => $order->order_id,
                'flashshipId'   => $f['supplierOrderId'] ?? null,
                'productName'   => $pd['name'] ?? 'Unknown',
                'quantity'      => (int) ($pd['quantity'] ?? 1),
                'revenue'       => round($revenue, 2),
                'supplierCost'  => round($supplierCost, 2),
                'platformFee'   => round($platformFee, 2),
                'profit'        => round($profit, 2),
                'margin'        => round($margin, 1),
                'platform'      => $order->platform,
                'status'        => $order->status,
                'date'          => $order->order_date?->toIso8601String(),
                'costBreakdown' => $f['cost'] ?? null,
            ];
        }

        $avgMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return response()->json([
            'success' => true,
            'summary' => [
                'totalRevenue'     => round($totalRevenue, 2),
                'totalCost'        => round($totalCost, 2),
                'totalPlatformFee' => round($totalPlatformFee, 2),
                'totalProfit'      => round($totalProfit, 2),
                'avgMargin'        => round($avgMargin, 1),
                'totalOrders'      => count($items),
                'ordersWithCost'   => $ordersWithCost,
            ],
            'data' => $items,
        ]);
    }

    public function syncFJPODTracking()
    {
        $orders = Order::whereRaw('LOWER(status) = ?', ['production'])
            ->where('fulfillment->supplier', 'FJPOD')
            ->get();

        $count = 0;
        $service = app(FJPODService::class);

        foreach ($orders as $order) {
            $fulfillment = $order->fulfillment;
            $supplierOrderId = $fulfillment['supplierOrderId'] ?? null;

            if ($supplierOrderId) {
                $status = $service->syncTracking($supplierOrderId);
                if ($status['success'] && ($status['shipped'] ?? false)) {
                    $fulfillment['status'] = 'SHIPPED';
                    $fulfillment['trackingNumber'] = $status['tracking_number'];
                    $fulfillment['carrier'] = $status['carrier'];
                    $order->fulfillment = $fulfillment;
                    $order->status = 'SHIPPED';

                    $tracking = $order->tracking_info ?? [];
                    $tracking['number'] = $status['tracking_number'];
                    $tracking['carrier'] = $status['carrier'];
                    $order->tracking_info = $tracking;

                    $timeline = $order->timeline ?? [];
                    $timeline[] = [
                        'event' => "Tracking updated: {$status['tracking_number']} ({$status['carrier']})",
                        'time' => now()->toIso8601String(),
                        'user' => 'System'
                    ];
                    $order->timeline = $timeline;
                    $order->save();
                    $count++;
                }
            }
        }

        return response()->json(['success' => true, 'count' => $count]);
    }

    public function syncPrintwayTracking()
    {
        $orders = Order::whereRaw('LOWER(status) = ?', ['production'])
            ->where('fulfillment->supplier', 'Printway')
            ->get();

        $count = 0;
        $service = app(PrintwayService::class);

        foreach ($orders as $order) {
            $fulfillment = $order->fulfillment;
            $supplierOrderId = $fulfillment['supplierOrderId'] ?? null;

            if ($supplierOrderId) {
                $status = $service->syncTracking($supplierOrderId);
                if ($status['success'] && ($status['shipped'] ?? false)) {
                    $fulfillment['status'] = 'SHIPPED';
                    $fulfillment['trackingNumber'] = $status['tracking_number'];
                    $fulfillment['carrier'] = $status['carrier'];
                    $order->fulfillment = $fulfillment;
                    $order->status = 'shipped';

                    $tracking = $order->tracking_info ?? [];
                    $tracking['number'] = $status['tracking_number'];
                    $tracking['carrier'] = $status['carrier'];
                    $order->tracking_info = $tracking;

                    $timeline = $order->timeline ?? [];
                    $timeline[] = [
                        'event' => "Tracking updated: {$status['tracking_number']} ({$status['carrier']})",
                        'time' => now()->toIso8601String(),
                        'user' => 'System'
                    ];
                    $order->timeline = $timeline;
                    $order->save();
                    $count++;
                }
            }
        }

        return response()->json(['success' => true, 'count' => $count]);
    }

    public function syncWalmartTracking()
    {
        // Placeholder for Walmart tracking push logic
        return response()->json(['success' => true, 'message' => 'Walmart sync not implemented yet']);
    }
}
