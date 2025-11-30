<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instruksi Pembayaran - Pesanan #{{ $order->order_uid }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
    @include('layouts.partials.navbar')

    <main class="py-12 pt-24">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto">
                
                @if(session('success'))
                    <div class="mb-6 p-4 bg-green-100 border border-green-200 rounded-xl text-green-700 text-sm flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                    </div>
                @endif
                @if(session('info'))
                    <div class="mb-6 p-4 bg-blue-100 border border-blue-200 rounded-xl text-blue-700 text-sm flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>{{ session('info') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-6 p-4 bg-red-100 border border-red-200 rounded-xl text-red-700 text-sm flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
                    </div>
                @endif

                @if(session('security_error'))
                    <script>
                        Swal.fire({
                            icon: 'error',
                            title: '🚨 Security Alert',
                            html: `
                                <div class="text-left space-y-2">
                                    <p class="font-semibold text-red-600">{{ session('security_error.title', 'Security Validation Failed') }}</p>
                                    <p class="text-sm text-gray-700">{{ session('security_error.message') }}</p>
                                    <div class="mt-3 p-2 bg-red-50 rounded text-xs">
                                        <strong>Error Type:</strong> {{ session('security_error.type', 'UNKNOWN') }}<br>
                                        <strong>Security Level:</strong> <span class="text-red-600 font-bold">{{ session('security_error.level', 'HIGH') }}</span>
                                    </div>
                                </div>
                            `,
                            confirmButtonColor: '#EF4444',
                            confirmButtonText: 'Understood'
                        });
                    </script>
                @endif

                @if(session('security_warning'))
                    <script>
                        Swal.fire({
                            icon: 'warning',
                            title: '⚠️ Security Warning',
                            html: `
                                <div class="text-left space-y-2">
                                    <p class="font-semibold text-yellow-600">{{ session('security_warning.title', 'Security Check Required') }}</p>
                                    <p class="text-sm text-gray-700">{{ session('security_warning.message') }}</p>
                                </div>
                            `,
                            confirmButtonColor: '#F59E0B',
                            confirmButtonText: 'Acknowledged'
                        });
                    </script>
                @endif

                @if(session('security_success'))
                    <script>
                        Swal.fire({
                            icon: 'success',
                            title: '✅ Security Verified',
                            html: `
                                <div class="text-left space-y-2">
                                    <p class="font-semibold text-green-600">{{ session('security_success.title', 'Security Validation Passed') }}</p>
                                    <p class="text-sm text-gray-700">{{ session('security_success.message') }}</p>
                                    <div class="mt-3 p-2 bg-green-50 rounded text-xs">
                                        <strong>Security Features:</strong><br>
                                        • Certificate Hash Verified<br>
                                        • Anti-Duplication Confirmed<br>
                                        • Ownership Authenticated
                                    </div>
                                </div>
                            `,
                            confirmButtonColor: '#10B981',
                            confirmButtonText: 'Excellent!'
                        });
                    </script>
                @endif

                <div class="bg-white rounded-2xl shadow-xl p-8">
                    <div class="text-center mb-8 border-b pb-6">
                        @if($order->status == 'CERTIFICATE_REQUESTED')
                            <i class="fas fa-file-invoice-dollar fa-3x text-yellow-500 mb-4"></i>
                            <h1 class="text-3xl font-bold text-gray-800">Lanjutkan Pembayaran</h1>
                        @elseif($order->status == 'CERTIFICATE_PAID')
                            <i class="fas fa-hourglass-half fa-3x text-blue-500 mb-4"></i>
                            <h1 class="text-3xl font-bold text-gray-800">Menunggu Verifikasi Issuer</h1>
                        @elseif($order->status == 'CERTIFICATE_ISSUED')
                            <i class="fas fa-certificate fa-3x text-green-500 mb-4"></i>
                            <h1 class="text-3xl font-bold text-gray-800">Sertifikat Sudah Diterbitkan</h1>
                        @elseif($order->status == 'COMPLETED')
                            <i class="fas fa-check-circle fa-3x text-green-500 mb-4"></i>
                            <h1 class="text-3xl font-bold text-gray-800">Pesanan Selesai</h1>
                        @else
                            <i class="fas fa-spinner fa-spin fa-3x text-gray-500 mb-4"></i>
                            <h1 class="text-3xl font-bold text-gray-800">Memproses Pesanan</h1>
                        @endif
                        <p class="text-gray-500 mt-1">Order ID <span class="font-semibold text-gray-700">#{{ $order->order_uid }}</span>.</p>
                    </div>

                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Status Pesanan:</span>
                            @php
                                $displayStatus = $order->blockchain_status ?? $order->status;
                                $statusLabel = $order->status_label;
                                $statusColor = $order->status_color;
                            @endphp
                            
                            <div class="flex flex-col items-end">
                                <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-{{ $statusColor }}-100 text-{{ $statusColor }}-800">
                                    {{ $statusLabel }}
                                </span>
                                
                                {{-- Show blockchain indicator if available --}}
                                @if($order->certificates->whereNotNull('blockchain_status')->count() > 0)
                                    <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        <i class="fas fa-cube mr-1"></i>Blockchain Verified
                                    </span>
                                @endif
                            </div>
                        </div>
                        
                        {{-- Show blockchain status progression if available --}}
                        @if($order->certificates->whereNotNull('blockchain_cert_id')->count() > 0)
                        <div class="border-t pt-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Status Blockchain:</h4>
                            <div class="space-y-2">
                                @php
                                    $uniqueCertificates = collect();
                                    foreach ($order->certificates as $c) {
                                        $key = $c->blockchain_cert_id ?: $c->certificate_uid;
                                        if (!$key) continue;
                                        if (!$uniqueCertificates->has($key)) {
                                            $uniqueCertificates->put($key, $c);
                                        }
                                    }
                                @endphp
                                @foreach($uniqueCertificates as $cert)
                                    @php $cid = $cert->blockchain_cert_id; @endphp
                                    <div class="flex items-center text-sm">
                                        <span id="status-dot-{{ $cid }}" class="w-2 h-2 rounded-full 
                                            @if($cert->blockchain_status === 'CERTIFICATE_REQUESTED') bg-yellow-500
                                            @elseif($cert->blockchain_status === 'CERTIFICATE_ISSUED') bg-blue-500
                                            @elseif($cert->blockchain_status === 'COMPLETED') bg-green-500
                                            @else bg-gray-500
                                            @endif mr-2"></span>
                                        <span class="text-gray-600">Certificate {{ $cid }}:</span>
                                        <span id="status-text-{{ $cid }}" data-initial-status="{{ $cert->blockchain_status }}" class="ml-2 font-medium 
                                            @if($cert->blockchain_status === 'CERTIFICATE_REQUESTED') text-yellow-600
                                            @elseif($cert->blockchain_status === 'CERTIFICATE_ISSUED') text-blue-600
                                            @elseif($cert->blockchain_status === 'COMPLETED') text-green-600
                                            @else text-gray-600
                                            @endif">{{ $cert->blockchain_status }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Jumlah REC Dibeli:</span>
                            <span class="text-xl font-bold text-gray-900">{{ number_format($totalMwh, 2, ',', '.') }} MWh</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Pembayaran:</span>
                            <span class="text-xl font-bold text-gray-900">Rp {{ number_format($order->total_price, 0, ',', '.') }}</span>
                        </div>
                    </div>
                    
                    @if($order->status == 'pending_payment')
                    <div class="mt-8 p-6 bg-gray-50 rounded-lg">
                        <h3 class="font-semibold text-lg mb-4">Instruksi Transfer (Simulasi)</h3>
                        <p class="text-sm text-gray-600 mb-2">Silakan lakukan transfer ke nomor Virtual Account di bawah ini:</p>
                        <div class="p-4 bg-white border rounded-md text-center">
                            <p class="text-sm text-gray-500">Bank Renewa (Contoh)</p>
                            <p class="text-2xl font-mono font-bold tracking-widest text-gray-800 my-2">{{ $order->virtual_account_number }}</p>
                        </div>
                    </div>
                    @endif

                    @if($order->certificates->isNotEmpty())
                        <h3 class="text-xl font-semibold mb-4 mt-8">📋 Sertifikat REC</h3>
                        
                        @foreach($order->certificates as $certificate)
                            <div class="border border-gray-200 rounded-lg p-4 mb-4">
                                
                                @if($certificate->blockchain_cert_id)
                                    <div class="mt-4">
                                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm font-medium text-gray-700">📜 Serial Number:</span>
                                                <code class="text-sm bg-blue-100 px-3 py-1 rounded text-blue-800 font-semibold">
                                                    {{ $certificate->certificate_uid ?? 'N/A' }}
                                                </code>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @endif

                    <div class="mt-8">
                        @if($order->status == 'CERTIFICATE_REQUESTED')
                            <p class="text-center text-sm text-gray-500 mb-4">Setelah Anda melakukan pembayaran, klik tombol di bawah ini.</p>
                            <form action="{{ route('buyer.orders.confirm', $order->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg transition-all text-lg">
                                    <i class="fas fa-check-circle mr-2"></i>Saya Sudah Bayar
                                </button>
                            </form>
                        @elseif($order->status == 'CERTIFICATE_PAID')
                             <div class="text-center p-4 bg-blue-50 rounded-lg">
                                <p class="text-blue-700 font-semibold">✅ Pembayaran sudah dikonfirmasi!</p>
                                <p class="text-sm text-gray-600 mt-2">Menunggu issuer untuk memverifikasi dan menerbitkan sertifikat.</p>
                                <p class="text-xs text-gray-400 mt-2">Status: CERTIFICATE_PAID</p>
                             </div>
                        @elseif($order->status == 'CERTIFICATE_ISSUED' || $order->certificates->where('blockchain_status', 'CERTIFICATE_ISSUED')->count() > 0)
                             <div class="text-center p-4 bg-green-50 rounded-lg space-y-3">
                                <p class="text-green-700 font-semibold">🎉 Sertifikat REC Anda sudah diterbitkan!</p>
                                <p class="text-sm text-gray-600">Sertifikat dapat dilihat dan digunakan sekarang.</p>
                                
                                <a href="{{ route('view-certificate-order') }}?order_id={{ $order->id }}" 
                                   class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition-all">
                                    <i class="fas fa-certificate mr-2"></i>Lihat Sertifikat
                                </a>
                             </div>
                        @elseif($order->status == 'COMPLETED' || $order->certificates->where('blockchain_status', 'COMPLETED')->count() > 0)
                             <div class="text-center p-4 bg-green-50 rounded-lg space-y-3">
                                <p class="text-green-700 font-semibold">🎉 Sertifikat REC Anda telah selesai!</p>
                                <p class="text-sm text-gray-600">Sertifikat sudah final dan siap digunakan.</p>
                                
                                <a href="{{ route('view-certificate-order') }}?order_id={{ $order->id }}" 
                                   class="inline-block bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition-all">
                                    <i class="fas fa-certificate mr-2"></i>Lihat Sertifikat
                                </a>
                             </div>
                        @else
                             <div class="text-center p-4 bg-gray-50 rounded-lg">
                                <p class="text-gray-700">Pesanan sedang diproses...</p>
                                <p class="text-sm text-gray-600 mt-2">Status: {{ $order->status }}</p>
                             </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    // --- TIDAK ADA PERUBAHAN PADA JAVASCRIPT ---
    // Semua fungsi JS (toggleHashDisplay, verifyOwnership, downloadOwnershipProof, checkIntegrity, handleSecurityError, getSecurityAction, fetchAndUpdateCertificateStatus, etc.)
    // tetap sama persis seperti kode asli Anda.
    
    function toggleHashDisplay(certificateId) {
        const shortHash = document.getElementById('hash-short-' + certificateId);
        const fullHash = document.getElementById('hash-full-' + certificateId);
        const toggleBtn = document.getElementById('hash-toggle-' + certificateId);
        
        if (fullHash.classList.contains('hidden')) {
            shortHash.classList.add('hidden');
            fullHash.classList.remove('hidden');
            toggleBtn.textContent = 'View Short';
        } else {
            shortHash.classList.remove('hidden');
            fullHash.classList.add('hidden');
            toggleBtn.textContent = 'View Full';
        }
    }

    function verifyOwnership(blockchainCertId) {
        Swal.fire({
            title: 'Verifying Ownership...',
            html: `
                <div class="flex items-center justify-center space-x-2">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span>Checking certificate ownership with blockchain proof</span>
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false
        });
        
        fetch(`/api/certificates/verify-ownership/${blockchainCertId}?buyerId={{ Auth::user()->name ?? 'Vian' }}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const verification = data.data.verification;
                const cryptoEvidence = data.data.cryptographicEvidence;
                
                let statusHtml = '<div class="text-left space-y-3">';
                
                // Security status checks
                const checks = [
                    { label: 'Ownership', status: verification.isOwner, icon: verification.isOwner ? '✅' : '❌' },
                    { label: 'Integrity', status: verification.integrityValid, icon: verification.integrityValid ? '✅' : '❌' },
                    { label: 'Proof Validity', status: verification.ownershipProofValid, icon: verification.ownershipProofValid ? '✅' : '⚠️' }
                ];
                
                checks.forEach(check => {
                    const statusClass = check.status ? 'text-green-600' : 'text-red-600';
                    const statusText = check.status ? 'VERIFIED' : 'FAILED';
                    statusHtml += `<div class="flex items-center justify-between"><span class="flex items-center"><span class="mr-2">${check.icon}</span>${check.label}:</span><span class="font-semibold ${statusClass}">${statusText}</span></div>`;
                });
                
                // Add cryptographic evidence
                if (cryptoEvidence.certificateFingerprint) {
                    statusHtml += `
                        <div class="mt-4 p-3 bg-gray-50 rounded">
                            <div class="text-xs space-y-1">
                                <div><strong>Certificate Hash:</strong></div>
                                <div class="font-mono text-xs break-all bg-white p-2 rounded border">${cryptoEvidence.certificateFingerprint}</div>
                            </div>
                        </div>
                    `;
                }
                
                statusHtml += '</div>';
                
                const allValid = verification.isOwner && verification.integrityValid;
                
                Swal.fire({
                    title: allValid ? '✅ Ownership Verified' : '❌ Verification Issues',
                    html: statusHtml,
                    icon: allValid ? 'success' : 'warning',
                    confirmButtonColor: allValid ? '#10B981' : '#F59E0B',
                    confirmButtonText: 'Close'
                });
            } else {
                handleSecurityError(new Error(data.message), 'Ownership Verification');
            }
        })
        .catch(error => {
            console.error('Ownership verification error:', error);
            handleSecurityError(error, 'Ownership Verification');
        });
    }

    function downloadOwnershipProof(blockchainCertId) {
        Swal.fire({
            title: 'Generating Proof...',
            text: 'Creating ownership proof document',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const proofContent = {
            certificateId: blockchainCertId,
            owner: '{{ Auth::user()->name ?? "Vian" }}',
            generatedAt: new Date().toISOString(),
            proofType: 'BLOCKCHAIN_OWNERSHIP_PROOF'
        };
        
        const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(proofContent, null, 2));
        const downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", `ownership_proof_${blockchainCertId}.json`);
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
        
        Swal.fire('Success', 'Ownership proof downloaded successfully!', 'success');
    }

    function checkIntegrity(blockchainCertId) {
        Swal.fire({
            title: 'Checking Integrity...',
            html: `
                <div class="flex items-center justify-center space-x-2">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
                    <span>Verifying certificate integrity with blockchain hash</span>
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false
        });
        
        fetch(`/api/certificates/energy-integrity/ENERGI_PLTA_1762385181_195`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const integrity = data.data.integrity;
                
                let statusHtml = '<div class="text-left space-y-3">';
                
                // Integrity status
                const integrityIcon = integrity.isValid ? '✅' : '❌';
                const integrityClass = integrity.isValid ? 'text-green-600' : 'text-red-600';
                const integrityText = integrity.isValid ? 'VALID' : 'COMPROMISED';
                
                statusHtml += `<div class="flex items-center justify-between"><span class="flex items-center"><span class="mr-2">${integrityIcon}</span>Integrity:</span><span class="font-semibold ${integrityClass}">${integrityText}</span></div>`;
                
                if (!integrity.isValid) {
                    statusHtml += `
                        <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded">
                            <div class="text-xs text-red-800">
                                <strong>⚠️ SECURITY ALERT:</strong> Certificate integrity check failed. This may indicate data tampering or corruption.
                            </div>
                        </div>
                    `;
                }
                
                // Hash comparison if available
                if (integrity.originalHash && integrity.recalculatedHash) {
                    statusHtml += `
                        <div class="mt-4 p-3 bg-gray-50 rounded">
                            <div class="text-xs space-y-2">
                                <div><strong>Hash Comparison:</strong></div>
                                <div>
                                    <div class="mb-1"><strong>Original:</strong></div>
                                    <div class="font-mono text-xs break-all bg-white p-2 rounded border">${integrity.originalHash}</div>
                                </div>
                                <div>
                                    <div class="mb-1"><strong>Current:</strong></div>
                                    <div class="font-mono text-xs break-all bg-white p-2 rounded border">${integrity.recalculatedHash}</div>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                statusHtml += '</div>';
                
                Swal.fire({
                    title: integrity.isValid ? '✅ Integrity Verified' : '🚨 Integrity Compromised',
                    html: statusHtml,
                    icon: integrity.isValid ? 'success' : 'error',
                    confirmButtonColor: integrity.isValid ? '#10B981' : '#EF4444',
                    confirmButtonText: integrity.isValid ? 'Excellent!' : 'Report Issue'
                });
            } else {
                handleSecurityError(new Error(data.message), 'Integrity Check');
            }
        })
        .catch(error => {
            console.error('Integrity check error:', error);
            handleSecurityError(error, 'Integrity Check');
        });
    }

    function handleSecurityError(error, context) {
        let errorType = 'UNKNOWN';
        let errorLevel = 'HIGH';
        let errorMessage = error.message || 'An unknown security error occurred';

        if (errorMessage.includes('DUPLICATION')) {
            errorType = 'DUPLICATE_CERTIFICATE';
            errorLevel = 'CRITICAL';
        } else if (errorMessage.includes('INTEGRITY')) {
            errorType = 'INTEGRITY_FAILURE';
            errorLevel = 'CRITICAL';
        } else if (errorMessage.includes('OWNERSHIP')) {
            errorType = 'OWNERSHIP_MISMATCH';
            errorLevel = 'HIGH';
        } else if (errorMessage.includes('TAMPERING')) {
            errorType = 'TAMPERING_DETECTED';
            errorLevel = 'CRITICAL';
        }

        const errorHtml = `
            <div class="text-left space-y-3">
                <div class="flex items-center space-x-2">
                    <span class="text-2xl">🚨</span>
                    <span class="font-bold text-red-600">${errorType.replace('_', ' ')}</span>
                </div>
                <p class="text-sm text-gray-700">${errorMessage}</p>
                <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded">
                    <div class="text-xs space-y-1">
                        <div><strong>Context:</strong> ${context}</div>
                        <div><strong>Security Level:</strong> <span class="text-red-600 font-bold">${errorLevel}</span></div>
                        <div><strong>Action Required:</strong> ${getSecurityAction(errorType)}</div>
                    </div>
                </div>
            </div>
        `;

        Swal.fire({
            title: 'Security Alert',
            html: errorHtml,
            icon: 'error',
            confirmButtonColor: '#EF4444',
            confirmButtonText: 'Understood',
            width: '500px'
        });
    }

    function getSecurityAction(errorType) {
        switch (errorType) {
            case 'DUPLICATE_CERTIFICATE':
                return 'Check if you already own a certificate for this energy data';
            case 'INTEGRITY_FAILURE':
                return 'Contact support - certificate data may be compromised';
            case 'OWNERSHIP_MISMATCH':
                return 'Verify that you are the rightful owner of this certificate';
            case 'TAMPERING_DETECTED':
                return 'Immediately contact support - security breach detected';
            default:
                return 'Contact support for assistance with this security issue';
        }
    }

    // --- Live fetch CouchDB status for displayed certificates ---
    document.addEventListener('DOMContentLoaded', function () {
        // Collect all certificate elements with status-text-* ids
        const statusSpans = document.querySelectorAll('[id^="status-text-"]');

        statusSpans.forEach(span => {
            const id = span.id.replace('status-text-', '');
            fetchAndUpdateCertificateStatus(id);
        });
    });

    function mapStatusToStyles(status) {
        // Normalize
        const s = (status || '').toUpperCase();
        if (s === 'COMPLETED' || s === 'PURCHASED') {
            return { dot: 'bg-green-500', textClass: 'text-green-600' };
        }
        if (s === 'CERTIFICATE_ISSUED' || s === 'ISSUED') {
            return { dot: 'bg-blue-500', textClass: 'text-blue-600' };
        }
        if (s === 'CERTIFICATE_REQUESTED' || s === 'REQUESTED') {
            return { dot: 'bg-yellow-500', textClass: 'text-yellow-600' };
        }
        return { dot: 'bg-gray-500', textClass: 'text-gray-600' };
    }

    function fetchAndUpdateCertificateStatus(certId) {
        if (!certId) return;

        // Use existing web route that already proxies CouchDB documents: /sync-certificate/{certId}
        fetch(`/sync-certificate/${encodeURIComponent(certId)}`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(async (r) => {
            // If response is not JSON, log and skip
            const text = await r.text();
            let payload = null;
            try {
                payload = JSON.parse(text);
            } catch (e) {
                console.warn('Non-JSON response fetching CouchDB status for', certId, r.status, text);
                return;
            }

            if (!payload || !payload.success) return;

            // For /sync-certificate route the CouchDB document is returned as `couch_data` or inside `couch_data` key
            const couch = payload.couch_data || payload.data || payload.couch || null;
            const status = couch && couch['certificateInfo'] && couch['certificateInfo']['status'] ? couch['certificateInfo']['status'] : null;

            const dot = document.getElementById('status-dot-' + certId);
            const text = document.getElementById('status-text-' + certId);

            if (!text) return;

            const styles = mapStatusToStyles(status);

            // Update dot classes
            if (dot) {
                dot.classList.remove('bg-green-500','bg-blue-500','bg-yellow-500','bg-gray-500');
                dot.classList.add(styles.dot);
            }

            // Update text content and color
            text.textContent = status || text.getAttribute('data-initial-status') || 'UNKNOWN';
            text.classList.remove('text-green-600','text-blue-600','text-yellow-600','text-gray-600');
            text.classList.add(styles.textClass);
        })
        .catch(err => {
            console.warn('Failed to fetch CouchDB status for', certId, err);
        });
    }
    </script>
</body>
</html>