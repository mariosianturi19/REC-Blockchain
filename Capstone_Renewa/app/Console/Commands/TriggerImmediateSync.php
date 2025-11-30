<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Certificate;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class TriggerImmediateSync extends Command
{
    protected $signature = 'blockchain:immediate-sync {--order-id= : Specific order ID to sync} {--certificate-id= : Specific certificate ID to sync}';
    protected $description = 'Immediately sync and complete certificates after issuer verification';

    public function handle()
    {
        $this->info('🚀 Triggering immediate blockchain sync...');
        
        $orderId = $this->option('order-id');
        $certificateId = $this->option('certificate-id');
        
        if ($orderId) {
            $this->syncSpecificOrder($orderId);
        } elseif ($certificateId) {
            $this->syncSpecificCertificate($certificateId);
        } else {
            $this->syncRecentlyPurchased();
        }
        
        return 0;
    }
    
    private function syncSpecificOrder($orderId)
    {
        $order = Order::find($orderId);
        if (!$order) {
            $this->error("Order {$orderId} not found");
            return;
        }
        
        $this->info("🔄 Syncing order {$order->order_uid}...");
        $this->syncOrderCertificates($order);
    }
    
    private function syncSpecificCertificate($certificateId)
    {
        $certificate = Certificate::find($certificateId);
        if (!$certificate) {
            $this->error("Certificate {$certificateId} not found");
            return;
        }
        
        $this->info("🔄 Syncing certificate {$certificate->id}...");
        $this->syncSingleCertificate($certificate);
    }
    
    private function syncRecentlyPurchased()
    {
        $this->info('🔄 Syncing recently purchased/verified certificates...');
        
        // Find certificates that might be PURCHASED but not COMPLETED
        $certificates = Certificate::whereNotNull('blockchain_cert_id')
            ->whereIn('blockchain_status', ['CERTIFICATE_PAID', 'PURCHASED', 'CERTIFICATE_VERIFIED'])
            ->where('updated_at', '>=', now()->subHours(2)) // Last 2 hours
            ->get();
            
        $this->info("Found {$certificates->count()} certificates to check");
        
        foreach ($certificates as $certificate) {
            $this->syncSingleCertificate($certificate);
        }
    }
    
    private function syncOrderCertificates($order)
    {
        foreach ($order->certificates()->whereNotNull('blockchain_cert_id')->get() as $certificate) {
            $this->syncSingleCertificate($certificate);
        }
        
        // Update order status after syncing all certificates
        $this->updateOrderStatus($order);
    }
    
    private function syncSingleCertificate($certificate)
    {
        try {
            $this->line("  Checking certificate {$certificate->id} ({$certificate->blockchain_cert_id})");
            
            // Check CouchDB for current status
            $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_' . $certificate->blockchain_cert_id;
            $couchResponse = @file_get_contents($couchUrl);
            
            if ($couchResponse === false) {
                $this->warn("    ⚠️ Cannot fetch from CouchDB");
                return;
            }
            
            $couchData = json_decode($couchResponse, true);
            if (!$couchData) {
                $this->warn("    ⚠️ Invalid CouchDB response");
                return;
            }
            
            $blockchainStatus = $couchData['certificateInfo']['status'];
            $currentStatus = $certificate->blockchain_status;
            
            $this->line("    Current: {$currentStatus} | Blockchain: {$blockchainStatus}");
            
            // Update status if changed
            if ($currentStatus !== $blockchainStatus) {
                $certificate->update([
                    'blockchain_status' => $blockchainStatus,
                    'blockchain_response' => json_encode($couchData),
                ]);
                
                $this->info("    ✅ Status updated: {$currentStatus} → {$blockchainStatus}");
            }
            
            // If status is PURCHASED, trigger completion
            if ($blockchainStatus === 'PURCHASED') {
                $this->info("    🚀 Certificate is PURCHASED, triggering completion...");
                $this->triggerCompletion($certificate, $couchData);
            }
            
            // If status is CERTIFICATE_ISSUED, also trigger completion
            if ($blockchainStatus === 'CERTIFICATE_ISSUED') {
                $this->info("    🚀 Certificate is ISSUED, triggering completion...");
                $this->triggerCompletion($certificate, $couchData);
            }
            
        } catch (\Exception $e) {
            $this->error("    ❌ Error syncing: {$e->getMessage()}");
        }
    }
    
    private function triggerCompletion($certificate, $couchData)
    {
        try {
            // Get buyer info
            $buyerId = $this->getBuyerIdFromData($couchData, $certificate);
            
            $this->line("    Using buyer ID: {$buyerId}");
            
            // Call completion API
            $response = Http::timeout(30)->put('http://localhost:3000/api/certificates/complete/' . $certificate->blockchain_cert_id, [
                'generatorId' => $buyerId
            ]);
            
            if ($response->successful() && $response->json('success')) {
                $certificate->update([
                    'blockchain_status' => 'COMPLETED',
                    'blockchain_response' => json_encode([
                        'step5_complete' => $response->json(),
                        'immediate_sync' => true,
                        'completed_at' => now()->toISOString()
                    ])
                ]);
                
                $this->info("    ✅ Certificate completed successfully!");
                
                // Update order status immediately
                if ($certificate->order) {
                    $this->updateOrderStatus($certificate->order);
                }
                
            } else {
                $this->warn("    ⚠️ Completion failed: " . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error("    ❌ Error completing: {$e->getMessage()}");
        }
    }
    
    private function getBuyerIdFromData($couchData, $certificate)
    {
        // Try multiple sources for buyer ID
        if (isset($couchData['parties']['buyer']['buyerId'])) {
            return $couchData['parties']['buyer']['buyerId'];
        }
        
        if (isset($couchData['paymentDetails']['paymentConfirmedBy'])) {
            return $couchData['paymentDetails']['paymentConfirmedBy'];
        }
        
        // Fallback to user profile
        if ($certificate->order && $certificate->order->buyer) {
            return $certificate->order->buyer->name;
        }
        
        return 'DefaultBuyer';
    }
    
    private function updateOrderStatus($order)
    {
        $certificates = $order->certificates->whereNotNull('blockchain_status');
        
        if ($certificates->isEmpty()) {
            return;
        }
        
        $allCompleted = $certificates->every(function($cert) {
            return $cert->blockchain_status === 'COMPLETED';
        });
        
        if ($allCompleted && $order->status !== 'completed') {
            $oldStatus = $order->status;
            $order->update(['status' => 'completed']);
            
            $this->info("  🎉 Order {$order->order_uid}: {$oldStatus} → completed");
        }
    }
}