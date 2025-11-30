<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Certificate;
use Illuminate\Support\Facades\Log;

class CleanupBlockchainCertificates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:blockchain-certificates {--dry-run : Show what would be cleaned without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up blockchain_cert_id for certificates that do not exist in CouchDB';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('🧹 Starting blockchain certificates cleanup...');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }
        
        // Get all certificates with blockchain_cert_id
        $certificates = Certificate::whereNotNull('blockchain_cert_id')->get();
        
        $this->info("Found {$certificates->count()} certificates with blockchain_cert_id");
        
        $cleanedCount = 0;
        $foundCount = 0;
        
        foreach ($certificates as $certificate) {
            $this->line("Checking certificate {$certificate->id} (blockchain_cert_id: {$certificate->blockchain_cert_id})");
            
            // Check if certificate exists in CouchDB
            $exists = $this->checkCertificateInCouchDB($certificate->blockchain_cert_id);
            
            if ($exists) {
                $this->info("  ✅ Found in CouchDB");
                $foundCount++;
            } else {
                $this->warn("  ❌ Not found in CouchDB");
                
                if (!$dryRun) {
                    // Clean up the certificate
                    $certificate->update([
                        'blockchain_cert_id' => null,
                        'blockchain_status' => null,
                        'blockchain_response' => null
                    ]);
                    
                    Log::info('Cleaned up certificate blockchain data', [
                        'certificate_id' => $certificate->id,
                        'old_blockchain_cert_id' => $certificate->blockchain_cert_id
                    ]);
                }
                
                $cleanedCount++;
            }
        }
        
        $this->info("\n📊 Cleanup Summary:");
        $this->info("✅ Found in CouchDB: {$foundCount}");
        $this->warn("🧹 " . ($dryRun ? 'Would clean' : 'Cleaned') . ": {$cleanedCount}");
        
        if ($dryRun && $cleanedCount > 0) {
            $this->info("\n💡 Run without --dry-run to actually perform the cleanup:");
            $this->info("php artisan cleanup:blockchain-certificates");
        }
        
        return Command::SUCCESS;
    }
    
    /**
     * Check if certificate exists in CouchDB
     */
    private function checkCertificateInCouchDB($blockchainCertId)
    {
        // Try multiple possible document IDs
        $possibleDocIds = [
            'CERTIFICATE_' . $blockchainCertId,
            $blockchainCertId
        ];
        
        foreach ($possibleDocIds as $docId) {
            $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/' . $docId;
            $response = @file_get_contents($couchUrl);
            
            if ($response !== false) {
                $data = json_decode($response, true);
                if ($data && !isset($data['error'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
}