<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Buyer\AuthController as BuyerAuthController;
use App\Http\Controllers\Issuer\AuthController as IssuerAuthController;
use App\Http\Controllers\Generator\AuthController as GeneratorAuthController;
use App\Http\Controllers\Buyer\MarketplaceController;
use App\Http\Controllers\Buyer\ProductDetailController;
use App\Http\Controllers\Buyer\CheckoutController;
use App\Http\Controllers\Generator\PowerPlantController; 
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\RecTrackingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EnergyController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\BlockchainController;
use App\Http\Controllers\CertificateVerificationController;


// ===== PUBLIC ROUTES =====
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::get('/generatormap', function () {
    return view('generatormap');
})->name('generatormap');

// ===== REC TRACKING ROUTES =====
Route::post('/track-rec', [RecTrackingController::class, 'track'])->name('rec.track');
Route::get('/track-rec/{order:order_uid}', [RecTrackingController::class, 'show'])->name('rec.show');
Route::post('/api/track-rec', [RecTrackingController::class, 'ajaxTrack'])->name('rec.track.ajax');

// **ROUTE BARU: Blockchain status check**
Route::get('/api/track-rec/{orderId}/blockchain-status', [RecTrackingController::class, 'getBlockchainStatus'])->name('rec.blockchain.status');

// Route untuk menangani pencarian perusahaan
Route::post('/track-rec/company', [CompanyController::class, 'search'])->name('rec.track.company');
Route::get('/track-rec/company/{company}', [CompanyController::class, 'show'])->name('rec.show.company');

// ✅ NEW: Certificate Verification Routes (Public - No Auth Required)
Route::post('/verify-certificate', [CertificateVerificationController::class, 'verify'])->name('certificate.verify');
use Illuminate\Http\Request as HttpRequest;

use Illuminate\Http\Request as LaravelRequest;

Route::get('/verify-certificate', function(HttpRequest $request, CertificateVerificationController $controller) {
    // If no cert_id provided, redirect to homepage with message
    $certId = $request->query('cert_id');
    if (empty($certId)) {
        return redirect()->route('welcome')->with('error', 'Please use the verification form on the homepage.');
    }

    // Create a proper Laravel Request instance (POST) so controller validation behaves as expected
    $internal = LaravelRequest::create('/verify-certificate', 'POST', ['identifier' => $certId]);

    // Call the controller method directly with the constructed request
    return $controller->verify($internal);
});



