<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\Certificate;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\Log;

class UpdateBlockchainStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting blockchain status update...');
        
        // Update existing certificates with dummy blockchain status
        $certificates = Certificate::whereNull('blockchain_status')->get();
        
        $this->command->info("Found {$certificates->count()} certificates without blockchain status");
        
        foreach ($certificates as $certificate) {
            // Simulate blockchain status based on certificate status
            $blockchainStatus = $this->mapCertificateStatusToBlockchain($certificate->status);
            
            $certificate->update([
                'blockchain_status' => $blockchainStatus,
                'blockchain_cert_id' => $certificate->certificate_uid,
                'blockchain_response' => json_encode([
                    'success' => true,
                    'message' => 'Simulated blockchain status',
                    'timestamp' => now()->toISOString()
                ])
            ]);
            
            $this->command->line("Updated certificate {$certificate->id} with blockchain status: {$blockchainStatus}");
        }
        
        // Update order statuses based on blockchain certificate status
        $orderStatusService = app(OrderStatusService::class);
        $orders = Order::whereHas('certificates', function ($query) {
            $query->whereNotNull('blockchain_status');
        })->get();
        
        $this->command->info("Updating {$orders->count()} orders with blockchain certificate status");
        
        $updatedCount = 0;
        foreach ($orders as $order) {
            if ($orderStatusService->updateOrderStatusFromBlockchain($order)) {
                $updatedCount++;
                $this->command->line("Updated order {$order->id} status to: {$order->fresh()->status}");
            }
        }
        
        $this->command->info("✅ Successfully updated {$updatedCount} orders");
        $this->command->info('Blockchain status update completed!');
    }
    
    /**
     * Map certificate status to blockchain status
     */
    private function mapCertificateStatusToBlockchain($certificateStatus)
    {
        $mapping = [
            'available_for_sale' => 'ISSUED',
            'on_hold' => 'PURCHASE_REQUESTED',
            'sold' => 'PURCHASED',
            'retired' => 'PURCHASED',
        ];
        
        return $mapping[$certificateStatus] ?? 'PENDING';
    }
}
