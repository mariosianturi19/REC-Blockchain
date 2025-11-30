<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CertificateVerificationController extends Controller
{
    /**
     * Verify certificate authenticity by hash or serial number
     */
    public function verify(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string|min:3'
        ]);

        $identifier = trim($request->input('identifier'));
        
        try {
            // Check if input is serial number or hash
            $isSerialNumber = Str::startsWith($identifier, 'REC-');
            
            // Query blockchain via API
            $blockchainData = $this->queryBlockchain($identifier, $isSerialNumber);
            
            if (!$blockchainData) {
                return view('certificate-verification-result', [
                    'verified' => false,
                    'message' => 'Certificate not found in the blockchain. Please check your identifier and try again.'
                ]);
            }

            // Parse blockchain data
            $certificateData = $this->parseBlockchainData($blockchainData);

            return view('certificate-verification-result', [
                'verified' => true,
                'certificate' => $certificateData,
                'message' => 'Certificate successfully verified on blockchain.'
            ]);

        } catch (\Exception $e) {
            Log::error('Certificate verification error: ' . $e->getMessage(), [
                'identifier' => $identifier,
                'trace' => $e->getTraceAsString()
            ]);
            
            return view('certificate-verification-result', [
                'verified' => false,
                'message' => 'An error occurred during verification. Please try again later.'
            ]);
        }
    }

    /**
     * Query blockchain for certificate data
     */
    private function queryBlockchain(string $identifier, bool $isSerialNumber): ?array
    {
        try {
            $apiUrl = 'http://localhost:3000'; // Blockchain API endpoint
            
            if ($isSerialNumber) {
                // Query by serial number (certificateId)
                $response = Http::timeout(10)->get("{$apiUrl}/api/certificates/{$identifier}");
            } else {
                // Query by hash (certificateHash)
                $response = Http::timeout(10)->get("{$apiUrl}/api/certificates/verify-hash/{$identifier}");
            }

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? $data ?? null;
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Blockchain query error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse blockchain certificate data structure
     */
    private function parseBlockchainData(array $blockchainData): array
    {
        // ✅ FIXED: Extract data from actual blockchain structure (CouchDB format)
        $certificateId = $blockchainData['certificateId'] ?? 'N/A';
        
        // Certificate Info
        $status = $blockchainData['certificateInfo']['status'] ?? 'UNKNOWN';
        
        // Energy Reference
        $energyAmount = $blockchainData['energyReference']['amount'] ?? 0;
        $energyUnit = $blockchainData['energyReference']['unit'] ?? 'MWh';
        $sourceType = $blockchainData['energyReference']['sourceType'] ?? 'Unknown';
        $location = $blockchainData['energyReference']['location'] ?? 'Unknown';
        $generationDate = $blockchainData['energyReference']['generationDate'] ?? null;
        
        // Lifecycle
        $issuedAt = $blockchainData['lifecycle']['issuedAt'] ?? null;
        $expiresAt = $blockchainData['lifecycle']['expiresAt'] ?? null;
        $completedAt = $blockchainData['lifecycle']['completedAt'] ?? null;
        
        // Parties
        $buyerId = $blockchainData['parties']['buyer']['buyerId'] ?? 'Unknown';
        $generatorId = $blockchainData['parties']['generator']['generatorId'] ?? 'Unknown';
        
        // Security
        $certificateHash = $blockchainData['security']['certificateHash'] ?? 'N/A';
        $serialNumber = $blockchainData['security']['serialNumber'] ?? 'N/A';
        $securityLevel = $blockchainData['security']['cryptographicStandard'] ?? 'SHA-256';
        $tamperProof = $blockchainData['security']['tamperProof'] ?? false;
        
        // Compliance
        $antiDuplicationVerified = $blockchainData['compliance']['antiDuplicationVerified'] ?? false;
        
        // Audit Trail
        $workflowStep = $blockchainData['auditTrail']['workflowStep'] ?? 'N/A';
        $createdAt = $blockchainData['auditTrail']['createdAt'] ?? null;
        $createdBy = $blockchainData['auditTrail']['createdBy'] ?? 'N/A';
        $issuanceTxId = $blockchainData['auditTrail']['issuanceTxId'] ?? 'N/A';

        // Use raw energy amount from blockchain/CouchDB as-is (no unit conversion).
        // The system enforces minimum purchase of 1 MWh, so we display the stored value directly.
        $energyAmountNumeric = is_numeric($energyAmount) ? (float)$energyAmount : 0.0;
        $amountMwh = $energyAmountNumeric; // raw value from CouchDB (assumed MWh)
        $amountKwh = $energyAmountNumeric; // kept as raw value as well (no conversion)

        return [
            'serial_number' => $serialNumber ?: $certificateId,
            'blockchain_cert_id' => $certificateId,
            'amount_mwh' => number_format($amountMwh, 2),
            'amount_kwh' => $amountKwh,
            'status' => $this->formatStatus($status),
            'energy_source' => $this->formatEnergySource($sourceType),
            'power_plant' => $generatorId,
            'location' => $location,
            'issued_at' => $issuedAt ? \Carbon\Carbon::parse($issuedAt)->format('d M Y, H:i') : 'N/A',
            'expires_at' => $expiresAt ? \Carbon\Carbon::parse($expiresAt)->format('d M Y') : 'N/A',
            'completed_at' => $completedAt ? \Carbon\Carbon::parse($completedAt)->format('d M Y, H:i') : 'N/A',
            'owner' => $this->maskOwnerName($buyerId),
            'generation_period' => [
                'start' => $generationDate,
                'end' => $generationDate,
            ],
            'blockchain_verified' => !empty($certificateId),
            'integrity_check' => $tamperProof,
            'security' => [
                'fingerprint' => $certificateHash,
                'serial_number' => $serialNumber,
                'security_level' => $securityLevel,
                'tamper_proof' => $tamperProof,
            ],
            'compliance' => [
                'anti_duplication' => $antiDuplicationVerified,
                'renewable_energy_verified' => $this->isRenewableEnergy($sourceType),
            ],
            'audit_trail' => [
                'workflow_step' => $workflowStep,
                'created_at' => $createdAt,
                'created_by' => $createdBy,
                'issuance_tx_id' => $issuanceTxId,
            ]
        ];
    }

    /**
     * Format status untuk display
     */
    private function formatStatus(string $status): string
    {
        $statusMap = [
            'COMPLETED' => 'Completed',
            'ACTIVE' => 'Active',
            'RETIRED' => 'Retired',
            'PENDING' => 'Pending',
            'ISSUED' => 'Issued',
        ];

        return $statusMap[$status] ?? ucfirst(strtolower($status));
    }

    /**
     * Format energy source untuk display
     */
    private function formatEnergySource(string $source): string
    {
        $sourceMap = [
            'PLTA' => 'Hydro (PLTA)',
            'PLTS' => 'Solar (PLTS)',
            'PLTB' => 'Wind (PLTB)',
            'PLTU' => 'Biomass (PLTU)',
            'PLTG' => 'Geothermal (PLTG)',
        ];

        return $sourceMap[$source] ?? $source;
    }

    /**
     * Check if energy source is renewable
     */
    private function isRenewableEnergy(string $source): bool
    {
        $renewableSources = ['PLTA', 'PLTS', 'PLTB', 'PLTU', 'PLTG', 'Solar', 'Wind', 'Hydro', 'Biomass', 'Geothermal'];
        return in_array($source, $renewableSources);
    }

    /**
     * Mask owner name for privacy protection
     */
    private function maskOwnerName(string $name): string
    {
        if (strlen($name) <= 3) {
            return str_repeat('*', strlen($name));
        }
        
        $firstPart = substr($name, 0, 3);
        $masked = str_repeat('*', strlen($name) - 3);
        
        return $firstPart . $masked;
    }
}
