<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Certificate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SyncCertificatesFromBlockchain extends Command
{
    protected $signature = 'blockchain:sync-certificates';
    protected $description = 'Auto-sync certificate status from CouchDB to Laravel database';

    public function handle()
    {
        $this->info('🔄 Starting certificate sync from blockchain...');
        
        $syncedCount = 0;
        $errorCount = 0;
        
                // Get all certificates that either have a blockchain id and are not final,
                // or those that have never had a blockchain_status set yet.
                $certificates = Certificate::where(function ($q) {
                                $q->whereNotNull('blockchain_cert_id')
                                    ->whereNotIn('blockchain_status', ['COMPLETED', 'BLOCKCHAIN_REQUEST_FAILED']);
                        })
                        ->orWhereNull('blockchain_status')
                        ->get();
        
        $this->info("Found {$certificates->count()} certificates to sync");
        
        foreach ($certificates as $certificate) {
            try {
                $blockchainCertId = $certificate->blockchain_cert_id ?: $certificate->certificate_uid;
                
                if (!$blockchainCertId) {
                    continue;
                }
                
                // Fetch the document from CouchDB using Http client with basic auth.
                try {
                    $couchUrl = 'http://localhost:5984/recchannel_rec/CERTIFICATE_' . $blockchainCertId;
                    $couchRes = \Illuminate\Support\Facades\Http::withBasicAuth('admin', 'adminpw')
                        ->timeout(10)
                        ->get($couchUrl);

                    if (!$couchRes->successful()) {
                        $this->warn("CouchDB returned {$couchRes->status()} for CERTIFICATE_{$blockchainCertId}");
                        continue;
                    }

                    $couchData = $couchRes->json();
                    if (!is_array($couchData) || !isset($couchData['certificateInfo']['status'])) {
                        $this->warn("CouchDB doc missing status for CERTIFICATE_{$blockchainCertId}");
                        continue;
                    }

                    $couchStatus = $couchData['certificateInfo']['status'];
                } catch (\Exception $e) {
                    $this->warn("Exception fetching CouchDB CERTIFICATE_{$blockchainCertId}: {$e->getMessage()}");
                    continue;
                }
                
                // Update blockchain_cert_id if NULL
                if (!$certificate->blockchain_cert_id) {
                    $certificate->blockchain_cert_id = $blockchainCertId;
                    $certificate->save();
                    $this->info("✅ Set blockchain_cert_id for certificate {$certificate->id}");
                }
                
                // Sync status if different
                if ($certificate->blockchain_status !== $couchStatus) {
                    $certificate->update([
                        'blockchain_status' => $couchStatus,
                        'blockchain_response' => json_encode($couchData)
                    ]);
                    
                    $this->info("✅ Synced certificate {$certificate->id}: {$certificate->blockchain_status} → {$couchStatus}");
                    $syncedCount++;
                }
                
                // Auto-complete if CERTIFICATE_ISSUED, but guard to avoid duplicate Step 5
                if ($couchStatus === 'CERTIFICATE_ISSUED' && $certificate->order) {
                    $order = $certificate->order;
                    $buyerId = $order->buyer->name ?? 'Buyer' . $order->buyer_id;

                    // Idempotency checks: skip if already completed or step5 already recorded
                    $existingResponse = $certificate->blockchain_response ? json_decode($certificate->blockchain_response, true) : [];
                    $alreadyCompleted = ($certificate->blockchain_status === 'COMPLETED')
                        || (!empty($certificate->completed_at))
                        || (is_array($existingResponse) && isset($existingResponse['step5_complete']));

                    if ($alreadyCompleted) {
                        $this->info("Skipping auto-complete for certificate {$certificate->id}: already completed or step5 recorded");
                    } else {
                        try {
                            $completeResponse = Http::timeout(30)
                                ->put('http://localhost:3000/api/certificates/complete/' . $blockchainCertId, [
                                    'buyerId' => $buyerId
                                ]);

                            if ($completeResponse->successful()) {
                                // merge existing blockchain_response with step5 result
                                $existingResponse['step5_complete'] = $completeResponse->json();
                                $existingResponse['completed_at'] = now()->toISOString();

                                $certificate->update([
                                    'blockchain_status' => 'COMPLETED',
                                    'status' => 'completed',
                                    'blockchain_response' => json_encode($existingResponse),
                                    'completed_at' => now()
                                ]);

                                $this->info("✅ Auto-completed certificate {$certificate->id}");
                                $syncedCount++;
                            }
                        } catch (\Exception $e) {
                            $this->warn("⚠️ Failed to auto-complete certificate {$certificate->id}: {$e->getMessage()}");
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("❌ Error syncing certificate {$certificate->id}: {$e->getMessage()}");
                $errorCount++;
            }
        }
        
        // Update order statuses
        $this->info('📦 Updating order statuses...');
        
        $orders = Order::whereNotIn('status', ['completed', 'cancelled'])
            ->with('certificates')
            ->get();
        
        foreach ($orders as $order) {
            $totalCertificates = $order->certificates->count();
            if ($totalCertificates === 0) continue;
            
            $readyCertificates = $order->certificates
                ->whereIn('blockchain_status', ['CERTIFICATE_ISSUED', 'COMPLETED'])
                ->count();
            
            if ($readyCertificates === $totalCertificates && $order->status !== 'completed') {
                $order->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
                
                $this->info("✅ Order {$order->id} marked as completed");
            }
        }
        
        $this->info("✅ Sync completed: {$syncedCount} certificates synced, {$errorCount} errors");
        
        Log::info('Blockchain certificate sync completed', [
            'synced' => $syncedCount,
            'errors' => $errorCount,
            'timestamp' => now()
        ]);
        
        return Command::SUCCESS;
    }
}