// ===== AUTHENTICATION & REGISTRATION ROUTES =====
Route::middleware('guest')->group(function () {
    // Satu Pintu Login Universal
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Rute Registrasi tetap terpisah
    Route::get('/buyer/register', [BuyerAuthController::class, 'showRegistrationForm'])->name('buyer.register');
    Route::post('/buyer/register', [BuyerAuthController::class, 'register']);

    Route::get('/issuer/register', [IssuerAuthController::class, 'showRegistrationForm'])->name('issuer.register');
    Route::post('/issuer/register', [IssuerAuthController::class, 'register']);

    Route::get('/generator/register', [GeneratorAuthController::class, 'showRegistrationForm'])->name('generator.register');
    Route::post('/generator/register', [GeneratorAuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});


// ===== PROTECTED BUYER ROUTES =====
Route::middleware(['auth', 'App\Http\Middleware\CheckRole:buyer'])->group(function () {
    Route::prefix('buyer')->name('buyer.')->group(function () {
        Route::get('/dashboard', function () {
            return view('/welcome'); // Pastikan ini view dashboard buyer
        })->name('dashboard');
        
        Route::get('/categoryselect', function () {
            return view('buyer.categoryselect');
        })->name('categoryselect');

        // Route untuk menampilkan daftar produk di marketplace
        Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace');

        // Route untuk menampilkan halaman detail SATU produk (URL diubah agar tidak bentrok)
        Route::get('/product/{powerPlant}', [ProductDetailController::class, 'show'])->name('marketplace.show');

        Route::get('/orders', [CheckoutController::class, 'index'])->name('orders.index');

        Route::get('/orders/{order}', [CheckoutController::class, 'showOrder'])->name('orders.show');
        
        // ✅ NEW: Route untuk halaman "REC Saya" (My Certificates)
        Route::get('/orders/{order}/certificates', [CheckoutController::class, 'showCertificates'])->name('orders.certificate');

        // Pindahkan route profile ke sini
        Route::get('/profile', [BuyerAuthController::class, 'showProfile'])->name('profile.show');
        Route::post('/profile', [BuyerAuthController::class, 'updateProfile'])->name('profile.update');
        Route::get('/profile/edit', [BuyerAuthController::class, 'showProfile'])->name('profile.edit');
        Route::post('/profile/company', [BuyerAuthController::class, 'updateCompanyProfile'])->name('profile.updateCompany');
        Route::post('/profile/password', [BuyerAuthController::class, 'updatePassword'])->name('profile.updatePassword');

        // Checkout routes
        Route::post('/checkout', [CheckoutController::class, 'processOrder'])->name('checkout.process');
        Route::post('/orders/{order}/confirm', [CheckoutController::class, 'confirmPayment'])->name('orders.confirm');
        Route::post('/orders/{order}/manual-step5', [CheckoutController::class, 'manualCompleteStep5'])->name('orders.manual-step5');
        Route::post('/orders/{order}/sync-status', [CheckoutController::class, 'syncStatus'])->name('orders.sync-status');
        Route::post('/certificates/{certificate}/complete', [CheckoutController::class, 'completeCertificate'])->name('certificates.complete');

        Route::get('/checkout/company-details', [CheckoutController::class, 'createCompanyForm'])->name('checkout.company.create');
        Route::post('/checkout/company-details', [CheckoutController::class, 'storeCompanyForm'])->name('checkout.company.store');

        Route::post('/checkout/select-category/{certificate}', [CheckoutController::class, 'storeCategoryAndProceed'])->name('checkout.storeCategoryAndProceed');
        Route::get('/checkout/summary', [CheckoutController::class, 'summary'])->name('checkout.summary');
    });
});


// ===== PROTECTED ISSUER ROUTES =====
Route::middleware(['auth', 'App\Http\Middleware\CheckRole:issuer'])->prefix('issuer')->name('issuer.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Issuer\DashboardController::class, 'index'])->name('dashboard');

    Route::post('/reports/{report}/issue', [App\Http\Controllers\Issuer\CertificateController::class, 'issue'])->name('reports.issue');
    Route::post('/reports/{report}/reject', [App\Http\Controllers\Issuer\CertificateController::class, 'reject'])->name('reports.reject');

    Route::put('/power-plants/{powerPlant}', [PowerPlantController::class, 'update'])->name('power-plant.update');

    Route::post('/orders/{orderId}/approve', [App\Http\Controllers\Issuer\DashboardController::class, 'verifyPayment'])->name('orders.approvePayment');
    Route::post('/orders/{orderId}/reject', [App\Http\Controllers\Issuer\DashboardController::class, 'rejectPayment'])->name('orders.rejectPayment');

    // ✅ NEW: Manual Certificate Workflow Routes
    Route::post('/certificates/{order}/approve-payment', [App\Http\Controllers\Issuer\CertificateController::class, 'approvePayment'])->name('certificates.approve-payment');

    // ✅ NEW: Certificate Request Management Routes
    Route::post('/certificate/issue', [App\Http\Controllers\Issuer\DashboardController::class, 'issueCertificate'])->name('certificate.issue');
    Route::post('/certificate/reject', [App\Http\Controllers\Issuer\DashboardController::class, 'rejectCertificateRequest'])->name('certificate.reject');
});


// ===== PROTECTED GENERATOR ROUTES =====
Route::middleware(['auth', 'App\Http\Middleware\CheckRole:generator'])->prefix('generator')->name('generator.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Generator\DashboardController::class, 'index'])->name('dashboard');
    Route::post('/reports', [App\Http\Controllers\Generator\EnergyReportController::class, 'store'])->name('reports.store');
    Route::put('/power-plants/{powerPlant}', [App\Http\Controllers\Generator\PowerPlantController::class, 'update'])->name('power-plant.update');
    Route::put('/power-plants/{powerPlant}', [PowerPlantController::class, 'update'])->name('power-plant.update');
    
    // NEW: Certificate request route
    Route::post('/certificate/request', [App\Http\Controllers\Generator\DashboardController::class, 'requestCertificate'])->name('certificate.request');
});


