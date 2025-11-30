<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Certificate;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class AutoCompleteCertificates extends Command
{
    protected $signature = 'certificates:auto-complete {--dry-run : Show what would be completed without making changes}';
    protected $description = 'Automatically complete Step 5 for certificates with CERTIFICATE_ISSUED status';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('🚀 Starting automatic certificate completion...');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }
        
        // Find certificates yang sudah CERTIFICATE_ISSUED tapi order masih pending
        $candidateCertificates = Certificate::where('blockchain_status', 'CERTIFICATE_ISSUED')
            ->whereHas('order', function ($query) {
                $query->whereIn('status', ['pending_payment', 'awaiting_confirmation']);
            })
            ->with(['order', 'order.buyer'])
            ->get();
            
        $this->info("Found {$candidateCertificates->count()} certificates ready for completion");
        
        $completedCount = 0;
        
        foreach ($candidateCertificates as $certificate) {
            try {
                $this->info("📋 Processing Certificate: {$certificate->blockchain_cert_id}");
                $this->line("  Order: #{$certificate->order->id} ({$certificate->order->order_uid})");
                $this->line("  Current Status: {$certificate->order->status}");
                
                if ($dryRun) {
                    $this->line("  [DRY RUN] Would complete Step 5 for this certificate");
                    continue;
                }
                
                // Step 5: Create Purchase Request (Auto-complete)
                $success = $this->createPurchaseRequest($certificate);
                
                if ($success) {
                    // Update order status to completed
                    $certificate->order->update([
                        'status' => 'completed',
                        'completion_date' => now()
                    ]);
                    
                    // Update certificate purchase status
                    $certificate->update([
                        'blockchain_purchase_status' => 'PURCHASE_CONFIRMED'
                    ]);
                    
                    $completedCount++;
                    $this->info("  ✅ Successfully completed Step 5 for certificate {$certificate->blockchain_cert_id}");
                    
                    Log::info("Auto-completed certificate", [
                        'certificate_id' => $certificate->id,
                        'blockchain_cert_id' => $certificate->blockchain_cert_id,
                        'order_id' => $certificate->order->id
                    ]);
                } else {
                    $this->error("  ❌ Failed to complete Step 5 for certificate {$certificate->blockchain_cert_id}");
                }
                
            } catch (Exception $e) {
                $this->error("  ❌ Error processing certificate {$certificate->blockchain_cert_id}: " . $e->getMessage());
                Log::error("Auto-completion error", [
                    'certificate_id' => $certificate->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->info("🎉 Completed {$completedCount} certificates automatically");
        
        return 0;
    }
    
    private function createPurchaseRequest(Certificate $certificate): bool
    {
        try {
            // Step 5: Create Purchase Request
            $purchaseData = [
                'certificateId' => $certificate->blockchain_cert_id,
                'buyerId' => $certificate->order->buyer->organization_id ?? 'BUYER001',
                'quantity' => floatval($certificate->energy_amount),
                'price' => floatval($certificate->price_per_mwh)
            ];
            
            $this->line("  🛒 Creating purchase request...");
            
            $response = Http::timeout(30)->post(config('app.blockchain_api_url') . '/api/step5/create-purchase', $purchaseData);
            
            if (!$response->successful()) {
                $this->error("  ❌ Purchase request failed: " . $response->body());
                return false;
            }
            
            $responseData = $response->json();
            
            if (!$responseData || !isset($responseData['success']) || !$responseData['success']) {
                $this->error("  ❌ Purchase request failed: Invalid response");
                return false;
            }
            
            $this->line("  ✅ Purchase request created successfully");
            
            // Step 6: Confirm Purchase (Auto)
            $this->line("  ✅ Confirming purchase...");
            
            $confirmData = [
                'certificateId' => $certificate->blockchain_cert_id,
                'buyerId' => $certificate->order->buyer->organization_id ?? 'BUYER001',
                'issuerId' => 'ISSUER001'
            ];
            
            $confirmResponse = Http::timeout(30)->post(config('app.blockchain_api_url') . '/api/step6/confirm-purchase', $confirmData);
            
            if (!$confirmResponse->successful()) {
                $this->error("  ❌ Purchase confirmation failed: " . $confirmResponse->body());
                return false;
            }
            
            $confirmResponseData = $confirmResponse->json();
            
            if (!$confirmResponseData || !isset($confirmResponseData['success']) || !$confirmResponseData['success']) {
                $this->error("  ❌ Purchase confirmation failed: Invalid response");
                return false;
            }
            
            $this->line("  ✅ Purchase confirmed successfully");
            
            return true;
            
        } catch (Exception $e) {
            $this->error("  ❌ Exception in purchase process: " . $e->getMessage());
            return false;
        }
    }
}