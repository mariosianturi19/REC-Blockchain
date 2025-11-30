<?php

namespace App\Http\Controllers;

use App\Services\BlockchainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Certificate as CertificateModel;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

class CertificateController extends Controller
{
    protected $blockchainService;

    public function __construct(BlockchainService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }

    /**
     * Step 3: Request Certificate (Generator)
     */
    public function request(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'energy_data_id' => 'required|string|max:255',
            'generator_id' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate certificate ID
            $certId = $this->blockchainService->generateId('certificate');
            if (!$certId) {
                $certId = 'SERTIFIKAT-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            }

            $generatorId = $request->generator_id ?? config('app.default_generator_id');

            $result = $this->blockchainService->requestCertificate(
                $certId,
                $request->energy_data_id,
                $generatorId
            );

            return response()->json([
                'success' => true,
                'message' => 'Permintaan sertifikat berhasil dibuat',
                'data' => [
                    'certificate_id' => $certId,
                    'energy_data_id' => $request->energy_data_id,
                    'blockchain_response' => $result
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Certificate request failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat permintaan sertifikat',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 4: Issue Certificate (Issuer)
     */
    public function issue(Request $request, $certId)
    {
        $validator = Validator::make($request->all(), [
            'issuer_id' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $issuerId = $request->issuer_id ?? config('app.default_issuer_id');

            $result = $this->blockchainService->issueCertificate($certId, $issuerId);

            return response()->json([
                'success' => true,
                'message' => 'Sertifikat berhasil diterbitkan',
                'data' => [
                    'certificate_id' => $certId,
                    'blockchain_response' => $result
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate issuance failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menerbitkan sertifikat',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 5: Create Purchase Request (Buyer)
     */
    public function purchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'certificate_id' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1',
            'buyer_id' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $buyerId = $request->buyer_id ?? config('app.default_buyer_id');

            $result = $this->blockchainService->createPurchaseRequest(
                $request->certificate_id,
                $buyerId,
                $request->amount
            );

            return response()->json([
                'success' => true,
                'message' => 'Permintaan pembelian berhasil dibuat',
                'data' => [
                    'certificate_id' => $request->certificate_id,
                    'buyer_id' => $buyerId,
                    'amount' => $request->amount,
                    'blockchain_response' => $result
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Purchase request failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat permintaan pembelian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 6: Confirm Purchase (Issuer)
     */
    public function confirm(Request $request, $certId)
    {
        try {
            $result = $this->blockchainService->confirmPurchase($certId);

            return response()->json([
                'success' => true,
                'message' => 'Pembelian berhasil dikonfirmasi',
                'data' => [
                    'certificate_id' => $certId,
                    'blockchain_response' => $result
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Purchase confirmation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengkonfirmasi pembelian',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Certificate Details
     */
    public function show($certId)
    {
        try {
            $result = $this->blockchainService->getCertificate($certId);

            return response()->json([
                'success' => true,
                'data' => $result['data'] ?? []
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get certificate: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data sertifikat',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get All Purchased Certificates
     */
    public function index()
    {
        try {
            $result = $this->blockchainService->getCertificate();

            return response()->json([
                'success' => true,
                'data' => $result['data'] ?? []
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get certificates: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data sertifikat',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download a nicely formatted PDF for a certificate.
     * Falls back with an instructive error if a PDF generator is not installed.
     */
    public function downloadPdf($certId)
    {
        // Redirect-friendly: if not authenticated, send to login
        if (!Auth::check()) {
            return redirect()->guest(route('login'));
        }

        // Find certificate by blockchain_cert_id or internal uid
        $certificate = CertificateModel::where('blockchain_cert_id', $certId)
            ->orWhere('certificate_uid', $certId)
            ->with(['owner', 'issuer', 'order', 'order.buyer', 'energyReport'])
            ->first();

        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate not found in local database'
            ], 404);
        }

        // Authorization: only the buyer recorded on the order can download (strict)
        $user = Auth::user();
        if (!($certificate->order && $certificate->order->buyer && $user->id === $certificate->order->buyer->id)) {
            abort(403, 'Unauthorized to download this certificate');
        }

        try {
            // Prepare data for the PDF view
            $blockchainResponse = null;
            if (!empty($certificate->blockchain_response)) {
                $blockchainResponse = json_decode($certificate->blockchain_response, true);
            }

            $certInfo = $blockchainResponse['certificateInfo'] ?? [];

            // Prefer to show the buyer as owner in the PDF
            $buyer = $certificate->order->buyer ?? null;

            $data = [
                'certificate' => $certificate,
                'certInfo' => $certInfo,
                'owner' => $buyer,
                'issuer' => $certificate->issuer,
                'order' => $certificate->order
            ];

            // Use DOMPDF if available (barryvdh/laravel-dompdf)
            if (class_exists('\\Barryvdh\\DomPDF\\Facade') || class_exists('PDF') || class_exists('Dompdf\\Dompdf')) {
                try {
                    $pdf = \PDF::loadView('pdf.certificate', $data)->setPaper('a4', 'portrait');
                    $fileName = 'REC_' . ($certificate->blockchain_cert_id ?? $certificate->certificate_uid) . '.pdf';
                    return $pdf->download($fileName);
                } catch (\Exception $e) {
                    Log::error('PDF generation failed: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'PDF generation failed',
                        'error' => $e->getMessage()
                    ], 500);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'PDF generator not installed. Please install barryvdh/laravel-dompdf via composer and publish the provider.',
                'install' => 'composer require barryvdh/laravel-dompdf'
            ], 501);

        } catch (\Exception $e) {
            Log::error('Download PDF failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}