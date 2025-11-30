<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BlockchainTrackingService
{
    protected $apiBaseUrl;
    protected $timeout;
    protected $retryTimes;

    public function __construct()
    {
        $this->apiBaseUrl = config('services.blockchain.api_url', 'http://localhost:3000/api');
        $this->timeout = config('services.blockchain.timeout', 30);
        $this->retryTimes = config('services.blockchain.retry_times', 3);
    }

    /**
     * Get REC data from blockchain for public tracking
     */
    public function getPublicRECData(string $orderId): ?array
    {
        try {
            $cacheKey = "blockchain_rec_{$orderId}";
            
            // Check cache first (5 minutes cache)
            if (Cache::has($cacheKey)) {
                Log::info("Returning cached blockchain data for order: {$orderId}");
                return Cache::get($cacheKey);
            }

            Log::info("Fetching blockchain data for order: {$orderId}");

            $response = Http::timeout($this->timeout)
                ->retry($this->retryTimes, 1000)
                ->get("{$this->apiBaseUrl}/public/rec/{$orderId}");

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['success'] && isset($data['data'])) {
                    // Cache for 5 minutes
                    Cache::put($cacheKey, $data['data'], 300);
                    
                    Log::info("Successfully retrieved blockchain data for order: {$orderId}");
                    return $data['data'];
                }
            }

            Log::warning("Blockchain API returned unsuccessful response for order: {$orderId}", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error("Failed to fetch blockchain data for order: {$orderId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Get REC history from blockchain
     */
    public function getRECHistory(string $orderId): ?array
    {
        try {
            $cacheKey = "blockchain_history_{$orderId}";
            
            // Check cache first (10 minutes cache)
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            Log::info("Fetching blockchain history for order: {$orderId}");

            $response = Http::timeout($this->timeout)
                ->retry($this->retryTimes, 1000)
                ->get("{$this->apiBaseUrl}/public/rec/{$orderId}/history");

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['success'] && isset($data['data'])) {
                    // Cache for 10 minutes
                    Cache::put($cacheKey, $data['data'], 600);
                    
                    Log::info("Successfully retrieved blockchain history for order: {$orderId}");
                    return $data['data'];
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Failed to fetch blockchain history for order: {$orderId}", [
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Check blockchain API health
     */
    public function checkHealthStatus(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->apiBaseUrl}/health");
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => 'healthy',
                    'message' => $data['message'] ?? 'API is running',
                    'timestamp' => $data['timestamp'] ?? now()->toISOString()
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'API returned non-200 status',
                'http_status' => $response->status()
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'down',
                'message' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Verify if REC data is consistent between database and blockchain
     */
    public function verifyRECConsistency(string $orderId, array $databaseData): array
    {
        $blockchainData = $this->getPublicRECData($orderId);
        
        if (!$blockchainData) {
            return [
                'consistent' => false,
                'reason' => 'blockchain_data_not_available',
                'message' => 'Data tidak tersedia di blockchain'
            ];
        }

        $checks = [
            'order_id' => $databaseData['order_uid'] === $blockchainData['orderId'],
            'amount' => abs($databaseData['total_amount'] - $blockchainData['amount']) < 0.01,
            'company' => $databaseData['company_name'] === $blockchainData['company'],
        ];

        $allPassed = array_reduce($checks, function($carry, $item) {
            return $carry && $item;
        }, true);

        return [
            'consistent' => $allPassed,
            'checks' => $checks,
            'blockchain_data' => $blockchainData,
            'database_data' => $databaseData,
            'verification_timestamp' => now()->toISOString()
        ];
    }

    /**
     * Format blockchain data for display
     */
    public function formatBlockchainDataForDisplay(array $blockchainData): array
    {
        return [
            'verification_badge' => [
                'status' => 'verified',
                'text' => 'Verified on Blockchain',
                'icon' => 'fas fa-shield-check',
                'color' => 'success'
            ],
            'blockchain_info' => [
                'transaction_id' => substr($blockchainData['blockchainTxId'], 0, 12) . '...',
                'verification_time' => $blockchainData['verificationTimestamp'],
                'certificate_id' => $blockchainData['certificateId'],
                'type' => $blockchainData['type'] ?? 'REC'
            ],
            'immutable_data' => [
                'company' => $blockchainData['company'],
                'amount' => $blockchainData['amount'],
                'issue_date' => $blockchainData['issueDate'],
                'status' => $blockchainData['status']
            ]
        ];
    }
}