<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OrderStatusService;
use App\Models\Order;

class UpdateOrderStatusFromBlockchain extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:update-status-from-blockchain {--order-id= : Update specific order ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update order status based on blockchain certificate status';

    protected $orderStatusService;

    public function __construct(OrderStatusService $orderStatusService)
    {
        parent::__construct();
        $this->orderStatusService = $orderStatusService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderId = $this->option('order-id');

        if ($orderId) {
            // Update specific order
            $order = Order::find($orderId);
            if (!$order) {
                $this->error("Order with ID {$orderId} not found");
                return 1;
            }

            $this->info("Updating order {$orderId}...");
            $updated = $this->orderStatusService->updateOrderStatusFromBlockchain($order);
            
            if ($updated) {
                $this->info("✅ Order {$orderId} status updated successfully");
                $this->line("New status: {$order->fresh()->status}");
            } else {
                $this->warn("No status update needed for order {$orderId}");
            }

        } else {
            // Update all orders
            $this->info("Updating all orders with blockchain certificates...");
            $updatedCount = $this->orderStatusService->updateAllOrdersFromBlockchain();
            
            $this->info("✅ Updated {$updatedCount} orders");
        }

        return 0;
    }
}
