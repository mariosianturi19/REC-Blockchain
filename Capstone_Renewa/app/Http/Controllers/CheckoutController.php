    public function show($id)
    {
        $order = Order::with(['orderItems.energyListing.powerPlant', 'certificates', 'buyer'])
            ->where('buyer_id', Auth::id())
            ->findOrFail($id);

        // 🚀 AUTO-TRIGGER: Sinkronisasi dan complete certificate otomatis
        $this->autoSyncAndCompleteCertificates($order);

        $totalMwh = $order->orderItems->sum(function ($item) {
            return $item->quantity * $item->energyListing->energy_amount;
        });

        return view('buyer.order-show', compact('order', 'totalMwh'));
    }

    /**
     * 🔄 AUTO-SYNC & AUTO-COMPLETE Certificates
     * Otomatis sinkronisasi status dari blockchain dan jalankan Step 5
     */
    private function autoSyncAndCompleteCertificates($order)
    {
        foreach ($order->certificates as $certificate) {
            if (!$certificate->blockchain_cert_id) {
                continue;
            }

            try {
                // 1. Cek status terkini di CouchDB
                $couchUrl = 'http://admin:adminpw@localhost:5984/recchannel_rec/CERTIFICATE_' . $certificate->blockchain_cert_id;
                $couchResponse = @file_get_contents($couchUrl);
                
                if ($couchResponse !== false) {
                    $couchData = json_decode($couchResponse, true);
                    
                    if (isset($couchData['certificateInfo']['status'])) {
                        $couchStatus = $couchData['certificateInfo']['status'];
                        
                        // 2. Update status jika berbeda
                        if ($certificate->blockchain_status !== $couchStatus) {
                            $certificate->update([
                                'blockchain_status' => $couchStatus,
                                'blockchain_response' => json_encode($couchData)
                            ]);
                            
                            Log::info('AUTO-SYNC: Certificate status updated', [
                                'certificate_id' => $certificate->id,
                                'old_status' => $certificate->blockchain_status,
                                'new_status' => $couchStatus
                            ]);
                        }
                        
                        // 🚀 AUTO-COMPLETE: Jika status CERTIFICATE_ISSUED, otomatis jalankan Step 5
                        if ($couchStatus === 'CERTIFICATE_ISSUED') {
                            $this->autoCompleteCertificate($certificate, $order);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('AUTO-SYNC: Failed to sync certificate', [
                    'certificate_id' => $certificate->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // 3. Update order status berdasarkan certificate status
        $this->autoUpdateOrderStatus($order);
    }

    /**
     * 🎯 AUTO-COMPLETE: Otomatis jalankan Step 5 untuk complete certificate
     */
    private function autoCompleteCertificate($certificate, $order)
    {
        try {
            $buyerId = $order->buyer->name ?? 'UnknownBuyer';
            
            Log::info('AUTO-COMPLETE: Starting Step 5', [
                'certificate_id' => $certificate->id,
                'blockchain_cert_id' => $certificate->blockchain_cert_id,
                'buyer_id' => $buyerId
            ]);
            
            $response = Http::timeout(30)
                ->put('http://localhost:3000/api/certificates/complete/' . $certificate->blockchain_cert_id, [
                    'buyerId' => $buyerId
                ]);

            if ($response->successful()) {
                $certificate->update([
                    'blockchain_status' => 'COMPLETED',
                    'status' => 'completed'
                ]);
                
                Log::info('AUTO-COMPLETE: Step 5 completed successfully', [
                    'certificate_id' => $certificate->id,
                    'blockchain_cert_id' => $certificate->blockchain_cert_id
                ]);
                
                // Set flash message untuk user
                session()->flash('success', '🎉 Sertifikat REC Anda sudah siap! Status telah diperbarui secara otomatis.');
                
            } else {
                Log::warning('AUTO-COMPLETE: Step 5 failed', [
                    'certificate_id' => $certificate->id,
                    'response' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('AUTO-COMPLETE: Exception in Step 5', [
                'certificate_id' => $certificate->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 📊 AUTO-UPDATE: Otomatis update order status
     */
    private function autoUpdateOrderStatus($order)
    {
        $certificates = $order->certificates()->whereNotNull('blockchain_status')->get();
        
        if ($certificates->isEmpty()) {
            return;
        }
        
        $anyIssued = $certificates->contains(function($cert) {
            return in_array($cert->blockchain_status, ['CERTIFICATE_ISSUED', 'COMPLETED']);
        });
        
        $allCompleted = $certificates->every(function($cert) {
            return $cert->blockchain_status === 'COMPLETED';
        });
        
        $newStatus = null;
        
        if ($allCompleted && $order->status !== 'completed') {
            $newStatus = 'completed';
        } elseif ($anyIssued && $order->status === 'awaiting_confirmation') {
            $newStatus = 'completed'; // Langsung completed ketika certificate issued
        }
        
        if ($newStatus) {
            $order->update(['status' => $newStatus]);
            
            Log::info('AUTO-UPDATE: Order status updated', [
                'order_id' => $order->id,
                'new_status' => $newStatus
            ]);
        }
    }