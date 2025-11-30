<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gradient-to-br from-green-50 to-blue-50 min-h-screen">
    @include('layouts.partials.navbar')

    <main class="py-12 pt-24">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-800 mb-2">
                    Sertifikat REC Anda
                </h1>
                <p class="text-gray-600">Order: <span class="font-semibold text-green-600">#{{ $order->order_uid }}</span></p>
            </div>

            @if($certificatesData && count($certificatesData) > 0)
                @foreach($certificatesData as $data)
                    @php
                        $certificate = $data['certificate'];
                        $blockchainData = $data['blockchain_data'];
                        $certInfo = $blockchainData['certificateInfo'] ?? [];
                        $security = $blockchainData['security'] ?? [];
                        $compliance = $blockchainData['compliance'] ?? [];
                        $auditTrail = $blockchainData['auditTrail'] ?? [];
                    @endphp

                    <div class="max-w-5xl mx-auto mb-8">
                        <!-- Certificate Card -->
                        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border-2 border-green-200">
                            <!-- Header Section -->
                            <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white p-8">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h2 class="text-3xl font-bold mb-2">Renewable Energy Certificate</h2>
                                        <p class="text-green-100 text-sm">Blockchain-Verified REC</p>
                                    </div>
                                    <div class="text-right">
                                        <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2">
                                            <p class="text-xs text-green-100">Status</p>
                                            <p class="text-xl font-bold">
                                                {{ $certInfo['status'] ?? 'UNKNOWN' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Main Content -->
                            <div class="p-8">
                                <!-- Certificate ID Section -->
                                <div class="mb-8 pb-6 border-b border-gray-200">
                                    <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-certificate text-green-600 mr-2"></i>
                                        Certificate Information
                                    </h3>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="bg-gray-50 rounded-lg p-4">
                                            <p class="text-xs text-gray-500 mb-1">Certificate ID / Serial Number</p>
                                            <p class="font-mono text-sm font-semibold text-gray-800 break-all">
                                                {{ $certInfo['certificateId'] ?? $certificate->blockchain_cert_id }}
                                            </p>
                                        </div>
                                        
                                        <div class="bg-gray-50 rounded-lg p-4">
                                            <p class="text-xs text-gray-500 mb-1">Pembangkit (Generator)</p>
                                            @php
                                                // Prefer Eloquent relation if it was eager-loaded: certificate->energyReport->powerPlant->name
                                                $powerPlantName = null;
                                                if (!empty($certificate->energyReport) && !empty($certificate->energyReport->powerPlant)) {
                                                    $powerPlantName = $certificate->energyReport->powerPlant->name;
                                                } else {
                                                    // Fallback: attempt to resolve from energy_report_id or energyReport->power_plant_id via direct DB lookup
                                                    $ppId = null;
                                                    if (!empty($certificate->energy_report_id)) {
                                                        // If certificate stores an energy_report_id
                                                        $energy = \DB::table('energy_reports')->where('id', $certificate->energy_report_id)->first();
                                                        if ($energy && !empty($energy->power_plant_id)) {
                                                            $ppId = $energy->power_plant_id;
                                                        }
                                                    } elseif (!empty($certificate->energyReport) && !empty($certificate->energyReport->power_plant_id)) {
                                                        $ppId = $certificate->energyReport->power_plant_id;
                                                    }

                                                    if ($ppId) {
                                                        $powerPlantName = \DB::table('power_plant')->where('id', $ppId)->value('name');
                                                    }
                                                }
                                            @endphp

                                            <p class="font-mono text-sm font-semibold text-blue-600">
                                                {{ $powerPlantName ?? 'Unknown Pembangkit' }}
                                            </p>
                                        </div>
                                        
                                        <div class="bg-gray-50 rounded-lg p-4">
                                            <p class="text-xs text-gray-500 mb-1">Energy Amount</p>
                                            <p class="text-2xl font-bold text-green-600">
                                                {{ number_format($certificate->amount_mwh, 2) }} MWh
                                            </p>
                                        </div>
                                        
                                        <div class="bg-gray-50 rounded-lg p-4">
                                            <p class="text-xs text-gray-500 mb-1">Issue Date</p>
                                            @php
                                                // Server-side: add 7 hours to the stored timestamp, then display date only (d M Y)
                                                $issued = \Carbon\Carbon::parse($certInfo['issuedDate'] ?? $certificate->created_at);
                                                $issuedGmt7DateOnly = $issued->setTimezone('UTC')->addHours(7)->format('d M Y');
                                            @endphp

                                            <p class="text-sm font-semibold text-gray-800">
                                                {{ $issuedGmt7DateOnly }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Blockchain Hash Section -->
                                <div class="mb-8 pb-6 border-b border-gray-200">
                                    <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                                        <i class="fas fa-fingerprint text-purple-600 mr-2"></i>
                                        Blockchain Hash
                                    </h3>
                                    
                                    <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-lg p-6 border border-purple-200">
                                        <div class="mb-4">
                                            <p class="text-xs text-gray-600 mb-2">Certificate Fingerprint (SHA-256)</p>
                                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                                <code class="text-xs font-mono text-gray-800 break-all leading-relaxed">
                                                    {{ $security['certificateFingerprint'] ?? $security['certificateHash'] ?? hash('sha256', $certificate->blockchain_cert_id) }}
                                                </code>
                                            </div>
                                        </div>
                                        
                                        @if(isset($security['antiDuplicationHash']))
                                        <div>
                                            <p class="text-xs text-gray-600 mb-2">Anti-Duplication Hash</p>
                                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                                <code class="text-xs font-mono text-gray-800 break-all leading-relaxed">
                                                    {{ $security['antiDuplicationHash'] }}
                                                </code>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-wrap gap-3 justify-center">
                                    <button 
                                        onclick="downloadCertificate('{{ $certificate->blockchain_cert_id }}')"
                                        class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-all shadow-lg hover:shadow-xl flex items-center space-x-2"
                                    >
                                        <i class="fas fa-download"></i>
                                        <span>Download Certificate</span>
                                    </button>
                                    
                                    <button 
                                        onclick="verifyOnBlockchain('{{ $certificate->blockchain_cert_id }}')"
                                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-all shadow-lg hover:shadow-xl flex items-center space-x-2"
                                    >
                                        <i class="fas fa-search"></i>
                                        <span>Verify on Blockchain</span>
                                    </button>
                                    
                                    <button 
                                        onclick="shareREC('{{ $certificate->blockchain_cert_id }}')"
                                        class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-3 px-6 rounded-lg transition-all shadow-lg hover:shadow-xl flex items-center space-x-2"
                                    >
                                        <i class="fas fa-share-alt"></i>
                                        <span>Share REC</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="bg-gray-50 px-8 py-4 border-t border-gray-200">
                                <p class="text-xs text-gray-500 text-center">
                                    This certificate is cryptographically secured on the blockchain. 
                                    Hash verification ensures authenticity and prevents tampering.
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="max-w-2xl mx-auto text-center py-12">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-8">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-5xl mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">No Certificates Found</h3>
                        <p class="text-gray-600">This order doesn't have any blockchain-verified certificates yet.</p>
                    </div>
                </div>
            @endif

            <!-- Back Button -->
            <div class="max-w-5xl mx-auto text-center mt-8">
                <a href="{{ route('buyer.orders.show', $order->id) }}" 
                   class="inline-flex items-center space-x-2 text-gray-600 hover:text-gray-800 transition-colors">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Order Details</span>
                </a>
            </div>
        </div>
    </main>

    <script>
    function downloadCertificate(certId) {
        // Redirect to server endpoint that returns a generated PDF
        Swal.fire({
            title: 'Preparing PDF...',
            html: 'Preparing your certificate PDF for download',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        // Use a navigation to the download route so browser handles the file download
        const downloadUrl = `/certificate/${encodeURIComponent(certId)}/download`;
        // Small delay so the modal is visible briefly
        setTimeout(() => {
            window.location.href = downloadUrl;
        }, 300);
    }

    function verifyOnBlockchain(certId) {
        // Perform a force sync via the backend route, then present real data
        Swal.fire({
            title: 'Verifying on Blockchain...',
            html: `
                <div class="flex items-center justify-center space-x-2">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span>Fetching latest on-chain status and syncing to server</span>
                </div>
            `,
            allowOutsideClick: false,
            showConfirmButton: false
        });

        (async () => {
            try {
                const resp = await fetch(`/sync-certificate/${encodeURIComponent(certId)}`, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' }
                });

                const text = await resp.text();
                let data = null;
                try { data = JSON.parse(text); } catch (e) { throw new Error('Invalid server response'); }

                if (!data || data.success === false) {
                    const errMsg = (data && data.error) ? data.error : 'Sync failed or certificate not found';
                    throw new Error(errMsg);
                }

                // Prefer couch_data status if available, otherwise DB values
                const couch = data.couch_data || data.couchData || {};
                const cert = data.certificate || {};
                const newStatus = (couch.certificateInfo && couch.certificateInfo.status) || cert.new_status || cert.blockchain_status || cert.blockchain_status;

                // Build a small subset of the CouchDB document to display
                const subset = {
                    certificateId: couch.certificateId || couch.certificateID || certId,
                    certificateInfo: {
                        issuanceStandard: (couch.certificateInfo && couch.certificateInfo.issuanceStandard) || 'N/A',
                        status: (couch.certificateInfo && couch.certificateInfo.status) || (cert.blockchain_status || 'UNKNOWN'),
                        statusDescription: (couch.certificateInfo && couch.certificateInfo.statusDescription) || '',
                        type: (couch.certificateInfo && couch.certificateInfo.type) || ''
                    }
                };

                const prettySubset = JSON.stringify(subset, null, 2);

                const statusColor = (subset.certificateInfo.status === 'COMPLETED' || subset.certificateInfo.status === 'VERIFIED') ? 'text-green-600' : 'text-yellow-600';

                Swal.fire({
                    icon: (subset.certificateInfo.status === 'COMPLETED') ? 'success' : 'info',
                    title: 'Blockchain Verification Result',
                    html: `
                        <div class="text-left space-y-3">
                            <p class="text-sm text-gray-700"><strong>Certificate ID:</strong> ${subset.certificateId}</p>
                            <p class="text-sm text-gray-700"><strong>Status:</strong> <span class="${statusColor} font-bold">${subset.certificateInfo.status || 'UNKNOWN'}</span></p>
                            <p class="text-sm text-gray-700"><strong>Issuance Standard:</strong> ${subset.certificateInfo.issuanceStandard}</p>
                            <p class="text-sm text-gray-700"><strong>Type:</strong> ${subset.certificateInfo.type}</p>
                            <p class="text-sm text-gray-700"><strong>Description:</strong> ${subset.certificateInfo.statusDescription || '-'}</p>
                            <p class="text-sm text-gray-700"><strong>Source:</strong> Server-synced CouchDB</p>
                            <p class="text-sm text-gray-700"><strong>Updated At:</strong> ${new Date().toLocaleString()}</p>
                        </div>
                    `,
                    confirmButtonColor: '#10B981',
                    width: 700
                });

            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Verification failed', text: err.message || 'Unknown error', confirmButtonColor: '#EF4444' });
            }
        })();
    }

    function shareREC(certId) {
        Swal.fire({
            title: 'Share Your REC',
            html: `
                <div class="text-left space-y-3">
                    <p class="text-sm text-gray-700 mb-4">Share your Renewable Energy Certificate:</p>
                    
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-600 mb-1">Certificate Link:</p>
                        <input 
                            type="text" 
                            value="${window.location.origin}/verify-certificate?cert_id=${certId}" 
                            readonly
                            class="w-full text-xs font-mono p-2 border rounded"
                            onclick="this.select()"
                        >
                    </div>
                    
                    <div class="flex gap-2 mt-4">
                        <button onclick="copyToClipboard('${certId}')" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 text-sm">
                            <i class="fas fa-copy mr-1"></i> Copy Link
                        </button>
                        <button onclick="shareViaEmail('${certId}')" class="flex-1 bg-green-600 text-white py-2 px-4 rounded hover:bg-green-700 text-sm">
                            <i class="fas fa-envelope mr-1"></i> Email
                        </button>
                    </div>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true
        });
    }

    function copyToClipboard(certId) {
                        const link = `${window.location.origin}/verify-certificate?cert_id=${certId}`;
        navigator.clipboard.writeText(link);
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Certificate link copied to clipboard',
            timer: 2000,
            showConfirmButton: false
        });
    }

    function shareViaEmail(certId) {
        const subject = encodeURIComponent('My Renewable Energy Certificate');
    const body = encodeURIComponent(`Check out my verified REC:\n\n${window.location.origin}/verify-certificate?cert_id=${certId}`);
        window.location.href = `mailto:?subject=${subject}&body=${body}`;
    }

    // Note: issue-date client-side conversion removed — date-only is rendered server-side now.
    </script>
</body>
</html>
