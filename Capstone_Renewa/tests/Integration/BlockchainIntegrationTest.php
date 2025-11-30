<?php

/**
 * REC Blockchain Integration Test
 * 
 * Test file untuk memverifikasi integrasi Laravel dengan REC Blockchain API
 * Jalankan file ini setelah setup selesai untuk memastikan semua endpoint berfungsi
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\BlockchainService;

class BlockchainIntegrationTest
{
    private $blockchainService;
    private $testResults = [];

    public function __construct()
    {
        $this->blockchainService = new BlockchainService();
    }

    /**
     * Jalankan semua test integrasi
     */
    public function runAllTests()
    {
        echo "🚀 Starting REC Blockchain Integration Tests...\n\n";

        $this->testHealthCheck();
        $this->testCompleteWorkflow();
        $this->testGenerateId();
        
        $this->printResults();
    }

    /**
     * Test 1: Health Check
     */
    private function testHealthCheck()
    {
        echo "📡 Testing Blockchain API Health Check...\n";
        
        try {
            $isHealthy = $this->blockchainService->healthCheck();
            
            if ($isHealthy) {
                $this->testResults['health_check'] = '✅ PASS - Blockchain API is healthy';
            } else {
                $this->testResults['health_check'] = '❌ FAIL - Blockchain API is down';
            }
        } catch (Exception $e) {
            $this->testResults['health_check'] = '❌ ERROR - ' . $e->getMessage();
        }

        echo $this->testResults['health_check'] . "\n\n";
    }

    /**
     * Test 2: Complete 6-Step Workflow
     */
    private function testCompleteWorkflow()
    {
        echo "🔄 Testing Complete 6-Step REC Workflow...\n";

        $energyDataId = 'TEST-ENERGY-' . date('Ymd-His');
        $certId = 'TEST-CERT-' . date('Ymd-His');

        // Step 1: Submit Energy Data
        try {
            $energyData = [
                'energyDataId' => $energyDataId,
                'generatorId' => 'TEST-GEN-001',
                'energyAmount' => 5000,
                'generationDate' => date('Y-m-d'),
                'location' => 'Jakarta Test',
                'energySource' => 'Solar'
            ];

            $result1 = $this->blockchainService->submitEnergyData($energyData);
            $this->testResults['step_1'] = $result1['success'] ? '✅ Step 1 - Submit Energy Data: PASS' : '❌ Step 1: FAIL';
        } catch (Exception $e) {
            $this->testResults['step_1'] = '❌ Step 1 ERROR: ' . $e->getMessage();
        }

        // Step 2: Verify Energy Data
        try {
            $result2 = $this->blockchainService->verifyEnergyData($energyDataId, 'TEST-ISSUER-001', 'Test verification');
            $this->testResults['step_2'] = $result2['success'] ? '✅ Step 2 - Verify Energy Data: PASS' : '❌ Step 2: FAIL';
        } catch (Exception $e) {
            $this->testResults['step_2'] = '❌ Step 2 ERROR: ' . $e->getMessage();
        }

        // Step 3: Request Certificate
        try {
            $result3 = $this->blockchainService->requestCertificate($certId, $energyDataId, 'TEST-GEN-001');
            $this->testResults['step_3'] = $result3['success'] ? '✅ Step 3 - Request Certificate: PASS' : '❌ Step 3: FAIL';
        } catch (Exception $e) {
            $this->testResults['step_3'] = '❌ Step 3 ERROR: ' . $e->getMessage();
        }

        // Step 4: Issue Certificate
        try {
            $result4 = $this->blockchainService->issueCertificate($certId, 'TEST-ISSUER-001');
            $this->testResults['step_4'] = $result4['success'] ? '✅ Step 4 - Issue Certificate: PASS' : '❌ Step 4: FAIL';
        } catch (Exception $e) {
            $this->testResults['step_4'] = '❌ Step 4 ERROR: ' . $e->getMessage();
        }

        // Step 5: Create Purchase Request
        try {
            $result5 = $this->blockchainService->createPurchaseRequest($certId, 'TEST-BUYER-001', '2500');
            $this->testResults['step_5'] = $result5['success'] ? '✅ Step 5 - Create Purchase Request: PASS' : '❌ Step 5: FAIL';
        } catch (Exception $e) {
            $this->testResults['step_5'] = '❌ Step 5 ERROR: ' . $e->getMessage();
        }

        // Step 6: Confirm Purchase
        try {
            $result6 = $this->blockchainService->confirmPurchase($certId);
            $this->testResults['step_6'] = $result6['success'] ? '✅ Step 6 - Confirm Purchase: PASS' : '❌ Step 6: FAIL';
        } catch (Exception $e) {
            $this->testResults['step_6'] = '❌ Step 6 ERROR: ' . $e->getMessage();
        }

        echo "\nWorkflow Test Results:\n";
        foreach (['step_1', 'step_2', 'step_3', 'step_4', 'step_5', 'step_6'] as $step) {
            echo "  " . $this->testResults[$step] . "\n";
        }
        echo "\n";
    }

    /**
     * Test 3: Generate ID Functionality
     */
    private function testGenerateId()
    {
        echo "🆔 Testing ID Generation...\n";

        try {
            $energyId = $this->blockchainService->generateId('energy', ['source' => 'Solar', 'location' => 'Jakarta']);
            $certId = $this->blockchainService->generateId('certificate');
            
            if ($energyId && $certId) {
                $this->testResults['generate_id'] = "✅ ID Generation: PASS\n  Energy ID: {$energyId}\n  Certificate ID: {$certId}";
            } else {
                $this->testResults['generate_id'] = '❌ ID Generation: FAIL - IDs not generated';
            }
        } catch (Exception $e) {
            $this->testResults['generate_id'] = '❌ ID Generation ERROR: ' . $e->getMessage();
        }

        echo $this->testResults['generate_id'] . "\n\n";
    }

    /**
     * Print final test results
     */
    private function printResults()
    {
        echo "📊 Integration Test Summary\n";
        echo str_repeat("=", 50) . "\n";

        $passCount = 0;
        $totalTests = count($this->testResults);

        foreach ($this->testResults as $test => $result) {
            if (strpos($result, '✅') !== false) {
                $passCount++;
            }
        }

        echo "Tests Passed: {$passCount}/{$totalTests}\n";
        
        if ($passCount === $totalTests) {
            echo "🎉 ALL TESTS PASSED! Integration is working correctly.\n";
        } else {
            echo "⚠️  Some tests failed. Please check the configuration.\n";
        }

        echo str_repeat("=", 50) . "\n";
    }
}

// Jalankan test jika file ini dieksekusi langsung
if (php_sapi_name() === 'cli') {
    $test = new BlockchainIntegrationTest();
    $test->runAllTests();
}