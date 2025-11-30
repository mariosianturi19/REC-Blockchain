<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification Result</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    @include('layouts.partials.navbar')

    <main class="py-12 pt-24">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto">
                
                @if($verified)
                    {{-- SUCCESS: Certificate Verified --}}
                    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                        {{-- Header --}}
                        <div class="bg-gradient-to-r from-green-500 to-green-600 p-8 text-white text-center">
                            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full mb-4">
                                <i class="fas fa-check-circle text-5xl text-green-500"></i>
                            </div>
                            <h1 class="text-3xl font-bold mb-2">CERTIFICATE VERIFIED</h1>
                            <p class="text-green-100">This certificate is authentic and registered on the blockchain.</p>
                        </div>

                        {{-- Certificate Details --}}
                        <div class="p-8">
                            <div class="mb-8">
                                <h2 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                                    <i class="fas fa-certificate mr-3 text-blue-600"></i>
                                    Certificate Details
                                </h2>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <p class="text-sm text-gray-500 font-medium uppercase mb-1">Serial Number</p>
                                        <p class="text-lg font-mono font-bold text-gray-800">{{ $certificate['serial_number'] }}</p>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <p class="text-sm text-gray-500 font-medium uppercase mb-1">Blockchain Certificate ID</p>
                                        <p class="text-lg font-mono font-bold text-blue-700">{{ $certificate['blockchain_cert_id'] }}</p>
                                    </div>
                                    
                                    <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                                        <p class="text-sm text-gray-600 font-medium uppercase mb-1">Energy Amount</p>
                                        <p class="text-2xl font-bold text-green-700">{{ $certificate['amount_mwh']}} MWh</p>
                                    </div>
                                    
                                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                                        <p class="text-sm text-gray-600 font-medium uppercase mb-1">Status</p>
                                        <p class="text-xl font-bold text-blue-700">{{ $certificate['status'] }}</p>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <p class="text-sm text-gray-500 font-medium uppercase mb-1">Energy Source</p>
                                        <p class="text-lg font-semibold text-gray-800">
                                            <i class="fas fa-leaf mr-2 text-green-600"></i>
                                            {{ $certificate['energy_source'] }}
                                        </p>
                                    </div>
                                    
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <p class="text-sm text-gray-500 font-medium uppercase mb-1">Power Plant</p>
                                        <p class="text-lg font-semibold text-gray-800">
                                            <i class="fas fa-industry mr-2 text-gray-600"></i>
                                            {{ $certificate['power_plant'] }}
                                        </p>
                                    </div>
                                    
                                    @if($certificate['issued_at'])
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <p class="text-sm text-gray-500 font-medium uppercase mb-1">Issuance Date</p>
                                        <p class="text-lg font-semibold text-gray-800">
                                            <i class="fas fa-calendar-alt mr-2 text-purple-600"></i>
                                            {{ \Carbon\Carbon::parse($certificate['issued_at'])->format('d F Y') }}
                                        </p>
                                    </div>
                                    @endif
                                    
                                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <p class="text-sm text-gray-500 font-medium uppercase mb-1">Owner</p>
                                        <p class="text-lg font-semibold text-gray-800">
                                            <i class="fas fa-user-shield mr-2 text-indigo-600"></i>
                                            {{ $certificate['owner'] }}
                                            <span class="text-xs text-gray-500 ml-2">(Privacy Protected)</span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                                <a href="{{ route('welcome') }}" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition-colors text-center">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Back to Homepage
                                </a>
                            </div>
                        </div>
                    </div>
                    
                @else
                    {{-- ERROR: Certificate Not Found --}}
                    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                        {{-- Header --}}
                        <div class="bg-gradient-to-r from-red-500 to-red-600 p-8 text-white text-center">
                            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full mb-4">
                                <i class="fas fa-times-circle text-5xl text-red-500"></i>
                            </div>
                            <h1 class="text-3xl font-bold mb-2">❌ CERTIFICATE NOT FOUND</h1>
                            <p class="text-red-100">The certificate could not be verified in the blockchain.</p>
                        </div>

                        {{-- Error Message --}}
                        <div class="p-8 text-center">
                            <div class="bg-red-50 border-2 border-red-200 rounded-xl p-6 mb-6">
                                <i class="fas fa-exclamation-triangle text-4xl text-red-600 mb-4"></i>
                                <p class="text-lg text-gray-700 mb-2">{{ $message }}</p>
                                <p class="text-sm text-gray-600">
                                    Please check your certificate hash or serial number and try again.
                                </p>
                            </div>

                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6 text-left">
                                <p class="text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-info-circle mr-2 text-yellow-600"></i>
                                    Troubleshooting Tips:
                                </p>
                                <ul class="text-sm text-gray-600 space-y-1 ml-6">
                                    <li>• Make sure you entered the complete certificate hash or serial number</li>
                                    <li>• Check for typos or extra spaces</li>
                                    <li>• Certificate hashes are case-sensitive</li>
                                    <li>• Serial numbers should be in format: REC-2025-XXXXXX</li>
                                </ul>
                            </div>

                            <a href="{{ route('welcome') }}#verify-section" class="inline-block px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Try Again
                            </a>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </main>

    <style>
        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>
</body>
</html>
