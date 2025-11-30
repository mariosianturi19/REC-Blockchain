<?php

namespace App\Http\Controllers\Generator;

use App\Http\Controllers\Controller;
use App\Models\EnergyReport;
use App\Models\Certificate;
use App\Models\PowerPlant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\BlockchainService;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $blockchainService;

    public function __construct(BlockchainService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }

    /**
     * Menampilkan halaman dasbor untuk Generator.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // 1. Ambil data dasar yang diperlukan
        $powerPlants = PowerPlant::where('user_id', $user->id)->get();
        $powerPlantIds = $powerPlants->pluck('id');
        $powerPlant = $powerPlants->first();

        // 2. Siapkan query untuk mengambil laporan energi
        $query = EnergyReport::whereIn('power_plant_id', $powerPlantIds);

        // (Opsional) Terapkan filter dan sorting pada query
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        $sortBy = $request->input('sort_by', 'newest');
        if ($sortBy === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // --- INI BAGIAN PENTING ---
        // 3. JALANKAN QUERY dan ambil datanya dari database
        $energyReports = $query->get();

        // 4. SETELAH DATA DIAMBIL, baru lakukan perhitungan
        $totalEnergyGenerated = $energyReports->sum('amount_mwh');
        $totalCertificatesIssued = Certificate::whereIn('energy_report_id', $energyReports->pluck('id'))->sum('amount_mwh');
        $totalReportsPending = $energyReports->where('status', 'pending_verification')->count();

        // 🔧 PERBAIKAN: Ubah status dari 'verified' menjadi 'approved'
        $verifiedReports = $energyReports->where('status', 'approved')->where('certificate_requested', false);

        // 6. Kirim semua variabel yang sudah benar ke view
        return view('generator.dashboard', compact(
            'user',
            'powerPlants',
            'powerPlant',
            'energyReports',
            'totalEnergyGenerated',
            'totalCertificatesIssued',
            'totalReportsPending',
            'verifiedReports'
        ));
    }

    /**
     * Request certificate untuk energy data yang sudah verified
     */
    public function requestCertificate(Request $request)
    {
        try {
            $validated = $request->validate([
                'energy_report_id' => 'required|exists:energy_reports,id',
            ]);

            $energyReport = EnergyReport::findOrFail($validated['energy_report_id']);
            
            // Pastikan energy report milik user yang login
            $powerPlant = PowerPlant::findOrFail($energyReport->power_plant_id);
            if ($powerPlant->user_id !== Auth::id()) {
                return back()->with('error', 'Anda tidak memiliki izin untuk request certificate ini.');
            }

            // 🔧 PERBAIKAN: Ubah status dari 'verified' menjadi 'approved'
            if ($energyReport->status !== 'approved') {
                return back()->with('error', 'Energy data harus disetujui terlebih dahulu sebelum request certificate.');
            }

            // Pastikan belum pernah request certificate
            if ($energyReport->certificate_requested) {
                return back()->with('error', 'Certificate sudah pernah di-request untuk energy data ini.');
            }

            // Generate certificate ID
            $timestamp = time();
            $powerPlantName = strtoupper(substr($powerPlant->name, 0, 4));
            $certificateId = 'CERT_' . $powerPlantName . '_' . $timestamp . '_' . rand(100, 999);
            $generatorId = 'GEN_' . $powerPlantName . '_' . Auth::id();

            Log::info('Generator requesting certificate', [
                'certificate_id' => $certificateId,
                'energy_data_id' => $energyReport->blockchain_energy_id,
                'generator_id' => $generatorId,
                'user_id' => Auth::id()
            ]);

            // Submit certificate request ke blockchain
            $blockchainResult = $this->blockchainService->requestCertificate(
                $certificateId,
                $energyReport->blockchain_energy_id,
                $generatorId
            );

            if ($blockchainResult && isset($blockchainResult['success']) && $blockchainResult['success']) {
                // Update energy report bahwa certificate sudah di-request
                $energyReport->update([
                    'certificate_requested' => true,
                    'certificate_id' => $certificateId,
                    'certificate_status' => 'CERTIFICATE_REQUESTED',
                    'certificate_response' => json_encode($blockchainResult),
                    'certificate_requested_at' => now(),
                ]);

                Log::info('SUCCESS: Certificate request submitted to blockchain', [
                    'energy_report_id' => $energyReport->id,
                    'certificate_id' => $certificateId,
                    'blockchain_result' => $blockchainResult
                ]);

                return back()->with('success', 
                    'SUCCESS! Certificate request berhasil dikirim ke blockchain! Certificate ID: ' . $certificateId);
            } else {
                throw new \Exception('Blockchain returned failure: ' . json_encode($blockchainResult));
            }

        } catch (\Exception $e) {
            Log::error('FAILED: Certificate request error', [
                'energy_report_id' => $validated['energy_report_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 
                'GAGAL request certificate: ' . $e->getMessage());
        }
    }
}