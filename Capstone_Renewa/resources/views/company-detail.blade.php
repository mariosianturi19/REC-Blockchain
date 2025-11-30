<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Perusahaan: {{ $company->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    @include('layouts.partials.navbar')

    <main class="py-12 pt-24">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-xl p-8">
                <header class="text-center mb-10 border-b pb-8">
                    <h1 class="text-4xl font-bold text-gray-800">{{ $company->name }}</h1>
                    <p class="text-lg text-gray-600 mt-2">Riwayat Pembelian Renewable Energy Certificate (REC)</p>
                </header>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase">Alamat</p>
                        <p class="text-xl font-medium text-gray-800">{{ $company->address }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase">NIB</p>
                        <p class="text-xl font-mono text-gray-800">{{ $company->nib }}</p>
                    </div>
                </div>

                <div class="mt-10">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-certificate mr-2 text-green-600"></i>
                        Riwayat Pembelian REC
                    </h2>
                    
                    @if($company->user->orders->isEmpty())
                        <div class="text-center py-10 bg-gray-50 rounded-lg">
                            <i class="fas fa-inbox fa-3x text-gray-300 mb-4"></i>
                            <p class="text-gray-500">Perusahaan ini belum memiliki riwayat pembelian REC.</p>
                        </div>
                    @else
                        {{-- Summary Statistics --}}
                        @php
                            $totalOrders = $company->user->orders->count();
                            $totalCertificates = $company->user->orders->sum(fn($order) => $order->certificates->count());
                            $totalMwh = $company->user->orders->sum(fn($order) => $order->certificates->sum('amount_mwh'));
                            $totalSpent = $company->user->orders->sum('total_price');
                        @endphp
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                            <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                                <p class="text-sm text-gray-600 font-medium">Total Orders</p>
                                <p class="text-2xl font-bold text-green-700">{{ $totalOrders }}</p>
                            </div>
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                                <p class="text-sm text-gray-600 font-medium">Total Certificates</p>
                                <p class="text-2xl font-bold text-blue-700">{{ $totalCertificates }}</p>
                            </div>
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
                                <p class="text-sm text-gray-600 font-medium">Total Energy</p>
                                <p class="text-2xl font-bold text-purple-700">{{ number_format($totalMwh, 2) }}</p>
                                <p class="text-xs text-gray-600">MWh</p>
                            </div>
                            <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
                                <p class="text-sm text-gray-600 font-medium">Total Investment</p>
                                <p class="text-2xl font-bold text-yellow-700">Rp {{ number_format($totalSpent / 1000000, 1) }}M</p>
                            </div>
                        </div>

                        {{-- Orders and Certificates List --}}
                        <div class="space-y-6">
                            @foreach($company->user->orders as $order)
                                <div class="border rounded-xl bg-white shadow-sm hover:shadow-md transition-shadow overflow-hidden">
                                    {{-- Order Header --}}
                                    <div class="bg-gradient-to-r from-green-50 to-blue-50 p-5 border-b">
                                        <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                                            <div>
                                                <h3 class="font-bold text-lg text-gray-800">
                                                    <i class="fas fa-receipt mr-2 text-green-600"></i>
                                                    Order #{{ $order->order_uid }}
                                                </h3>
                                                <p class="text-sm text-gray-600 mt-1">
                                                    <i class="fas fa-calendar mr-2"></i>
                                                    {{ $order->created_at->format('d F Y, H:i') }}
                                                </p>
                                            </div>
                                            <div class="mt-3 md:mt-0 flex items-center gap-3">
                                                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-semibold rounded-full">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    {{ ucfirst($order->status) }}
                                                </span>
                                                <a href="{{ route('view-certificate-order', ['order_id' => $order->id]) }}" 
                                                   class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition-colors">
                                                    <i class="fas fa-eye mr-2"></i>View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Order Summary --}}
                                    <div class="p-5 bg-gray-50">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                            <div>
                                                <p class="text-xs text-gray-500 font-medium uppercase">Total Certificates</p>
                                                <p class="text-lg font-bold text-gray-800">{{ $order->certificates->count() }} certificates</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 font-medium uppercase">Total Energy</p>
                                                <p class="text-lg font-bold text-gray-800">{{ number_format($order->certificates->sum('amount_mwh'), 2) }} MWh</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 font-medium uppercase">Order Value</p>
                                                <p class="text-lg font-bold text-gray-800">Rp {{ number_format($order->total_price, 0, ',', '.') }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Certificates List --}}
                                    <div class="p-5">
                                        <h4 class="text-sm font-semibold text-gray-700 mb-3 uppercase">
                                            <i class="fas fa-certificate mr-2 text-blue-600"></i>
                                            Certificates in this Order
                                        </h4>
                                        
                                        <div class="space-y-3">
                                            @foreach($order->certificates as $certificate)
                                                @php
                                                    $powerPlant = $certificate->energyReport?->powerPlant;
                                                    $hasBlockchain = !empty($certificate->blockchain_cert_id);
                                                @endphp
                                                
                                                <div class="border rounded-lg p-4 bg-white hover:bg-gray-50 transition-colors">
                                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                                        {{-- Certificate Info --}}
                                                        <div class="flex-1">
                                                            <div class="flex items-center gap-2 mb-2">
                                                                <span class="font-mono text-sm font-bold text-blue-700">
                                                                    {{ $certificate->certificate_uid }}
                                                                </span>
                                                                @if($hasBlockchain)
                                                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded">
                                                                        <i class="fas fa-cube mr-1"></i>Blockchain
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            
                                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-gray-600">
                                                                <div>
                                                                    <i class="fas fa-industry mr-2 text-gray-400"></i>
                                                                    <span class="font-medium">{{ $powerPlant?->name ?? 'N/A' }}</span>
                                                                </div>
                                                                <div>
                                                                    <i class="fas fa-bolt mr-2 text-yellow-500"></i>
                                                                    <span class="font-medium">{{ number_format($certificate->amount_mwh, 2) }} MWh</span>
                                                                </div>
                                                                <div>
                                                                    <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>
                                                                    {{ $powerPlant?->location ?? 'N/A' }}
                                                                </div>
                                                                <div>
                                                                    <i class="fas fa-leaf mr-2 text-green-500"></i>
                                                                    {{ $powerPlant?->energy_source ?? 'N/A' }}
                                                                </div>
                                                            </div>

                                                            @if($hasBlockchain)
                                                                <div class="mt-2 pt-2 border-t border-gray-200">
                                                                    <p class="text-xs text-gray-500">
                                                                        <i class="fas fa-fingerprint mr-1"></i>
                                                                        Blockchain ID: 
                                                                        <span class="font-mono font-semibold text-blue-600">{{ $certificate->blockchain_cert_id }}</span>
                                                                    </p>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        {{-- Certificate Status --}}
                                                        <div class="text-center md:text-right">
                                                            @if($certificate->blockchain_status === 'COMPLETED')
                                                                <div class="inline-flex items-center px-3 py-2 bg-green-100 text-green-800 rounded-lg">
                                                                    <i class="fas fa-check-circle mr-2 text-green-600"></i>
                                                                    <div class="text-left">
                                                                        <p class="text-xs font-medium">Status</p>
                                                                        <p class="text-sm font-bold">COMPLETED</p>
                                                                    </div>
                                                                </div>
                                                            @elseif($certificate->blockchain_status === 'CERTIFICATE_ISSUED')
                                                                <div class="inline-flex items-center px-3 py-2 bg-blue-100 text-blue-800 rounded-lg">
                                                                    <i class="fas fa-certificate mr-2 text-blue-600"></i>
                                                                    <div class="text-left">
                                                                        <p class="text-xs font-medium">Status</p>
                                                                        <p class="text-sm font-bold">ISSUED</p>
                                                                    </div>
                                                                </div>
                                                            @else
                                                                <div class="inline-flex items-center px-3 py-2 bg-yellow-100 text-yellow-800 rounded-lg">
                                                                    <i class="fas fa-clock mr-2 text-yellow-600"></i>
                                                                    <div class="text-left">
                                                                        <p class="text-xs font-medium">Status</p>
                                                                        <p class="text-sm font-bold">{{ $certificate->blockchain_status ?? 'PENDING' }}</p>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    {{-- Generation Period --}}
                                                    @if($certificate->generation_start_date && $certificate->generation_end_date)
                                                        <div class="mt-3 pt-3 border-t border-gray-200 text-xs text-gray-600">
                                                            <i class="fas fa-calendar-alt mr-2"></i>
                                                            Generation Period: 
                                                            <span class="font-medium">
                                                                {{ \Carbon\Carbon::parse($certificate->generation_start_date)->format('d M Y') }} - 
                                                                {{ \Carbon\Carbon::parse($certificate->generation_end_date)->format('d M Y') }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                
                <div class="text-center mt-12">
                    <a href="{{ route('welcome') }}#track-section" class="text-green-600 hover:text-green-800 font-medium transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Kembali ke Halaman Utama
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>