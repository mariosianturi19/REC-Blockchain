<?php

namespace App\Http\Controllers;

use App\Services\BlockchainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlockchainController extends Controller
{
    protected $blockchain;

    public function __construct(BlockchainService $blockchain)
    {
        $this->blockchain = $blockchain;
    }

    /**
     * Dashboard for Blockchain Operations
     */
    public function dashboard()
    {
        $healthCheck = $this->blockchain->healthCheck();
        
        return view('blockchain.dashboard', [
            'health' => $healthCheck,
            'title' => 'REC Blockchain Dashboard'
        ]);
    }

    /**
     * Step 1: Submit Energy Data Form
     */
    public function submitEnergyForm()
    {
        return view('blockchain.submit-energy', [
            'title' => 'Submit Energy Data to Blockchain'
        ]);
    }

    /**
     * Step 1: Process Energy Data Submission
     */
    public function submitEnergyData(Request $request)
    {
        $request->validate([
            'amount_kwh' => 'required|numeric|min:1',
            'source_type' => 'required|string|in:Solar,Wind,Hydro,Biomass',
            'location' => 'required|string|max:255',
            'timestamp' => 'required|date'
        ]);

        try {
            $energyId = 'ENERGY-' . now()->format('YmdHis') . '-' . Str::random(4);
            $generatorId = config('app.default_generator_id');

            $result = $this->blockchain->submitEnergyData(
                $energyId,
                $request->amount_kwh,
                $request->source_type,
                $request->timestamp,
                $request->location,
                $generatorId
            );

            return redirect()->route('blockchain.verify-energy-form')
                ->with('success', 'Energy data submitted successfully to blockchain!')
                ->with('energy_id', $energyId)
                ->with('blockchain_result', $result);

        } catch (\Exception $e) {
            Log::error('Submit Energy Data Failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to submit energy data: ' . $e->getMessage()]);
        }
    }

    /**
     * Step 2: Verify Energy Data Form
     */
    public function verifyEnergyForm()
    {
        return view('blockchain.verify-energy', [
            'title' => 'Verify Energy Data'
        ]);
    }

    /**
     * Step 2: Process Energy Data Verification
     */
    public function verifyEnergyData(Request $request)
    {
        $request->validate([
            'energy_id' => 'required|string'
        ]);

        try {
            $issuerId = config('app.default_issuer_id');

            $result = $this->blockchain->verifyEnergyData(
                $request->energy_id,
                $issuerId
            );

            return redirect()->route('blockchain.request-certificate-form')
                ->with('success', 'Energy data verified successfully!')
                ->with('energy_id', $request->energy_id)
                ->with('blockchain_result', $result);

        } catch (\Exception $e) {
            Log::error('Verify Energy Data Failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to verify energy data: ' . $e->getMessage()]);
        }
    }

    /**
     * Step 3: Request Certificate Form
     */
    public function requestCertificateForm()
    {
        return view('blockchain.request-certificate', [
            'title' => 'Request REC Certificate'
        ]);
    }

    /**
     * Step 3: Process Certificate Request
     */
    public function requestCertificate(Request $request)
    {
        $request->validate([
            'energy_id' => 'required|string'
        ]);

        try {
            $certId = 'CERT-' . $request->energy_id;
            $generatorId = config('app.default_generator_id');

            $result = $this->blockchain->requestCertificate(
                $certId,
                $request->energy_id,
                $generatorId
            );

            return redirect()->route('blockchain.issue-certificate-form')
                ->with('success', 'Certificate request submitted successfully!')
                ->with('energy_id', $request->energy_id)
                ->with('cert_id', $certId)
                ->with('blockchain_result', $result);

        } catch (\Exception $e) {
            Log::error('Request Certificate Failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to request certificate: ' . $e->getMessage()]);
        }
    }

    /**
     * Step 4: Issue Certificate Form
     */
    public function issueCertificateForm()
    {
        return view('blockchain.issue-certificate', [
            'title' => 'Issue REC Certificate'
        ]);
    }

    /**
     * Step 4: Process Certificate Issuance
     */
    public function issueCertificate(Request $request)
    {
        $request->validate([
            'cert_id' => 'required|string'
        ]);

        try {
            $issuerId = config('app.default_issuer_id');

            $result = $this->blockchain->issueCertificate(
                $request->cert_id,
                $issuerId
            );

            return redirect()->route('blockchain.complete-certificate-form')
                ->with('success', 'Certificate issued successfully!')
                ->with('cert_id', $request->cert_id)
                ->with('blockchain_result', $result);

        } catch (\Exception $e) {
            Log::error('Issue Certificate Failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to issue certificate: ' . $e->getMessage()]);
        }
    }

    /**
     * Step 5: Complete Certificate Form
     */
    public function completeCertificateForm()
    {
        return view('blockchain.complete-certificate', [
            'title' => 'Complete REC Certificate'
        ]);
    }

    /**
     * Step 5: Process Certificate Completion
     */
    public function completeCertificate(Request $request)
    {
        $request->validate([
            'cert_id' => 'required|string'
        ]);

        try {
            // ✅ FIXED: Use buyerId instead of generatorId for Step 5
            $buyerId = config('app.default_buyer_id', 'buyer1');

            $result = $this->blockchain->completeCertificate(
                $request->cert_id,
                $buyerId  // ✅ FIXED: Changed from $generatorId to $buyerId
            );

            return redirect()->route('blockchain.workflow-complete')
                ->with('success', 'Certificate completed successfully!')
                ->with('cert_id', $request->cert_id)
                ->with('blockchain_result', $result);

        } catch (\Exception $e) {
            Log::error('Complete Certificate Failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to complete certificate: ' . $e->getMessage()]);
        }
    }

    /**
     * Complete Workflow (All 5 Steps)
     */
    public function completeWorkflowForm()
    {
        return view('blockchain.complete-workflow', [
            'title' => 'Complete 5-Step REC Workflow'
        ]);
    }

    /**
     * Execute Complete Workflow
     */
    public function executeCompleteWorkflow(Request $request)
    {
        $request->validate([
            'amount_kwh' => 'required|numeric|min:1',
            'source_type' => 'required|string|in:Solar,Wind,Hydro,Biomass',
            'location' => 'required|string|max:255',
            'timestamp' => 'required|date'
        ]);

        try {
            $energyId = 'ENERGY-' . now()->format('YmdHis') . '-' . Str::random(4);
            $generatorId = config('app.default_generator_id');
            $issuerId = config('app.default_issuer_id');

            $result = $this->blockchain->executeCompleteWorkflow(
                $energyId,
                $request->amount_kwh,
                $request->source_type,
                $request->timestamp,
                $request->location,
                $generatorId,
                $issuerId
            );

            if ($result['success']) {
                return view('blockchain.workflow-complete', [
                    'title' => 'Workflow Completed Successfully',
                    'result' => $result,
                    'energy_id' => $energyId,
                    'cert_id' => $result['cert_id']
                ]);
            } else {
                return back()->withErrors(['error' => $result['message']]);
            }

        } catch (\Exception $e) {
            Log::error('Complete Workflow Failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to execute workflow: ' . $e->getMessage()]);
        }
    }

    /**
     * Workflow Complete Success Page
     */
    public function workflowComplete()
    {
        return view('blockchain.workflow-complete', [
            'title' => '🎉 REC Certificate Workflow Completed!'
        ]);
    }

    /**
     * View Energy Data
     */
    public function viewEnergyData(Request $request)
    {
        try {
            $energyId = $request->query('energy_id');
            
            if ($energyId) {
                $energyData = $this->blockchain->getEnergyData($energyId);
                return view('blockchain.view-energy', [
                    'title' => 'Energy Data Details',
                    'energyData' => $energyData,
                    'energyId' => $energyId
                ]);
            }

            $allEnergyData = $this->blockchain->getAllEnergyData();
            return view('blockchain.view-energy', [
                'title' => 'All Energy Data',
                'allEnergyData' => $allEnergyData
            ]);

        } catch (\Exception $e) {
            Log::error('View Energy Data Failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to retrieve energy data: ' . $e->getMessage()]);
        }
    }

    /**
     * View Certificate
     * UPDATED: Automatically run Step 5 when buyer views certificate
     */
    public function viewCertificate(Request $request)
    {
        try {
            $certId = $request->query('cert_id');
            
            if ($certId) {
                // First, get the current certificate data
                $certificate = $this->blockchain->getCertificate($certId);
                
                // 🎯 AUTO-RUN Step 5 if certificate status is CERTIFICATE_ISSUED
                if ($certificate['success'] && isset($certificate['data'])) {
                    $certData = $certificate['data'];
                    
                    // Check if certificate needs completion (Step 5)
                    if (isset($certData['status']) && $certData['status'] === 'CERTIFICATE_ISSUED') {
                        Log::info('🚀 Auto-running Step 5: Complete Certificate for buyer view', [
                            'cert_id' => $certId,
                            'current_status' => $certData['status']
                        ]);
                        
                        try {
                            // ✅ Get the correct buyer ID from authenticated user or certificate data
                            $buyerId = null;
                            
                            // First try to get from authenticated user
                            if (auth()->check() && auth()->user()->role === 'buyer') {
                                $buyerId = auth()->user()->id;
                                Log::info('✅ Using authenticated buyer ID', ['buyer_id' => $buyerId]);
                            }
                            // Then try certificate data
                            elseif (isset($certData['buyerId'])) {
                                $buyerId = $certData['buyerId'];
                                Log::info('✅ Using buyer ID from certificate data', ['buyer_id' => $buyerId]);
                            }
                            // Then try owner field
                            elseif (isset($certData['owner'])) {
                                $buyerId = $certData['owner'];
                                Log::info('✅ Using owner ID as buyer ID', ['buyer_id' => $buyerId]);
                            }
                            // Fallback to config
                            else {
                                $buyerId = config('app.default_buyer_id', 'buyer1');
                                Log::info('✅ Using fallback buyer ID from config', ['buyer_id' => $buyerId]);
                            }
                            
                            Log::info('✅ Running Step 5 - Complete Certificate', [
                                'cert_id' => $certId,
                                'buyer_id' => $buyerId,
                                'current_status' => $certData['status']
                            ]);
                            
                            $completionResult = $this->blockchain->completeCertificate($certId, $buyerId);
                            
                            if ($completionResult['success']) {
                                Log::info('✅ Step 5 completed successfully - Certificate status now COMPLETED', [
                                    'cert_id' => $certId,
                                    'buyer_id' => $buyerId,
                                    'new_status' => 'COMPLETED'
                                ]);
                                
                                // Get updated certificate data after completion
                                $certificate = $this->blockchain->getCertificate($certId);
                                
                                // Add success message to show user
                                session()->flash('success', '🎉 Certificate has been completed! Status updated to COMPLETED.');
                            } else {
                                Log::warning('⚠️ Step 5 completion failed during certificate view', [
                                    'cert_id' => $certId,
                                    'buyer_id' => $buyerId,
                                    'error' => $completionResult['message'] ?? 'Unknown error'
                                ]);
                                
                                session()->flash('warning', 'Certificate retrieved but completion failed: ' . ($completionResult['message'] ?? 'Unknown error'));
                            }
                        } catch (\Exception $e) {
                            Log::error('❌ Step 5 completion error during certificate view', [
                                'cert_id' => $certId,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            
                            session()->flash('error', 'Certificate retrieved but auto-completion failed: ' . $e->getMessage());
                        }
                    } elseif (isset($certData['status']) && $certData['status'] === 'COMPLETED') {
                        Log::info('ℹ️ Certificate already completed', [
                            'cert_id' => $certId,
                            'status' => $certData['status']
                        ]);
                        
                        session()->flash('info', '✅ Certificate is already completed.');
                    } else {
                        Log::info('ℹ️ Certificate not ready for completion', [
                            'cert_id' => $certId,
                            'current_status' => $certData['status'] ?? 'unknown'
                        ]);
                    }
                }
                
                // Di dalam method viewCertificate
                if (isset($certData['status']) && $certData['status'] === 'CERTIFICATE_ISSUED') {
                    try {
                        // Get buyer ID from order or certificate data
                        $buyerId = $certificate->order->buyer->name ?? 
                                  $certificate->order->buyer->profile->company_name ??
                                  'Buyer' . $certificate->order->buyer_id;

                        Log::info('🚀 Auto-completing certificate on view', [
                            'cert_id' => $certId,
                            'buyer_id' => $buyerId
                        ]);
                        
                        $response = Http::timeout(30)->put('http://localhost:3000/api/certificates/complete/' . $certId, [
                            'buyerId' => $buyerId
                        ]);

                        if ($response->successful()) {
                            DB::transaction(function() use ($certificate, $response) {
                                $certificate->update([
                                    'blockchain_status' => 'COMPLETED',
                                    'blockchain_response' => json_encode($response->json()),
                                    'updated_at' => now()
                                ]);

                                // Update order status juga
                                if ($certificate->order) {
                                    $certificate->order->update([
                                        'status' => 'completed',
                                        'completed_at' => now()
                                    ]);
                                }
                            });

                            session()->flash('success', '🎉 Sertifikat berhasil diaktifkan! Status: COMPLETED');
                            Log::info('✅ Certificate auto-completed on view', [
                                'cert_id' => $certId,
                                'status' => 'COMPLETED'
                            ]);
                        } else {
                            Log::warning('⚠️ Auto-completion failed', [
                                'cert_id' => $certId,
                                'response' => $response->body()
                            ]);
                            session()->flash('warning', 'Sertifikat sudah dapat dilihat, tapi gagal mengaktifkan secara otomatis.');
                        }
                    } catch (\Exception $e) {
                        Log::error('❌ Error in auto-completion:', [
                            'cert_id' => $certId,
                            'error' => $e->getMessage()
                        ]);
                        session()->flash('error', 'Terjadi kesalahan saat mengaktifkan sertifikat: ' . $e->getMessage());
                    }
                }

                return view('blockchain.view-certificate', [
                    'title' => 'Certificate Details',
                    'certificate' => $certificate,
                    'certId' => $certId
                ]);
            }

            return view('blockchain.view-certificate', [
                'title' => 'Certificate Lookup',
                'searchForm' => true
            ]);

        } catch (\Exception $e) {
            Log::error('View Certificate Failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to retrieve certificate: ' . $e->getMessage()]);
        }
    }

    /**
     * ✅ NEW: View certificate details for buyer with order_id
     * Shows detailed certificate info: hash, timestamp, blockchain data, etc.
     */
    public function viewCertificateByOrder(Request $request)
    {
        try {
            $orderId = $request->query('order_id');
            
            if (!$orderId) {
                return back()->withErrors(['error' => 'Order ID is required']);
            }

            // Get order with certificates
            $order = \App\Models\Order::with(['certificates', 'buyer.company'])
                ->findOrFail($orderId);

            // Check if buyer owns this order
            if (auth()->check() && auth()->user()->role === 'buyer') {
                if ($order->buyer_id !== auth()->id()) {
                    abort(403, 'Unauthorized access to certificate');
                }
            }

            // Get all certificates for this order with blockchain data
            $certificatesData = [];
            
            foreach ($order->certificates as $certificate) {
                if (!$certificate->blockchain_cert_id) {
                    continue;
                }

                try {
                    // Fetch from CouchDB untuk data blockchain terbaru
                    $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_' . $certificate->blockchain_cert_id;
                    $couchResponse = @file_get_contents($couchUrl);
                    
                    $blockchainData = null;
                    if ($couchResponse !== false) {
                        $blockchainData = json_decode($couchResponse, true);
                    }

                    $certificatesData[] = [
                        'certificate' => $certificate,
                        'blockchain_data' => $blockchainData,
                        'order' => $order
                    ];

                } catch (\Exception $e) {
                    Log::warning('Failed to fetch blockchain data for certificate', [
                        'certificate_id' => $certificate->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return view('buyer.certificate-detail', [
                'title' => 'Certificate Details - Order #' . $order->order_uid,
                'order' => $order,
                'certificatesData' => $certificatesData
            ]);

        } catch (\Exception $e) {
            Log::error('View Certificate By Order Failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to retrieve certificate: ' . $e->getMessage()]);
        }
    }

    /**
     * API Health Check
     */
    public function healthCheck()
    {
        $health = $this->blockchain->healthCheck();
        return response()->json($health);
    }
}