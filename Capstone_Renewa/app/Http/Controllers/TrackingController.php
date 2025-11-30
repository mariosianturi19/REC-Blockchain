<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BlockchainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    private $blockchainService;

    public function __construct(BlockchainService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }

    /**
     * Show public tracking page
     */
    public function index()
    {
        return view('tracking.index');
    }

    /**
     * Track transaction by ID (API endpoint)
     */
    public function trackTransaction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid transaction ID format',
                'errors' => $validator->errors()
            ], 400);
        }

        $transactionId = $request->input('transaction_id');
        
        // Sanitize transaction ID
        $transactionId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $transactionId);
        
        if (empty($transactionId)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid transaction ID'
            ], 400);
        }

        $result = $this->blockchainService->trackTransaction($transactionId);

        if ($result['success'] ?? false) {
            $formattedData = $this->blockchainService->formatPublicTransaction($result);
            
            return response()->json([
                'success' => true,
                'data' => $formattedData,
                'blockchain_verified' => $result['blockchain_verified'] ?? false
            ]);
        }

        return response()->json($result, 404);
    }

    /**
     * Get company transactions
     */
    public function getCompanyTransactions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid company name',
                'errors' => $validator->errors()
            ], 400);
        }

        $companyName = $request->input('company');
        $result = $this->blockchainService->getCompanyTransactions($companyName);

        return response()->json($result);
    }

    /**
     * Verify transaction authenticity
     */
    public function verifyTransaction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid transaction ID',
                'errors' => $validator->errors()
            ], 400);
        }

        $transactionId = $request->input('transaction_id');
        $transactionId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $transactionId);

        if (empty($transactionId)) {
            return response()->json([
                'success' => false,
                'verified' => false,
                'error' => 'Invalid transaction ID'
            ], 400);
        }

        $result = $this->blockchainService->verifyTransaction($transactionId);
        return response()->json($result);
    }

    /**
     * Get blockchain network status
     */
    public function getNetworkStatus(): JsonResponse
    {
        $status = $this->blockchainService->getNetworkStatus();
        return response()->json($status);
    }

    /**
     * Show tracking page with specific transaction
     */
    public function showTransaction($transactionId)
    {
        // Sanitize transaction ID
        $transactionId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $transactionId);
        
        if (empty($transactionId)) {
            return redirect()->route('tracking.index')
                ->with('error', 'Invalid transaction ID');
        }

        return view('tracking.show', compact('transactionId'));
    }

    /**
     * Track multiple transactions (batch)
     */
    public function trackBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_ids' => 'required|array|max:10',
            'transaction_ids.*' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid request format',
                'errors' => $validator->errors()
            ], 400);
        }

        $transactionIds = $request->input('transaction_ids');
        $results = [];
        $errors = [];

        foreach ($transactionIds as $id) {
            $sanitizedId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $id);
            
            if (empty($sanitizedId)) {
                $errors[] = "Invalid transaction ID: {$id}";
                continue;
            }

            try {
                $result = $this->blockchainService->trackTransaction($sanitizedId);
                
                if ($result['success'] ?? false) {
                    $results[$sanitizedId] = $this->blockchainService->formatPublicTransaction($result);
                } else {
                    $errors[] = "Transaction not found: {$sanitizedId}";
                }
            } catch (\Exception $e) {
                $errors[] = "Error tracking {$sanitizedId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => !empty($results),
            'data' => $results,
            'errors' => $errors,
            'total_found' => count($results),
            'total_errors' => count($errors)
        ]);
    }

    /**
     * Export transaction data (for authorized users)
     */
    public function exportTransaction(Request $request): JsonResponse
    {
        // This would require authentication in a real implementation
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|string|max:255',
            'format' => 'required|in:json,csv,xml'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid export parameters',
                'errors' => $validator->errors()
            ], 400);
        }

        $transactionId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $request->input('transaction_id'));
        $format = $request->input('format');

        if (empty($transactionId)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid transaction ID'
            ], 400);
        }

        $result = $this->blockchainService->trackTransaction($transactionId);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => 'Transaction not found'
            ], 404);
        }

        $formattedData = $this->blockchainService->formatPublicTransaction($result);

        switch ($format) {
            case 'csv':
                return $this->exportToCsv($formattedData);
            case 'xml':
                return $this->exportToXml($formattedData);
            default:
                return response()->json([
                    'success' => true,
                    'data' => $formattedData,
                    'format' => 'json'
                ]);
        }
    }

    /**
     * Export data to CSV format
     */
    private function exportToCsv($data)
    {
        $csvData = [];
        $csvData[] = array_keys($data);
        $csvData[] = array_values($data);

        $output = fopen('php://temp', 'w');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="transaction_' . $data['id'] . '.csv"');
    }

    /**
     * Export data to XML format
     */
    private function exportToXml($data)
    {
        $xml = new \SimpleXMLElement('<transaction/>');
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                foreach ($value as $subKey => $subValue) {
                    $child->addChild($subKey, htmlspecialchars($subValue));
                }
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }

        return response($xml->asXML())
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename="transaction_' . $data['id'] . '.xml"');
    }
}