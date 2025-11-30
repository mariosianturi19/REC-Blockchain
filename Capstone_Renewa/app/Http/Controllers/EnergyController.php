<?php

namespace App\Http\Controllers;

use App\Services\BlockchainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EnergyController extends Controller
{
    protected $blockchainService;

    public function __construct(BlockchainService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }

    /**
     * Step 1: Submit Energy Data (Generator)
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|string|max:255',
            'amount_kwh' => 'required|numeric|min:1',
            'source_type' => 'required|string|max:100',
            'timestamp' => 'required|string',
            'location' => 'required|string|max:255',
            'generatorId' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $generatorId = $request->generatorId ?? config('app.default_generator_id');

            $result = $this->blockchainService->submitEnergyData(
                $request->id,
                $request->amount_kwh,
                $request->source_type,
                $request->timestamp,
                $request->location,
                $generatorId
            );

            return response()->json([
                'success' => true,
                'message' => 'Data energi berhasil dikirim ke blockchain',
                'data' => [
                    'energy_data_id' => $request->id,
                    'blockchain_response' => $result
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Energy submission failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim data energi ke blockchain',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Step 2: Verify Energy Data (Issuer)
     */
    public function verify(Request $request, $energyDataId)
    {
        $validator = Validator::make($request->all(), [
            'issuerId' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $issuerId = $request->issuerId ?? config('app.default_issuer_id');
            
            $result = $this->blockchainService->verifyEnergyData($energyDataId, $issuerId);

            return response()->json([
                'success' => true,
                'message' => 'Data energi berhasil diverifikasi',
                'data' => [
                    'energy_data_id' => $energyDataId,
                    'blockchain_response' => $result
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Energy verification failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memverifikasi data energi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Energy Data
     */
    public function show($energyDataId = null)
    {
        try {
            $result = $this->blockchainService->getEnergyData($energyDataId);

            return response()->json([
                'success' => true,
                'data' => $result['data'] ?? []
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get energy data: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data energi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get All Energy Data
     */
    public function index()
    {
        return $this->show();
    }

    /**
     * Health Check
     */
    public function health()
    {
        $isHealthy = $this->blockchainService->healthCheck();
        
        return response()->json([
            'success' => $isHealthy,
            'message' => $isHealthy ? 'Blockchain API is healthy' : 'Blockchain API is down',
            'timestamp' => now()
        ], $isHealthy ? 200 : 503);
    }
}