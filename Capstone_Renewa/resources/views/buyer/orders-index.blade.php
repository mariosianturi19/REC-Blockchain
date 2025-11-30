@extends('layouts.buyer')

@section('title', 'Daftar Pesanan')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">📋 Daftar Pesanan REC</h1>
        <a href="{{ route('buyer.marketplace') }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
            <i class="fas fa-plus mr-2"></i>Beli REC Baru
        </a>
    </div>

    @if($orders->isEmpty())
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <i class="fas fa-receipt fa-4x text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-600 mb-2">Belum Ada Pesanan</h3>
            <p class="text-gray-500 mb-4">Anda belum memiliki pesanan REC. Mulai berkontribusi untuk energi terbarukan!</p>
            <a href="{{ route('buyer.marketplace') }}" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors">
                <i class="fas fa-shopping-cart mr-2"></i>Mulai Belanja
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6">
            @foreach($orders as $order)
                <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                    <!-- ✅ NEW: Enhanced Header with Security Badge -->
                    <div class="p-6 border-b">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    @php
                                        // Prefer on-chain certificate status when available.
                                        // If any certificate in the order is COMPLETED, show completed.
                                        $effectiveStatus = $order->status;
                                        $certStatuses = $order->certificates->pluck('blockchain_status')->filter();

                                        if ($certStatuses->contains('COMPLETED')) {
                                            $effectiveStatus = 'completed';
                                        } elseif ($certStatuses->contains('PURCHASED')) {
                                            $effectiveStatus = 'completed';
                                        } elseif ($certStatuses->contains('CERTIFICATE_ISSUED') || $certStatuses->contains('ISSUED') || $certStatuses->contains('CERTIFICATE_ISSUED')) {
                                            $effectiveStatus = 'CERTIFICATE_ISSUED';
                                        } elseif ($certStatuses->contains('REQUESTED')) {
                                            $effectiveStatus = 'REQUESTED';
                                        }
                                    @endphp

                                    @if(strtolower($effectiveStatus) === 'completed')
                                        <i class="fas fa-check-circle fa-2x text-green-500"></i>
                                    @elseif(in_array($effectiveStatus, ['CERTIFICATE_ISSUED','ISSUED','CERTIFICATE_ISSUED']))
                                        <i class="fas fa-certificate fa-2x text-green-500"></i>
                                    @elseif(strtolower($effectiveStatus) === 'awaiting_confirmation' || strtoupper($effectiveStatus) === 'REQUESTED')
                                        <i class="fas fa-hourglass-half fa-2x text-blue-500"></i>
                                    @else
                                        <i class="fas fa-clock fa-2x text-yellow-500"></i>
                                    @endif
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">#{{ $order->order_uid }}</h3>
                                    <p class="text-sm text-gray-500">{{ $order->created_at->format('d M Y, H:i') }}</p>
                                </div>
                            </div>
                            
                            <!-- ✅ NEW: Security & Status Badges -->
                            <div class="mt-3 sm:mt-0 flex flex-wrap gap-2">
                                <!-- Order Status Badge -->
                                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-{{ $order->status_color }}-100 text-{{ $order->status_color }}-800">
                                    {{ $order->status_label }}
                                </span>
                                
                                <!-- ✅ NEW: Security Level Badge -->
                                @if($order->certificates->whereNotNull('blockchain_cert_id')->count() > 0)
                                    @php
                                        // Calculate security level directly in view
                                        $certificates = $order->certificates->whereNotNull('blockchain_cert_id');
                                        $highCount = 0;
                                        $mediumCount = 0;
                                        
                                        foreach ($certificates as $cert) {
                                            $hasIntegrity = $cert->blockchain_status === 'COMPLETED' || 
                                                          $cert->blockchain_status === 'CERTIFICATE_ISSUED';
                                            $hasOwnership = in_array($cert->blockchain_status, ['CERTIFICATE_ISSUED', 'COMPLETED']);
                                            $hasUniqueness = $cert->blockchain_cert_id && 
                                                           $cert->blockchain_status !== 'SECURITY_VALIDATION_FAILED';
                                            
                                            if ($hasIntegrity && $hasOwnership && $hasUniqueness) {
                                                $highCount++;
                                            } elseif ($hasIntegrity || $hasOwnership) {
                                                $mediumCount++;
                                            }
                                        }
                                        
                                        if ($highCount >= ($certificates->count() / 2)) {
                                            $securityLevel = 'HIGH';
                                            $securityColor = 'green';
                                            $securityIcon = '🛡️';
                                        } elseif (($highCount + $mediumCount) >= ($certificates->count() / 2)) {
                                            $securityLevel = 'MEDIUM';
                                            $securityColor = 'yellow'; 
                                            $securityIcon = '🔒';
                                        } else {
                                            $securityLevel = 'LOW';
                                            $securityColor = 'red';
                                            $securityIcon = '⚠️';
                                        }
                                    @endphp
                                    <span class="px-2 py-1 inline-flex items-center text-xs leading-4 font-medium rounded-md bg-{{ $securityColor }}-100 text-{{ $securityColor }}-800">
                                        <span class="mr-1">{{ $securityIcon }}</span>
                                        Security: {{ $securityLevel }}
                                    </span>
                                @endif
                                
                                <!-- ✅ NEW: Blockchain Verification Badge -->
                                @if($order->certificates->whereNotNull('blockchain_cert_id')->count() > 0)
                                    <span class="px-2 py-1 inline-flex items-center text-xs leading-4 font-medium rounded-md bg-blue-100 text-blue-800">
                                        <i class="fas fa-cube mr-1"></i>
                                        Blockchain Verified
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Order Details -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total REC</p>
                                <p class="text-lg font-semibold text-gray-900">{{ number_format($order->certificates->sum('amount_mwh'), 2, ',', '.') }} MWh</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Pembayaran</p>
                                <p class="text-lg font-semibold text-gray-900">Rp {{ number_format($order->total_price, 0, ',', '.') }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Jumlah Sertifikat</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $order->certificates->count() }} sertifikat</p>
                            </div>
                        </div>

                        <!-- ✅ NEW: Security Alerts -->
                        @if($order->certificates->where('blockchain_status', 'SECURITY_VALIDATION_FAILED')->count() > 0)
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                                <div class="flex items-start">
                                    <span class="text-red-600 text-lg mr-2">🚨</span>
                                    <div>
                                        <h4 class="text-sm font-semibold text-red-800">Security Alert</h4>
                                        <p class="text-xs text-red-700">
                                            {{ $order->certificates->where('blockchain_status', 'SECURITY_VALIDATION_FAILED')->count() }} certificate(s) failed security validation.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($order->certificates->where('blockchain_status', 'INTEGRITY_COMPROMISED')->count() > 0)
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                                <div class="flex items-start">
                                    <span class="text-red-600 text-lg mr-2">⚠️</span>
                                    <div>
                                        <h4 class="text-sm font-semibold text-red-800">Integrity Alert</h4>
                                        <p class="text-xs text-red-700">
                                            {{ $order->certificates->where('blockchain_status', 'INTEGRITY_COMPROMISED')->count() }} certificate(s) may be compromised. Contact support immediately.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mt-6">
                            <div class="flex flex-wrap gap-2 mb-3 sm:mb-0">
                                <a href="{{ route('buyer.orders.show', $order->id) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                                    <i class="fas fa-eye mr-2"></i>Detail
                                </a>
                                
                                <!-- {{-- ✅ FIXED: Button muncul untuk status CERTIFICATE_ISSUED dan completed --}}
                                @if(in_array($order->status, ['CERTIFICATE_ISSUED', 'completed']))
                                    <a href="{{ route('buyer.orders.certificate', $order->id) }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                                        <i class="fas fa-certificate mr-2"></i>Lihat Sertifikat
                                    </a>
                                @endif -->
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<!-- ✅ NEW: Security Verification Script -->
<script>
function verifyOrderSecurity(orderId) {
    Swal.fire({
        title: 'Verifying Order Security...',
        html: `
            <div class="flex items-center justify-center space-x-2">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
                <span>Checking all certificates in this order</span>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false
    });
    
    fetch(`/api/orders/${orderId}/security-status`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const certificates = data.data.certificates_security;
            
            let statusHtml = '<div class="text-left space-y-3">';
            statusHtml += '<h4 class="font-semibold text-gray-800 mb-3">Security Verification Results</h4>';
            
            certificates.forEach(cert => {
                statusHtml += '<div class="border rounded p-3 space-y-2">';
                statusHtml += `<div class="font-medium text-sm">Certificate: ${cert.blockchain_cert_id || 'N/A'}</div>`;
                
                const checks = [
                    { label: 'Integrity', status: cert.integrity_verified },
                    { label: 'Ownership', status: cert.ownership_authenticated },
                    { label: 'Uniqueness', status: cert.uniqueness_confirmed }
                ];
                
                checks.forEach(check => {
                    const icon = check.status ? '✅' : '❌';
                    const statusClass = check.status ? 'text-green-600' : 'text-red-600';
                    statusHtml += `<div class="flex items-center text-xs"><span class="mr-2">${icon}</span><span class="${statusClass}">${check.label}</span></div>`;
                });
                
                statusHtml += `<div class="text-xs text-gray-600">Security Level: <span class="font-semibold">${cert.security_level}</span></div>`;
                statusHtml += '</div>';
            });
            
            statusHtml += '</div>';
            
            Swal.fire({
                title: '🛡️ Security Verification Complete',
                html: statusHtml,
                icon: 'info',
                confirmButtonColor: '#7C3AED',
                confirmButtonText: 'Close',
                width: '600px'
            });
        } else {
            Swal.fire('Error', 'Failed to verify order security: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Security verification error:', error);
        Swal.fire('Error', 'Failed to verify order security. Please try again.', 'error');
    });
}
</script>
@endsection