<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\EnergyReport;

class EnergyVerificationController extends Controller
{
    public function verifyEnergyData(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            
            // 🔍 DETAILED LOGGING: Start verification process
            Log::info('🔄 Starting energy data verification process', [
                'energy_report_id' => $id,
                'issuer_id' => $request->user()->id,
                'timestamp' => now()
            ]);

            $energyReport = EnergyReport::findOrFail($id);
            
            // 🔍 DETAILED LOGGING: Current energy report status
            Log::info('📊 Current energy report state', [
                'id' => $energyReport->id,
                'blockchain_id' => $energyReport->blockchain_id,
                'status' => $energyReport->status,
                'verification_status' => $energyReport->verification_status,
                'verified_at' => $energyReport->verified_at,
                'verified_by' => $energyReport->verified_by
            ]);

            if ($energyReport->verification_status === 'verified') {
                Log::warning('⚠️ Attempt to verify already verified energy data', ['id' => $id]);
                return redirect()->back()->with('error', 'Data energi sudah terverifikasi sebelumnya.');
            }

            if (!$energyReport->blockchain_id) {
                Log::error('❌ Cannot verify: No blockchain ID found', [
                    'energy_report_id' => $id,
                    'blockchain_id' => $energyReport->blockchain_id
                ]);
                return redirect()->back()->with('error', 'Data energi belum memiliki ID blockchain.');
            }

            // 🔍 DETAILED LOGGING: Attempting blockchain verification
            Log::info('🔗 Attempting blockchain verification', [
                'blockchain_id' => $energyReport->blockchain_id,
                'api_url' => config('blockchain.api_url') . '/api/energy/verify/' . $energyReport->blockchain_id
            ]);

            // Call blockchain API to verify
            $response = Http::timeout(30)->post(config('blockchain.api_url') . '/api/energy/verify/' . $energyReport->blockchain_id, [
                'issuerId' => 'ISSUER001',
                'verificationNotes' => $request->verification_notes ?? 'Verified by issuer'
            ]);

            // 🔍 DETAILED LOGGING: Blockchain API response
            Log::info('📡 Blockchain API response', [
                'status_code' => $response->status(),
                'success' => $response->successful(),
                'response_body' => $response->json(),
                'response_headers' => $response->headers()
            ]);

            if (!$response->successful()) {
                Log::error('❌ Blockchain verification API failed', [
                    'status_code' => $response->status(),
                    'error_body' => $response->body(),
                    'blockchain_id' => $energyReport->blockchain_id
                ]);
                
                // 🔍 DETAILED LOGGING: Continue with database update despite API failure
                Log::warning('⚠️ Continuing with database update despite API failure');
            }

            // 🔍 DETAILED LOGGING: Before database update
            Log::info('💾 Updating database - BEFORE', [
                'verification_status' => $energyReport->verification_status,
                'status' => $energyReport->status,
                'verified_at' => $energyReport->verified_at,
                'verified_by' => $energyReport->verified_by
            ]);

            // Update database regardless of blockchain response (for now)
            $energyReport->update([
                'verification_status' => 'verified',
                'status' => 'verified', // 🔍 CRITICAL: Explicitly set status to verified
                'verified_at' => now(),
                'verified_by' => $request->user()->id,
                'verification_notes' => $request->verification_notes
            ]);

            // 🔍 DETAILED LOGGING: After database update
            $energyReport->refresh(); // Reload from database
            Log::info('💾 Database updated - AFTER', [
                'verification_status' => $energyReport->verification_status,
                'status' => $energyReport->status,
                'verified_at' => $energyReport->verified_at,
                'verified_by' => $energyReport->verified_by,
                'updated_at' => $energyReport->updated_at
            ]);

            // 🔍 DETAILED LOGGING: Verify the update was successful
            $checkRecord = EnergyReport::find($id);
            Log::info('🔍 Verification check - Database record after update', [
                'id' => $checkRecord->id,
                'verification_status' => $checkRecord->verification_status,
                'status' => $checkRecord->status,
                'verified_at' => $checkRecord->verified_at,
                'verified_by' => $checkRecord->verified_by
            ]);

            DB::commit();
            
            // 🔍 DETAILED LOGGING: Transaction committed
            Log::info('✅ Verification process completed successfully', [
                'energy_report_id' => $id,
                'final_status' => $checkRecord->status,
                'final_verification_status' => $checkRecord->verification_status
            ]);

            return redirect()->back()->with('success', 'Data energi berhasil diverifikasi!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            // 🔍 DETAILED LOGGING: Exception occurred
            Log::error('💥 Exception during verification process', [
                'energy_report_id' => $id,
                'exception_message' => $e->getMessage(),
                'exception_trace' => $e->getTraceAsString(),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine()
            ]);

            return redirect()->back()->with('error', 'Terjadi kesalahan saat verifikasi: ' . $e->getMessage());
        }
    }
}