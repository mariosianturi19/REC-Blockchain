<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\PowerPlant;
use App\Models\Certificate;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketplaceController extends Controller
{
    /**
     * Menampilkan halaman marketplace dengan daftar pembangkit yang dikelompokkan.
     */
    public function index(Request $request)
    {
        // Cek apakah pengguna sudah memilih kategori.
        if (!$request->has('category')) {
            return redirect()->route('buyer.categoryselect')->with('info', 'Silakan pilih kategori pembelian terlebih dahulu.');
        }

        // Validasi input dari URL
        $validated = $request->validate([
            'category' => 'required|string|in:Retail,Signature,Enterprise',
        ]);

        $category = $validated['category'];

        // Tentukan minimal pembelian di sisi server
        $minPurchase = match ($category) {
            'Retail' => 10,
            'Enterprise' => 200,
            default => 0,
        };

        // Query utama untuk mengambil data Pembangkit
        $query = PowerPlant::query()
            ->with('user') // Eager load relasi user
            ->withSum(['certificates' => function ($query) {
                // Pastikan hanya menjumlahkan sertifikat yang tersedia
                $query->where('certificates.status', 'available_for_sale');
            }], 'amount_mwh')
            // Pastikan hanya menampilkan pembangkit yang punya sertifikat
            ->whereHas('certificates', function ($query) {
                $query->where('certificates.status', 'available_for_sale');
            });

        // Filter berdasarkan total energi yang tersedia harus lebih besar dari minimal pembelian
        // (Contoh: Jangan tampilkan pembangkit 150 MWh jika kategori Enterprise min. 200)
        $query->having('certificates_sum_amount_mwh', '>=', $minPurchase);

        // Eksekusi query
        $powerPlants = $query->get();
        
        // ======================================================
        // === PERBAIKANNYA DI SINI ===
        // ======================================================
        // Buat array $filters untuk dikirim ke view.
        $filters = [
            'category' => $category
        ];

        // Kirim 'powerPlants' dan 'filters' ke view.
        return view('buyer.marketplace', compact('powerPlants', 'filters'));
    }

    /**
     * ✅ NEW: Phase 2 - Pre-purchase validation API endpoint
     * Real-time check for duplicate purchases dan security validation
     */
    public function validatePurchase(Request $request)
    {
        $request->validate([
            'power_plant_id' => 'required|integer|exists:power_plants,id',
            'requested_amount' => 'required|numeric|min:0.1',
            'certificate_ids' => 'array|nullable',
            'certificate_ids.*' => 'integer|exists:certificates,id'
        ]);

        $buyerId = Auth::id();
        $powerPlantId = $request->power_plant_id;
        $requestedAmount = $request->requested_amount;
        $certificateIds = $request->certificate_ids ?? [];

        // **1. Check for duplicate purchases**
        $duplicateCheck = $this->checkDuplicatePurchase($buyerId, $powerPlantId, $certificateIds);
        
        // **2. Check certificate security status**
        $securityCheck = $this->checkCertificateSecurity($certificateIds);
        
        // **3. Check availability**
        $availabilityCheck = $this->checkAvailability($powerPlantId, $requestedAmount);

        // **4. Compile validation results**
        $validationResult = [
            'is_valid' => true,
            'warnings' => [],
            'errors' => [],
            'security_info' => [
                'overall_level' => 'HIGH',
                'certificates_checked' => count($certificateIds),
                'blockchain_verified' => 0,
                'integrity_confirmed' => 0
            ]
        ];

        // **Process duplicate check**
        if (!$duplicateCheck['is_valid']) {
            $validationResult['is_valid'] = false;
            $validationResult['errors'][] = [
                'type' => 'DUPLICATION',
                'level' => 'CRITICAL',
                'title' => '⚠️ Duplicate Purchase Detected',
                'message' => $duplicateCheck['message'],
                'details' => $duplicateCheck['details']
            ];
        }

        // **Process security check**
        if (!$securityCheck['is_secure']) {
            if ($securityCheck['severity'] === 'CRITICAL') {
                $validationResult['is_valid'] = false;
                $validationResult['errors'][] = [
                    'type' => 'SECURITY',
                    'level' => 'CRITICAL',
                    'title' => '🔒 Security Issue Detected',
                    'message' => $securityCheck['message'],
                    'details' => $securityCheck['details']
                ];
            } else {
                $validationResult['warnings'][] = [
                    'type' => 'SECURITY',
                    'level' => 'MEDIUM',
                    'title' => '🔍 Security Verification Needed',
                    'message' => $securityCheck['message'],
                    'details' => $securityCheck['details']
                ];
            }
        }

        // **Process availability check**
        if (!$availabilityCheck['is_available']) {
            $validationResult['is_valid'] = false;
            $validationResult['errors'][] = [
                'type' => 'AVAILABILITY',
                'level' => 'HIGH',
                'title' => '📊 Insufficient Stock',
                'message' => $availabilityCheck['message'],
                'details' => $availabilityCheck['details']
            ];
        }

        // **Update security info**
        $validationResult['security_info'] = array_merge($validationResult['security_info'], $securityCheck['stats']);

        return response()->json($validationResult);
    }

    /**
     * Check if user has already purchased from same power plant/certificates
     */
    private function checkDuplicatePurchase($buyerId, $powerPlantId, $certificateIds)
    {
        // Check for existing orders with same power plant
        $existingOrders = Order::where('buyer_id', $buyerId)
            ->whereHas('orderItems.powerPlant', function($query) use ($powerPlantId) {
                $query->where('id', $powerPlantId);
            })
            ->where('status', '!=', 'cancelled')
            ->with(['orderItems.certificates'])
            ->get();

        if ($existingOrders->isEmpty()) {
            return [
                'is_valid' => true,
                'message' => 'No duplicate purchases found'
            ];
        }

        // Check for certificate overlap
        $existingCertIds = [];
        foreach ($existingOrders as $order) {
            foreach ($order->orderItems as $item) {
                foreach ($item->certificates as $cert) {
                    $existingCertIds[] = $cert->id;
                }
            }
        }

        $duplicateCertIds = array_intersect($certificateIds, $existingCertIds);
        
        if (!empty($duplicateCertIds)) {
            return [
                'is_valid' => false,
                'message' => 'You have already purchased some of these certificates',
                'details' => [
                    'existing_orders' => $existingOrders->count(),
                    'duplicate_certificates' => $duplicateCertIds,
                    'latest_order' => $existingOrders->first()->order_number ?? null
                ]
            ];
        }

        return [
            'is_valid' => true,
            'message' => 'No certificate overlap detected',
            'details' => [
                'existing_orders' => $existingOrders->count(),
                'no_overlap' => true
            ]
        ];
    }

    /**
     * Check certificate security status and blockchain verification
     */
    private function checkCertificateSecurity($certificateIds)
    {
        if (empty($certificateIds)) {
            return [
                'is_secure' => true,
                'severity' => 'LOW',
                'message' => 'No certificates to verify',
                'stats' => [
                    'overall_level' => 'UNKNOWN',
                    'certificates_checked' => 0,
                    'blockchain_verified' => 0,
                    'integrity_confirmed' => 0
                ]
            ];
        }

        $certificates = Certificate::whereIn('id', $certificateIds)->get();
        
        $stats = [
            'overall_level' => 'HIGH',
            'certificates_checked' => $certificates->count(),
            'blockchain_verified' => 0,
            'integrity_confirmed' => 0,
            'ownership_confirmed' => 0
        ];

        $securityIssues = [];
        $lowSecurityCount = 0;

        foreach ($certificates as $certificate) {
            // Check blockchain verification
            if ($certificate->blockchain_cert_id) {
                $stats['blockchain_verified']++;
                
                // Check integrity
                if (in_array($certificate->blockchain_status, ['CERTIFICATE_ISSUED', 'COMPLETED'])) {
                    $stats['integrity_confirmed']++;
                }
                
                // Check ownership
                if ($certificate->blockchain_cert_id && $certificate->blockchain_hash) {
                    $stats['ownership_confirmed']++;
                }
            } else {
                $lowSecurityCount++;
                $securityIssues[] = "Certificate {$certificate->certificate_number} is not blockchain verified";
            }
        }

        // Determine overall security level
        $verificationRate = $stats['blockchain_verified'] / $stats['certificates_checked'];
        
        if ($verificationRate >= 0.8) {
            $stats['overall_level'] = 'HIGH';
        } elseif ($verificationRate >= 0.5) {
            $stats['overall_level'] = 'MEDIUM';
        } else {
            $stats['overall_level'] = 'LOW';
        }

        // Determine if secure
        $isSecure = $lowSecurityCount === 0;
        $severity = $lowSecurityCount > ($stats['certificates_checked'] / 2) ? 'CRITICAL' : 'MEDIUM';

        return [
            'is_secure' => $isSecure,
            'severity' => $severity,
            'message' => $isSecure 
                ? 'All certificates are blockchain verified' 
                : "⚠️ {$lowSecurityCount} certificate(s) need blockchain verification",
            'details' => $securityIssues,
            'stats' => $stats
        ];
    }

    /**
     * Check if requested amount is available
     */
    private function checkAvailability($powerPlantId, $requestedAmount)
    {
        $powerPlant = PowerPlant::with(['certificates' => function($query) {
            $query->where('status', 'available_for_sale');
        }])->find($powerPlantId);

        if (!$powerPlant) {
            return [
                'is_available' => false,
                'message' => 'Power plant not found',
                'details' => []
            ];
        }

        $availableAmount = $powerPlant->certificates->sum('amount_mwh');

        if ($requestedAmount > $availableAmount) {
            return [
                'is_available' => false,
                'message' => "Insufficient stock. Requested: {$requestedAmount} MWh, Available: {$availableAmount} MWh",
                'details' => [
                    'requested' => $requestedAmount,
                    'available' => $availableAmount,
                    'shortfall' => $requestedAmount - $availableAmount
                ]
            ];
        }

        return [
            'is_available' => true,
            'message' => 'Stock available',
            'details' => [
                'requested' => $requestedAmount,
                'available' => $availableAmount,
                'remaining' => $availableAmount - $requestedAmount
            ]
        ];
    }
}