<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Certificate;
use Illuminate\Support\Facades\Log;

class OrderStatusService
{
    /**
     * Update order status based on blockchain certificate status
     */
    public function updateOrderStatusFromBlockchain(Order $order)
    {
        try {
            // Load certificates dengan blockchain data
            $order->load('certificates');
            
            if ($order->certificates->isEmpty()) {
                Log::warning("Order {$order->id} has no certificates");
                return false;
            }

            // Ambil status blockchain terbaru dari certificates
            $blockchainStatuses = $order->certificates
                ->whereNotNull('blockchain_status')
                ->pluck('blockchain_status')
                ->unique();

            if ($blockchainStatuses->isEmpty()) {
                Log::info("Order {$order->id} has no blockchain status in certificates");
                return false;
            }

            // Tentukan status tertinggi berdasarkan hierarchy
            $statusHierarchy = [
                'PENDING' => 1,
                'VERIFIED' => 2,
                'REQUESTED' => 3,
                'ISSUED' => 4,
                'CERTIFICATE_ISSUED' => 5, // ✅ FIXED: Add CERTIFICATE_ISSUED to hierarchy
                'PURCHASE_REQUESTED' => 6,
                'PURCHASED' => 7,
                'COMPLETED' => 8, // ✅ FIXED: COMPLETED is the highest status
            ];

            $highestStatus = $blockchainStatuses->reduce(function ($carry, $status) use ($statusHierarchy) {
                $currentLevel = $statusHierarchy[$status] ?? 0;
                $carryLevel = $statusHierarchy[$carry] ?? 0;
                
                return $currentLevel > $carryLevel ? $status : $carry;
            }, 'PENDING');

            // Map blockchain status ke order status
            $newOrderStatus = $this->mapBlockchainToOrderStatus($highestStatus);
            
            // Update jika berbeda
            if ($order->status !== $newOrderStatus) {
                $oldStatus = $order->status;
                $order->status = $newOrderStatus;
                $order->save();
                
                Log::info("Order {$order->id} status updated", [
                    'old_status' => $oldStatus,
                    'new_status' => $newOrderStatus,
                    'blockchain_status' => $highestStatus
                ]);
                
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error("Failed to update order status from blockchain", [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Map blockchain status to Laravel order status
     */
    private function mapBlockchainToOrderStatus($blockchainStatus)
    {
        $mapping = [
            'PENDING' => Order::STATUS_PENDING,
            'VERIFIED' => Order::STATUS_VERIFIED,
            'REQUESTED' => Order::STATUS_REQUESTED,
            'ISSUED' => Order::STATUS_ISSUED,
            'CERTIFICATE_ISSUED' => Order::STATUS_PURCHASED, // ✅ FIXED: Certificate ready, buyer can access
            'PURCHASE_REQUESTED' => Order::STATUS_PURCHASE_REQUESTED,
            'PURCHASED' => Order::STATUS_PURCHASED,
            'COMPLETED' => Order::STATUS_PURCHASED,
        ];

        // Map to legacy status for backward compatibility
        $legacyMapping = [
            'PENDING' => 'pending_payment',
            'VERIFIED' => 'pending_payment', 
            'REQUESTED' => 'pending_payment',
            'ISSUED' => 'pending_payment',     // ⭐ Certificate sudah ready untuk dibeli
            'CERTIFICATE_ISSUED' => 'completed', // ✅ FIXED: Certificate issued = buyer can access
            'PURCHASE_REQUESTED' => 'awaiting_confirmation',
            'PURCHASED' => 'completed',
            'COMPLETED' => 'completed', // ✅ FIXED: Final completion status
        ];

        // Use legacy mapping for existing system
        return $legacyMapping[$blockchainStatus] ?? 'pending_payment';
    }

    /**
     * Update semua orders yang memiliki blockchain certificates
     */
    public function updateAllOrdersFromBlockchain()
    {
        $orders = Order::whereHas('certificates', function ($query) {
            $query->whereNotNull('blockchain_status');
        })->get();

        $updatedCount = 0;
        
        foreach ($orders as $order) {
            if ($this->updateOrderStatusFromBlockchain($order)) {
                $updatedCount++;
            }
        }

        Log::info("Bulk status update completed", [
            'total_orders' => $orders->count(),
            'updated_orders' => $updatedCount
        ]);

        return $updatedCount;
    }

    public function mapBlockchainStatusToReadableStatus($blockchainStatus)
    {
        $statusMap = [
            'CERTIFICATE_REQUESTED' => 'processing',
            'CERTIFICATE_PAID' => 'processing', 
            'CERTIFICATE_VERIFIED' => 'processing',
            'CERTIFICATE_ISSUED' => 'ready_to_view',  // ✅ FIXED: Changed from 'awaiting_confirmation' to 'ready_to_view'
            'COMPLETED' => 'completed',
            'REQUEST_FAILED' => 'failed',
            'PAYMENT_CONFIRM_FAILED' => 'failed',
            'VERIFICATION_FAILED' => 'failed',
            'ISSUE_FAILED' => 'failed',
            'COMPLETE_FAILED' => 'failed'
        ];

        return $statusMap[$blockchainStatus] ?? 'processing';
    }

    public function getDisplayMessage($order, $blockchainStatus = null)
    {
        $status = $blockchainStatus ?: $order->status;
        
        $messages = [
            'pending_payment' => 'Menunggu Pembayaran',
            'awaiting_confirmation' => 'Menunggu Verifikasi Pembayaran',
            'processing' => 'Sedang Diproses',
            'ready_to_view' => 'Sertifikat Siap Dilihat',  // ✅ NEW: Added this status
            'completed' => 'Selesai',
            'failed' => 'Gagal'
        ];

        // ✅ FIXED: Check certificate blockchain status for better messaging
        if ($order && $order->certificates->isNotEmpty()) {
            $firstCert = $order->certificates->first();
            if ($firstCert && $firstCert->blockchain_status) {
                $mappedStatus = $this->mapBlockchainStatusToReadableStatus($firstCert->blockchain_status);
                return $messages[$mappedStatus] ?? $messages['processing'];
            }
        }

        return $messages[$status] ?? 'Status Tidak Diketahui';
    }
}