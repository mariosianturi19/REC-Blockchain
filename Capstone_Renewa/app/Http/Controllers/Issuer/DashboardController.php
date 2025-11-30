<?php

namespace App\Http\Controllers\Issuer;

use App\Http\Controllers\Controller;
use App\Models\EnergyReport;
use App\Models\Certificate;
use App\Models\Order;
use App\Services\BlockchainService;
use App\Services\OrderStatusService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
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
        try {
            $pendingReports = EnergyReport::with(['powerPlant.user'])
                ->where('status', 'pending_verification')
                ->orderBy('created_at', 'asc')
                ->get();

            // ✅ FIXED: Orders that need payment verification - Updated for BLOCKCHAIN WORKFLOW
            // Status CERTIFICATE_PAID berarti buyer sudah bayar dan menunggu verifikasi issuer
            $pendingOrders = Order::where('status', 'CERTIFICATE_PAID')
                ->with(['buyer', 'certificates.energyReport.powerPlant'])
                ->orderBy('created_at', 'asc')
                ->get()
                ->filter(function($order) {
                    return $order->certificates->count() > 0 && 
                           $order->certificates->first()->energyReport && 
                           $order->certificates->first()->energyReport->powerPlant;
                });

            // ✅ FIXED: Orders with issued certificates - Updated for BLOCKCHAIN WORKFLOW
            // Status CERTIFICATE_ISSUED berarti issuer sudah issue dan buyer bisa lihat sertifikat
            $verifiedOrders = Order::where('status', 'CERTIFICATE_ISSUED')
                ->orWhere('status', 'COMPLETED')
                ->with(['buyer', 'certificates.energyReport.powerPlant'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->filter(function($order) {
                    return $order->certificates->count() > 0 && 
                           $order->certificates->first()->energyReport && 
                           $order->certificates->first()->energyReport->powerPlant;
                });

            // Certificate requests dari Generator - Simplified query
            $certificateRequests = EnergyReport::with(['powerPlant.user'])
                ->where('status', 'approved')
                ->where('certificate_requested', true)
                ->whereNotNull('certificate_id')
                ->where('certificate_status', 'CERTIFICATE_REQUESTED')
                ->orderBy('certificate_requested_at', 'asc')
                ->get()
                ->filter(function($report) {
                    // Check if certificate hasn't been issued yet
                    $issuedCert = Certificate::where('energy_report_id', $report->id)
                        ->where('blockchain_status', 'CERTIFICATE_ISSUED')
                        ->first();
                    return !$issuedCert;
                });

            $recIssuedThisMonth = Certificate::whereYear('created_at', Carbon::now()->year)
                                              ->whereMonth('created_at', Carbon::now()->month)
                                              ->sum('amount_mwh') ?? 0;

            $stats = [
                'pending_reviews' => $pendingReports->count(),
                'pending_payments' => $pendingOrders->count(),
                'verified_orders' => $verifiedOrders->count(),
                'certificate_requests' => $certificateRequests->count(),
                'rec_issued_month' => $recIssuedThisMonth,
            ];

            return view('issuer.dashboard', compact('pendingReports', 'pendingOrders', 'verifiedOrders', 'certificateRequests', 'stats'));

        } catch (\Exception $e) {
            Log::error('Dashboard query error: ' . $e->getMessage());
            
            // Fallback data jika terjadi error
            $stats = [
                'pending_reviews' => 0,
                'pending_payments' => 0,
                'verified_orders' => 0,
                'certificate_requests' => 0,
                'rec_issued_month' => 0,
            ];

            return view('issuer.dashboard', [
                'pendingReports' => collect(),
                'pendingOrders' => collect(),
                'verifiedOrders' => collect(),
                'certificateRequests' => collect(),
                'stats' => $stats,
                'error' => 'Terjadi kesalahan dalam memuat data dashboard. Silakan refresh halaman.'
            ]);
        }
    }

    /**
     * ✅ FIXED: Step 3 - Issuer verify payment AND issue certificate to blockchain
     * Ensures blockchain_cert_id is available before attempting issuance
     */
    public function verifyPayment($orderId)
    {
        try {
            $order = Order::findOrFail($orderId);

            // ✅ FIXED: Allow both CERTIFICATE_PAID and CERTIFICATE_ISSUED status
            // This handles retry case where order status is CERTIFICATE_ISSUED but certificate is still CERTIFICATE_PAID
            $allowedStatuses = ['CERTIFICATE_PAID', 'CERTIFICATE_ISSUED'];
            
            if (!in_array($order->status, $allowedStatuses)) {
                throw new \Exception('Pesanan ini tidak dapat diverifikasi. Status saat ini: ' . $order->status);
            }

            // ✅ NEW: Check if certificates are actually in CERTIFICATE_PAID status
            $certificatesNeedingVerification = $order->certificates()
                ->where('blockchain_status', 'CERTIFICATE_PAID')
                ->count();
            
            if ($certificatesNeedingVerification === 0) {
                // Check if already completed
                $completedCertificates = $order->certificates()
                    ->where('blockchain_status', 'CERTIFICATE_ISSUED')
                    ->count();
                
                if ($completedCertificates > 0) {
                    return redirect()->route('issuer.dashboard')
                        ->with('info', 'Pesanan ini sudah diverifikasi sebelumnya.');
                }
                
                throw new \Exception('Tidak ada sertifikat yang perlu diverifikasi untuk pesanan ini.');
            }

            Log::info('🔍 STEP 3: Starting payment verification and certificate issuance', [
                'order_id' => $order->id,
                'order_uid' => $order->order_uid,
                'order_status' => $order->status,
                'certificates_count' => $order->certificates->count(),
                'certificates_needing_verification' => $certificatesNeedingVerification
            ]);

            $successfulIssues = 0;
            $totalCertificates = $order->certificates->count();

            // ✅ STEP 3: Issue certificates to blockchain
            foreach ($order->certificates as $certificate) {
                // Only process certificates with CERTIFICATE_PAID status
                if ($certificate->blockchain_status !== 'CERTIFICATE_PAID') {
                    continue;
                }

                $blockchainCertId = $certificate->blockchain_cert_id;
                
                if (!$blockchainCertId) {
                    Log::warning('⚠️ Certificate has no blockchain_cert_id, skipping', [
                        'certificate_id' => $certificate->id
                    ]);
                    continue;
                }

                try {
                    Log::info('🚀 STEP 3: Issuing certificate to blockchain', [
                        'certificate_id' => $certificate->id,
                        'blockchain_cert_id' => $blockchainCertId
                    ]);

                    // Call blockchain API to issue certificate
                    $issueResponse = \Illuminate\Support\Facades\Http::timeout(30)
                        ->put('http://localhost:3000/api/certificates/issue/' . $blockchainCertId, [
                            'issuerId' => 'ISSUER001'
                        ]);

                    if ($issueResponse->successful() && $issueResponse->json('success')) {
                        // Wait for blockchain to finish
                        sleep(2);
                        
                        // Update certificate status directly without transaction
                        DB::table('certificates')
                            ->where('id', $certificate->id)
                            ->update([
                                'blockchain_status' => 'CERTIFICATE_ISSUED',
                                'blockchain_response' => json_encode([
                                    'step3_issue' => $issueResponse->json(),
                                    'issued_at' => now()->toISOString()
                                ]),
                                'updated_at' => now()
                            ]);

                        $successfulIssues++;
                        
                        Log::info('✅ STEP 3 SUCCESS: Certificate issued', [
                            'certificate_id' => $certificate->id,
                            'blockchain_cert_id' => $blockchainCertId
                        ]);
                    } else {
                        Log::error('❌ STEP 3 FAILED: API error', [
                            'certificate_id' => $certificate->id,
                            'blockchain_cert_id' => $blockchainCertId,
                            'response' => $issueResponse->body()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('❌ STEP 3 ERROR: Exception', [
                        'certificate_id' => $certificate->id,
                        'blockchain_cert_id' => $blockchainCertId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // ✅ UPDATE: Order status tanpa transaction
            DB::table('orders')
                ->where('id', $order->id)
                ->update([
                    'status' => 'CERTIFICATE_ISSUED',
                    'payment_verified_at' => now(),
                    'updated_at' => now()
                ]);

            Log::info('✅ Step 3: Payment verification completed', [
                'order_id' => $order->id,
                'successful_issues' => $successfulIssues,
                'total_certificates' => $totalCertificates
            ]);

            // ✅ NEW: After payment verification, fetch CouchDB document for each certificate
            // and update the local certificates table to match CouchDB's certificateInfo.status
            foreach ($order->certificates as $certificate) {
                try {
                    $blockchainCertId = $certificate->blockchain_cert_id ?: $certificate->certificate_uid;
                    if (!$blockchainCertId) continue;

                    $couchUrl = 'http://localhost:5984/recchannel_rec/CERTIFICATE_' . $blockchainCertId;
                    $couchRes = \Illuminate\Support\Facades\Http::withBasicAuth('admin', 'adminpw')
                        ->timeout(10)
                        ->get($couchUrl);

                    if ($couchRes->successful()) {
                        $couchData = $couchRes->json();
                        $couchStatus = $couchData['certificateInfo']['status'] ?? null;

                        if ($couchStatus) {
                            $updatePayload = [
                                'blockchain_status' => $couchStatus,
                                'blockchain_response' => json_encode($couchData),
                                'updated_at' => now()
                            ];

                            if ($couchStatus === 'COMPLETED') {
                                $updatePayload['status'] = 'completed';
                                $updatePayload['completed_at'] = now();
                            }

                            $certificate->update($updatePayload);

                            Log::info('✅ Certificate DB synced from CouchDB after payment verification', [
                                'certificate_id' => $certificate->id,
                                'blockchain_cert_id' => $blockchainCertId,
                                'new_status' => $couchStatus
                            ]);
                        }
                    } else {
                        Log::warning('CouchDB not returning document after payment verification', [
                            'certificate_id' => $certificate->id,
                            'blockchain_cert_id' => $blockchainCertId,
                            'status_code' => $couchRes->status()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to sync certificate from CouchDB after payment verification', [
                        'certificate_id' => $certificate->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Generate success message
            if ($successfulIssues === $totalCertificates && $successfulIssues > 0) {
                $message = "Pembayaran untuk pesanan #{$order->order_uid} telah diverifikasi. " .
                          "✅ {$successfulIssues}/{$totalCertificates} sertifikat berhasil diterbitkan ke blockchain. " .
                          "Buyer sekarang dapat melihat dan mengaktifkan sertifikat mereka.";
            } elseif ($successfulIssues > 0) {
                $message = "Pembayaran untuk pesanan #{$order->order_uid} telah diverifikasi. " .
                          "⚠️ {$successfulIssues}/{$totalCertificates} sertifikat berhasil diterbitkan. " .
                          "Buyer dapat melihat sertifikat yang sudah aktif.";
            } else {
                $message = "Pembayaran untuk pesanan #{$order->order_uid} telah diverifikasi, " .
                          "tapi sertifikat belum dapat diterbitkan ke blockchain.";
            }

            return redirect()->route('issuer.dashboard')->with('success', $message);

        } catch (\Throwable $e) {
            Log::error("Payment verification failed for Order ID: {$orderId}. Message: " . $e->getMessage());
            return redirect()->route('issuer.dashboard')->with('error', 'Gagal memverifikasi pembayaran. Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Manual Step 4: Issue Certificate to Blockchain
     * UPDATED: Better error handling and database persistence
     */
    private function issueCertificateManual($certificate, $order)
    {
        try {
            // ✅ ENHANCED: Check certificate status from database first
            $freshCertificate = Certificate::find($certificate->id);
            if (!$freshCertificate) {
                Log::warning('Certificate not found in database', [
                    'certificate_id' => $certificate->id
                ]);
                return;
            }

            // Check if certificate has blockchain_cert_id from Step 3
            if (!$freshCertificate->blockchain_cert_id) {
                Log::warning('⚠️ Certificate has no blockchain_cert_id, skipping issue', [
                    'certificate_id' => $freshCertificate->id,
                    'blockchain_status' => $freshCertificate->blockchain_status
                ]);
                return;
            }

            // Check if already issued
            if ($freshCertificate->blockchain_status === 'CERTIFICATE_ISSUED') {
                Log::info('✅ Certificate already issued, skipping', [
                    'certificate_id' => $freshCertificate->id,
                    'blockchain_cert_id' => $freshCertificate->blockchain_cert_id
                ]);
                return;
            }

            Log::info('🚀 Starting Step 4: Issue Certificate', [
                'certificate_id' => $freshCertificate->id,
                'blockchain_cert_id' => $freshCertificate->blockchain_cert_id,
                'order_id' => $order->id
            ]);

            // Step 4: Issue Certificate via direct API call
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->put('http://localhost:3000/api/certificates/issue/' . $freshCertificate->blockchain_cert_id, [
                    'issuerId' => 'ISSUER001'
                ]);

            if ($response->successful() && $response->json('success')) {
                // ✅ ENHANCED: Use separate transaction for database update
                DB::transaction(function () use ($freshCertificate, $response, $order) {
                    // Update certificate with Step 4 success
                    $existingResponse = $freshCertificate->blockchain_response ? json_decode($freshCertificate->blockchain_response, true) : [];
                    $existingResponse['step4_issue'] = $response->json();
                    $existingResponse['issued_at'] = now()->toISOString();

                    $updated = $freshCertificate->update([
                        'blockchain_status' => 'CERTIFICATE_ISSUED',
                        'blockchain_response' => json_encode($existingResponse),
                        'owner_id' => $order->buyer_id, // Transfer ownership to buyer
                        'status' => 'sold'
                    ]);

                    if (!$updated) {
                        throw new \Exception('Failed to update certificate in database');
                    }

                    Log::info('✅ Step 4 database update successful', [
                        'certificate_id' => $freshCertificate->id,
                        'blockchain_cert_id' => $freshCertificate->blockchain_cert_id,
                        'blockchain_status' => 'CERTIFICATE_ISSUED'
                    ]);
                });

                Log::info('✅ Step 4 SUCCESS: Certificate issue completed', [
                    'certificate_id' => $freshCertificate->id,
                    'blockchain_cert_id' => $freshCertificate->blockchain_cert_id,
                    'new_owner' => $order->buyer_id
                ]);
            } else {
                throw new \Exception('Certificate issue failed: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('❌ Step 4 FAILED: Certificate issue failed', [
                'certificate_id' => $certificate->id,
                'blockchain_cert_id' => $certificate->blockchain_cert_id ?? 'null',
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            // Mark certificate as failed but continue with database operations
            try {
                $certificate->update([
                    'blockchain_status' => 'ISSUE_FAILED',
                    'blockchain_error' => $e->getMessage(),
                    'owner_id' => $order->buyer_id, // Still transfer ownership locally
                    'status' => 'sold'
                ]);
            } catch (\Exception $dbError) {
                Log::error('Failed to update certificate error status', [
                    'certificate_id' => $certificate->id,
                    'db_error' => $dbError->getMessage()
                ]);
            }
        }
    }

    /**
     * Menolak pembayaran dengan update blockchain status
     */
    public function rejectPayment($orderId)
    {
        DB::beginTransaction();
        try {
            $order = Order::findOrFail($orderId);

            if ($order->status !== 'awaiting_confirmation') {
                throw new \Exception('Pesanan ini tidak lagi dalam status menunggu konfirmasi.');
            }

            // Update blockchain status untuk certificates
            $certificates = $order->certificates;
            foreach ($certificates as $certificate) {
                if ($certificate->blockchain_cert_id) {
                    $certificate->update([
                        'blockchain_purchase_status' => 'rejected',
                        'blockchain_reject_reason' => 'Payment rejected by issuer'
                    ]);

                    Log::info('Purchase rejected on blockchain', [
                        'certificate_id' => $certificate->id,
                        'blockchain_cert_id' => $certificate->blockchain_cert_id,
                        'order_id' => $order->id
                    ]);
                }
            }

            // Return certificates to available status
            $order->certificates()->update(['status' => 'available_for_sale', 'order_id' => null]);
            $order->status = 'cancelled';
            $order->save();

            DB::commit();

            return redirect()->route('issuer.dashboard')->with('success', 'Pembayaran untuk pesanan ' . $order->order_uid . ' telah ditolak dan sertifikat dikembalikan ke status tersedia.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Penolakan Gagal untuk Order ID: {$orderId}. Pesan: " . $e->getMessage());
            return redirect()->route('issuer.dashboard')->with('error', 'Gagal menolak transaksi. Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * ✅ NEW: Issue Certificate untuk request yang masuk dari Generator
     */
    public function issueCertificate(Request $request)
    {
        try {
            $validated = $request->validate([
                'energy_report_id' => 'required|exists:energy_reports,id',
            ]);

            $energyReport = EnergyReport::with(['powerPlant.user'])->findOrFail($validated['energy_report_id']);
            
            // Validasi status
            if ($energyReport->status !== 'approved') {
                return back()->with('error', 'Energy report harus dalam status approved.');
            }

            if (!$energyReport->certificate_requested) {
                return back()->with('error', 'Certificate belum di-request untuk energy report ini.');
            }

            if (!$energyReport->certificate_id) {
                return back()->with('error', 'Certificate ID tidak ditemukan.');
            }

            // ✅ PERBAIKAN: Cek status certificate di blockchain SEBELUM mencoba issue
            try {
                $blockchainCertificate = $this->blockchainService->getCertificateById($energyReport->certificate_id);
                
                if ($blockchainCertificate && isset($blockchainCertificate['certificateInfo']['status'])) {
                    $blockchainStatus = $blockchainCertificate['certificateInfo']['status'];
                    
                    if ($blockchainStatus === 'CERTIFICATE_ISSUED') {
                        // Certificate sudah di-issue! Update database dan redirect dengan success
                        $existingCertificate = Certificate::where('energy_report_id', $energyReport->id)
                            ->where('blockchain_cert_id', $energyReport->certificate_id)
                            ->first();
                        
                        if (!$existingCertificate) {
                            // Buat certificate record yang hilang
                            $certificate = Certificate::create([
                                'energy_report_id' => $energyReport->id,
                                'blockchain_cert_id' => $energyReport->certificate_id,
                                'amount_mwh' => $energyReport->amount_mwh,
                                'issue_date' => now(),
                                'issuer_id' => auth()->id(),
                                'owner_id' => $energyReport->powerPlant->user_id,
                                'certificate_uid' => $energyReport->certificate_id,
                                'generation_start_date' => $energyReport->period_start,
                                'generation_end_date' => $energyReport->period_end,
                                'status' => 'available_for_sale',
                                'blockchain_status' => 'CERTIFICATE_ISSUED',
                                'blockchain_response' => json_encode($blockchainCertificate),
                            ]);
                            
                            Log::info('Certificate record created for already issued blockchain certificate', [
                                'certificate_id' => $certificate->id,
                                'blockchain_cert_id' => $energyReport->certificate_id
                            ]);
                        }
                        
                        // Update energy report
                        $energyReport->update([
                            'certificate_status' => 'CERTIFICATE_ISSUED',
                            'certificate_response' => json_encode($blockchainCertificate),
                        ]);
                        
                        $issuedAt = $blockchainCertificate['lifecycle']['issuedAt'] ?? 'N/A';
                        $issuedBy = $blockchainCertificate['lifecycle']['issuedBy'] ?? 'N/A';
                        
                        return back()->with('success', 
                            'Certificate sudah berhasil di-issue sebelumnya! ' .
                            'Certificate ID: ' . $energyReport->certificate_id . 
                            ' untuk ' . number_format($energyReport->amount_mwh, 2, ',', '.') . ' MWh. ' .
                            'Issued by: ' . $issuedBy . ' at ' . $issuedAt);
                    } elseif ($blockchainStatus !== 'CERTIFICATE_REQUESTED') {
                        return back()->with('error', 
                            'Certificate tidak dalam status yang tepat untuk di-issue. ' .
                            'Status saat ini: ' . $blockchainStatus);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Could not check blockchain certificate status', [
                    'certificate_id' => $energyReport->certificate_id,
                    'error' => $e->getMessage()
                ]);
                // Lanjutkan dengan proses issue normal
            }

            // Cek apakah certificate sudah pernah di-issue dengan benar di database
            $existingCertificate = Certificate::where('energy_report_id', $energyReport->id)
                ->where('blockchain_status', 'CERTIFICATE_ISSUED')
                ->first();
            
            if ($existingCertificate) {
                return back()->with('error', 'Certificate sudah pernah di-issue untuk energy report ini.');
            }

            // Hapus certificate yang tidak valid (tanpa blockchain_cert_id)
            Certificate::where('energy_report_id', $energyReport->id)
                ->whereNull('blockchain_cert_id')
                ->delete();

            // Generate issuer ID
            $issuerId = 'ISSUER_' . auth()->id();

            Log::info('Issuer issuing certificate', [
                'certificate_id' => $energyReport->certificate_id,
                'energy_report_id' => $energyReport->id,
                'issuer_id' => $issuerId,
                'amount_mwh' => $energyReport->amount_mwh
            ]);

            // Step 4: Issue Certificate di blockchain
            $blockchainResult = $this->blockchainService->issueCertificate(
                $energyReport->certificate_id,
                $issuerId
            );

            if ($blockchainResult && isset($blockchainResult['success']) && $blockchainResult['success']) {
                // Buat certificate record di database
                $certificate = Certificate::create([
                    'energy_report_id' => $energyReport->id,
                    'blockchain_cert_id' => $energyReport->certificate_id,
                    'amount_mwh' => $energyReport->amount_mwh,
                    'issue_date' => now(),
                    'issuer_id' => auth()->id(),
                    'owner_id' => $energyReport->powerPlant->user_id, // Generator yang memiliki certificate
                    'certificate_uid' => $energyReport->certificate_id,
                    'generation_start_date' => $energyReport->period_start,
                    'generation_end_date' => $energyReport->period_end,
                    'status' => 'available_for_sale',
                    'blockchain_status' => 'CERTIFICATE_ISSUED',
                    'blockchain_response' => json_encode($blockchainResult),
                ]);

                // Update energy report
                $energyReport->update([
                    'certificate_status' => 'CERTIFICATE_ISSUED',
                    'certificate_response' => json_encode($blockchainResult),
                ]);

                Log::info('SUCCESS: Certificate issued successfully', [
                    'certificate_id' => $certificate->id,
                    'blockchain_cert_id' => $energyReport->certificate_id,
                    'energy_report_id' => $energyReport->id,
                    'blockchain_result' => $blockchainResult
                ]);

                return back()->with('success', 
                    'SUCCESS! Certificate berhasil di-issue! Certificate ID: ' . $energyReport->certificate_id . 
                    ' untuk ' . number_format($energyReport->amount_mwh, 2, ',', '.') . ' MWh');
            } else {
                throw new \Exception('Blockchain returned failure: ' . json_encode($blockchainResult));
            }

        } catch (\Exception $e) {
            Log::error('FAILED: Issue certificate error', [
                'energy_report_id' => $validated['energy_report_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // ✅ PERBAIKAN: Berikan pesan error yang lebih informatif
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'must be CERTIFICATE_REQUESTED before issuance') !== false) {
                $errorMessage = 'Certificate mungkin sudah pernah di-issue sebelumnya. Silakan refresh halaman dan cek status terbaru.';
            }

            return back()->with('error', 'GAGAL issue certificate: ' . $errorMessage);
        }
    }

    /**
     * ✅ NEW: Reject Certificate Request
     */
    public function rejectCertificateRequest(Request $request)
    {
        try {
            $validated = $request->validate([
                'energy_report_id' => 'required|exists:energy_reports,id',
                'rejection_reason' => 'required|string|max:500'
            ]);

            $energyReport = EnergyReport::findOrFail($validated['energy_report_id']);
            
            // Update energy report dengan status rejected
            $energyReport->update([
                'certificate_requested' => false,
                'certificate_status' => 'CERTIFICATE_REJECTED',
                'certificate_response' => json_encode([
                    'rejected_by' => auth()->id(),
                    'rejected_at' => now(),
                    'rejection_reason' => $validated['rejection_reason']
                ]),
            ]);

            Log::info('Certificate request rejected', [
                'energy_report_id' => $energyReport->id,
                'rejected_by' => auth()->id(),
                'reason' => $validated['rejection_reason']
            ]);

            return back()->with('success', 'Certificate request berhasil ditolak.');

        } catch (\Exception $e) {
            Log::error('Failed to reject certificate request', [
                'energy_report_id' => $validated['energy_report_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menolak certificate request: ' . $e->getMessage());
        }
    }

    /**
     * Show energy report details for verification
     */
    public function showEnergyReport($id)
    {
        try {
            $report = EnergyReport::with(['powerPlant.user'])
                ->findOrFail($id);
            
            return view('issuer.energy-report-detail', compact('report'));
        } catch (\Exception $e) {
            Log::error('Failed to load energy report details', [
                'report_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('issuer.dashboard')
                ->with('error', 'Gagal memuat detail energy report.');
        }
    }

    /**
     * Verify and approve energy report
     */
    public function verifyEnergyReport(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'action' => 'required|in:approve,reject',
                'notes' => 'nullable|string|max:500'
            ]);

            $report = EnergyReport::findOrFail($id);

            if ($report->status !== 'pending_verification') {
                return back()->with('error', 'Energy report ini tidak dalam status pending verification.');
            }

            $newStatus = $validated['action'] === 'approve' ? 'approved' : 'rejected';
            
            $report->update([
                'status' => $newStatus,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'verification_notes' => $validated['notes']
            ]);

            $message = $validated['action'] === 'approve' 
                ? 'Energy report berhasil disetujui.'
                : 'Energy report berhasil ditolak.';

            Log::info('Energy report verification completed', [
                'report_id' => $report->id,
                'action' => $validated['action'],
                'verified_by' => auth()->id()
            ]);

            return redirect()->route('issuer.dashboard')->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Failed to verify energy report', [
                'report_id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal memverifikasi energy report: ' . $e->getMessage());
        }
    }

    /**
     * ✅ NEW: Get certificate status from CouchDB for verification
     */
    private function getCertificateStatusFromCouchDB($blockchainCertId)
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withBasicAuth('admin', 'adminpw')
                ->get("http://localhost:5984/recchannel_rec/CERTIFICATE_{$blockchainCertId}");

            if ($response->successful()) {
                $certificate = $response->json();
                return $certificate['certificateInfo']['status'] ?? null;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get certificate status from CouchDB', [
                'blockchain_cert_id' => $blockchainCertId,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
}