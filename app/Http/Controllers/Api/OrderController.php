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
        $order = Order::where('order_id', $id)->firstOrFail();
        
        $request->validate([
            'supplier' => 'required|in:Flashship,Printway,FJPOD'
        ]);
        
        $supplier = $request->input('supplier');
        $result = ['success' => false, 'error' => 'Unknown supplier'];

        if ($supplier === 'Flashship') {
            $service = app(FlashshipService::class);
            $result = $service->createOrder(array_merge($order->toArray(), $request->all()));
        } elseif ($supplier === 'Printway') {
            $service = app(PrintwayService::class);
            $result = $service->createOrder(array_merge($order->toArray(), $request->all()));
        } elseif ($supplier === 'FJPOD') {
            $service = app(FJPODService::class);
            $result = $service->createOrder(array_merge($order->toArray(), $request->all()));
        }

        if ($result['success'] ?? false) {
            $fulfillment = $order->fulfillment ?? [];
            $fulfillment['supplier'] = $supplier;
            $fulfillment['supplierOrderId'] = $result['orderId'] ?? $result['flashshipOrderId'] ?? $result['fjpodOrderId'] ?? null;
            $fulfillment['status'] = 'PENDING';
            $fulfillment['fulfilledAt'] = now()->toIso8601String();
            
            // Capture specific metadata if provided
            if ($request->has('sku')) $fulfillment['selectedSku'] = $request->input('sku');
            if ($request->has('variant_id')) $fulfillment['selectedVariantId'] = $request->input('variant_id');
            
            $order->fulfillment = $fulfillment;
            $order->status = 'production';
            
            // Add timeline entry
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
     * Bulk fulfill order with multiple items
     */
    public function fulfillBulk(Request $request, $id)
    {
        $order = Order::where('order_id', $id)->firstOrFail();
        
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
        
        // Prepare order data for bulk fulfillment
        $orderData = [
            'orderId' => $order->order_id,
            'order_id' => $order->order_id,
            'customer' => [
                'firstName' => $order->customer_details['firstName'] ?? 'Customer',
                'lastName' => $order->customer_details['lastName'] ?? 'Name',
                'email' => $order->customer_details['email'] ?? '',
                'phone' => $order->customer_details['phone'] ?? '',
                'address' => [
                    'line1' => $order->customer_details['address']['line1'] ?? '',
                    'line2' => $order->customer_details['address']['line2'] ?? '',
                    'city' => $order->customer_details['address']['city'] ?? '',
                    'state' => $order->customer_details['address']['state'] ?? '',
                    'zip' => $order->customer_details['address']['zip'] ?? '',
                    'country' => $order->customer_details['address']['country'] ?? 'US'
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
                    'name' => ($order->customer_details['firstName'] ?? 'Customer') . ' ' . ($order->customer_details['lastName'] ?? 'Name'),
                    'email' => $order->customer_details['email'] ?? 'noreply@printway.io',
                    'phone' => $order->customer_details['phone'] ?? '',
                    'address1' => $order->customer_details['address']['line1'] ?? '',
                    'address2' => $order->customer_details['address']['line2'] ?? '',
                    'city' => $order->customer_details['address']['city'] ?? '',
                    'province' => $order->customer_details['address']['state'] ?? '',
                    'state' => $order->customer_details['address']['state'] ?? '',
                    'zip' => $order->customer_details['address']['zip'] ?? '',
                    'countryCode' => $order->customer_details['address']['country'] ?? 'US'
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
        
        // Call appropriate service
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
            $order->status = 'production';
            
            // Add timeline entry
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

    public function syncFlashshipTracking()
    {
        $orders = Order::where('status', 'production')
            ->where('fulfillment->supplier', 'Flashship')
            ->get();

        $count = 0;
        $service = app(FlashshipService::class);

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

    public function syncFJPODTracking()
    {
        $orders = Order::where('status', 'production')
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

    public function syncPrintwayTracking()
    {
        $orders = Order::where('status', 'production')
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
