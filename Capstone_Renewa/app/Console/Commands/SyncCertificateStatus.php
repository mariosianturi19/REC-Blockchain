<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Certificate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCertificateStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'certificates:sync-status';

    /**
     * The console command description.
     */
    protected $description = 'Automatically sync certificate status from CouchDB to MySQL and run Step 5 completion';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting automatic certificate status sync...');
        
        // Get all orders yang statusnya pending atau awaiting confirmation
        $pendingOrders = Order::whereIn('status', ['pending_payment', 'awaiting_confirmation'])
            ->with(['certificates', 'buyer'])
            ->get();
        
        $totalSynced = 0;
        $totalCompleted = 0;
        
        foreach ($pendingOrders as $order) {
            $this->info("📋 Processing Order #{$order->id} ({$order->order_uid})");
            
            foreach ($order->certificates as $certificate) {
                if (!$certificate->blockchain_cert_id) {
                    continue;
                }
                
                try {
                    // 1. Cek status di CouchDB
                    $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_' . $certificate->blockchain_cert_id;
                    $couchResponse = @file_get_contents($couchUrl);
                    
                    if ($couchResponse !== false) {
                        $couchData = json_decode($couchResponse, true);
                        
                        if (isset($couchData['certificateInfo']['status'])) {
                            $couchStatus = $couchData['certificateInfo']['status'];
                            
                            // 2. Update database jika status berbeda
                            if ($certificate->blockchain_status !== $couchStatus) {
                                $certificate->update([
                                    'blockchain_status' => $couchStatus,
                                    'blockchain_response' => json_encode($couchData)
                                ]);
                                
                                $this->info("✅ Certificate #{$certificate->id} synced: {$certificate->blockchain_status} → {$couchStatus}");
                                $totalSynced++;
                                
                                Log::info('AUTO SYNC: Certificate status updated', [
                                    'certificate_id' => $certificate->id,
                                    'old_status' => $certificate->blockchain_status,
                                    'new_status' => $couchStatus
                                ]);
                            }
                            
                            // 3. Jika CERTIFICATE_ISSUED, otomatis jalankan Step 5
                            if ($couchStatus === 'CERTIFICATE_ISSUED') {
                                $buyerId = $order->buyer->name ?? 'UnknownBuyer';
                                
                                $this->info("🚀 Running Step 5 for Certificate #{$certificate->id} with buyer: {$buyerId}");
                                
                                $response = Http::timeout(30)
                                    ->put('http://localhost:3000/api/certificates/complete/' . $certificate->blockchain_cert_id, [
                                        'buyerId' => $buyerId
                                    ]);

                                if ($response->successful()) {
                                    $certificate->update([
                                        'blockchain_status' => 'COMPLETED',
                                        'status' => 'completed'
                                    ]);
                                    
                                    $this->info("🎉 Certificate #{$certificate->id} completed successfully!");
                                    $totalCompleted++;
                                    
                                    Log::info('AUTO SYNC: Step 5 completed', [
                                        'certificate_id' => $certificate->id,
                                        'blockchain_cert_id' => $certificate->blockchain_cert_id,
                                        'buyer_id' => $buyerId
                                    ]);
                                } else {
                                    $this->warn("⚠️ Step 5 failed for Certificate #{$certificate->id}: " . $response->body());
                                }
                            }
                            
                            // 4. Jika sudah COMPLETED di CouchDB, sync ke database
                            if ($couchStatus === 'COMPLETED' && $certificate->blockchain_status !== 'COMPLETED') {
                                $certificate->update([
                                    'blockchain_status' => 'COMPLETED',
                                    'status' => 'completed'
                                ]);
                                
                                $this->info("✅ Certificate #{$certificate->id} marked as completed from CouchDB");
                            }
                        }
                    } else {
                        $this->warn("⚠️ Cannot fetch Certificate #{$certificate->id} from CouchDB");
                    }
                    
                } catch (\Exception $e) {
                    $this->error("❌ Error processing Certificate #{$certificate->id}: " . $e->getMessage());
                    Log::error('AUTO SYNC: Exception occurred', [
                        'certificate_id' => $certificate->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // 5. Update order status berdasarkan certificate status
            $this->updateOrderStatus($order);
        }
        
        $this->info("🏁 Sync completed! Synced: {$totalSynced}, Completed: {$totalCompleted}");
        Log::info('AUTO SYNC: Batch completed', [
            'total_synced' => $totalSynced,
            'total_completed' => $totalCompleted
        ]);
        
        return 0;
    }
    
    /**
     * Update order status berdasarkan certificate status
     */
    private function updateOrderStatus($order)
    {
        $certificates = $order->certificates()->whereNotNull('blockchain_status')->get();
        
        if ($certificates->isEmpty()) {
            return;
        }
        
        $allCompleted = $certificates->every(function($cert) {
            return $cert->blockchain_status === 'COMPLETED';
        });
        
        $anyIssued = $certificates->contains(function($cert) {
            return in_array($cert->blockchain_status, ['CERTIFICATE_ISSUED', 'COMPLETED']);
        });
        
        $newStatus = null;
        
        if ($allCompleted && $order->status !== 'completed') {
            $newStatus = 'completed';
        } elseif ($anyIssued && $order->status !== 'completed') {
            // ✅ FIXED: Langsung set ke completed ketika certificate issued
            $newStatus = 'completed';
        }
        
        if ($newStatus) {
            $order->update(['status' => $newStatus]);
            
            $this->info("📋 Order #{$order->id} status updated to: {$newStatus}");
            
            Log::info('AUTO SYNC: Order status updated', [
                'order_id' => $order->id,
                'new_status' => $newStatus
            ]);
        }
    }
}
