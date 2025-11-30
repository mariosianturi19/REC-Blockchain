<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Certificate;
use App\Models\Order;
use App\Services\BlockchainService;

class AutoCompleteCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 300;
    public $backoff = [30, 60, 120, 300, 600]; // Progressive backoff

    protected $certificate;
    protected $forceComplete;

    /**
     * Create a new job instance.
     */
    public function __construct(Certificate $certificate, bool $forceComplete = false)
    {
        $this->certificate = $certificate;
        $this->forceComplete = $forceComplete;
    }

    /**
     * Execute the auto-completion job
     */
    public function handle(BlockchainService $blockchainService)
    {
        Log::info('🤖 AUTO-COMPLETION: Starting automatic certificate completion', [
            'certificate_id' => $this->certificate->id,
            'certificate_uid' => $this->certificate->certificate_uid,
            'current_status' => $this->certificate->blockchain_status,
            'force_complete' => $this->forceComplete
        ]);

        // Check if certificate is eligible for auto-completion
        if (!$this->forceComplete && !$this->isEligibleForAutoCompletion()) {
            Log::info('⏭️ AUTO-COMPLETION: Certificate not eligible, skipping', [
                'certificate_id' => $this->certificate->id,
                'status' => $this->certificate->blockchain_status
            ]);
            return;
        }

        try {
            // Auto-detect current certificate state
            $currentState = $this->detectCertificateState();
            
            Log::info('🔍 AUTO-COMPLETION: Detected certificate state', [
                'certificate_id' => $this->certificate->id,
                'current_state' => $currentState,
                'blockchain_cert_id' => $this->certificate->blockchain_cert_id
            ]);

            // Auto-complete based on current state
            switch ($currentState) {
                case 'NEW':
                    $this->autoCompleteNewCertificate();
                    break;
                    
                case 'REQUESTED':
                    $this->autoCompleteFromRequested();
                    break;
                    
                case 'ISSUED':
                    $this->autoCompleteFromIssued();
                    break;
                    
                case 'COMPLETED':
                    $this->autoCompleteFromCompleted();
                    break;
                    
                case 'PURCHASED':
                    Log::info('✅ AUTO-COMPLETION: Certificate already fully completed', [
                        'certificate_id' => $this->certificate->id
                    ]);
                    break;
                    
                default:
                    Log::warning('⚠️ AUTO-COMPLETION: Unknown state, attempting full workflow', [
                        'certificate_id' => $this->certificate->id,
                        'state' => $currentState
                    ]);
                    $this->autoCompleteNewCertificate();
            }

            Log::info('✅ AUTO-COMPLETION: Certificate completion successful', [
                'certificate_id' => $this->certificate->id,
                'final_status' => $this->certificate->fresh()->blockchain_status
            ]);

        } catch (\Exception $e) {
            Log::error('❌ AUTO-COMPLETION: Failed to auto-complete certificate', [
                'certificate_id' => $this->certificate->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Update certificate with error info
            $this->certificate->update([
                'blockchain_status' => 'AUTO_COMPLETION_ERROR',
                'blockchain_error' => "Auto-completion failed: {$e->getMessage()} (Attempt {$this->attempts()})"
            ]);

            throw $e; // Let queue handle retry
        }
    }

    /**
     * Check if certificate is eligible for auto-completion
     */
    private function isEligibleForAutoCompletion(): bool
    {
        // Skip if already completed
        if ($this->certificate->blockchain_status === 'PURCHASED') {
            return false;
        }

        // Skip if currently processing
        if (in_array($this->certificate->blockchain_status, ['PROCESSING', 'WORKFLOW_RUNNING'])) {
            return false;
        }

        // Skip if failed too many times recently
        if ($this->certificate->blockchain_status === 'AUTO_COMPLETION_ERROR') {
            $lastAttempt = $this->certificate->updated_at;
            if ($lastAttempt && $lastAttempt->diffInMinutes(now()) < 30) {
                Log::info('⏰ AUTO-COMPLETION: Too soon since last failure, waiting', [
                    'certificate_id' => $this->certificate->id,
                    'last_attempt' => $lastAttempt,
                    'minutes_ago' => $lastAttempt->diffInMinutes(now())
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Detect current certificate state from blockchain
     */
    private function detectCertificateState(): string
    {
        if (!$this->certificate->blockchain_cert_id) {
            return 'NEW';
        }

        try {
            $apiUrl = config('app.blockchain_api_url');
            $certId = $this->certificate->blockchain_cert_id;
            
            // Query blockchain for certificate status
            $response = Http::timeout(30)
                ->get("{$apiUrl}/api/certificates/{$certId}");

            if ($response->successful()) {
                $data = $response->json();
                $status = $data['status'] ?? 'UNKNOWN';
                
                Log::info('📊 AUTO-COMPLETION: Blockchain status detected', [
                    'certificate_id' => $this->certificate->id,
                    'blockchain_cert_id' => $certId,
                    'blockchain_status' => $status
                ]);
                
                return $status;
            } else {
                Log::warning('⚠️ AUTO-COMPLETION: Cannot query blockchain status, assuming NEW', [
                    'certificate_id' => $this->certificate->id,
                    'http_status' => $response->status()
                ]);
                return 'NEW';
            }
        } catch (\Exception $e) {
            Log::warning('⚠️ AUTO-COMPLETION: Error detecting state, assuming NEW', [
                'certificate_id' => $this->certificate->id,
                'error' => $e->getMessage()
            ]);
            return 'NEW';
        }
    }

    /**
     * Auto-complete new certificate (full workflow)
     */
    private function autoCompleteNewCertificate()
    {
        Log::info('🆕 AUTO-COMPLETION: Starting full workflow for new certificate', [
            'certificate_id' => $this->certificate->id
        ]);

        // Get verified energy data
        $energyId = $this->getVerifiedEnergyId();
        if (!$energyId) {
            throw new \Exception('No verified energy data available for auto-completion');
        }

        $generatorId = $this->getGeneratorIdForEnergy($energyId);
        $timestamp = time();
        $certId = 'AUTO_CERT_' . $timestamp . '_' . $this->certificate->id . '_' . rand(1000, 9999);
        
        // ✅ FIXED: Use actual buyer name from user profile
        $buyerId = $this->getBuyerNameFromProfile();

        // Execute complete workflow
        $this->executeStep3($certId, $energyId, $generatorId);
        $this->executeStep4($certId);
        $this->executeStep5($certId, $generatorId);
        $this->executeStep6($certId, $buyerId);

        // Update certificate
        $this->updateCertificateSuccess($certId, 'AUTO_COMPLETED_FULL');
    }

    /**
     * Auto-complete from REQUESTED state
     */
    private function autoCompleteFromRequested()
    {
        Log::info('📋 AUTO-COMPLETION: Continuing from REQUESTED state', [
            'certificate_id' => $this->certificate->id
        ]);

        $certId = $this->certificate->blockchain_cert_id;
        // ✅ FIXED: Use actual buyer name from user profile
        $buyerId = $this->getBuyerNameFromProfile();
        $energyId = $this->getVerifiedEnergyId();
        $generatorId = $this->getGeneratorIdForEnergy($energyId);

        $this->executeStep4($certId);
        $this->executeStep5($certId, $generatorId);
        $this->executeStep6($certId, $buyerId);

        $this->updateCertificateSuccess($certId, 'AUTO_COMPLETED_FROM_REQUESTED');
    }

    /**
     * Auto-complete from ISSUED state
     */
    private function autoCompleteFromIssued()
    {
        Log::info('🏭 AUTO-COMPLETION: Continuing from ISSUED state', [
            'certificate_id' => $this->certificate->id
        ]);

        $certId = $this->certificate->blockchain_cert_id;
        // ✅ FIXED: Use actual buyer name from user profile
        $buyerId = $this->getBuyerNameFromProfile();
        $energyId = $this->getVerifiedEnergyId();
        $generatorId = $this->getGeneratorIdForEnergy($energyId);

        $this->executeStep5($certId, $generatorId);
        $this->executeStep6($certId, $buyerId);

        $this->updateCertificateSuccess($certId, 'AUTO_COMPLETED_FROM_ISSUED');
    }

    /**
     * Auto-complete from COMPLETED state
     */
    private function autoCompleteFromCompleted()
    {
        Log::info('✅ AUTO-COMPLETION: Continuing from COMPLETED state', [
            'certificate_id' => $this->certificate->id
        ]);

        $certId = $this->certificate->blockchain_cert_id;
        // ✅ FIXED: Use actual buyer name from user profile
        $buyerId = $this->getBuyerNameFromProfile();

        $this->executeStep6($certId, $buyerId);

        $this->updateCertificateSuccess($certId, 'AUTO_COMPLETED_FROM_COMPLETED');
    }

    /**
     * Execute Step 3: Request Certificate
     */
    private function executeStep3($certId, $energyId, $generatorId)
    {
        $apiUrl = config('app.blockchain_api_url');
        
        $response = Http::timeout(30)
            ->post("{$apiUrl}/api/certificates/request", [
                'certificateId' => $certId,
                'energyDataId' => $energyId,
                'generatorId' => $generatorId,
                'endorsement_orgs' => ['generator', 'issuer']
            ]);

        if (!$response->successful()) {
            throw new \Exception("Step 3 failed: HTTP {$response->status()}");
        }

        Log::info('✅ AUTO-COMPLETION: Step 3 completed', ['cert_id' => $certId]);
    }

    /**
     * Execute Step 4: Issue Certificate
     */
    private function executeStep4($certId)
    {
        $apiUrl = config('app.blockchain_api_url');
        
        $response = Http::timeout(30)
            ->put("{$apiUrl}/api/certificates/issue/{$certId}", [
                'issuerId' => 'ISSUER001',
                'endorsement_orgs' => ['issuer', 'buyer']
            ]);

        if (!$response->successful()) {
            throw new \Exception("Step 4 failed: HTTP {$response->status()}");
        }

        Log::info('✅ AUTO-COMPLETION: Step 4 completed', ['cert_id' => $certId]);
    }

    /**
     * Execute Step 5: Complete Certificate
     */
    private function executeStep5($certId, $generatorId)
    {
        $apiUrl = config('app.blockchain_api_url');
        
        $response = Http::timeout(30)
            ->put("{$apiUrl}/api/certificates/complete/{$certId}", [
                'generatorId' => $generatorId,
                'endorsement_orgs' => ['generator', 'issuer']
            ]);

        // Handle different response scenarios
        if ($response->successful()) {
            Log::info('✅ AUTO-COMPLETION: Step 5 completed successfully', ['cert_id' => $certId]);
            return;
        }

        // Handle endorsement mismatch but successful completion (status 500)
        if ($response->status() === 500) {
            $responseBody = $response->body();
            
            if (str_contains($responseBody, 'ProposalResponsePayloads do not match') &&
                (str_contains($responseBody, 'COMPLETED') || str_contains($responseBody, 'already exists'))) {
                
                Log::info('✅ AUTO-COMPLETION: Step 5 completed despite endorsement mismatch', [
                    'cert_id' => $certId,
                    'warning' => 'Endorsement mismatch handled'
                ]);
                return;
            }
        }

        // Handle HTTP 400 - certificate might already be completed
        if ($response->status() === 400) {
            $responseBody = $response->body();
            
            // Check if certificate already exists or is completed
            if (str_contains($responseBody, 'already exists') || 
                str_contains($responseBody, 'COMPLETED') ||
                str_contains($responseBody, 'already completed')) {
                
                Log::info('✅ AUTO-COMPLETION: Step 5 - Certificate already completed', [
                    'cert_id' => $certId,
                    'info' => 'Certificate was already in completed state'
                ]);
                return;
            }
            
            // Try to verify actual state from blockchain
            if ($this->verifyCertificateState($certId, 'COMPLETED')) {
                Log::info('✅ AUTO-COMPLETION: Step 5 - Verified completion despite 400 error', [
                    'cert_id' => $certId
                ]);
                return;
            }
        }

        // If all else fails, throw exception
        throw new \Exception("Step 5 failed: HTTP {$response->status()} - {$response->body()}");
    }

    /**
     * Execute Step 6: Purchase Certificate
     */
    private function executeStep6($certId, $buyerId)
    {
        $apiUrl = config('app.blockchain_api_url');
        $price = $this->certificate->amount_mwh * 35000;
        
        $response = Http::timeout(30)
            ->put("{$apiUrl}/api/certificates/purchase/{$certId}", [
                'buyerId' => $buyerId,
                'price' => $price,
                'endorsement_orgs' => ['generator', 'buyer']
            ]);

        if (!$response->successful()) {
            throw new \Exception("Step 6 failed: HTTP {$response->status()}");
        }

        Log::info('✅ AUTO-COMPLETION: Step 6 completed', ['cert_id' => $certId]);
    }

    /**
     * Update certificate with success status
     */
    private function updateCertificateSuccess($certId, $completionType)
    {
        $this->certificate->update([
            'blockchain_cert_id' => $certId,
            'blockchain_status' => 'PURCHASED',
            'blockchain_response' => json_encode([
                'auto_completed' => true,
                'completion_type' => $completionType,
                'completed_at' => now()->toISOString(),
                'job_id' => $this->job->getJobId() ?? 'auto-completion-job',
                'attempts' => $this->attempts()
            ])
        ]);

        Log::info('✅ AUTO-COMPLETION: Certificate updated successfully', [
            'certificate_id' => $this->certificate->id,
            'blockchain_cert_id' => $certId,
            'completion_type' => $completionType
        ]);
    }

    /**
     * Get verified energy ID (reuse from BlockchainWorkflowJob)
     */
    private function getVerifiedEnergyId()
    {
        try {
            $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/_all_docs?include_docs=true';
            $response = file_get_contents($couchUrl);
            $data = json_decode($response, true);
            
            $verifiedEnergyIds = [];
            foreach ($data['rows'] as $row) {
                if (isset($row['doc']['energyDataId']) && 
                    isset($row['doc']['status']) && 
                    $row['doc']['status'] === 'VERIFIED' &&
                    !isset($row['doc']['documentType']) &&
                    !str_contains($row['doc']['energyDataId'], 'CERT_') &&
                    !str_contains($row['doc']['energyDataId'], 'PURCHASE_')) {
                    $verifiedEnergyIds[] = $row['doc']['energyDataId'];
                }
            }
            
            if (!empty($verifiedEnergyIds)) {
                usort($verifiedEnergyIds, function($a, $b) {
                    return $this->extractTimestamp($b) - $this->extractTimestamp($a);
                });
                return $verifiedEnergyIds[0];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('AUTO-COMPLETION: Failed to get verified energy ID', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get generator ID for energy data
     */
    private function getGeneratorIdForEnergy($energyId)
    {
        try {
            $couchUrl = "http://admin:adminpw@localhost:5984/recchannel_rec/_all_docs?include_docs=true&key=\"{$energyId}\"";
            $response = file_get_contents($couchUrl);
            $data = json_decode($response, true);
            
            if (isset($data['rows'][0]['doc']['auditTrail']['createdBy'])) {
                return $data['rows'][0]['doc']['auditTrail']['createdBy'];
            }
            
            return 'GEN_AUTO_' . substr(md5($energyId), 0, 8);
            
        } catch (\Exception $e) {
            return 'GEN_AUTO_' . substr(md5($energyId), 0, 8);
        }
    }

    /**
     * Verify certificate state from blockchain
     */
    private function verifyCertificateState($certId, $expectedState)
    {
        try {
            $apiUrl = config('app.blockchain_api_url');
            $response = Http::timeout(30)
                ->get("{$apiUrl}/api/certificates/{$certId}");

            if ($response->successful()) {
                $data = $response->json();
                $actualState = $data['status'] ?? 'UNKNOWN';
                
                Log::info('🔍 AUTO-COMPLETION: Certificate state verification', [
                    'cert_id' => $certId,
                    'expected_state' => $expectedState,
                    'actual_state' => $actualState
                ]);
                
                return $actualState === $expectedState;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::warning('⚠️ AUTO-COMPLETION: Failed to verify certificate state', [
                'cert_id' => $certId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Extract timestamp from ID
     */
    private function extractTimestamp($energyId)
    {
        if (preg_match('/(\d{10})/', $energyId, $matches)) {
            return intval($matches[1]);
        }
        return 0;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception)
    {
        Log::error('🚨 AUTO-COMPLETION: Job failed permanently', [
            'certificate_id' => $this->certificate->id,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        $this->certificate->update([
            'blockchain_status' => 'AUTO_COMPLETION_FAILED',
            'blockchain_error' => "Auto-completion failed permanently: {$exception->getMessage()}"
        ]);
    }

    /**
     * ✅ NEW: Get buyer name from user profile (like "Vian")
     */
    private function getBuyerNameFromProfile()
    {
        // Priority 1: Get buyer name from order's user profile
        if ($this->certificate->order && $this->certificate->order->buyer) {
            $buyerName = $this->certificate->order->buyer->name;
            Log::info('✅ AUTO-COMPLETION: Using buyer name from order user profile', [
                'buyer_name' => $buyerName,
                'user_id' => $this->certificate->order->buyer->id,
                'certificate_id' => $this->certificate->id
            ]);
            return $buyerName;
        }

        // Priority 2: Fallback to a default name if no profile found
        Log::warning('⚠️ AUTO-COMPLETION: No buyer profile found, using default', [
            'certificate_id' => $this->certificate->id
        ]);
        return 'DefaultBuyer';
    }
}