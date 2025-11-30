<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Sertifikat REC - Order #{{ $order->order_uid }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .certificate-bg {
            background-image: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border: 1px solid #d1fae5;
        }
        .blockchain-verified {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        .blockchain-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px solid #3b82f6;
        }
    </style>
</head>
<body class="bg-gray-100">
    @include('layouts.partials.navbar')

    <main class="py-12 pt-24">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto certificate-bg rounded-2xl shadow-xl p-8 md:p-12">
                <header class="text-center mb-10 border-b-2 border-green-200 pb-8">
                    <div class="flex justify-center items-center mb-4">
                        <i class="fas fa-check-circle fa-4x text-green-500 mr-4"></i>
                        @if($blockchainVerified && $blockchainData)
                            <div class="blockchain-verified px-4 py-2 rounded-full text-sm font-bold">
                                <i class="fas fa-shield-check mr-2"></i>BLOCKCHAIN VERIFIED
                            </div>
                        @endif
                    </div>
                    <h1 class="text-4xl font-bold text-gray-800">Sertifikat Energi Terbarukan</h1>
                    <p class="text-lg text-gray-600 mt-2">Bukti Kontribusi Terhadap Energi Bersih</p>
                </header>

                {{-- BLOCKCHAIN VERIFICATION SECTION --}}
                @if($blockchainVerified && $blockchainData)
                <div class="blockchain-card rounded-xl p-6 mb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-blue-800">
                            <i class="fas fa-cube mr-2"></i>Verifikasi Blockchain
                        </h3>
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                            <i class="fas fa-check mr-1"></i>Terverifikasi
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-semibold text-gray-600">Transaction ID:</span>
                            <div class="font-mono text-blue-700 break-all">{{ $blockchainData['blockchain_info']['transaction_id'] }}</div>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-600">Verification Time:</span>
                            <div class="text-gray-800">{{ date('d M Y, H:i', strtotime($blockchainData['blockchain_info']['verification_time'])) }}</div>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-600">Certificate ID:</span>
                            <div class="font-mono text-blue-700">{{ $blockchainData['blockchain_info']['certificate_id'] }}</div>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-600">Type:</span>
                            <div class="text-gray-800">{{ $blockchainData['blockchain_info']['type'] }}</div>
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            Data ini telah disimpan secara permanen di blockchain dan tidak dapat diubah, memberikan jaminan transparansi dan keaslian.
                        </p>
                    </div>
                </div>
                @elseif($blockchainVerified === false)
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-8">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-3"></i>
                        <div>
                            <h4 class="font-semibold text-yellow-800">Verifikasi Blockchain Tidak Tersedia</h4>
                            <p class="text-sm text-yellow-700">Data REC tersedia di database namun belum terverifikasi blockchain.</p>
                        </div>
                    </div>
                </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase">Diterbitkan Untuk</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $order->buyer->company->name ?? 'Informasi Perusahaan Tidak Tersedia' }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase">Order ID</p>
                        <p class="text-2xl font-mono text-green-700 bg-green-100 p-2 rounded-lg inline-block">{{ $order->order_uid }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase">Tanggal Pembelian</p>
                        <p class="text-xl font-medium text-gray-800">{{ $order->created_at->format('d F Y') }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase">Total Energi Terverifikasi</p>
                        <p class="text-xl font-bold text-gray-800">{{ number_format($totalMwh, 2, ',', '.') }} MWh</p>
                    </div>
                </div>

                <div class="mt-10">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Rincian Sumber Energi</h2>
                    <div class="space-y-4">
                        @foreach($order->certificates as $certificate)
                        <div class="p-4 border rounded-lg bg-white/50">
                            <p class="font-semibold text-gray-800">{{ $certificate->energyReport->powerPlant->name }}</p>
                            <div class="flex justify-between items-center text-sm text-gray-600 mt-1">
                                <span><i class="fas fa-plug mr-2"></i>Tipe: {{ $certificate->energyReport->powerPlant->energy_type }}</span>
                                <span class="font-bold text-green-700">{{ number_format($certificate->amount_mwh, 2, ',', '.') }} MWh</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- BLOCKCHAIN HISTORY SECTION --}}
                @if($blockchainHistory && count($blockchainHistory) > 0)
                <div class="mt-10">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-history mr-2"></i>Riwayat Blockchain
                    </h2>
                    <div class="bg-white rounded-lg border overflow-hidden">
                        @foreach($blockchainHistory as $history)
                        <div class="p-4 border-b last:border-b-0 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-semibold text-gray-800">{{ $history['action'] ?? 'Transaction' }}</p>
                                    <p class="text-sm text-gray-600">{{ $history['timestamp'] ?? 'Unknown time' }}</p>
                                </div>
                                <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded">
                                    {{ substr($history['txId'] ?? 'N/A', 0, 8) }}...
                                </span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                
                <div class="text-center mt-12">
                    <a href="{{ route('welcome') }}" class="text-green-600 hover:text-green-800 font-medium transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Kembali ke Halaman Utama
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>