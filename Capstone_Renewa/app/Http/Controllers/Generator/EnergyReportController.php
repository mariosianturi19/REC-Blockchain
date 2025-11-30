<?php

namespace App\Http\Controllers\Generator;

use App\Http\Controllers\Controller;
use App\Models\EnergyReport;
use App\Models\PowerPlant;
use App\Services\BlockchainService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EnergyReportController extends Controller
{
    protected $blockchainService;

    public function __construct(BlockchainService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }

    /**
     * Menyimpan laporan produksi energi yang baru.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // 1. Validasi data yang masuk dari form
        $validated = $request->validate([
            'power_plant_id' => 'required|exists:power_plants,id',
            'reporting_period_start' => 'required|date',
            'reporting_period_end' => 'required|date|after_or_equal:reporting_period_start',
            'amount_mwh' => 'required|numeric|min:0',
            'supporting_document' => 'nullable|file|mimes:pdf|max:2048',
        ]);

        // 2. Otorisasi
        $powerPlant = PowerPlant::findOrFail($validated['power_plant_id']);
        if ($powerPlant->user_id !== Auth::id()) {
            return back()->with('error', 'Anda tidak memiliki izin untuk melaporkan produksi untuk pembangkit ini.');
        }

        $documentPath = null;
        // 3. Simpan dokumen jika ada
        if ($request->hasFile('supporting_document')) {
            $documentPath = $request->file('supporting_document')->store('report_documents', 'public');
        }

        // 4. Generate Professional Energy ID
        $timestamp = time();
        $powerPlantName = strtoupper(substr($powerPlant->name, 0, 4)); // Ambil 4 huruf pertama nama PLTS
        $energyDataId = 'ENERGI_' . $powerPlantName . '_' . $timestamp . '_' . rand(100, 999);

        Log::info('Generating professional energy ID', [
            'energy_data_id' => $energyDataId,
            'power_plant_name' => $powerPlant->name,
            'user_id' => Auth::id(),
            'power_plant_id' => $validated['power_plant_id'],
            'amount_mwh' => $validated['amount_mwh']
        ]);

        // 5. Buat laporan baru di database
        $energyReport = EnergyReport::create([
            'power_plant_id' => $validated['power_plant_id'],
            'reporting_period_start' => $validated['reporting_period_start'],
            'reporting_period_end' => $validated['reporting_period_end'],
            'amount_mwh' => (float) $validated['amount_mwh'],
            'supporting_document_path' => $documentPath,
            'status' => 'pending_verification',
            'blockchain_energy_id' => $energyDataId,
        ]);

        // 6. PAKSA Submit to Blockchain dengan ID yang simple dan pasti berhasil
        try {
            Log::info('FORCING blockchain submission with retry and simple data', [
                'energy_report_id' => $energyReport->id,
                'energy_data_id' => $energyDataId
            ]);

            // Prepare professional data untuk blockchain
            $energyAmount = $validated['amount_mwh'] * 1000; // Convert MWh to kWh
            $sourceType = $powerPlant->energy_type ?? 'Solar'; // Use actual energy type
            $generationDate = $validated['reporting_period_end'];
            $location = 'Indonesia'; // Could be enhanced with actual location
            $generatorId = 'GEN_' . $powerPlantName . '_' . Auth::id(); // Professional generator ID

            // Submit dengan data yang simple
            $blockchainResult = $this->blockchainService->submitEnergyData(
                $energyDataId,
                $energyAmount,
                $sourceType,
                $generationDate,
                $location,
                $generatorId
            );
            
            if ($blockchainResult && isset($blockchainResult['success']) && $blockchainResult['success']) {
                $energyReport->update([
                    'blockchain_status' => 'submitted',
                    'blockchain_response' => json_encode($blockchainResult)
                ]);
                
                Log::info('SUCCESS: Frontend energy data submitted to blockchain', [
                    'energy_report_id' => $energyReport->id,
                    'blockchain_id' => $energyDataId,
                    'blockchain_result' => $blockchainResult
                ]);

                return redirect()->route('generator.dashboard')->with('success', 
                    'SUCCESS! Laporan energi berhasil dikirim ke blockchain! Energy ID: ' . $energyDataId . ' - Silakan refresh untuk melihat di CouchDB.');
            } else {
                throw new \Exception('Blockchain returned failure: ' . json_encode($blockchainResult));
            }

        } catch (\Exception $e) {
            Log::error('FAILED: Frontend energy submission error', [
                'energy_report_id' => $energyReport->id,
                'energy_data_id' => $energyDataId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $energyReport->update([
                'blockchain_status' => 'failed',
                'blockchain_error' => $e->getMessage()
            ]);
            
            return redirect()->route('generator.dashboard')->with('error', 
                'GAGAL submit ke blockchain: ' . $e->getMessage() . '. Coba lagi dengan data yang berbeda!');
        }
    }
}