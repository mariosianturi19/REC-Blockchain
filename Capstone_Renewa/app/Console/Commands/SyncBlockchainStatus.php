<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Certificate;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncBlockchainStatus extends Command
{
    protected $signature = 'blockchain:sync-status {--order-id= : Specific order ID to sync}';
    protected $description = 'Sync blockchain status from CouchDB to Laravel database';

    public function handle()
    {
        $this->info('🔄 Starting blockchain status synchronization...');
        
        $orderIdFilter = $this->option('order-id');
        
        // Get certificates that need syncing
        $certificates = Certificate::whereNotNull('blockchain_cert_id');
        
        if ($orderIdFilter) {
            $certificates = $certificates->where('order_id', $orderIdFilter);
        }
        
        $certificates = $certificates->get();
        
        $this->info("Found {$certificates->count()} certificates to sync");
        
        $syncedCount = 0;
        
        foreach ($certificates as $certificate) {
            try {
                $this->line("Syncing certificate {$certificate->id} (blockchain_cert_id: {$certificate->blockchain_cert_id})");
                
                // Fetch from CouchDB
                $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_' . $certificate->blockchain_cert_id;
                $couchResponse = @file_get_contents($couchUrl);
                
                if ($couchResponse === false) {
                    $this->warn("  ⚠️ Certificate not found in CouchDB");
                    continue;
                }
                
                $couchData = json_decode($couchResponse, true);
                
                if (!$couchData || !isset($couchData['certificateInfo']['status'])) {
                    $this->warn("  ⚠️ Invalid CouchDB response");
                    continue;
                }
                
                $blockchainStatus = $couchData['certificateInfo']['status'];
                $currentStatus = $certificate->blockchain_status;
                
                if ($currentStatus !== $blockchainStatus) {
                    $certificate->update([
                        'blockchain_status' => $blockchainStatus,
                        'blockchain_response' => json_encode($couchData)
                    ]);
                    
                    $this->info("  ✅ Updated: {$currentStatus} → {$blockchainStatus}");
                    $syncedCount++;
                } else {
                    $this->line("  ✓ Already in sync: {$blockchainStatus}");
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Error syncing certificate {$certificate->id}: {$e->getMessage()}");
            }
        }
        
        $this->info("🎉 Synchronization completed! {$syncedCount} certificates updated");
        
        // Update order statuses based on certificate statuses
        $this->updateOrderStatuses($orderIdFilter);
        
        return 0;
    }
    
    private function updateOrderStatuses($orderIdFilter = null)
    {
        $this->info('🔄 Updating order statuses...');
        
        $orders = Order::whereHas('certificates', function ($query) {
            $query->whereNotNull('blockchain_status');
        });
        
        if ($orderIdFilter) {
            $orders = $orders->where('id', $orderIdFilter);
        }
        
        $orders = $orders->get();
        
        foreach ($orders as $order) {
            $certificates = $order->certificates->whereNotNull('blockchain_status');
            
            if ($certificates->isEmpty()) {
                continue;
            }
            
            // Determine order status based on certificate statuses
            $allCompleted = $certificates->every(function($cert) {
                return $cert->blockchain_status === 'COMPLETED';
            });
            
            $anyIssued = $certificates->contains(function($cert) {
                return $cert->blockchain_status === 'CERTIFICATE_ISSUED';
            });
            
            $newStatus = null;
            
            if ($allCompleted) {
                $newStatus = 'completed';
            } elseif ($anyIssued) {
                $newStatus = 'awaiting_confirmation';
            }
            
            if ($newStatus && $order->status !== $newStatus) {
                $oldStatus = $order->status;
                $order->update(['status' => $newStatus]);
                $this->info("  ✅ Order {$order->id}: {$oldStatus} → {$newStatus}");
            }
        }
    }
}