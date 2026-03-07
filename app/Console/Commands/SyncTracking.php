<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Services\FlashshipService;
use App\Services\PrintwayService;
use Illuminate\Support\Facades\Log;

class SyncTracking extends Command
{
    protected $signature = 'tracking:sync';
    protected $description = 'Sync tracking information from suppliers (Flashship, Printway)';

    protected $flashship;
    protected $printway;

    public function __construct(FlashshipService $flashship, PrintwayService $printway)
    {
        parent::__construct();
        $this->flashship = $flashship;
        $this->printway = $printway;
    }

    public function handle()
    {
        $this->info('Starting Tracking Sync...');

        $orders = Order::whereNotNull('fulfillment->supplierOrderId')
            ->whereNull('fulfillment->trackingNumber')
            ->whereNotIn('status', ['CANCELLED', 'REFUNDED', 'RETURNED', 'DELIVERED'])
            ->get();

        $this->info("Found {$orders->count()} pending orders.");

        foreach ($orders as $order) {
            $supplier = $order->fulfillment['supplier'] ?? '';
            $supplierOrderId = $order->fulfillment['supplierOrderId'] ?? null;

            if (!$supplier || !$supplierOrderId) continue;

            $this->line("Processing Order {$order->order_id} ({$supplier})...");

            try {
                $result = null;

                if (strtolower($supplier) === 'flashship') {
                    $result = $this->flashship->syncTracking($supplierOrderId);
                } elseif (strtolower($supplier) === 'printway') {
                    $result = $this->printway->syncTracking($supplierOrderId);
                }

                if ($result && ($result['success'] ?? false) && ($result['shipped'] ?? false)) {
                    $trackingNumber = $result['tracking_number'] ?? '';
                    $carrier = $result['carrier'] ?? '';
                    $this->info("  Found tracking: {$trackingNumber}");

                    $fulfillment = $order->fulfillment ?? [];
                    $fulfillment['trackingNumber'] = $trackingNumber;
                    $fulfillment['carrier'] = $carrier;
                    $fulfillment['trackingUrl'] = "https://www.google.com/search?q={$trackingNumber}";
                    $fulfillment['status'] = 'SHIPPED';

                    $order->fulfillment = $fulfillment;

                    // Update tracking_info JSON
                    $tracking = $order->tracking_info ?? [];
                    $tracking['number'] = $trackingNumber;
                    $tracking['carrier'] = $carrier;
                    $order->tracking_info = $tracking;

                    // Update Status
                    if (!in_array($order->status, ['shipped', 'SHIPPED', 'DELIVERED'])) {
                        $order->status = 'shipped';
                    }

                    // Add timeline entry
                    $timeline = $order->timeline ?? [];
                    $timeline[] = [
                        'event' => "Tracking updated: {$trackingNumber} ({$carrier})",
                        'time' => now()->toIso8601String(),
                        'user' => 'System'
                    ];
                    $order->timeline = $timeline;

                    $order->save();
                } else {
                    $this->line("  No tracking yet.");
                }

                sleep(1); // Rate limit

            } catch (\Exception $e) {
                Log::error("Error syncing tracking for {$order->order_id}: " . $e->getMessage());
                $this->error("  Error: " . $e->getMessage());
            }
        }

        $this->info('Tracking Sync Completed.');
    }
}
