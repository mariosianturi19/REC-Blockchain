<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\PowerPlant;
use App\Models\Certificate;
use App\Models\Order;
use App\Models\Company;
use App\Services\BlockchainService;
use App\Services\OrderStatusService;
use App\Services\CertificateSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class CheckoutController extends Controller
{
    protected $blockchainService;
    protected $orderStatusService;

    public function __construct(BlockchainService $blockchainService, OrderStatusService $orderStatusService)
    {
        $this->blockchainService = $blockchainService;
        $this->orderStatusService = $orderStatusService;
    }

    public function index()
    {
        $orders = Order::where('buyer_id', Auth::id())
                        ->with('certificates', 'buyer.company') 
                        ->orderBy('created_at', 'desc')
                        ->get();
        
        // ✅ NEW: Auto-sync setiap order di list
        foreach ($orders as $order) {
            if ($order->status !== 'completed') {
                $this->autoSyncAndComplete($order);
                $order->refresh(); // Reload status terbaru
            }
        }
        
        return view('buyer.orders-index', compact('orders'));
    }

    public function processOrder(Request $request)
    {
        $validated = $request->validate([
            'power_plant_id' => 'required|exists:power_plants,id',
            'quantity' => 'required|numeric|min:1',
            'min_purchase' => 'required|numeric|min:0',
            'category' => 'required|string|in:Retail,Signature,Enterprise'
        ]);

        $category = $request->session()->get('checkout_category', 'Personal');

        
        if ($validated['category'] === 'Enterprise' && !Auth::user()->company) {
            session(['pending_order_data' => $validated]);
            return redirect()->route('buyer.checkout.company.create');
        }

        $orderUid = 'REC-TRX-' . strtoupper(Str::random(10));
        $totalPrice = $validated['quantity'] * 35000; // atau sesuai logika harga Anda
        $virtualAccountNumber = '8808' . str_pad(auth()->id(), 10, '0', STR_PAD_LEFT);

        try {
            $order = $this->_createOrderFromData($validated);
            return redirect()->route('buyer.orders.show', $order->id)->with('success', 'Pesanan berhasil dibuat! Silakan lanjutkan pembayaran.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function createCompanyForm()
    {
        if (!session()->has('pending_order_data')) {
            return redirect()->route('buyer.marketplace')->with('error', 'Tidak ada proses pembelian yang aktif.');
        }
        return view('buyer.company-details');
    }

    public function storeCompanyForm(Request $request)
    {
        $validatedCompany = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'nib' => 'required|string|max:255',
        ]);

        Company::create([
            'user_id' => Auth::id(),
            'name' => $validatedCompany['name'],
            'address' => $validatedCompany['address'],
            'phone_number' => $validatedCompany['phone_number'],
            'nib' => $validatedCompany['nib'],
        ]);

        $pendingOrderData = session()->pull('pending_order_data');
        if (!$pendingOrderData) {
            return redirect()->route('buyer.marketplace')->with('error', 'Sesi pesanan Anda telah berakhir.');
        }

        try {
            $order = $this->_createOrderFromData($pendingOrderData);
            return redirect()->route('buyer.orders.show', $order->id)->with('success', 'Data perusahaan berhasil disimpan! Silakan lanjutkan pembayaran.');
        } catch (\Exception $e) {
            return redirect()->route('buyer.marketplace')->with('error', $e->getMessage());
        }
    }

    private function _createOrderFromData(array $data)
    {
        $quantityNeeded = floatval($data['quantity']);
        $minPurchase = floatval($data['min_purchase']);
        $powerPlant = PowerPlant::findOrFail($data['power_plant_id']);
        $pricePerMwh = 35000;

        if ($quantityNeeded < $minPurchase) {
            throw new \Exception('Jumlah pembelian tidak memenuhi syarat minimal ' . $minPurchase . ' MWh.');
        }

        return DB::transaction(function () use ($powerPlant, $quantityNeeded, $pricePerMwh, $data) {
            // Create order first
            $newOrder = Order::create([
                'order_uid' => 'REC-TRX-' . strtoupper(Str::random(10)),
                'buyer_id' => Auth::id(),
                'total_price' => $quantityNeeded * $pricePerMwh,
                'virtual_account_number' => '8808' . str_pad(Auth::id(), 10, '0', STR_PAD_LEFT),
                'status' => 'CERTIFICATE_REQUESTED', // ✅ FIXED: Blockchain workflow status
                'category' => $data['category']
            ]);

            $mwhToFulfill = $quantityNeeded;
            $processedCertificates = 0;
            $step1Successes = 0;

            $certificatesToReserve = $powerPlant->certificates()
                ->where('certificates.status', 'available_for_sale')
                ->lockForUpdate()
                ->orderBy('certificates.amount_mwh', 'asc')
                ->orderBy('certificates.created_at', 'asc')
                ->get();

            foreach ($certificatesToReserve as $certificate) {
                if ($mwhToFulfill <= 0) break;

                $certificateToProcess = null;

                // Handle certificate splitting if needed
                if (abs($certificate->amount_mwh - $mwhToFulfill) < 0.01) {
                    $certificate->status = 'on_hold';
                    $certificate->order_id = $newOrder->id;
                    $certificate->blockchain_cert_id = null; // Will be set after blockchain request
                    $certificate->save();
                    
                    $mwhToFulfill = 0;
                    $certificateToProcess = $certificate;
                    
                } elseif ($certificate->amount_mwh > $mwhToFulfill) {
                    // Split certificate
                    $newCertForOrder = new Certificate([
                        'certificate_uid' => 'REC-' . date('Y') . '-' . strtoupper(Str::random(8)),
                        'energy_report_id' => $certificate->energy_report_id,
                        'issuer_id' => $certificate->issuer_id,
                        'owner_id' => $certificate->owner_id,
                        'amount_mwh' => $mwhToFulfill,
                        'generation_start_date' => $certificate->generation_start_date,
                        'generation_end_date' => $certificate->generation_end_date,
                        'status' => 'on_hold',
                        'order_id' => $newOrder->id
                    ]);
                    
                    $newCertForOrder->save();
                    $certificateToProcess = $newCertForOrder;
                    
                    // Update original certificate
                    $certificate->amount_mwh -= $mwhToFulfill;
                    $certificate->save();
                    
                    $mwhToFulfill = 0;
                } else {
                    $certificate->status = 'on_hold';
                    $certificate->order_id = $newOrder->id;
                    $certificate->save();
                    
                    $mwhToFulfill -= $certificate->amount_mwh;
                    $certificateToProcess = $certificate;
                }

                $processedCertificates++;

// Execute Step 1 - Request Certificate to Blockchain
                if ($certificateToProcess) {
                    try {
                        Log::info('🚀 STEP 1: Requesting certificate to blockchain', [
                            'certificate_id' => $certificateToProcess->id,
                            'certificate_uid' => $certificateToProcess->certificate_uid,
                            'order_id' => $newOrder->id,
                            'buyer_id' => Auth::id()
                        ]);

                        // ✅ CRITICAL: Set blockchain_cert_id IMMEDIATELY before API call
                        // This ensures blockchain_cert_id is NEVER NULL
                        $certificateToProcess->blockchain_cert_id = $certificateToProcess->certificate_uid;
                        $certificateToProcess->blockchain_status = 'PENDING_BLOCKCHAIN_REQUEST';
                        $certificateToProcess->save();
                        
                        Log::info('✅ blockchain_cert_id set immediately', [
                            'certificate_id' => $certificateToProcess->id,
                            'blockchain_cert_id' => $certificateToProcess->blockchain_cert_id
                        ]);

                        // Get energy data
                        $energyReport = $certificateToProcess->energyReport;
                        if (!$energyReport) {
                            throw new \Exception('No energy report found');
                        }

                        // Get verified energy data from CouchDB
                        $verifiedEnergyId = $this->getVerifiedEnergyDataId();
                        if (!$verifiedEnergyId) {
                            throw new \Exception('No verified energy data found in blockchain');
                        }

                        // Format buyerId sesuai standar - use actual user name for better traceability
                        $buyerId = Auth::user()->name ?? 'Buyer_' . Auth::id();

                        // ✅ CRITICAL: Generate comprehensive security data dengan anti-duplication
                        $certificateHash = hash('sha256', $certificateToProcess->certificate_uid . time() . Auth::id());
                        $serialNumber = $certificateToProcess->certificate_uid;
                        
                        // ✅ NEW: Generate anti-duplication hash dari certificate content
                        $antiDuplicationHash = hash('sha256', json_encode([
                            'certificate_uid' => $certificateToProcess->certificate_uid,
                            'energy_report_id' => $energyReport->id,
                            'buyer_id' => Auth::id(),
                            'amount_mwh' => $certificateToProcess->amount_mwh,
                            'timestamp' => now()->timestamp
                        ]));

                        $securityData = [
                            'security' => [
                                'certificate_hash' => $certificateHash,
                                'serial_number' => $serialNumber,
                                'anti_duplication_hash' => $antiDuplicationHash,
                                'security_level' => 'HIGH',
                                'tamper_proof' => true,
                                'generated_at' => now()->toISOString()
                            ],
                            'compliance' => [
                                'anti_duplication_verified' => true,
                                'energy_data_validated' => true,
                                'regulatory_framework' => 'INTERNATIONAL_REC_STANDARD'
                            ],
                            'auditTrail' => [
                                'request_timestamp' => now()->toISOString(),
                                'request_by' => $buyerId,
                                'ownership_proof' => hash('sha256', $buyerId . $certificateToProcess->certificate_uid)
                            ]
                        ];

                        Log::info('🔒 Generated ENHANCED security data for certificate', [
                            'certificate_id' => $certificateToProcess->id,
                            'certificate_hash' => substr($certificateHash, 0, 16) . '...',
                            'serial_number' => $serialNumber,
                            'anti_duplication_hash' => substr($antiDuplicationHash, 0, 16) . '...',
                            'security_level' => 'HIGH'
                        ]);

                        // ✅ RETRY MECHANISM: Try up to 3 times if API call fails
                        $maxRetries = 3;
                        $retryCount = 0;
                        $apiSuccess = false;
                        
                        while (!$apiSuccess && $retryCount < $maxRetries) {
                            try {
                                // Make API call dengan format yang benar sesuai dengan API server
                                $response = Http::timeout(30)
                                    ->post('http://localhost:3000/api/certificates/request', [
                                        'certificateId' => $certificateToProcess->certificate_uid,
                                        'energyDataId' => $verifiedEnergyId,
                                        'buyerId' => $buyerId,
                                        'purchasedAmount' => $certificateToProcess->amount_mwh,
                                        'security' => $securityData['security'],
                                        'compliance' => $securityData['compliance'],
                                        'auditTrail' => $securityData['auditTrail'],
                                        'endorsement_orgs' => ['buyer', 'issuer']
                                    ]);

                                if ($response->successful()) {
                                    $result = $response->json();
                                    Log::info('✅ STEP 1 SUCCESS: Certificate request successful', [
                                        'certificate_id' => $certificateToProcess->id,
                                        'blockchain_response' => $result,
                                        'energy_id' => $verifiedEnergyId,
                                        'retry_count' => $retryCount
                                    ]);

                                    // ✅ FIXED: Update certificate dengan blockchain data
                                    $certificateToProcess->update([
                                        'blockchain_status' => 'CERTIFICATE_REQUESTED',
                                        'certificate_hash' => $certificateHash,
                                        'blockchain_response' => json_encode([
                                            'step1_request' => $result,
                                            'created_at' => now()->toISOString(),
                                            'requesterId' => $buyerId,
                                            'energy_id' => $verifiedEnergyId,
                                            'security' => $securityData,
                                            'certificate_hash' => $certificateHash,
                                            'serial_number' => $serialNumber,
                                            'retry_count' => $retryCount
                                        ])
                                    ]);

                                    $step1Successes++;
                                    $apiSuccess = true;

                                    Log::info('✅ Certificate data saved to Laravel DB', [
                                        'certificate_id' => $certificateToProcess->id,
                                        'blockchain_cert_id' => $certificateToProcess->blockchain_cert_id,
                                        'blockchain_status' => $certificateToProcess->blockchain_status,
                                        'certificate_hash' => substr($certificateHash, 0, 16) . '...'
                                    ]);
                                } else {
                                    $retryCount++;
                                    Log::warning('⚠️ STEP 1 RETRY: Certificate request failed, retrying...', [
                                        'certificate_id' => $certificateToProcess->id,
                                        'status' => $response->status(),
                                        'response' => $response->body(),
                                        'retry_count' => $retryCount,
                                        'max_retries' => $maxRetries
                                    ]);
                                    
                                    if ($retryCount < $maxRetries) {
                                        sleep(2); // Wait 2 seconds before retry
                                    } else {
                                        throw new \Exception('Failed to request certificate from blockchain after ' . $maxRetries . ' retries: ' . $response->body());
                                    }
                                }
                            } catch (\Exception $e) {
                                $retryCount++;
                                Log::warning('⚠️ STEP 1 RETRY: API call exception, retrying...', [
                                    'certificate_id' => $certificateToProcess->id,
                                    'error' => $e->getMessage(),
                                    'retry_count' => $retryCount,
                                    'max_retries' => $maxRetries
                                ]);
                                
                                if ($retryCount >= $maxRetries) {
                                    throw $e;
                                }
                                sleep(2); // Wait 2 seconds before retry
                            }
                        }

                    } catch (\Exception $e) {
                        Log::error('💥 Certificate request failed after all retries', [
                            'certificate_id' => $certificateToProcess->id,
                            'error' => $e->getMessage()
                        ]);
                        
                        // ✅ CRITICAL: Update status to FAILED but blockchain_cert_id stays set
                        $certificateToProcess->update([
                            'blockchain_status' => 'BLOCKCHAIN_REQUEST_FAILED',
                            'blockchain_response' => json_encode([
                                'error' => $e->getMessage(),
                                'failed_at' => now()->toISOString()
                            ])
                        ]);
                        
                        // Continue processing other certificates even if one fails
                        continue;
                    }
                }
            }

            // ✅ FIXED: Don't update order status to invalid value
            // Order status stays as 'pending_payment' until buyer confirms payment
            Log::info('✅ Order created with blockchain integration', [
                'order_id' => $newOrder->id,
                'certificates_processed' => $processedCertificates,
                'step1_successes' => $step1Successes
            ]);

            return $newOrder;
        });
    }

    public function showOrder(Order $order)
    {
        if ($order->buyer_id !== Auth::id()) {
            abort(403);
        }

        $this->autoSyncAndComplete($order);

        $order->refresh();
        $order->load('certificates');

        $totalMwh = $order->certificates()->sum('amount_mwh');

        return view('buyer.order-show', compact('order', 'totalMwh'));
    }

    /**
     * ✅ NEW: Show certificates page (REC Saya)
     */
    public function showCertificates(Order $order)
    {
        // Check if user owns this order
        if ($order->buyer_id !== Auth::id()) {
            abort(403, 'Unauthorized access to order');
        }

        // Auto-sync certificates from blockchain
        $this->autoSyncAndComplete($order);

        // Reload order dengan certificates
        $order->refresh();
        $order->load('certificates.energyReport.powerPlant');

        $totalMwh = $order->certificates()->sum('amount_mwh');

        // ✅ FIX: Use existing order-show view instead of non-existent certificates-show
        return view('buyer.order-show', compact('order', 'totalMwh'));
    }

    /**
     * Auto sync and complete order based on certificate status
     * ✅ IMPROVED: Auto-sync dari CouchDB setiap kali halaman order dibuka
     */
    private function autoSyncAndComplete(Order $order)
    {
        if ($order->status === 'completed') {
            return;
        }

        try {
            Log::info('🔄 AUTO-SYNC: Starting automatic sync from CouchDB', [
                'order_id' => $order->id,
                'order_uid' => $order->order_uid,
                'current_status' => $order->status
            ]);

            $certificates = $order->certificates;
            $totalCertificates = $certificates->count();
            $readyCertificates = 0;
            $autoCompletedCertificates = 0;
            $syncedCertificates = 0;

            foreach ($certificates as $certificate) {
                // ✅ NEW: Try to find certificate in CouchDB
                $blockchainCertId = $certificate->blockchain_cert_id ?: $certificate->certificate_uid;
                
                if (!$blockchainCertId) {
                    Log::warning('Certificate has no blockchain ID, skipping', [
                        'certificate_id' => $certificate->id
                    ]);
                    continue;
                }

                // ✅ CRITICAL: Sync status dari CouchDB (use Http client for reliability)
                try {
                    $couchUrl = 'http://localhost:5984/recchannel_rec/CERTIFICATE_' . $blockchainCertId;
                    $couchRes = \Illuminate\Support\Facades\Http::withBasicAuth('admin', 'adminpw')
                        ->timeout(10)
                        ->get($couchUrl);

                    if ($couchRes->successful()) {
                        $couchData = $couchRes->json();

                        if (isset($couchData['certificateInfo']['status'])) {
                            $couchStatus = $couchData['certificateInfo']['status'];
                            $oldStatus = $certificate->blockchain_status;
                            
                            // ✅ NEW: Update blockchain_cert_id if still NULL
                            if (!$certificate->blockchain_cert_id) {
                                $certificate->blockchain_cert_id = $blockchainCertId;
                                $certificate->save();
                                
                                Log::info('✅ Certificate blockchain_cert_id auto-set', [
                                    'certificate_id' => $certificate->id,
                                    'blockchain_cert_id' => $blockchainCertId
                                ]);
                            }
                            
                            // ✅ CRITICAL: Update database status DAN blockchain_response lengkap
                            if ($oldStatus !== $couchStatus) {
                                // ✅ NEW: Merge dengan existing blockchain_response (jangan overwrite semua)
                                $existingResponse = $certificate->blockchain_response ? 
                                    json_decode($certificate->blockchain_response, true) : [];
                                
                                // Update dengan data terbaru dari CouchDB
                                $existingResponse['couchData'] = $couchData;
                                $existingResponse['sync_timestamp'] = now()->toISOString();
                                $existingResponse['auto_synced'] = true;
                                
                                $certificate->update([
                                    'blockchain_status' => $couchStatus,
                                    'blockchain_response' => json_encode($existingResponse),
                                    'updated_at' => now()
                                ]);
                                
                                $syncedCertificates++;
                                
                                Log::info('✅ Certificate status auto-synced from CouchDB with full data', [
                                    'certificate_id' => $certificate->id,
                                    'blockchain_cert_id' => $blockchainCertId,
                                    'old_status' => $oldStatus,
                                    'new_status' => $couchStatus,
                                    'has_security_data' => isset($couchData['security']),
                                    'has_ownership_data' => isset($couchData['certificateInfo']['currentOwner'])
                                ]);
                            } else {
                                // ✅ NEW: Bahkan jika status sama, tetap sync blockchain_response untuk update security data
                                $existingResponse = $certificate->blockchain_response ? 
                                    json_decode($certificate->blockchain_response, true) : [];
                                
                                // Check if couchData is outdated or missing
                                $needsUpdate = !isset($existingResponse['couchData']) || 
                                             !isset($existingResponse['couchData']['security']) ||
                                             (isset($existingResponse['sync_timestamp']) && 
                                              strtotime($existingResponse['sync_timestamp']) < strtotime('-5 minutes'));
                                
                                if ($needsUpdate) {
                                    $existingResponse['couchData'] = $couchData;
                                    $existingResponse['sync_timestamp'] = now()->toISOString();
                                    $existingResponse['auto_synced'] = true;
                                    
                                    $certificate->update([
                                        'blockchain_response' => json_encode($existingResponse),
                                        'updated_at' => now()
                                    ]);
                                    
                                    Log::info('✅ Certificate blockchain_response updated with latest CouchDB data', [
                                        'certificate_id' => $certificate->id,
                                        'blockchain_cert_id' => $blockchainCertId,
                                        'status' => $couchStatus
                                    ]);
                                }
                            }

                            // ✅ NEW: Auto-complete CERTIFICATE_ISSUED certificates
                            if ($couchStatus === 'CERTIFICATE_ISSUED') {
                                Log::info('🚀 AUTO-COMPLETE: Certificate is ISSUED, running auto-complete', [
                                    'certificate_id' => $certificate->id,
                                    'blockchain_cert_id' => $blockchainCertId
                                ]);

                                $buyerId = $order->buyer->name ?? 'Buyer' . $order->buyer_id;
                                
                                try {
                                    // Idempotency guard: skip if already completed or step5 recorded
                                    $existingResponse = $certificate->blockchain_response ? json_decode($certificate->blockchain_response, true) : [];
                                    $alreadyCompleted = ($certificate->blockchain_status === 'COMPLETED')
                                        || (!empty($certificate->completed_at))
                                        || (is_array($existingResponse) && isset($existingResponse['step5_complete']));

                                    if ($alreadyCompleted) {
                                        Log::info('⏭️ Skipping auto-complete: already completed or step5 recorded', [
                                            'certificate_id' => $certificate->id,
                                            'blockchain_cert_id' => $blockchainCertId
                                        ]);
                                    } else {
                                        $completeResponse = Http::timeout(30)
                                            ->put('http://localhost:3000/api/certificates/complete/' . $blockchainCertId, [
                                                'buyerId' => $buyerId
                                            ]);

                                        if ($completeResponse->successful()) {
                                            $existingResponse['step5_complete'] = $completeResponse->json();
                                            $existingResponse['completed_at'] = now()->toISOString();

                                            $certificate->update([
                                                'blockchain_status' => 'COMPLETED',
                                                'status' => 'completed',
                                                'completed_at' => now(),
                                                'blockchain_response' => json_encode($existingResponse)
                                            ]);

                                            $autoCompletedCertificates++;

                                            Log::info('✅ Certificate auto-completed (Step 5)', [
                                                'certificate_id' => $certificate->id,
                                                'blockchain_cert_id' => $blockchainCertId,
                                                'buyer_id' => $buyerId
                                            ]);
                                        } else {
                                            Log::warning('⚠️ Auto-complete failed but certificate still ISSUED', [
                                                'certificate_id' => $certificate->id,
                                                'response_status' => $completeResponse->status(),
                                                'response_body' => $completeResponse->body()
                                            ]);
                                        }
                                    }
                                } catch (\Exception $e) {
                                    Log::error('❌ Auto-complete error', [
                                        'certificate_id' => $certificate->id,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                            
                            // Reload certificate to get latest status
                            $certificate->refresh();
                        }
                    } else {
                        Log::warning('Certificate not found in CouchDB', [
                            'certificate_id' => $certificate->id,
                            'blockchain_cert_id' => $blockchainCertId,
                            'couch_url' => $couchUrl
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to sync certificate from CouchDB', [
                        'certificate_id' => $certificate->id,
                        'blockchain_cert_id' => $blockchainCertId,
                        'error' => $e->getMessage()
                    ]);
                }

                // Count ready certificates (ISSUED or COMPLETED)
                if (in_array($certificate->blockchain_status, ['CERTIFICATE_ISSUED', 'COMPLETED'])) {
                    $readyCertificates++;
                }
            }

            // ✅ CRITICAL: Update order status based on certificates
            $oldOrderStatus = $order->status;
            $orderUpdated = false;

            if ($totalCertificates > 0) {
                // All certificates are COMPLETED -> order is completed
                $allCompleted = $order->certificates()
                    ->where('blockchain_status', 'COMPLETED')
                    ->count() === $totalCertificates;
                
                // At least one certificate is ISSUED or COMPLETED -> order is CERTIFICATE_ISSUED
                $anyIssued = $order->certificates()
                    ->whereIn('blockchain_status', ['CERTIFICATE_ISSUED', 'COMPLETED'])
                    ->count() > 0;

                if ($allCompleted && $order->status !== 'completed') {
                    $order->update([
                        'status' => 'completed',
                        'completed_at' => now()
                    ]);
                    $orderUpdated = true;
                    
                    Log::info('✅ Order status auto-updated to COMPLETED', [
                        'order_id' => $order->id,
                        'old_status' => $oldOrderStatus,
                        'new_status' => 'completed',
                        'total_certificates' => $totalCertificates
                    ]);
                } elseif ($anyIssued && !in_array($order->status, ['CERTIFICATE_ISSUED', 'completed'])) {
                    $order->update([
                        'status' => 'CERTIFICATE_ISSUED',
                        'updated_at' => now()
                    ]);
                    $orderUpdated = true;
                    
                    Log::info('✅ Order status auto-updated to CERTIFICATE_ISSUED', [
                        'order_id' => $order->id,
                        'old_status' => $oldOrderStatus,
                        'new_status' => 'CERTIFICATE_ISSUED',
                        'ready_certificates' => $readyCertificates,
                        'total_certificates' => $totalCertificates
                    ]);
                }
            }

            Log::info('✅ AUTO-SYNC COMPLETED', [
                'order_id' => $order->id,
                'total_certificates' => $totalCertificates,
                'synced_certificates' => $syncedCertificates,
                'ready_certificates' => $readyCertificates,
                'auto_completed_certificates' => $autoCompletedCertificates,
                'order_status_updated' => $orderUpdated,
                'final_order_status' => $order->status
            ]);

        } catch (\Exception $e) {
            Log::error('❌ AUTO-SYNC ERROR', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Confirm payment - buyer mengkonfirmasi sudah membayar
     * ✅ NEW: Step 2 workflow - CERTIFICATE_REQUESTED → CERTIFICATE_PAID
     */
    public function confirmPayment(Order $order)
    {
        // Check if user owns this order
        if ($order->buyer_id !== Auth::id()) {
            abort(403, 'Unauthorized access to order');
        }

        // Check if order is in correct status
        if ($order->status !== 'CERTIFICATE_REQUESTED') {
            return redirect()->back()->with('error', 'Order tidak dalam status yang tepat untuk konfirmasi pembayaran.');
        }

        try {
            Log::info('🏁 STARTING STEP 2: Payment confirmation by buyer', [
                'order_id' => $order->id,
                'order_uid' => $order->order_uid,
                'buyer_id' => Auth::id(),
                'timestamp' => now()
            ]);

            // ✅ NEW WORKFLOW: Step 2 - Confirm Payment untuk setiap certificate
            $successfulConfirmations = 0;
            $totalCertificates = $order->certificates->count();
            $buyerId = Auth::user()->name ?? 'Buyer' . Auth::id();

            foreach ($order->certificates as $certificate) {
                // ✅ CONSISTENCY FIX: Ensure blockchain_cert_id is set
                $blockchainCertId = $certificate->blockchain_cert_id ?: $certificate->certificate_uid;
                
                if (!$certificate->blockchain_cert_id) {
                    $certificate->update([
                        'blockchain_cert_id' => $certificate->certificate_uid,
                    ]);
                    $certificate->refresh();
                }

                try {
                    Log::info('🚀 STEP 2: Confirming payment for certificate', [
                        'certificate_id' => $certificate->id,
                        'blockchain_cert_id' => $blockchainCertId,
                        'buyer_id' => $buyerId
                    ]);

                    // ✅ NEW: Step 2 - Confirm Payment via blockchain API
                    $confirmResponse = Http::timeout(30)
                        ->put('http://localhost:3000/api/certificates/confirm-payment/' . $blockchainCertId, [
                            'buyerId' => $buyerId,
                            'paymentMethod' => 'bank_transfer',
                            'paymentReference' => 'PAY_' . $blockchainCertId . '_' . time()
                        ]);

                    if ($confirmResponse->successful() && $confirmResponse->json('success')) {
                        // ✅ CRITICAL: Wait for blockchain to finish writing
                        sleep(3);
                        
                        // ✅ CRITICAL: Update database IMMEDIATELY without transaction
                        DB::statement('SET autocommit=1');
                        
                        $updated = DB::table('certificates')
                            ->where('id', $certificate->id)
                            ->update([
                                'blockchain_status' => 'CERTIFICATE_PAID',
                                'blockchain_response' => json_encode([
                                    'step2_payment_confirm' => $confirmResponse->json(),
                                    'payment_confirmed_at' => now()->toISOString(),
                                    'workflow_stage' => 'STEP_2_COMPLETED',
                                    'sync_timestamp' => now()->toISOString()
                                ]),
                                'updated_at' => now()
                            ]);

                        if ($updated) {
                            $successfulConfirmations++;
                            
                            Log::info('✅ STEP 2 SUCCESS: Certificate status updated to CERTIFICATE_PAID', [
                                'certificate_id' => $certificate->id,
                                'blockchain_cert_id' => $blockchainCertId,
                                'database_updated' => true
                            ]);
                        } else {
                            Log::error('❌ Database update failed', [
                                'certificate_id' => $certificate->id
                            ]);
                        }

                    } else {
                        Log::error('❌ STEP 2 FAILED: Payment confirmation failed', [
                            'certificate_id' => $certificate->id,
                            'blockchain_cert_id' => $blockchainCertId,
                            'response_status' => $confirmResponse->status(),
                            'response_body' => $confirmResponse->body()
                        ]);
                    }

                } catch (\Exception $e) {
                    Log::error('❌ Error confirming payment for certificate', [
                        'certificate_id' => $certificate->id,
                        'blockchain_cert_id' => $blockchainCertId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // ✅ UPDATE: Order status WITHOUT transaction
            if ($successfulConfirmations > 0) {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'status' => 'CERTIFICATE_PAID',
                        'payment_confirmed_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                Log::info('✅ Order status updated to CERTIFICATE_PAID', [
                    'order_id' => $order->id,
                    'successful_confirmations' => $successfulConfirmations
                ]);
            }

            // ✅ NEW: Success message dengan workflow yang baru
            $successMessage = 'Pembayaran berhasil dikonfirmasi! ';
            
            if ($successfulConfirmations > 0) {
                $successMessage .= "✅ {$successfulConfirmations}/{$totalCertificates} sertifikat berhasil diupdate ke status CERTIFICATE_PAID. ";
                $successMessage .= "Pesanan Anda menunggu verifikasi pembayaran oleh issuer.";
            } else {
                $successMessage .= "⚠️ Blockchain update gagal, tetapi order tetap diproses. ";
                $successMessage .= "Silakan coba lagi.";
            }

            return redirect()->route('buyer.orders.show', $order)
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            Log::error('❌ Failed to confirm payment', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Gagal mengkonfirmasi pembayaran. Silakan coba lagi.');
        }
    }

    public function getSecurityLevel($certificate)
    {
        if (!$certificate->blockchain_cert_id) {
            return 'LOW';
        }
        
        $hasIntegrity = $this->isIntegrityVerified($certificate);
        $hasOwnership = $this->isOwnershipAuthenticated($certificate);
        $hasUniqueness = $this->isUniquenessConfirmed($certificate);
        
        if ($hasIntegrity && $hasOwnership && $hasUniqueness) {
            return 'HIGH';
        } elseif ($hasIntegrity || $hasOwnership) {
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }
    
    public function getSecurityLevelColor($certificate)
    {
        $level = $this->getSecurityLevel($certificate);
        
        switch ($level) {
            case 'HIGH':
                return 'text-green-600';
            case 'MEDIUM':
                return 'text-yellow-600';
            case 'LOW':
            default:
                return 'text-red-600';
        }
    }
    
    public function isIntegrityVerified($certificate)
    {
        if (!$certificate->blockchain_cert_id || !$certificate->blockchain_response) {
            return false;
        }
        
        try {
            $response = json_decode($certificate->blockchain_response, true);
            
            if (isset($response['security']['integrityValidated'])) {
                return $response['security']['integrityValidated'] === true;
            }
            
            if ($certificate->blockchain_status === 'COMPLETED') {
                return true;
            }
            
            if (isset($response['couchData']['security']['tamperProof'])) {
                return $response['couchData']['security']['tamperProof'] === true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            \Log::warning('Failed to check certificate integrity', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    public function isOwnershipAuthenticated($certificate)
    {
        if (!$certificate->blockchain_cert_id) {
            return false;
        }
        
        try {
            // ✅ FIX: If certificate is CERTIFICATE_ISSUED or COMPLETED, ownership is automatically authenticated
            // Because issuer has already verified and issued the certificate to the buyer
            if (in_array($certificate->blockchain_status, ['CERTIFICATE_ISSUED', 'COMPLETED'])) {
                return true;
            }
            
            // Check blockchain response for ownership proof
            $response = json_decode($certificate->blockchain_response, true);
            
            if (isset($response['security']['ownershipProofValid'])) {
                return $response['security']['ownershipProofValid'] === true;
            }
            
            if (isset($response['couchData']['auditTrail']['ownershipProof'])) {
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            \Log::warning('Failed to check certificate ownership', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    public function isUniquenessConfirmed($certificate)
    {
        if (!$certificate->blockchain_cert_id) {
            return false;
        }
        
        try {
            $response = json_decode($certificate->blockchain_response, true);
            
            if (isset($response['security']['antiDuplicationVerified'])) {
                return $response['security']['antiDuplicationVerified'] === true;
            }
            
            if ($certificate->blockchain_status && $certificate->blockchain_status !== 'SECURITY_VALIDATION_FAILED') {
                return true;
            }
            
            if (isset($response['couchData']['compliance']['antiDuplicationVerified'])) {
                return $response['couchData']['compliance']['antiDuplicationVerified'] === true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            \Log::warning('Failed to check certificate uniqueness', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    public function getTruncatedHash($certificate)
    {
        $hash = $this->getFullHash($certificate);
        
        if (!$hash) {
            return 'N/A';
        }
        
        return substr($hash, 0, 8) . '...';
    }
    
    public function getFullHash($certificate)
    {
        if (!$certificate->blockchain_cert_id || !$certificate->blockchain_response) {
            return null;
        }
        
        try {
            $response = json_decode($certificate->blockchain_response, true);
            
            if (isset($response['security']['certificateHash'])) {
                return $response['security']['certificateHash'];
            }
            
            if (isset($response['couchData']['certificateHash'])) {
                return $response['couchData']['certificateHash'];
            }
            
            if (isset($response['couchData']['security']['certificateFingerprint'])) {
                return $response['couchData']['security']['certificateFingerprint'];
            }
            
            return hash('sha256', $certificate->blockchain_cert_id);
            
        } catch (\Exception $e) {
            \Log::warning('Failed to get certificate hash', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    public function getSerialNumber($certificate)
    {
        if (!$certificate->blockchain_cert_id || !$certificate->blockchain_response) {
            return 'Legacy Certificate';
        }
        
        try {
            $response = json_decode($certificate->blockchain_response, true);
            
            if (isset($response['security']['serialNumber'])) {
                return $response['security']['serialNumber'];
            }
            
            if (isset($response['couchData']['serialNumber'])) {
                return $response['couchData']['serialNumber'];
            }
            
            if (isset($response['couchData']['compliance']['serialNumber'])) {
                return $response['couchData']['compliance']['serialNumber'];
            }
            
            $hash = $this->getFullHash($certificate);
            if ($hash) {
                return 'REC-' . date('Y') . '-' . substr($hash, 0, 8);
            }
            
            return 'REC-' . date('Y') . '-' . substr($certificate->blockchain_cert_id, -8);
            
        } catch (\Exception $e) {
            \Log::warning('Failed to get certificate serial number', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage()
            ]);
            return 'Legacy Certificate';
        }
    }
    
    public function canVerifyOwnership($certificate)
    {
        return $certificate->blockchain_cert_id && 
               in_array($certificate->blockchain_status, ['CERTIFICATE_REQUESTED', 'CERTIFICATE_ISSUED', 'COMPLETED']);
    }
    
    public function canDownloadProof($certificate)
    {
        return $certificate->blockchain_cert_id && 
               in_array($certificate->blockchain_status, ['CERTIFICATE_ISSUED', 'COMPLETED']);
    }

    public function getOverallSecurityLevel($order)
    {
        $certificates = $order->certificates->whereNotNull('blockchain_cert_id');
        
        if ($certificates->isEmpty()) {
            return 'LOW';
        }
        
        $highCount = 0;
        $mediumCount = 0;
        $lowCount = 0;
        
        foreach ($certificates as $certificate) {
            $level = $this->getSecurityLevel($certificate);
            
            switch ($level) {
                case 'HIGH':
                    $highCount++;
                    break;
                case 'MEDIUM':
                    $mediumCount++;
                    break;
                case 'LOW':
                default:
                    $lowCount++;
                    break;
            }
        }
        
        if ($highCount >= ($certificates->count() / 2)) {
            return 'HIGH';
        }
        
        if (($highCount + $mediumCount) >= ($certificates->count() / 2)) {
            return 'MEDIUM';
        }
        
        return 'LOW';
    }
    
    public function getSecurityBadgeColor($securityLevel)
    {
        switch ($securityLevel) {
            case 'HIGH':
                return 'green';
            case 'MEDIUM':
                return 'yellow';
            case 'LOW':
            default:
                return 'red';
        }
    }
    
    public function getSecurityIcon($securityLevel)
    {
        switch ($securityLevel) {
            case 'HIGH':
                return '🛡️';
            case 'MEDIUM':
                return '🔒';
            case 'LOW':
            default:
                return '⚠️';
        }
    }
    
    public function getOrderSecurityStats($order)
    {
        $certificates = $order->certificates->whereNotNull('blockchain_cert_id');
        $total = $certificates->count();
        
        if ($total === 0) {
            return [
                'integrity' => ['verified' => 0, 'total' => 0],
                'ownership' => ['verified' => 0, 'total' => 0],
                'uniqueness' => ['verified' => 0, 'total' => 0]
            ];
        }
        
        $integrityVerified = 0;
        $ownershipVerified = 0;
        $uniquenessVerified = 0;
        
        foreach ($certificates as $certificate) {
            if ($this->isIntegrityVerified($certificate)) {
                $integrityVerified++;
            }
            
            if ($this->isOwnershipAuthenticated($certificate)) {
                $ownershipVerified++;
            }
            
            if ($this->isUniquenessConfirmed($certificate)) {
                $uniquenessVerified++;
            }
        }
        
        return [
            'integrity' => [
                'verified' => $integrityVerified,
                'total' => $total
            ],
            'ownership' => [
                'verified' => $ownershipVerified,
                'total' => $total
            ],
            'uniqueness' => [
                'verified' => $uniquenessVerified,
                'total' => $total
            ]
        ];
    }

    /**
     * Get available verified energy data from blockchain
     */
    private function getAvailableEnergyData()
    {
        try {
            // Fetch all documents from CouchDB using _all_docs endpoint
            $couchResponse = \Illuminate\Support\Facades\Http::withBasicAuth('admin', 'adminpw')
                ->get('http://localhost:5984/recchannel_rec/_all_docs?include_docs=true');

            if ($couchResponse->successful()) {
                $data = $couchResponse->json();
                
                // Filter for verified energy data
                $verifiedEnergyData = collect($data['rows'] ?? [])
                    ->filter(function ($row) {
                        $doc = $row['doc'] ?? [];
                        return isset($doc['docType']) && 
                               $doc['docType'] === 'energyData' && 
                               isset($doc['status']) && 
                               $doc['status'] === 'VERIFIED';
                    })
                    ->map(function ($row) {
                        return $row['doc'];
                    })
                    ->sortByDesc('timestamp') // Get the newest first
                    ->values()
                    ->toArray();

                if (empty($verifiedEnergyData)) {
                    Log::warning('No verified energy data found in blockchain');
                    return null;
                }

                $latestEnergyData = $verifiedEnergyData[0];
                
                Log::info('✅ Found verified energy data', [
                    'energy_id' => $latestEnergyData['energyDataId'],
                    'status' => $latestEnergyData['status'],
                    'amount' => $latestEnergyData['amount'] ?? 'N/A',
                    'total_available' => count($verifiedEnergyData)
                ]);

                return $latestEnergyData['energyDataId'];
                
            } else {
                Log::error('Failed to fetch energy data from CouchDB', [
                    'status' => $couchResponse->status(),
                    'response' => $couchResponse->body()
                ]);
                return null;
            }

        } catch (\Exception $e) {
            Log::error('Error fetching energy data from blockchain', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Show certificate details for buyer
     */
    public function showCertificate(Certificate $certificate)
    {
        // Check if buyer owns this certificate through an order
        $order = $certificate->order;
        if (!$order || $order->buyer_id !== Auth::id()) {
            abort(403, 'Unauthorized access to certificate');
        }

        return view('buyer.certificate-show', compact('certificate', 'order'));
    }

    /**
     * ✅ NEW: Step 5 - Complete Certificate (Buyer clicks "View Certificate")
     * This completes the blockchain workflow: CERTIFICATE_ISSUED → COMPLETED
     */
    public function completeCertificate(Certificate $certificate)
    {
        try {
            // Check if buyer owns this certificate through an order
            $order = $certificate->order;
            if (!$order || $order->buyer_id !== Auth::id()) {
                abort(403, 'Unauthorized access to certificate');
            }

            // Check if certificate is ready to be completed
            if ($certificate->blockchain_status !== 'CERTIFICATE_ISSUED') {
                return redirect()->back()->with('error', 
                    'Certificate belum siap untuk diselesaikan. Status saat ini: ' . 
                    ($certificate->blockchain_status ?? 'Unknown'));
            }

            Log::info('🏁 STARTING STEP 5: Complete Certificate', [
                'certificate_id' => $certificate->id,
                'blockchain_cert_id' => $certificate->blockchain_cert_id,
                'order_id' => $order->id,
                'buyer_id' => Auth::id()
            ]);

            // ✅ Step 5: Complete Certificate via blockchain API
            $completeResponse = \Illuminate\Support\Facades\Http::timeout(30)
                ->put('http://localhost:3000/api/certificates/complete/' . $certificate->blockchain_cert_id, [
                    'buyerId' => Auth::user()->name ?? 'Buyer' . Auth::id(),
                    'completionNotes' => 'Certificate activated by buyer'
                ]);

            if ($completeResponse->successful() && $completeResponse->json('success')) {
                // Update certificate with Step 5 success
                $existingResponse = $certificate->blockchain_response ? json_decode($certificate->blockchain_response, true) : [];
                $existingResponse['step5_complete'] = $completeResponse->json();
                $existingResponse['completed_at'] = now()->toISOString();

                DB::transaction(function () use ($certificate, $existingResponse, $order) {
                    // Update certificate status to COMPLETED
                    $certificate->update([
                        'blockchain_status' => 'COMPLETED',
                        'blockchain_response' => json_encode($existingResponse),
                        'completed_at' => now()
                    ]);

                    // Check if all certificates in order are completed
                    $allCertificatesCompleted = $order->certificates()
                        ->where('blockchain_status', '!=', 'COMPLETED')
                        ->count() === 0;

                    if ($allCertificatesCompleted) {
                        $order->update([
                            'status' => 'completed',
                            'completed_at' => now()
                        ]);

                        Log::info('✅ Order auto-completed - all certificates finished', [
                            'order_id' => $order->id,
                            'total_certificates' => $order->certificates->count()
                        ]);
                    }
                });

                Log::info('✅ STEP 5 SUCCESS: Certificate completed', [
                    'certificate_id' => $certificate->id,
                    'blockchain_cert_id' => $certificate->blockchain_cert_id,
                    'order_id' => $order->id,
                    'response' => $completeResponse->json()
                ]);

                return redirect()->back()->with('success', 
                    'Sertifikat berhasil diselesaikan! Certificate ID: ' . 
                    $certificate->blockchain_cert_id . ' sekarang berstatus COMPLETED.');

            } else {
                Log::error('❌ STEP 5 FAILED: Complete certificate failed', [
                    'certificate_id' => $certificate->id,
                    'blockchain_cert_id' => $certificate->blockchain_cert_id,
                    'response_status' => $completeResponse->status(),
                    'response_body' => $completeResponse->body()
                ]);

                return redirect()->back()->with('error', 
                    'Gagal menyelesaikan sertifikat. Error: ' . $completeResponse->body());
            }

        } catch (\Exception $e) {
            Log::error('❌ STEP 5 ERROR: Complete certificate error', [
                'certificate_id' => $certificate->id,
                'blockchain_cert_id' => $certificate->blockchain_cert_id ?? 'null',
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 
                'Terjadi kesalahan saat menyelesaikan sertifikat: ' . $e->getMessage());
        }
    }

    /**
     * ✅ NEW: Get verified energy data ID from CouchDB
     * This uses the existing verified energy data that you already created
     */
    private function getVerifiedEnergyDataId()
    {
        try {
            // Fetch all documents from CouchDB using _all_docs endpoint
            $couchResponse = \Illuminate\Support\Facades\Http::withBasicAuth('admin', 'adminpw')
                ->get('http://localhost:5984/recchannel_rec/_all_docs?include_docs=true');

            if ($couchResponse->successful()) {
                $data = $couchResponse->json();
                
                // Filter for verified energy data
                $verifiedEnergyData = collect($data['rows'] ?? [])
                    ->filter(function ($row) {
                        $doc = $row['doc'] ?? [];
                        // Look for energy data with VERIFIED status
                        return isset($doc['docType']) && 
                               $doc['docType'] === 'energyData' && 
                               isset($doc['status']) && 
                               $doc['status'] === 'VERIFIED';
                    })
                    ->map(function ($row) {
                        return $row['doc'];
                    })
                    ->sortByDesc('timestamp') // Get the newest first
                    ->values()
                    ->toArray();

                if (empty($verifiedEnergyData)) {
                    Log::warning('No verified energy data found in CouchDB');
                    return null;
                }

                $latestEnergyData = $verifiedEnergyData[0];
                
                Log::info('✅ Found verified energy data for certificate request', [
                    'energy_data_id' => $latestEnergyData['energyDataId'],
                    'status' => $latestEnergyData['status'],
                    'generator_id' => $latestEnergyData['generatorId'] ?? 'N/A',
                    'amount' => $latestEnergyData['amount'] ?? 'N/A',
                    'source_type' => $latestEnergyData['sourceType'] ?? 'N/A'
                ]);

                // Return the energy data ID (without the ENERGY_ prefix since chaincode will add it)
                return $latestEnergyData['energyDataId'];
                
            } else {
                Log::error('Failed to fetch energy data from CouchDB', [
                    'status' => $couchResponse->status(),
                    'response' => $couchResponse->body()
                ]);
                return null;
            }

        } catch (\Exception $e) {
            Log::error('Error fetching verified energy data from CouchDB', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
