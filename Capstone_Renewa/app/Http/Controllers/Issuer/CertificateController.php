<?php

namespace App\Http\Controllers\Issuer;

use App\Http\Controllers\Controller;
use App\Models\EnergyReport;
use App\Models\Certificate;
use App\Models\Order; // <-- PENTING: Import model Order
use App\Services\BlockchainService;
use App\Services\OrderStatusService;
use App\Services\CertificateSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    protected $blockchainService;
    protected $orderStatusService;

    public function __construct(BlockchainService $blockchainService, OrderStatusService $orderStatusService)
    {
        $this->blockchainService = $blockchainService;
        $this->orderStatusService = $orderStatusService;
    }

    /**
     * Menyetujui laporan energi dan menerbitkan sertifikat.
     * Ini mengintegrasikan Step 2 (Verify Energy Data) dan Step 4 (Issue Certificate)
     */
    public function issue(Request $request, EnergyReport $report)
    {
        // 🔍 DETAILED LOGGING: Start of issue process
        Log::info('🏁 STARTING CERTIFICATE ISSUE PROCESS', [
            'energy_report_id' => $report->id,
            'current_status' => $report->status,
            'blockchain_energy_id' => $report->blockchain_energy_id,
            'blockchain_verification_status' => $report->blockchain_verification_status,
            'issuer_id' => Auth::id(),
            'timestamp' => now()
        ]);

        if ($report->status !== 'pending_verification') {
            Log::warning('⚠️ Report already processed', [
                'energy_report_id' => $report->id,
                'current_status' => $report->status
            ]);
            return redirect()->route('issuer.dashboard')->with('error', 'Laporan ini sudah diproses.');
        }

        try {
            // ⭐ PENTING: Verifikasi blockchain DULU sebelum database transaction
            $blockchainVerified = false;
            $verificationResult = null;
            $certificateBlockchainData = null;
            
            // Step 2: Verify Energy Data di Blockchain FIRST
            if ($report->blockchain_energy_id) {
                try {
                    Log::info('🔗 STEP 2: Starting blockchain verification', [
                        'energy_report_id' => $report->id,
                        'blockchain_id' => $report->blockchain_energy_id
                    ]);

                    $verificationResult = $this->blockchainService->verifyEnergyData(
                        $report->blockchain_energy_id,
                        config('app.default_issuer_id', 'ISSUER-REC-001'),
                        'Energy report approved by issuer'
                    );

                    // 🔍 DETAILED LOGGING: Blockchain verification response
                    Log::info('📡 Blockchain verification response', [
                        'energy_report_id' => $report->id,
                        'verification_result' => $verificationResult,
                        'success_field' => isset($verificationResult['success']) ? $verificationResult['success'] : 'NOT_SET',
                        'response_type' => gettype($verificationResult)
                    ]);

                    // ⭐ FIX: Proper success detection
                    if (isset($verificationResult['success']) && $verificationResult['success'] === true) {
                        $blockchainVerified = true;
                        Log::info('✅ STEP 2 SUCCESS: Blockchain verification successful', [
                            'energy_report_id' => $report->id,
                            'result' => $verificationResult
                        ]);

                        // Generate certificate UID untuk blockchain operations
                        $certificateUid = 'REC-' . now()->year . '-' . strtoupper(Str::random(8));

                        // ⭐ ENABLED: Certificate operations re-enabled for proper blockchain flow
                        try {
                            Log::info('🔄 STEP 3: Starting certificate request to blockchain', [
                                'certificate_uid' => $certificateUid,
                                'energy_id' => $report->blockchain_energy_id
                            ]);

                            // Step 3: Request Certificate
                            Log::info('🚀 STEP 3: Requesting certificate to blockchain', [
                                'certificate_uid' => $certificateUid,
                                'energy_id' => $report->blockchain_energy_id,
                                'generator_id' => config('app.default_generator_id')
                            ]);

                            // Make direct API call to blockchain
                            $requestResponse = \Illuminate\Support\Facades\Http::timeout(30)
                                ->post('http://localhost:3000/api/certificates', [
                                    'certId' => $certificateUid,
                                    'energyId' => $report->blockchain_energy_id,
                                    'generatorId' => config('app.default_generator_id', 'GEN-PLTSA-001'),
                                    'securityData' => [
                                        'certificate_hash' => hash('sha256', $certificateUid . now()),
                                        'serial_number' => $certificateUid,
                                        'security_level' => 'HIGH'
                                    ]
                                ]);

                            if ($requestResponse->successful()) {
                                $requestResult = $requestResponse->json();
                                Log::info('✅ STEP 3 SUCCESS: Certificate request successful', [
                                    'certificate_uid' => $certificateUid,
                                    'response' => $requestResult
                                ]);

                                // Proceed to Step 4 only if request was successful
                                if (isset($requestResult['success']) && $requestResult['success'] === true) {
                                    // Step 4: Issue Certificate
                                    Log::info('🔄 STEP 4: Starting certificate issue', [
                                        'certificate_uid' => $certificateUid
                                    ]);

                                    $issueResponse = \Illuminate\Support\Facades\Http::timeout(30)
                                        ->put("http://localhost:3000/api/certificates/issue/{$certificateUid}", [
                                            'issuerId' => config('app.default_issuer_id', 'ISSUER-REC-001')
                                        ]);

                                    if ($issueResponse->successful()) {
                                        $issueResult = $issueResponse->json();
                                        Log::info('✅ STEP 4 SUCCESS: Certificate issued successfully', [
                                            'certificate_uid' => $certificateUid,
                                            'response' => $issueResult
                                        ]);

                                        $certificateBlockchainData = [
                                            'certificate_uid' => $certificateUid,
                                            'blockchain_cert_id' => $certificateUid,
                                            'blockchain_status' => 'CERTIFICATE_REQUESTED',
                                            'blockchain_response' => json_encode([
                                                'step3_request' => $requestResult,
                                                'step4_issue' => $issueResult,
                                                'created_at' => now()->toISOString()
                                            ])
                                        ];
                                    }
                                }
                            } else {
                                Log::error('❌ STEP 3 FAILED: Certificate request failed', [
                                    'certificate_uid' => $certificateUid,
                                    'status' => $requestResponse->status(),
                                    'response' => $requestResponse->body()
                                ]);
                            }

                            Log::info('✅ STEP 3/4 COMPLETED: Certificate operations completed', [
                                'certificate_uid' => $certificateUid,
                                'blockchain_status' => $certificateBlockchainData['blockchain_status'],
                                'has_blockchain_cert_id' => !is_null($certificateBlockchainData['blockchain_cert_id'])
                            ]);

                            // After issuing, try to fetch CouchDB status and sync local certificate
                            try {
                                $blockchainCertId = $certificate->blockchain_cert_id ?? ($certificateUid ?? null);
                                if ($blockchainCertId) {
                                    $couchUrl = 'http://localhost:5984/recchannel_rec/CERTIFICATE_' . $blockchainCertId;
                                    $couchRes = \Illuminate\Support\Facades\Http::withBasicAuth('admin', 'adminpw')
                                        ->timeout(10)
                                        ->get($couchUrl);

                                    if ($couchRes->successful()) {
                                        $couchData = $couchRes->json();
                                        $couchStatus = $couchData['certificateInfo']['status'] ?? null;

                                        if ($couchStatus) {
                                            $update = ['blockchain_status' => $couchStatus, 'blockchain_response' => json_encode($couchData), 'updated_at' => now()];
                                            if ($couchStatus === 'COMPLETED') {
                                                $update['status'] = 'completed';
                                                $update['completed_at'] = now();
                                            }
                                            // If the certificate model exists, update it
                                            if (isset($certificate) && $certificate instanceof \App\Models\Certificate) {
                                                $certificate->update($update);
                                            }
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                Log::warning('Failed to fetch CouchDB after issuing certificate', [
                                    'certificate_id' => isset($certificate->id) ? $certificate->id : null,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        } catch (\Exception $certError) {
                            Log::warning('⚠️ STEP 3/4 EXCEPTION: Certificate operations failed but continuing', [
                                'error' => $certError->getMessage(),
                                'energy_report_id' => $report->id,
                                'exception_trace' => $certError->getTraceAsString()
                            ]);
                            
                            // ✅ FIXED: Still set blockchain_cert_id even if operations fail
                            $certificateBlockchainData = [
                                'certificate_uid' => $certificateUid,
                                'blockchain_cert_id' => $certificateUid, // ✅ ALWAYS set blockchain_cert_id for Step 5!
                                'blockchain_status' => 'CERTIFICATE_ERROR_BUT_AVAILABLE',
                                'request_result' => null,
                                'issue_result' => null,
                                'error' => $certError->getMessage()
                            ];
                        }
                    } else {
                        Log::error('❌ STEP 2 FAILED: Blockchain verification returned non-success', [
                            'energy_report_id' => $report->id,
                            'verification_result' => $verificationResult,
                            'success_value' => isset($verificationResult['success']) ? $verificationResult['success'] : 'NOT_SET'
                        ]);
                        
                        // ✅ NEW: Generate certificate_uid even when verification fails
                        $certificateUid = 'REC-' . now()->year . '-' . strtoupper(Str::random(8));
                        $certificateBlockchainData = [
                            'certificate_uid' => $certificateUid,
                            'blockchain_cert_id' => $certificateUid, // ✅ ALWAYS set blockchain_cert_id for Step 5!
                            'blockchain_status' => 'VERIFICATION_FAILED_BUT_AVAILABLE',
                            'request_result' => null,
                            'issue_result' => null,
                            'error' => 'Blockchain verification failed'
                        ];
                    }

                } catch (\Exception $e) {
                    Log::error('❌ STEP 2 EXCEPTION: Blockchain verification failed with exception', [
                        'energy_report_id' => $report->id,
                        'error' => $e->getMessage(),
                        'blockchain_id' => $report->blockchain_energy_id,
                        'exception_trace' => $e->getTraceAsString()
                    ]);
                    
                    // Don't fail the entire process - continue with database operations
                    $blockchainVerified = false;
                }
            } else {
                Log::warning('⚠️ No blockchain_energy_id found', [
                    'energy_report_id' => $report->id,
                    'blockchain_energy_id' => $report->blockchain_energy_id
                ]);
            }

            // Step 5: Database Transaction
            Log::info('💾 STEP 5: Starting database transaction', [
                'energy_report_id' => $report->id,
                'blockchain_verified' => $blockchainVerified,
                'has_certificate_data' => !is_null($certificateBlockchainData)
            ]);

            DB::transaction(function () use ($report, $blockchainVerified, $verificationResult, $certificateBlockchainData) {
                // 🔍 DETAILED LOGGING: Before energy report update
                Log::info('📊 BEFORE energy report update', [
                    'energy_report_id' => $report->id,
                    'current_status' => $report->status,
                    'current_blockchain_verification_status' => $report->blockchain_verification_status
                ]);

                // Update report dengan hasil blockchain verification
                if ($report->blockchain_energy_id) {
                    if ($blockchainVerified) {
                        $report->update([
                            'blockchain_verification_status' => 'verified',
                            'blockchain_verification_response' => json_encode($verificationResult)
                        ]);
                        
                        Log::info('✅ Energy report blockchain status updated to VERIFIED', [
                            'energy_report_id' => $report->id
                        ]);
                    } else {
                        $report->update([
                            'blockchain_verification_status' => 'failed',
                            'blockchain_verification_error' => 'Verification failed or returned non-success'
                        ]);
                        
                        Log::warning('⚠️ Energy report blockchain status updated to FAILED', [
                            'energy_report_id' => $report->id
                        ]);
                    }
                }

                // Update report status ke approved
                $oldStatus = $report->status;
                $report->status = 'approved';
                $report->save();

                // 🔍 DETAILED LOGGING: After energy report update
                Log::info('📊 AFTER energy report update', [
                    'energy_report_id' => $report->id,
                    'old_status' => $oldStatus,
                    'new_status' => $report->status,
                    'blockchain_verification_status' => $report->blockchain_verification_status
                ]);

                $ownerId = $report->powerPlant->user_id;

                // ✅ ENHANCED: Generate security hash for certificate data
                $energyDataForHash = [
                    'power_plant_id' => $report->powerPlant->id,
                    'amount_mwh' => $report->amount_mwh,
                    'reporting_period_start' => $report->reporting_period_start,
                    'reporting_period_end' => $report->reporting_period_end,
                    'generation_method' => $report->generation_method ?? 'renewable'
                ];
                
                $energyDataHash = CertificateSecurityService::generateEnergyDataHash($energyDataForHash);
                
                // ✅ ANTI-DUPLICATION: Check for duplicate energy data
                if (CertificateSecurityService::checkEnergyDataDuplicate($energyDataHash)) {
                    Log::warning('⚠️ Duplicate energy data detected', [
                        'energy_report_id' => $report->id,
                        'energy_data_hash' => $energyDataHash
                    ]);
                    
                    // Update energy report with anti-duplication flag
                    $report->update([
                        'energy_data_hash' => $energyDataHash,
                        'anti_duplication_verified' => false,
                        'blockchain_verification_error' => 'Duplicate energy data detected'
                    ]);
                } else {
                    // Mark as unique
                    $report->update([
                        'energy_data_hash' => $energyDataHash,
                        'anti_duplication_verified' => true
                    ]);
                    
                    Log::info('✅ Energy data uniqueness verified', [
                        'energy_report_id' => $report->id,
                        'energy_data_hash' => substr($energyDataHash, 0, 16) . '...'
                    ]);
                }

                // Create certificate in database
                $certificateData = [
                    'energy_report_id' => $report->id,
                    'issuer_id' => Auth::id(),
                    'owner_id' => $ownerId,
                    'amount_mwh' => $report->amount_mwh,
                    'generation_start_date' => $report->reporting_period_start,
                    'generation_end_date' => $report->reporting_period_end,
                    'status' => 'available_for_sale',
                ];

                // ✅ ENHANCED: Generate certificate security hash and serial number
                $certificateHash = CertificateSecurityService::generateCertificateHash($certificateData);
                $serialNumber = CertificateSecurityService::generateSerialNumber($certificateHash);
                
                // ✅ ANTI-DUPLICATION: Check for duplicate certificate
                if (CertificateSecurityService::checkCertificateDuplicate($certificateHash)) {
                    Log::warning('⚠️ Duplicate certificate detected', [
                        'energy_report_id' => $report->id,
                        'certificate_hash' => substr($certificateHash, 0, 16) . '...'
                    ]);
                    throw new \Exception('Duplicate certificate detected. This energy data may have already been certified.');
                }

                // Add security fields to certificate data
                $certificateData = array_merge($certificateData, [
                    'certificate_hash' => $certificateHash,
                    'serial_number' => $serialNumber
                ]);

                // Add blockchain data if available
                if ($certificateBlockchainData) {
                    $certificateData = array_merge($certificateData, [
                        'certificate_uid' => $certificateBlockchainData['certificate_uid'],
                        'blockchain_cert_id' => $certificateBlockchainData['certificate_uid'], // Gunakan certificate_uid sebagai blockchain_cert_id
                        'blockchain_status' => $certificateBlockchainData['blockchain_status'],
                        'blockchain_response' => json_encode([
                            'request' => $certificateBlockchainData['request_result'],
                            'issue' => $certificateBlockchainData['issue_result'],
                            'created_at' => now()->toISOString()
                        ])
                    ]);
                    
                    Log::info('📋 Certificate data includes blockchain information', [
                        'certificate_uid' => $certificateBlockchainData['certificate_uid'],
                        'blockchain_cert_id' => $certificateBlockchainData['certificate_uid'],
                        'blockchain_status' => $certificateBlockchainData['blockchain_status']
                    ]);
                } else {
                    // Fallback jika blockchain gagal
                    $certificateData['certificate_uid'] = 'REC-' . now()->year . '-' . strtoupper(Str::random(8));
                    
                    // ⭐ FIX: Set proper status based on blockchain verification
                    if ($blockchainVerified) {
                        $certificateData['blockchain_status'] = 'VERIFIED_ONLY';
                        $certificateData['blockchain_verified'] = true;
                    } else {
                        // If blockchain is disabled, mark as AVAILABLE directly
                        if (!$this->blockchainService->isEnabled()) {
                            $certificateData['blockchain_status'] = 'AVAILABLE';
                            $certificateData['blockchain_verified'] = false;
                            Log::info('📋 Blockchain disabled - Certificate marked as AVAILABLE', [
                                'certificate_uid' => $certificateData['certificate_uid']
                            ]);
                        } else {
                            $certificateData['blockchain_status'] = 'PENDING';
                            $certificateData['blockchain_verified'] = false;
                        }
                    }
                    
                    Log::warning('📋 Certificate data created without blockchain information', [
                        'certificate_uid' => $certificateData['certificate_uid'],
                        'blockchain_status' => $certificateData['blockchain_status'],
                        'blockchain_enabled' => $this->blockchainService->isEnabled()
                    ]);
                }

                $certificate = Certificate::create($certificateData);

                // 🔍 DETAILED LOGGING: Certificate creation result
                Log::info('📝 Certificate created in database', [
                    'certificate_id' => $certificate->id,
                    'certificate_uid' => $certificate->certificate_uid,
                    'blockchain_status' => $certificate->blockchain_status ?? 'NO_BLOCKCHAIN',
                    'blockchain_verified' => $blockchainVerified,
                    'certificate_data_full' => $certificateData
                ]);

                // Link to orders if certificate is properly created
                if ($certificate && isset($certificate->id)) {
                    Log::info('🔗 Linking certificate to orders', [
                        'certificate_id' => $certificate->id
                    ]);
                    $this->linkCertificateToOrders($certificate);
                } else {
                    Log::error('❌ Certificate creation failed - no ID found', [
                        'certificate' => $certificate
                    ]);
                }
            });

            // 🔍 DETAILED LOGGING: Final status check
            $finalReport = EnergyReport::find($report->id);
            Log::info('🏁 FINAL STATUS CHECK', [
                'energy_report_id' => $report->id,
                'final_status' => $finalReport->status,
                'final_blockchain_verification_status' => $finalReport->blockchain_verification_status,
                'blockchain_verified' => $blockchainVerified,
                'has_certificate_data' => !is_null($certificateBlockchainData)
            ]);

            // Success message dengan status blockchain yang lebih akurat
            $message = 'Laporan energi telah disetujui dan sertifikat telah diterbitkan.';
            if ($blockchainVerified && $certificateBlockchainData && $certificateBlockchainData['blockchain_status'] === 'CERTIFICATE_ISSUED') {
                $message .= ' ✅ Blockchain verification & certificate issuance: SUCCESS';
            } elseif ($blockchainVerified) {
                $message .= ' ✅ Blockchain verification: SUCCESS';
            } else {
                $message .= ' ⚠️ Blockchain verification: FAILED (processed locally)';
            }

            Log::info('✅ Certificate issue process completed', [
                'energy_report_id' => $report->id,
                'success_message' => $message
            ]);

            return redirect()->route('issuer.dashboard')->with('success', $message);

        } catch (\Exception $e) {
            Log::error('💥 Certificate issuance failed completely', [
                'energy_report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return redirect()->route('issuer.dashboard')->with('error', 'Gagal memproses laporan energi: ' . $e->getMessage());
        }
    }

    /**
     * Link certificate yang baru dibuat ke orders yang menunggu
     * dan update status orders berdasarkan blockchain status
     */
    private function linkCertificateToOrders($certificate)
    {
        try {
            // ✅ IMPROVED: Update certificates dalam orders yang belum memiliki blockchain_cert_id
            $updatedCertificates = Certificate::whereNull('blockchain_cert_id')
                ->where('status', 'on_hold')
                ->whereHas('energyReport.powerPlant', function ($query) use ($certificate) {
                    $query->where('id', $certificate->energyReport->powerPlant->id);
                })
                ->update([
                    'blockchain_cert_id' => $certificate->blockchain_cert_id,
                    'blockchain_status' => $certificate->blockchain_status,
                    'blockchain_response' => $certificate->blockchain_response
                ]);

            Log::info('✅ Updated certificates with blockchain data', [
                'new_certificate_id' => $certificate->id,
                'blockchain_cert_id' => $certificate->blockchain_cert_id,
                'updated_certificates_count' => $updatedCertificates,
                'blockchain_status' => $certificate->blockchain_status
            ]);

            // Update related orders
            $relatedOrders = Order::whereIn('status', ['pending_payment', 'awaiting_confirmation'])
                ->whereHas('certificates', function ($query) use ($certificate) {
                    $query->whereHas('energyReport.powerPlant', function ($plantQuery) use ($certificate) {
                        $plantQuery->where('id', $certificate->energyReport->powerPlant->id);
                    });
                })
                ->get();

            foreach ($relatedOrders as $order) {
                // Update order status menggunakan service
                $this->orderStatusService->updateOrderStatusFromBlockchain($order);

                Log::info('Updated order status after certificate linking', [
                    'order_id' => $order->id,
                    'new_status' => $order->fresh()->status,
                    'linked_certificate_id' => $certificate->id,
                    'blockchain_cert_id' => $certificate->blockchain_cert_id
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to link certificate to orders', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Menolak laporan energi.
     */
    public function reject(Request $request, EnergyReport $report)
    {
        $request->validate(['rejection_reason' => 'required|string|max:255']);

        if ($report->status !== 'pending_verification') {
            return redirect()->route('issuer.dashboard')->with('error', 'Laporan ini sudah diproses.');
        }

        $report->status = 'rejected';
        $report->rejection_reason = $request->rejection_reason;
        $report->save();

        return redirect()->route('issuer.dashboard')->with('success', 'Laporan energi telah ditolak.');
    }

    /**
     * Menyetujui pembayaran dan mentransfer kepemilikan sertifikat.
     * UPDATED: Blockchain Step 5 (Purchase Request) + Step 6 (Confirm Purchase)
     */
    public function approvePayment(Request $request, Order $order)
    {
        // 🔍 DETAILED LOGGING: Start of payment approval process
        Log::info('🏁 STARTING PAYMENT APPROVAL PROCESS', [
            'order_id' => $order->id,
            'order_uid' => $order->order_uid,
            'current_status' => $order->status,
            'buyer_id' => $order->buyer_id,
            'issuer_id' => Auth::id(),
            'timestamp' => now()
        ]);

        // Pastikan order masih menunggu konfirmasi
        if ($order->status !== 'awaiting_confirmation') {
            Log::warning('⚠️ Order already processed', [
                'order_id' => $order->id,
                'current_status' => $order->status
            ]);
            return redirect()->route('issuer.dashboard')->with('error', 'Pesanan ini sudah diproses sebelumnya.');
        }

        try {
            $successfulBlockchainOperations = 0;
            $totalCertificates = $order->certificates->count();
            
            // Step 1: Create blockchain buy requests for each certificate
            foreach ($order->certificates as $certificate) {
                try {
                    Log::info('🚀 Processing certificate for blockchain', [
                        'certificate_id' => $certificate->id,
                        'energy_report_id' => $certificate->energy_report_id
                    ]);

                    // Get energy data blockchain ID
                    $energyReport = $certificate->energyReport;
                    if (!$energyReport->blockchain_energy_id) {
                        Log::warning('⚠️ Energy report has no blockchain ID, skipping', [
                            'certificate_id' => $certificate->id,
                            'energy_report_id' => $energyReport->id
                        ]);
                        continue;
                    }

                    // Generate buy request ID
                    $buyRequestId = 'BUY_REQUEST_' . $order->order_uid . '_CERT_' . $certificate->id;
                    $buyerId = 'BUYER_' . str_pad($order->buyer_id, 3, '0', STR_PAD_LEFT);
                    
                    Log::info('📝 Creating blockchain buy request', [
                        'buy_request_id' => $buyRequestId,
                        'buyer_id' => $buyerId,
                        'energy_id' => $energyReport->blockchain_energy_id,
                        'amount' => $certificate->amount_mwh
                    ]);

                    // Step 5: Create Buy Request via blockchain API
                    $buyRequestResponse = \Illuminate\Support\Facades\Http::timeout(30)
                        ->post('http://localhost:3000/api/buy-requests', [
                            'requestId' => $buyRequestId,
                            'energyDataId' => $energyReport->blockchain_energy_id,
                            'buyerId' => $buyerId,
                            'requestedAmount' => $certificate->amount_mwh,
                            'pricePerKwh' => 35000 // Default price
                        ]);

                    if ($buyRequestResponse->successful()) {
                        Log::info('✅ Buy request created successfully', [
                            'buy_request_id' => $buyRequestId,
                            'response' => $buyRequestResponse->json()
                        ]);

                        // Step 6: Make Payment (simulate buyer payment)
                        $paymentResponse = \Illuminate\Support\Facades\Http::timeout(30)
                            ->post("http://localhost:3000/api/buy-requests/{$buyRequestId}/payment", [
                                'paymentMethod' => 'bank_transfer',
                                'paymentReference' => $order->virtual_account_number
                            ]);

                        if ($paymentResponse->successful()) {
                            Log::info('✅ Payment confirmed on blockchain', [
                                'buy_request_id' => $buyRequestId,
                                'payment_response' => $paymentResponse->json()
                            ]);

                            // Step 7: Verify Payment (issuer verifies)
                            $verifyResponse = \Illuminate\Support\Facades\Http::timeout(30)
                                ->post("http://localhost:3000/api/buy-requests/{$buyRequestId}/verify-payment", [
                                    'issuerId' => 'ISSUER001',
                                    'verificationNotes' => 'Payment verified by issuer'
                                ]);

                            if ($verifyResponse->successful()) {
                                Log::info('✅ Payment verification completed', [
                                    'buy_request_id' => $buyRequestId,
                                    'verification_response' => $verifyResponse->json()
                                ]);

                                // Update certificate with blockchain data
                                $certificate->update([
                                    'blockchain_purchase_status' => 'VERIFIED',
                                    'blockchain_purchase_response' => json_encode([
                                        'buy_request_id' => $buyRequestId,
                                        'create_response' => $buyRequestResponse->json(),
                                        'payment_response' => $paymentResponse->json(),
                                        'verify_response' => $verifyResponse->json()
                                    ]),
                                    'owner_id' => $order->buyer_id,
                                    'status' => 'sold'
                                ]);

                                $successfulBlockchainOperations++;
                            } else {
                                Log::error('❌ Payment verification failed', [
                                    'buy_request_id' => $buyRequestId,
                                    'error' => $verifyResponse->body()
                                ]);
                            }
                        } else {
                            Log::error('❌ Payment confirmation failed', [
                                'buy_request_id' => $buyRequestId,
                                'error' => $paymentResponse->body()
                            ]);
                        }
                    } else {
                        Log::error('❌ Buy request creation failed', [
                            'buy_request_id' => $buyRequestId,
                            'error' => $buyRequestResponse->body()
                        ]);
                    }

                } catch (\Exception $e) {
                    Log::error('❌ Certificate blockchain processing failed', [
                        'certificate_id' => $certificate->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Update order status regardless of blockchain success
            DB::transaction(function () use ($order) {
                $order->update([
                    'status' => 'completed',
                    'order_completed_at' => now()
                ]);

                // Transfer ownership of certificates to buyer (database level)
                $order->certificates()->update([
                    'owner_id' => $order->buyer_id,
                    'status' => 'sold'
                ]);
            });

            // Success message with blockchain status
            $message = 'Pembayaran untuk pesanan #' . $order->order_uid . ' telah disetujui.';
            if ($successfulBlockchainOperations > 0) {
                $message .= " ✅ {$successfulBlockchainOperations}/{$totalCertificates} sertifikat berhasil diproses ke blockchain.";
            } else {
                $message .= " ⚠️ Proses blockchain gagal, tetapi transaksi tetap berhasil di database.";
            }

            Log::info('✅ Payment approval process completed', [
                'order_id' => $order->id,
                'blockchain_operations' => $successfulBlockchainOperations,
                'total_certificates' => $totalCertificates
            ]);

            return redirect()->route('issuer.dashboard')->with('success', $message);

        } catch (\Exception $e) {
            Log::error('💥 Payment approval failed completely', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('issuer.dashboard')->with('error', 'Gagal menyelesaikan transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Manual Step 4: Issue Certificate to Blockchain
     */
    private function issueCertificateManual($certificate, $order)
    {
        try {
            // Check if certificate has blockchain_cert_id from Step 3
            if (!$certificate->blockchain_cert_id) {
                Log::warning('⚠️ Certificate has no blockchain_cert_id, skipping issue', [
                    'certificate_id' => $certificate->id,
                    'blockchain_status' => $certificate->blockchain_status
                ]);
                return;
            }

            // Step 4: Issue Certificate via direct API call
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->put('http://localhost:3000/api/certificates/issue/' . $certificate->blockchain_cert_id, [
                    'issuerId' => 'ISSUER001'
                ]);

            if ($response->successful() && $response->json('success')) {
                // Update certificate with Step 4 success
                $existingResponse = $certificate->blockchain_response ? json_decode($certificate->blockchain_response, true) : [];
                $existingResponse['step4_issue'] = $response->json();
                $existingResponse['issued_at'] = now()->toISOString();

                $certificate->update([
                    'blockchain_status' => 'CERTIFICATE_ISSUED',
                    'blockchain_response' => json_encode($existingResponse),
                    'owner_id' => $order->buyer_id, // Transfer ownership to buyer
                    'status' => 'sold'
                ]);

                Log::info('✅ Step 4 SUCCESS: Certificate issue completed', [
                    'certificate_id' => $certificate->id,
                    'blockchain_cert_id' => $certificate->blockchain_cert_id,
                    'new_owner' => $order->buyer_id
                ]);
            } else {
                throw new \Exception('Certificate issue failed: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('❌ Step 4 FAILED: Certificate issue failed', [
                'certificate_id' => $certificate->id,
                'blockchain_cert_id' => $certificate->blockchain_cert_id,
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            // Mark certificate as failed but continue with database operations
            $certificate->update([
                'blockchain_status' => 'ISSUE_FAILED',
                'blockchain_error' => $e->getMessage(),
                'owner_id' => $order->buyer_id, // Still transfer ownership locally
                'status' => 'sold'
            ]);
        }
    }

    /**
     * Menolak pembayaran.
     */
    public function rejectPayment(Request $request, Order $order)
    {
        // Logika untuk menolak pembayaran bisa dikembangkan di sini.
        // Contoh: mengembalikan status order ke 'pending_payment' atau 'cancelled'.
        // Dan mengembalikan status sertifikat dari 'on_hold' ke 'available_for_sale'.

        return redirect()->route('issuer.dashboard')->with('info', 'Fitur tolak pembayaran sedang dalam pengembangan.');
    }

    /**
     * Verifikasi pembayaran untuk order
     */
    public function verifyPayment(Request $request, $orderId)
    {
        try {
            $order = Order::findOrFail($orderId);

            // ✅ FIXED: Proper status update with correct workflow 
            DB::beginTransaction();
            
            // Step 4: Issue Certificate to blockchain for each certificate
            $successfulIssues = 0;
            $totalCertificates = $order->certificates->count();
            
            foreach ($order->certificates as $certificate) {
                if ($certificate->blockchain_cert_id && $certificate->blockchain_status === 'CERTIFICATE_REQUESTED') {
                    try {
                        // Step 4: Issue Certificate via API
                        $issueResponse = \Illuminate\Support\Facades\Http::timeout(30)
                            ->put('http://localhost:3000/api/certificates/issue/' . $certificate->blockchain_cert_id, [
                                'issuerId' => 'ISSUER001'
                            ]);

                        if ($issueResponse->successful() && $issueResponse->json('success')) {
                            // Update certificate with Step 4 success
                            $existingResponse = $certificate->blockchain_response ? json_decode($certificate->blockchain_response, true) : [];
                            $existingResponse['step4_issue'] = $issueResponse->json();
                            $existingResponse['issued_at'] = now()->toISOString();

                            $certificate->update([
                                'blockchain_status' => 'CERTIFICATE_ISSUED',
                                'blockchain_response' => json_encode($existingResponse)
                            ]);

                            $successfulIssues++;
                            
                            Log::info('✅ Step 4 SUCCESS: Certificate issued', [
                                'certificate_id' => $certificate->id,
                                'blockchain_cert_id' => $certificate->blockchain_cert_id
                            ]);
                        } else {
                            Log::warning('⚠️ Step 4 FAILED: Certificate issue failed', [
                                'certificate_id' => $certificate->id,
                                'blockchain_cert_id' => $certificate->blockchain_cert_id,
                                'response' => $issueResponse->body()
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('❌ Certificate issue error', [
                            'certificate_id' => $certificate->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Update order status to payment_verified (proper workflow)
            $order->update([
                'status' => 'payment_verified',  // ✅ FIXED: Proper quotes around status value
                'payment_verified_at' => now(),
            ]);

            DB::commit();

            Log::info('Payment verified successfully with blockchain operations', [
                'order_id' => $orderId,
                'new_status' => $order->status,
                'successful_issues' => $successfulIssues,
                'total_certificates' => $totalCertificates
            ]);

            $message = 'Pembayaran berhasil diverifikasi.';
            if ($successfulIssues > 0) {
                $message .= " ✅ {$successfulIssues}/{$totalCertificates} sertifikat berhasil diterbitkan ke blockchain.";
            } else {
                $message .= " ⚠️ Penerbitan blockchain gagal, tetapi verifikasi pembayaran berhasil.";
            }

            return redirect()->route('issuer.dashboard')->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to verify payment', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('issuer.dashboard')->with('error', 'Gagal memverifikasi pembayaran. Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
