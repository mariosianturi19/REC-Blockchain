<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EnergyReport;
use App\Services\BlockchainService;
use Illuminate\Support\Facades\Log;

class VerifyPendingEnergyData extends Command
{
    protected $signature = 'energy:verify-pending';
    protected $description = 'Verify all pending energy data in blockchain that should have been verified';

    protected $blockchainService;

    public function __construct(BlockchainService $blockchainService)
    {
        parent::__construct();
        $this->blockchainService = $blockchainService;
    }

    public function handle()
    {
        $this->info('🔍 Searching for approved reports with PENDING blockchain status...');
        
        // Find approved reports that have blockchain_energy_id but no verification
        $pendingReports = EnergyReport::where('status', 'approved')
            ->whereNotNull('blockchain_energy_id')
            ->where(function ($query) {
                $query->whereNull('blockchain_verification_status')
                      ->orWhere('blockchain_verification_status', '!=', 'verified');
            })
            ->get();

        $this->line("Found {$pendingReports->count()} reports that need blockchain verification");

        if ($pendingReports->isEmpty()) {
            $this->info('✅ No pending energy data found. All reports are properly verified.');
            return 0;
        }

        $this->newLine();
        $successCount = 0;
        $errorCount = 0;

        foreach ($pendingReports as $report) {
            $this->line("Processing Report ID {$report->id} - Energy ID: {$report->blockchain_energy_id}");
            
            try {
                // Step 2: Verify Energy Data di Blockchain
                $verificationResult = $this->blockchainService->verifyEnergyData(
                    $report->blockchain_energy_id,
                    config('app.default_issuer_id', 'ISSUER-REC-001'),
                    'Retroactive verification for approved energy report'
                );

                $report->update([
                    'blockchain_verification_status' => 'verified',
                    'blockchain_verification_response' => json_encode($verificationResult)
                ]);

                $this->info("  ✅ Successfully verified: {$report->blockchain_energy_id}");
                $successCount++;

                Log::info('Retroactive energy data verification', [
                    'energy_report_id' => $report->id,
                    'blockchain_id' => $report->blockchain_energy_id,
                    'verification_result' => $verificationResult
                ]);

            } catch (\Exception $e) {
                $this->error("  ❌ Failed to verify {$report->blockchain_energy_id}: {$e->getMessage()}");
                $errorCount++;

                $report->update([
                    'blockchain_verification_status' => 'failed',
                    'blockchain_verification_error' => $e->getMessage()
                ]);

                Log::error('Failed retroactive energy data verification', [
                    'energy_report_id' => $report->id,
                    'blockchain_id' => $report->blockchain_energy_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("📊 Verification Summary:");
        $this->line("✅ Successfully verified: {$successCount}");
        $this->line("❌ Failed: {$errorCount}");
        
        if ($successCount > 0) {
            $this->info("🎉 Energy data verification completed! Now test your web app - status should update correctly.");
        }

        return 0;
    }
}
