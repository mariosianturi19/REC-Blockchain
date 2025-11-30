<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EnergyController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\BlockchainController;
use App\Http\Controllers\Buyer\CheckoutController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('api')->group(function () {
    // Health Check
    Route::get('/health', [EnergyController::class, 'health']);
    
    // ✅ NEW: Phase 2 - Pre-purchase validation
    Route::middleware(['auth'])->prefix('marketplace')->group(function () {
        Route::post('/validate-purchase', [\App\Http\Controllers\Buyer\MarketplaceController::class, 'validatePurchase']);
    });
    
    // Energy Data Management (Steps 1-2)
    Route::prefix('energy')->group(function () {
        Route::post('/submit', [EnergyController::class, 'submit']);
        Route::put('/verify/{energyDataId}', [EnergyController::class, 'verify']);
        Route::get('/', [EnergyController::class, 'index']);
        Route::get('/{energyDataId}', [EnergyController::class, 'show']);
    });
    
    // Certificate Management (Steps 3-6)
    Route::prefix('certificates')->group(function () {
        Route::post('/request', [CertificateController::class, 'request']);
        Route::put('/issue/{certId}', [CertificateController::class, 'issue']);
        Route::post('/purchase', [CertificateController::class, 'purchase']);
        Route::put('/confirm/{certId}', [CertificateController::class, 'confirm']);
        Route::get('/', [CertificateController::class, 'index']);
        Route::get('/{certId}', [CertificateController::class, 'show']);

        // Certificate ownership verification with transaction-based proof
        Route::get('/verify-ownership/{certId}', function ($certId) {
            try {
                $buyerId = request()->query('buyerId');
                
                if (!$buyerId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'buyerId parameter is required'
                    ], 400);
                }

                // Forward request to Node.js API server
                $response = \Illuminate\Support\Facades\Http::timeout(30)
                    ->get("http://localhost:3000/api/certificates/verify-ownership/{$certId}", [
                        'buyerId' => $buyerId
                    ]);

                if ($response->successful()) {
                    return response()->json($response->json());
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to verify ownership',
                        'error' => $response->body()
                    ], $response->status());
                }
                
            } catch (\Exception $e) {
                \Log::error('API: Certificate ownership verification failed', [
                    'certificate_id' => $certId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Internal server error during ownership verification',
                    'error' => $e->getMessage()
                ], 500);
            }
        });
        
        // Energy data integrity verification
        Route::get('/energy-integrity/{energyId}', function ($energyId) {
            try {
                // Forward request to Node.js API server
                $response = \Illuminate\Support\Facades\Http::timeout(30)
                    ->get("http://localhost:3000/api/certificates/energy-integrity/{$energyId}");

                if ($response->successful()) {
                    return response()->json($response->json());
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to check energy integrity',
                        'error' => $response->body()
                    ], $response->status());
                }
                
            } catch (\Exception $e) {
                \Log::error('API: Energy integrity check failed', [
                    'energy_id' => $energyId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Internal server error during integrity check',
                    'error' => $e->getMessage()
                ], 500);
            }
        });
        
        // Certificate integrity verification
        Route::get('/certificate-integrity/{certId}', function ($certId) {
            try {
                // Forward request to Node.js API server
                $response = \Illuminate\Support\Facades\Http::timeout(30)
                    ->get("http://localhost:3000/api/certificates/certificate-integrity/{$certId}");

                if ($response->successful()) {
                    return response()->json($response->json());
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to check certificate integrity',
                        'error' => $response->body()
                    ], $response->status());
                }
                
            } catch (\Exception $e) {
                \Log::error('API: Certificate integrity check failed', [
                    'certificate_id' => $certId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Internal server error during integrity check',
                    'error' => $e->getMessage()
                ], 500);
            }
        });
        
        // Check energy data duplication
        Route::post('/check-energy-duplication', function () {
            try {
                $request = request()->all();
                
                // Forward request to Node.js API server
                $response = \Illuminate\Support\Facades\Http::timeout(30)
                    ->post('http://localhost:3000/api/certificates/check-energy-duplication', $request);

                if ($response->successful()) {
                    return response()->json($response->json());
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to check energy duplication',
                        'error' => $response->body()
                    ], $response->status());
                }
                
            } catch (\Exception $e) {
                \Log::error('API: Energy duplication check failed', [
                    'request_data' => request()->all(),
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Internal server error during duplication check',
                    'error' => $e->getMessage()
                ], 500);
            }
        });

        // Fetch raw CouchDB certificate document (useful for live status)
        Route::get('/couch/{certId}', function ($certId) {
            try {
                $blockchainCertId = $certId;
                $couchUrl = "http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_{$blockchainCertId}";
                $couchResponse = @file_get_contents($couchUrl);

                if ($couchResponse === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Certificate not found in CouchDB'
                    ], 404);
                }

                $couchData = json_decode($couchResponse, true);

                return response()->json([
                    'success' => true,
                    'data' => $couchData
                ]);

            } catch (\Exception $e) {
                \Log::error('API: Fetch CouchDB certificate failed', [
                    'cert_id' => $certId,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Internal server error',
                    'error' => $e->getMessage()
                ], 500);
            }
        });
    });

    // Complete Workflow Testing
    Route::post('/workflow/complete', [BlockchainController::class, 'executeCompleteWorkflow']);

    // Order management API endpoints with security
    Route::middleware(['auth'])->prefix('orders')->group(function () {
        // Request certificate with enhanced security validation
        Route::post('/{order}/request-certificates', [CheckoutController::class, 'requestCertificateStep3']);
        
        // ✅ NEW: Force sync certificate from CouchDB to Laravel database
        Route::post('/{order}/force-sync-certificates', function ($orderId) {
            try {
                $order = \App\Models\Order::with('certificates')->findOrFail($orderId);
                
                \Log::info('🔄 API: Force sync certificates requested', [
                    'order_id' => $order->id,
                    'order_uid' => $order->order_uid,
                    'certificates_count' => $order->certificates->count()
                ]);
                
                $syncResults = [];
                $successCount = 0;
                $failCount = 0;
                
                foreach ($order->certificates as $certificate) {
                    $blockchainCertId = $certificate->blockchain_cert_id ?: $certificate->certificate_uid;
                    
                    if (!$blockchainCertId) {
                        $syncResults[] = [
                            'certificate_id' => $certificate->id,
                            'status' => 'skipped',
                            'reason' => 'No blockchain_cert_id or certificate_uid'
                        ];
                        $failCount++;
                        continue;
                    }
                    
                    try {
                        // Fetch from CouchDB
                        $couchUrl = "http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_{$blockchainCertId}";
                        $couchResponse = @file_get_contents($couchUrl);
                        
                        if ($couchResponse === false) {
                            $syncResults[] = [
                                'certificate_id' => $certificate->id,
                                'blockchain_cert_id' => $blockchainCertId,
                                'status' => 'failed',
                                'reason' => 'Certificate not found in CouchDB'
                            ];
                            $failCount++;
                            continue;
                        }
                        
                        $couchData = json_decode($couchResponse, true);
                        $couchStatus = $couchData['certificateInfo']['status'] ?? null;
                        
                        if (!$couchStatus) {
                            $syncResults[] = [
                                'certificate_id' => $certificate->id,
                                'blockchain_cert_id' => $blockchainCertId,
                                'status' => 'failed',
                                'reason' => 'No status in CouchDB data'
                            ];
                            $failCount++;
                            continue;
                        }
                        
                        // Update certificate in Laravel database
                        $oldStatus = $certificate->blockchain_status;
                        $certificate->update([
                            'blockchain_cert_id' => $blockchainCertId,
                            'blockchain_status' => $couchStatus,
                            'blockchain_response' => json_encode($couchData)
                        ]);
                        
                        $syncResults[] = [
                            'certificate_id' => $certificate->id,
                            'blockchain_cert_id' => $blockchainCertId,
                            'status' => 'success',
                            'old_status' => $oldStatus,
                            'new_status' => $couchStatus,
                            'couch_data' => $couchData
                        ];
                        $successCount++;
                        
                        \Log::info('✅ Certificate synced from CouchDB', [
                            'certificate_id' => $certificate->id,
                            'blockchain_cert_id' => $blockchainCertId,
                            'old_status' => $oldStatus,
                            'new_status' => $couchStatus
                        ]);
                        
                    } catch (\Exception $e) {
                        $syncResults[] = [
                            'certificate_id' => $certificate->id,
                            'blockchain_cert_id' => $blockchainCertId,
                            'status' => 'error',
                            'error' => $e->getMessage()
                        ];
                        $failCount++;
                        
                        \Log::error('❌ Failed to sync certificate', [
                            'certificate_id' => $certificate->id,
                            'blockchain_cert_id' => $blockchainCertId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                // Update order status based on certificates
                $allCertsPaid = $order->certificates->every(function($cert) {
                    return $cert->blockchain_status === 'CERTIFICATE_PAID';
                });
                
                if ($allCertsPaid && $order->certificates->count() > 0) {
                    $order->update(['status' => 'CERTIFICATE_PAID']);
                    \Log::info('✅ Order status updated to CERTIFICATE_PAID', [
                        'order_id' => $order->id
                    ]);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => "Force sync completed: {$successCount} success, {$failCount} failed",
                    'data' => [
                        'order_id' => $order->id,
                        'order_uid' => $order->order_uid,
                        'order_status' => $order->fresh()->status,
                        'total_certificates' => $order->certificates->count(),
                        'success_count' => $successCount,
                        'fail_count' => $failCount,
                        'sync_results' => $syncResults
                    ]
                ]);
                
            } catch (\Exception $e) {
                \Log::error('❌ API: Force sync failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to force sync certificates',
                    'error' => $e->getMessage()
                ], 500);
            }
        });
        
        // Get order security status
        Route::get('/{order}/security-status', function ($orderId) {
            try {
                $order = \App\Models\Order::with('certificates')->findOrFail($orderId);
                
                if ($order->buyer_id !== auth()->id()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access to order'
                    ], 403);
                }
                
                $controller = new CheckoutController(
                    app(\App\Services\BlockchainService::class),
                    app(\App\Services\OrderStatusService::class)
                );
                
                $securityStatus = [];
                foreach ($order->certificates as $certificate) {
                    $securityStatus[] = [
                        'certificate_id' => $certificate->id,
                        'blockchain_cert_id' => $certificate->blockchain_cert_id,
                        'security_level' => $controller->getSecurityLevel($certificate),
                        'integrity_verified' => $controller->isIntegrityVerified($certificate),
                        'ownership_authenticated' => $controller->isOwnershipAuthenticated($certificate),
                        'uniqueness_confirmed' => $controller->isUniquenessConfirmed($certificate),
                        'certificate_hash' => $controller->getTruncatedHash($certificate),
                        'serial_number' => $controller->getSerialNumber($certificate)
                    ];
                }
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_id' => $order->id,
                        'order_status' => $order->status,
                        'certificates_security' => $securityStatus
                    ]
                ]);
                
            } catch (\Exception $e) {
                \Log::error('API: Order security status failed', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get order security status',
                    'error' => $e->getMessage()
                ], 500);
            }
        });
    });
});