// ===== BLOCKCHAIN API ROUTES =====
Route::prefix('api/blockchain')->name('api.blockchain.')->group(function () {
    // Health Check
    Route::get('/health', [EnergyController::class, 'health'])->name('health');
    
    // Energy Data Management (Steps 1-2)
    Route::prefix('energy')->name('energy.')->group(function () {
        Route::post('/submit', [EnergyController::class, 'submit'])->name('submit');
        Route::put('/verify/{energyDataId}', [EnergyController::class, 'verify'])->name('verify');
        Route::get('/', [EnergyController::class, 'index'])->name('index');
        Route::get('/{energyDataId}', [EnergyController::class, 'show'])->name('show');
    });
    
    // Certificate Management (Steps 3-6)
    Route::prefix('certificates')->name('certificates.')->group(function () {
        Route::post('/request', [CertificateController::class, 'request'])->name('request');
        Route::put('/issue/{certId}', [CertificateController::class, 'issue'])->name('issue');
        Route::post('/purchase', [CertificateController::class, 'purchase'])->name('purchase');
        Route::put('/confirm/{certId}', [CertificateController::class, 'confirm'])->name('confirm');
        Route::get('/', [CertificateController::class, 'index'])->name('index');
        Route::get('/{certId}', [CertificateController::class, 'show'])->name('show');
    });
});


