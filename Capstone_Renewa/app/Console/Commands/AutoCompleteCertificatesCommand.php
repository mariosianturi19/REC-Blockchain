<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Certificate;
use App\Jobs\AutoCompleteCertificateJob;
use Illuminate\Support\Facades\Log;

class AutoCompleteCertificatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'certificates:auto-complete 
                            {--force : Force completion for all certificates}
                            {--id= : Auto-complete specific certificate ID}
                            {--status= : Auto-complete certificates with specific status}
                            {--limit=10 : Maximum number of certificates to process}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically complete certificates that are ready for completion';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🤖 Starting automatic certificate completion...');

        $force = $this->option('force');
        $specificId = $this->option('id');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');

        if ($specificId) {
            $this->processSpecificCertificate($specificId, $force);
            return;
        }

        $certificates = $this->getCertificatesForAutoCompletion($status, $limit);
        
        if ($certificates->isEmpty()) {
            $this->info('✅ No certificates found for auto-completion');
            return;
        }

        $this->info("🔄 Found {$certificates->count()} certificates for auto-completion");

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($certificates as $certificate) {
            try {
                $this->line("Processing Certificate ID: {$certificate->id} (Status: {$certificate->blockchain_status})");
                
                // Dispatch auto-completion job
                AutoCompleteCertificateJob::dispatch($certificate, $force)
                    ->onQueue('blockchain-auto-completion');
                
                $processed++;
                $this->info("  ✅ Queued for auto-completion");
                
            } catch (\Exception $e) {
                $failed++;
                $this->error("  ❌ Failed to queue: {$e->getMessage()}");
                
                Log::error('AUTO-COMPLETION: Failed to dispatch job', [
                    'certificate_id' => $certificate->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("📊 Auto-completion summary:");
        $this->info("  • Processed: {$processed}");
        $this->info("  • Skipped: {$skipped}");
        $this->info("  • Failed: {$failed}");
        
        if ($processed > 0) {
            $this->info("🚀 {$processed} certificates queued for auto-completion!");
            $this->comment("Check job queue status with: php artisan queue:work");
        }
    }

    /**
     * Process specific certificate by ID
     */
    private function processSpecificCertificate($certificateId, $force)
    {
        $certificate = Certificate::find($certificateId);
        
        if (!$certificate) {
            $this->error("❌ Certificate with ID {$certificateId} not found");
            return;
        }

        $this->info("🎯 Processing specific certificate ID: {$certificateId}");
        $this->info("   Status: {$certificate->blockchain_status}");
        $this->info("   Order ID: {$certificate->order_id}");
        $this->info("   Amount: {$certificate->amount_mwh} MWh");

        try {
            AutoCompleteCertificateJob::dispatch($certificate, $force)
                ->onQueue('blockchain-auto-completion');
            
            $this->info("✅ Certificate queued for auto-completion");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to queue certificate: {$e->getMessage()}");
        }
    }

    /**
     * Get certificates eligible for auto-completion
     */
    private function getCertificatesForAutoCompletion($status, $limit)
    {
        $query = Certificate::with('order');

        if ($status) {
            $query->where('blockchain_status', $status);
        } else {
            // Auto-completion eligible statuses
            $eligibleStatuses = [
                'NEW',
                'PENDING', 
                'REQUESTED',
                'ISSUED',
                'COMPLETED',
                'ERROR',
                'AUTO_COMPLETION_ERROR',
                'WORKFLOW_ERROR'
            ];
            
            $query->whereIn('blockchain_status', $eligibleStatuses);
        }

        // Exclude recently failed auto-completions (wait at least 30 minutes)
        $query->where(function($q) {
            $q->where('blockchain_status', '!=', 'AUTO_COMPLETION_ERROR')
              ->orWhere('updated_at', '<=', now()->subMinutes(30));
        });

        // Order by priority: older certificates first
        $query->orderBy('created_at', 'asc');

        return $query->limit($limit)->get();
    }
}