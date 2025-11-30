<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Certificate;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AutoSyncBlockchain extends Command
{
    protected $signature = 'blockchain:auto-sync {--fix-incomplete : Fix incomplete blockchain workflows}';
    protected $description = 'Automatically synchronize blockchain status from CouchDB and complete pending workflows';

    public function handle()
    {
        $this->info('🚀 Starting automatic blockchain synchronization...');
        
        // Step 1: Sync all existing certificates with CouchDB
        $this->syncWithCouchDB();
        
        // Step 2: Complete pending blockchain workflows
        if ($this->option('fix-incomplete')) {
            $this->completeIncompleteCertificates();
        }
        
        // Step 3: Update order statuses
        $this->updateOrderStatuses();
        
        $this->info('✅ Automatic blockchain synchronization completed!');
        
        return 0;
    }

    private function syncWithCouchDB()
    {
        $this->info('🔄 Syncing with CouchDB...');
        
        $certificates = Certificate::whereNotNull('blockchain_cert_id')->get();
        $syncedCount = 0;
        
        foreach ($certificates as $certificate) {
            try {
                $this->line("  Checking certificate {$certificate->id} ({$certificate->blockchain_cert_id})");
                
                // Try multiple document ID formats
                $possibleDocIds = [
                    'CERTIFICATE_' . $certificate->blockchain_cert_id,
                    $certificate->blockchain_cert_id
                ];

                $couchData = null;
                $foundDocId = null;

                foreach ($possibleDocIds as $docId) {
                    try {
                        $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/' . $docId;
                        $response = @file_get_contents($couchUrl);
                        
                        if ($response !== false) {
                            $data = json_decode($response, true);
                            if ($data && isset($data['certificateInfo']['status'])) {
                                $couchData = $data;
                                $foundDocId = $docId;
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                if (!$couchData) {
                    $this->warn("    ⚠️ Certificate not found in CouchDB");
                    continue;
                }

                $blockchainStatus = $couchData['certificateInfo']['status'];
                $currentStatus = $certificate->blockchain_status;

                if ($currentStatus !== $blockchainStatus) {
                    DB::transaction(function () use ($certificate, $blockchainStatus, $couchData) {
                        $certificate->update([
                            'blockchain_status' => $blockchainStatus,
                            'blockchain_response' => json_encode($couchData),
                            'updated_at' => now()
                        ]);
                    });
                    
                    $this->info("    ✅ Updated: {$currentStatus} → {$blockchainStatus}");
                    $syncedCount++;
                } else {
                    $this->line("    ✓ Already in sync: {$blockchainStatus}");
                }
                
            } catch (\Exception $e) {
                $this->error("    ❌ Error syncing certificate {$certificate->id}: {$e->getMessage()}");
            }
        }
        
        $this->info("📊 Synced {$syncedCount} certificates from CouchDB");
    }

    private function completeIncompleteCertificates()
    {
        $this->info('🔧 Completing incomplete blockchain workflows...');
        
        // Find certificates that are CERTIFICATE_ISSUED but not COMPLETED
        $issuedCertificates = Certificate::where('blockchain_status', 'CERTIFICATE_ISSUED')
            ->whereNotNull('blockchain_cert_id')
            ->get();

        $this->info("Found {$issuedCertificates->count()} certificates ready for completion");

        foreach ($issuedCertificates as $certificate) {
            try {
                $this->line("  Completing certificate {$certificate->id} ({$certificate->blockchain_cert_id})");
                
                // Get buyer ID from CouchDB
                $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_' . $certificate->blockchain_cert_id;
                $couchResponse = @file_get_contents($couchUrl);
                
                if ($couchResponse === false) {
                    $this->warn("    ⚠️ Cannot fetch certificate from CouchDB");
                    continue;
                }

                $couchData = json_decode($couchResponse, true);
                
                if (!$couchData || $couchData['certificateInfo']['status'] !== 'CERTIFICATE_ISSUED') {
                    $this->warn("    ⚠️ Certificate not ready for completion in CouchDB");
                    continue;
                }

                // Get correct buyer ID from blockchain data or use profile name
                $buyerId = $this->getBuyerIdFromCouchData($couchData, $certificate) ?? 
                          $this->getBuyerNameFromCertificateProfile($certificate) ?? 
                          'DefaultBuyer';

                $this->line("    Using buyer ID: {$buyerId}");

                // Execute Step 5: Complete Certificate
                $response = Http::timeout(30)
                    ->put('http://localhost:3000/api/certificates/complete/' . $certificate->blockchain_cert_id, [
                        'generatorId' => $buyerId
                    ]);

                if ($response->successful() && $response->json('success')) {
                    DB::transaction(function () use ($certificate, $response, $couchData) {
                        $certificate->update([
                            'blockchain_status' => 'COMPLETED',
                            'blockchain_response' => json_encode([
                                'step5_complete' => $response->json(),
                                'completed_at' => now()->toISOString(),
                                'couch_data' => $couchData
                            ])
                        ]);
                    });

                    $this->info("    ✅ Certificate completed successfully");
                } else {
                    // Check if it was an endorsement mismatch but actually succeeded
                    if ($response->status() === 500) {
                        sleep(2); // Wait for blockchain sync
                        
                        $recheckResponse = @file_get_contents($couchUrl);
                        if ($recheckResponse) {
                            $recheckData = json_decode($recheckResponse, true);
                            
                            if (isset($recheckData['certificateInfo']['status']) && 
                                $recheckData['certificateInfo']['status'] === 'COMPLETED') {
                                
                                DB::transaction(function () use ($certificate, $recheckData) {
                                    $certificate->update([
                                        'blockchain_status' => 'COMPLETED',
                                        'blockchain_response' => json_encode([
                                            'step5_complete' => [
                                                'success' => true,
                                                'message' => 'Certificate completed (verified after endorsement mismatch)',
                                                'completed_at' => $recheckData['lifecycle']['completedAt'] ?? now()->toISOString()
                                            ],
                                            'couch_data' => $recheckData
                                        ])
                                    ]);
                                });

                                $this->info("    ✅ Certificate completed (endorsement mismatch handled)");
                                continue;
                            }
                        }
                    }
                    
                    $this->warn("    ⚠️ Certificate completion failed: " . $response->body());
                }
                
            } catch (\Exception $e) {
                $this->error("    ❌ Error completing certificate {$certificate->id}: {$e->getMessage()}");
            }
        }
    }

    private function updateOrderStatuses()
    {
        $this->info('📋 Updating order statuses...');
        
        $orders = Order::whereHas('certificates', function ($query) {
            $query->whereNotNull('blockchain_status');
        })->get();

        $updatedCount = 0;

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
                return in_array($cert->blockchain_status, ['CERTIFICATE_ISSUED', 'COMPLETED']);
            });

            // ✅ NEW: Check if all certificates are PURCHASED (verified by issuer)
            $allPurchased = $certificates->every(function($cert) {
                return in_array($cert->blockchain_status, ['PURCHASED', 'COMPLETED']);
            });

            $newStatus = null;
            
            if ($allCompleted) {
                $newStatus = 'completed';
            } elseif ($allPurchased && $order->status !== 'completed') {
                // ✅ NEW: If issuer verified payment but not yet completed, trigger completion
                $this->info("  🔄 Triggering completion for purchased certificates in order {$order->order_uid}");
                $this->triggerCompletionForPurchasedCertificates($order);
                $newStatus = 'completed';
            } elseif ($anyIssued && $order->status !== 'completed') {
                $newStatus = 'awaiting_confirmation';
            }

            if ($newStatus && $order->status !== $newStatus) {
                $oldStatus = $order->status;
                
                DB::transaction(function () use ($order, $newStatus) {
                    $order->update(['status' => $newStatus]);
                });
                
                $this->info("  ✅ Order {$order->order_uid}: {$oldStatus} → {$newStatus}");
                $updatedCount++;
            }
        }
        
        $this->info("📊 Updated {$updatedCount} order statuses");
    }

    /**
     * ✅ NEW: Trigger completion for purchased certificates
     */
    private function triggerCompletionForPurchasedCertificates($order)
    {
        $purchasedCertificates = $order->certificates()
            ->where('blockchain_status', 'PURCHASED')
            ->get();

        foreach ($purchasedCertificates as $certificate) {
            try {
                $this->info("    🚀 Completing certificate {$certificate->id}");

                // Get buyer ID from certificate data
                $buyerId = $this->getBuyerIdFromCouchData(
                    json_decode($certificate->blockchain_response, true), 
                    $certificate
                ) ?? $this->getBuyerNameFromCertificateProfile($certificate) ?? 'DefaultBuyer';

                // Call Step 5 completion endpoint
                $response = Http::timeout(30)->put('http://localhost:3000/api/certificates/complete/' . $certificate->blockchain_cert_id, [
                    'generatorId' => $buyerId
                ]);

                if ($response->successful() && $response->json('success')) {
                    $certificate->update([
                        'blockchain_status' => 'COMPLETED',
                        'blockchain_response' => json_encode([
                            'step5_complete' => [
                                'success' => true,
                                'message' => 'Certificate auto-completed after purchase verification',
                                'completed_at' => now()->toISOString()
                            ]
                        ])
                    ]);
                    
                    $this->info("    ✅ Certificate {$certificate->id} completed successfully");
                } else {
                    $this->warn("    ⚠️ Failed to complete certificate {$certificate->id}: " . $response->body());
                }

            } catch (\Exception $e) {
                $this->error("    ❌ Error completing certificate {$certificate->id}: {$e->getMessage()}");
            }
        }
    }

    /**
     * ✅ FIXED: Get buyer ID from CouchDB data - updated to use parties.buyer.buyerId
     */
    private function getBuyerIdFromCouchData($couchData, $certificate)
    {
        // ✅ NEW: Try to get buyer ID from parties.buyer.buyerId (main source)
        if (isset($couchData['parties']['buyer']['buyerId'])) {
            return $couchData['parties']['buyer']['buyerId'];
        }
        
        // Fallback: Try certificateInfo.buyerId
        if (isset($couchData['certificateInfo']['buyerId'])) {
            return $couchData['certificateInfo']['buyerId'];
        }
        
        // Fallback: Try certificateInfo.generatorId
        if (isset($couchData['certificateInfo']['generatorId'])) {
            return $couchData['certificateInfo']['generatorId'];
        }
        
        // Fallback: Try lifecycle.purchasedBy
        if (isset($couchData['lifecycle']['purchasedBy'])) {
            return $couchData['lifecycle']['purchasedBy'];
        }
        
        // ✅ NEW: Try to get from parties.buyer.name as fallback
        if (isset($couchData['parties']['buyer']['name'])) {
            return $couchData['parties']['buyer']['name'];
        }
        
        return null;
    }

    /**
     * ✅ NEW: Get buyer name from certificate's user profile
     */
    private function getBuyerNameFromCertificateProfile($certificate)
    {
        // Get buyer name from order/certificate profile
        if ($certificate->order && $certificate->order->user) {
            return $certificate->order->user->profile->company_name ?? $certificate->order->user->name;
        }
        
        return null;
    }
}