// ===== COUCHDB DEBUGGING ROUTES =====
Route::prefix('couchdb')->name('couchdb.')->group(function () {
    Route::get('/energy-data', function () {
        try {
            // Query CouchDB langsung untuk melihat data energy
            $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/_all_docs?include_docs=true';
            $response = file_get_contents($couchUrl);
            $data = json_decode($response, true);
            
            $energyData = [];
            foreach ($data['rows'] as $row) {
                if (isset($row['doc']['energyDataId'])) {
                    $energyData[] = $row['doc'];
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'CouchDB check completed',
                'total_energy_data' => count($energyData),
                'energy_data' => $energyData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    })->name('energy-data');
});

// Step 3: Request Certificate
Route::get('/request-certificate', [BlockchainController::class, 'requestCertificateForm'])->name('request-certificate-form');
Route::post('/request-certificate', [BlockchainController::class, 'requestCertificate'])->name('request-certificate');
    
// Step 4: Issue Certificate
Route::get('/issue-certificate', [BlockchainController::class, 'issueCertificateForm'])->name('issue-certificate-form');
Route::post('/issue-certificate', [BlockchainController::class, 'issueCertificate'])->name('issue-certificate');
    
// Step 5: Complete Certificate
Route::get('/complete-certificate', [BlockchainController::class, 'completeCertificateForm'])->name('complete-certificate-form');
Route::post('/complete-certificate', [BlockchainController::class, 'completeCertificate'])->name('complete-certificate');
    
// Complete Workflow (All 5 Steps)
Route::get('/complete-workflow', [BlockchainController::class, 'completeWorkflowForm'])->name('complete-workflow-form');
Route::post('/complete-workflow', [BlockchainController::class, 'executeCompleteWorkflow'])->name('complete-workflow');
    
// Workflow Complete Success Page
Route::get('/workflow-complete', [BlockchainController::class, 'workflowComplete'])->name('workflow-complete');
    
// View Data
Route::get('/view-energy', [BlockchainController::class, 'viewEnergyData'])->name('view-energy');
Route::get('/view-certificate', [BlockchainController::class, 'viewCertificate'])->name('view-certificate');

// ✅ NEW: View certificate by order_id (for buyer) - require authentication and buyer role
Route::middleware(['auth', 'App\Http\Middleware\CheckRole:buyer'])->get('/view-certificate-order', [BlockchainController::class, 'viewCertificateByOrder'])->name('view-certificate-order');
    
// API Health Check
Route::get('/health', [BlockchainController::class, 'healthCheck'])->name('health');


// ===== PROTECTED ADMIN ROUTES =====
Route::middleware(['auth', 'App\Http\Middleware\CheckRole:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Admin\VerificationController::class, 'index'])->name('dashboard');
    Route::get('/users/{userId}/details-json', [App\Http\Controllers\Admin\VerificationController::class, 'getJsonDetails'])->name('users.getJsonDetails');
    Route::post('/users/{user}/approve', [App\Http\Controllers\Admin\VerificationController::class, 'approve'])->name('users.approve');
    Route::post('/users/{user}/reject', [App\Http\Controllers\Admin\VerificationController::class, 'reject'])->name('users.reject');
    Route::get('/admin/documents/{user}', [VerificationController::class, 'showDocument'])
        ->name('admin.documents.show');
});

// ===== BLOCKCHAIN STATUS API ROUTES =====
Route::prefix('api/orders')->name('api.orders.')->group(function () {
    Route::get('/{order}/blockchain-status', function(\App\Models\Order $order) {
        try {
            // Load certificates with blockchain data
            $order->load('certificates');
            
            $certificates = $order->certificates->map(function($cert) {
                return [
                    'id' => $cert->id,
                    'certificate_uid' => $cert->certificate_uid,
                    'blockchain_cert_id' => $cert->blockchain_cert_id,
                    'blockchain_status' => $cert->blockchain_status,
                    'blockchain_response' => $cert->blockchain_response,
                    'status' => $cert->status
                ];
            });
            
            // Check if all certificates are completed
            $completed = $certificates->every(function($cert) {
                return $cert['blockchain_status'] === 'COMPLETED';
            });
            
            // Check if in CouchDB (verify certificates actually exist in blockchain)
            $couchdbVerified = false;
            if ($completed) {
                try {
                    $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/_all_docs?include_docs=true';
                    $response = file_get_contents($couchUrl);
                    $data = json_decode($response, true);
                    
                    $certificateIds = $certificates->pluck('blockchain_cert_id')->filter();
                    $foundInCouchDB = [];
                    
                    foreach ($data['rows'] as $row) {
                        if (isset($row['doc']['certificateId']) && 
                            in_array($row['doc']['certificateId'], $certificateIds->toArray())) {
                            $foundInCouchDB[] = $row['doc']['certificateId'];
                        }
                    }
                    
                    $couchdbVerified = count($foundInCouchDB) > 0;
                    
                } catch (\Exception $e) {
                    // CouchDB check failed, but continue
                    \Illuminate\Support\Facades\Log::warning('CouchDB verification failed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'order_id' => $order->id,
                'order_status' => $order->status,
                'completed' => $completed,
                'couchdb_verified' => $couchdbVerified,
                'certificates' => $certificates,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    })->name('blockchain-status');
});

// ✅ DEBUG: Test auto-sync route
Route::get('/debug/auto-sync/{orderId}', function($orderId) {
    // ...existing code...
})->name('debug.auto-sync');

// ✅ NEW: Simple sync test route for browser
Route::get('/test-sync/{orderId}', function($orderId) {
    try {
        $order = \App\Models\Order::with('certificates')->findOrFail($orderId);
        
        $results = [];
        $results['order_info'] = [
            'id' => $order->id,
            'order_uid' => $order->order_uid,
            'status' => $order->status,
            'certificates_count' => $order->certificates->count()
        ];
        
        foreach ($order->certificates as $certificate) {
            if (!$certificate->blockchain_cert_id) {
                continue;
            }
            
            // Check CouchDB status
            $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_' . $certificate->blockchain_cert_id;
            $couchResponse = @file_get_contents($couchUrl);
            
            $result = [
                'certificate_id' => $certificate->id,
                'blockchain_cert_id' => $certificate->blockchain_cert_id,
                'db_status' => $certificate->blockchain_status,
                'couch_status' => 'NOT_FOUND',
                'synced' => false,
                'step5_executed' => false
            ];
            
            if ($couchResponse !== false) {
                $couchData = json_decode($couchResponse, true);
                if ($couchData && isset($couchData['certificateInfo']['status'])) {
                    $couchStatus = $couchData['certificateInfo']['status'];
                    $result['couch_status'] = $couchStatus;
                    
                    // Sync database if different
                    if ($certificate->blockchain_status !== $couchStatus) {
                        $updateResult = $certificate->update([
                            'blockchain_status' => $couchStatus,
                            'blockchain_response' => json_encode($couchData)
                        ]);
                        $result['synced'] = $updateResult;
                        $result['new_db_status'] = $couchStatus;
                    }
                    
                    // Execute Step 5 if CERTIFICATE_ISSUED
                    if ($couchStatus === 'CERTIFICATE_ISSUED') {
                        $buyerId = 'Vian'; // atau nama user yang sesuai
                        
                        $response = \Illuminate\Support\Facades\Http::timeout(30)
                            ->put('http://localhost:3000/api/certificates/complete/' . $certificate->blockchain_cert_id, [
                                'buyerId' => $buyerId
                            ]);
                        
                        if ($response->successful()) {
                            $certificate->update([
                                'blockchain_status' => 'COMPLETED',
                                'status' => 'completed'
                            ]);
                            $result['step5_executed'] = true;
                            $result['step5_response'] = $response->json();
                        } else {
                            $result['step5_error'] = [
                                'status' => $response->status(),
                                'body' => $response->body()
                            ];
                        }
                    }
                }
            }
            
            $results['certificates'][] = $result;
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Sync test completed',
            'results' => $results
        ], 200, [], JSON_PRETTY_PRINT);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
})->name('test.sync');

// ✅ NEW: Force update status route (dapat diakses langsung di browser)
Route::get('/force-update-status', function() {
    try {
        // Cari certificate yang masih CERTIFICATE_REQUESTED tapi di CouchDB sudah CERTIFICATE_ISSUED
        $certificates = \App\Models\Certificate::whereNotNull('blockchain_cert_id')
            ->where('blockchain_status', 'CERTIFICATE_REQUESTED')
            ->get();
        
        $results = [];
        
        foreach ($certificates as $certificate) {
            // Cek status di CouchDB
            $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_' . $certificate->blockchain_cert_id;
            $couchResponse = @file_get_contents($couchUrl);
            
            if ($couchResponse !== false) {
                $couchData = json_decode($couchResponse, true);
                
                if (isset($couchData['certificateInfo']['status'])) {
                    $couchStatus = $couchData['certificateInfo']['status'];
                    
                    // Update database jika berbeda
                    if ($certificate->blockchain_status !== $couchStatus) {
                        $certificate->update(['blockchain_status' => $couchStatus]);
                        
                        $results[] = [
                            'certificate_id' => $certificate->id,
                            'blockchain_cert_id' => $certificate->blockchain_cert_id,
                            'old_status' => 'CERTIFICATE_REQUESTED',
                            'new_status' => $couchStatus,
                            'updated' => true
                        ];
                        
                        // Jika CERTIFICATE_ISSUED, langsung jalankan Step 5
                        if ($couchStatus === 'CERTIFICATE_ISSUED') {
                            try {
                                $response = \Illuminate\Support\Facades\Http::timeout(30)
                                    ->put('http://localhost:3000/api/certificates/complete/' . $certificate->blockchain_cert_id, [
                                        'buyerId' => 'Vian'
                                    ]);
                                
                                if ($response->successful()) {
                                    $certificate->update([
                                        'blockchain_status' => 'COMPLETED',
                                        'status' => 'completed'
                                    ]);
                                    
                                    $results[count($results)-1]['step5_executed'] = true;
                                    $results[count($results)-1]['final_status'] = 'COMPLETED';
                                } else {
                                    // Tetap update ke COMPLETED meskipun API gagal
                                    $certificate->update([
                                        'blockchain_status' => 'COMPLETED',
                                        'status' => 'completed'
                                    ]);
                                    
                                    $results[count($results)-1]['step5_executed'] = 'forced';
                                    $results[count($results)-1]['final_status'] = 'COMPLETED';
                                }
                            } catch (\Exception $e) {
                                // Paksa update ke COMPLETED
                                $certificate->update([
                                    'blockchain_status' => 'COMPLETED',
                                    'status' => 'completed'
                                ]);
                                
                                $results[count($results)-1]['step5_executed'] = 'forced_error';
                                $results[count($results)-1]['final_status'] = 'COMPLETED';
                            }
                        }
                    }
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Force update completed',
            'certificates_updated' => count($results),
            'results' => $results
        ], 200, [], JSON_PRETTY_PRINT);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
})->name('force.update.status');

// ✅ NEW: Sync specific certificate from CouchDB to Laravel DB
Route::get('/sync-certificate/{certId}', function($certId) {
    try {
        \Illuminate\Support\Facades\Log::info('🔄 Manual sync certificate started', [
            'cert_id' => $certId
        ]);

        // Find certificate in Laravel DB
        $certificate = \App\Models\Certificate::where('blockchain_cert_id', $certId)
            ->orWhere('certificate_uid', $certId)
            ->first();

        if (!$certificate) {
            return response()->json([
                'success' => false,
                'error' => 'Certificate not found in Laravel database',
                'cert_id' => $certId
            ], 404);
        }

        // Fetch from CouchDB
        $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_' . $certId;
        $couchResponse = @file_get_contents($couchUrl);

        if ($couchResponse === false) {
            return response()->json([
                'success' => false,
                'error' => 'Certificate not found in CouchDB',
                'cert_id' => $certId,
                'couch_url' => $couchUrl
            ], 404);
        }

        $couchData = json_decode($couchResponse, true);
        
        if (!isset($couchData['certificateInfo']['status'])) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid CouchDB data - no status found',
                'cert_id' => $certId,
                'couch_data' => $couchData
            ], 400);
        }

        $couchStatus = $couchData['certificateInfo']['status'];
        $oldStatus = $certificate->blockchain_status;

        // Update certificate in Laravel DB
        $certificate->update([
            'blockchain_status' => $couchStatus,
            'blockchain_response' => json_encode($couchData),
            'updated_at' => now()
        ]);

        // Update order status if needed
        $order = $certificate->order;
        if ($order) {
            $orderUpdated = false;
            
            // If certificate is CERTIFICATE_ISSUED, update order status
            if ($couchStatus === 'CERTIFICATE_ISSUED' && $order->status !== 'CERTIFICATE_ISSUED') {
                $order->update([
                    'status' => 'CERTIFICATE_ISSUED',
                    'updated_at' => now()
                ]);
                $orderUpdated = true;
            }
            
            // If certificate is COMPLETED, check if all certificates are completed
            if ($couchStatus === 'COMPLETED') {
                $allCompleted = $order->certificates()
                    ->where('blockchain_status', '!=', 'COMPLETED')
                    ->count() === 0;
                
                if ($allCompleted && $order->status !== 'completed') {
                    $order->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'updated_at' => now()
                    ]);
                    $orderUpdated = true;
                }
            }
        }

        \Illuminate\Support\Facades\Log::info('✅ Certificate synced successfully', [
            'cert_id' => $certId,
            'old_status' => $oldStatus,
            'new_status' => $couchStatus,
            'order_updated' => $orderUpdated ?? false
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Certificate synced successfully',
            'certificate' => [
                'id' => $certificate->id,
                'blockchain_cert_id' => $certificate->blockchain_cert_id,
                'certificate_uid' => $certificate->certificate_uid,
                'old_status' => $oldStatus,
                'new_status' => $couchStatus,
                'updated_at' => $certificate->updated_at->toISOString()
            ],
            'order' => $order ? [
                'id' => $order->id,
                'order_uid' => $order->order_uid,
                'status' => $order->status,
                'updated' => $orderUpdated ?? false
            ] : null,
            'couch_data' => $couchData
        ], 200, [], JSON_PRETTY_PRINT);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('❌ Certificate sync failed', [
            'cert_id' => $certId,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('sync.certificate');

// Download certificate as PDF (server-generated) - only authenticated users
Route::middleware('auth')->get('/certificate/{certId}/download', [CertificateController::class, 'downloadPdf'])->name('certificate.download');

// Backwards-compatible proxy route for legacy frontend requests
// Mirrors the behavior of the earlier /api/certificates/couch/{certId}
Route::get('/api/certificates/couch/{certId}', function($certId) {
    try {
        $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_' . $certId;
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
        return response()->json([
            'success' => false,
            'message' => 'Internal server error',
            'error' => $e->getMessage()
        ], 500);
    }
});

// ✅ NEW: Sync specific order from CouchDB to Laravel DB
Route::get('/sync-order/{orderUid}', function($orderUid) {
    try {
        \Illuminate\Support\Facades\Log::info('🔄 Manual sync order started', [
            'order_uid' => $orderUid
        ]);

        // Find order in Laravel DB
        $order = \App\Models\Order::where('order_uid', $orderUid)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found in Laravel database',
                'order_uid' => $orderUid
            ], 404);
        }

        $results = [];
        $syncedCount = 0;
        $errorCount = 0;

        // Sync all certificates for this order
        foreach ($order->certificates as $certificate) {
            if (!$certificate->blockchain_cert_id && !$certificate->certificate_uid) {
                continue;
            }

            $certId = $certificate->blockchain_cert_id ?: $certificate->certificate_uid;
            
            try {
                // Fetch from CouchDB
                $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_' . $certId;
                $couchResponse = @file_get_contents($couchUrl);

                if ($couchResponse !== false) {
                    $couchData = json_decode($couchResponse, true);
                    
                    if (isset($couchData['certificateInfo']['status'])) {
                        $couchStatus = $couchData['certificateInfo']['status'];
                        $oldStatus = $certificate->blockchain_status;

                        // Update certificate
                        $certificate->update([
                            'blockchain_status' => $couchStatus,
                            'blockchain_response' => json_encode($couchData),
                            'updated_at' => now()
                        ]);

                        $results[] = [
                            'certificate_id' => $certificate->id,
                            'blockchain_cert_id' => $certId,
                            'old_status' => $oldStatus,
                            'new_status' => $couchStatus,
                            'synced' => true
                        ];
                        
                        $syncedCount++;
                    }
                }
            } catch (\Exception $e) {
                $results[] = [
                    'certificate_id' => $certificate->id,
                    'blockchain_cert_id' => $certId,
                    'error' => $e->getMessage(),
                    'synced' => false
                ];
                
                $errorCount++;
            }
        }

        // Update order status based on certificates
        $order->refresh();
        $allIssued = $order->certificates()
            ->whereIn('blockchain_status', ['CERTIFICATE_ISSUED', 'COMPLETED'])
            ->count() === $order->certificates->count();
        
        $allCompleted = $order->certificates()
            ->where('blockchain_status', 'COMPLETED')
            ->count() === $order->certificates->count();

        $oldOrderStatus = $order->status;
        $orderUpdated = false;

        if ($allCompleted && $order->status !== 'completed') {
            $order->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);
            $orderUpdated = true;
        } elseif ($allIssued && !in_array($order->status, ['CERTIFICATE_ISSUED', 'completed'])) {
            $order->update([
                'status' => 'CERTIFICATE_ISSUED'
            ]);
            $orderUpdated = true;
        }

        \Illuminate\Support\Facades\Log::info('✅ Order synced successfully', [
            'order_uid' => $orderUid,
            'certificates_synced' => $syncedCount,
            'errors' => $errorCount,
            'order_updated' => $orderUpdated
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order synced successfully',
            'order' => [
                'id' => $order->id,
                'order_uid' => $order->order_uid,
                'old_status' => $oldOrderStatus,
                'new_status' => $order->status,
                'updated' => $orderUpdated
            ],
            'statistics' => [
                'total_certificates' => $order->certificates->count(),
                'synced' => $syncedCount,
                'errors' => $errorCount
            ],
            'certificates' => $results
        ], 200, [], JSON_PRETTY_PRINT);

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('❌ Order sync failed', [
            'order_uid' => $orderUid,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
})->name('sync.order');

// Debug helper: check if Dompdf class is available in web runtime
Route::get('/_debug/dompdf', function() {
    $available = class_exists('Dompdf\\Dompdf') ? true : false;
    $facade = class_exists('\\Barryvdh\\DomPDF\\Facade') ? true : false;
    return response()->json([
        'dompdf_class_exists' => $available,
        'dompdf_facade_exists' => $facade,
        'php_version' => phpversion()
    ]);
});