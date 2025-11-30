<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\BlockchainTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RecTrackingController extends Controller
{
    protected $blockchainService;

    public function __construct(BlockchainTrackingService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }

    /**
     * Menangani permintaan pencarian dari form (tradisional/fallback).
     * ✅ FIXED: Redirect ke view-certificate-order dengan order_id numerik
     */
    public function track(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string|exists:orders,order_uid',
        ]);

        $order = Order::where('order_uid', $request->order_id)
                      ->where('category', 'Enterprise')
                      ->first();

        if (!$order) {
            return redirect()->route('welcome')->with('error', 'Order ID tidak ditemukan atau bukan merupakan pembelian kategori Enterprise.');
        }

        // ✅ NEW: Redirect ke halaman view-certificate-order dengan order_id numerik
        return redirect()->route('view-certificate-order', ['order_id' => $order->id]);
    }

    /**
     * ==========================================================
     * METODE BARU UNTUK MENANGANI PERMINTAAN AJAX DENGAN BLOCKCHAIN
     * ==========================================================
     */
    public function ajaxTrack(Request $request)
    {
        // Validasi input secara manual untuk kontrol response JSON
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }
        
        // Cari order berdasarkan order_uid dan kategori
        $order = Order::where('order_uid', $request->order_id)
                      ->where('category', 'Enterprise')
                      ->first();

        // Jika order tidak ditemukan, kirim response error
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order ID tidak ditemukan atau bukan merupakan pembelian kategori Enterprise.',
            ], 404);
        }

        // **INTEGRASI BLOCKCHAIN: Ambil data dari blockchain**
        $blockchainData = null;
        $blockchainVerified = false;
        
        try {
            Log::info("Attempting to fetch blockchain data for order: {$request->order_id}");
            
            $blockchainData = $this->blockchainService->getPublicRECData($request->order_id);
            
            if ($blockchainData) {
                $blockchainVerified = true;
                
                // Verifikasi konsistensi data
                $order->load(['buyer.company']);
                $databaseData = [
                    'order_uid' => $order->order_uid,
                    'total_amount' => $order->certificates->sum('amount_mwh'),
                    'company_name' => $order->buyer->company->name ?? 'Unknown'
                ];
                
                $consistencyCheck = $this->blockchainService->verifyRECConsistency(
                    $request->order_id, 
                    $databaseData
                );
                
                Log::info("Blockchain data verification completed", [
                    'order_id' => $request->order_id,
                    'consistent' => $consistencyCheck['consistent']
                ]);
                
                $formattedBlockchainData = $this->blockchainService->formatBlockchainDataForDisplay($blockchainData);
                
                // ✅ FIXED: Redirect ke view-certificate-order dengan order_id numerik
                return response()->json([
                    'success' => true,
                    'redirect_url' => route('view-certificate-order', ['order_id' => $order->id]),
                    'blockchain_verified' => true,
                    'blockchain_data' => $formattedBlockchainData,
                    'consistency_check' => $consistencyCheck,
                    'message' => 'REC berhasil diverifikasi melalui blockchain'
                ]);
            }
            
        } catch (\Exception $e) {
            Log::warning('Blockchain verification failed, falling back to database', [
                'order_id' => $request->order_id,
                'error' => $e->getMessage()
            ]);
        }

        // ✅ FIXED: Fallback redirect juga ke view-certificate-order
        return response()->json([
            'success' => true,
            'redirect_url' => route('view-certificate-order', ['order_id' => $order->id]),
            'blockchain_verified' => false,
            'message' => 'REC ditemukan (verifikasi blockchain tidak tersedia)'
        ]);
    }

    /**
     * Menampilkan detail REC dari order yang valid dengan data blockchain
     */
    public function show(Order $order)
    {
        if ($order->category !== 'Enterprise') {
            abort(404, 'Order tidak ditemukan.');
        }
        
        $order->load(['buyer.company', 'certificates.energyReport.powerPlant']);
        $totalMwh = $order->certificates->sum('amount_mwh');

        // **AMBIL DATA BLOCKCHAIN UNTUK DITAMPILKAN**
        $blockchainData = null;
        $blockchainHistory = null;
        $blockchainVerified = false;
        
        try {
            $blockchainData = $this->blockchainService->getPublicRECData($order->order_uid);
            
            if ($blockchainData) {
                $blockchainVerified = true;
                $blockchainData = $this->blockchainService->formatBlockchainDataForDisplay($blockchainData);
                
                // Ambil history juga
                $blockchainHistory = $this->blockchainService->getRECHistory($order->order_uid);
            }
            
        } catch (\Exception $e) {
            Log::warning('Failed to fetch blockchain data for display', [
                'order_id' => $order->order_uid,
                'error' => $e->getMessage()
            ]);
        }

        return view('rec-detail', compact(
            'order', 
            'totalMwh', 
            'blockchainData', 
            'blockchainHistory', 
            'blockchainVerified'
        ));
    }

    /**
     * **ENDPOINT BARU: Get blockchain status untuk order tertentu**
     */
    public function getBlockchainStatus(Request $request, $orderId)
    {
        try {
            $healthStatus = $this->blockchainService->checkHealthStatus();
            $blockchainData = $this->blockchainService->getPublicRECData($orderId);
            
            return response()->json([
                'success' => true,
                'health_status' => $healthStatus,
                'data_available' => $blockchainData !== null,
                'blockchain_data' => $blockchainData ? $this->blockchainService->formatBlockchainDataForDisplay($blockchainData) : null
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check blockchain status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}