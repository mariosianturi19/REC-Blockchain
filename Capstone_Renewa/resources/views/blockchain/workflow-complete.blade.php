@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Success Header -->
        <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg p-8 mb-8 text-center">
            <div class="text-6xl mb-4">🎉</div>
            <h1 class="text-3xl font-bold mb-2">Workflow Completed Successfully!</h1>
            <p class="text-green-100 text-lg">Your REC Certificate has been processed through all 5 steps</p>
        </div>

        @if(isset($result) && $result['success'])
        <!-- Workflow Results -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-6 flex items-center">
                <span class="mr-2">📊</span> Workflow Results
            </h2>
            
            <div class="grid md:grid-cols-2 gap-6 mb-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-2">🆔 Generated IDs</h3>
                    <div class="space-y-2 text-sm">
                        <p><strong>Energy ID:</strong> <code class="bg-blue-100 px-2 py-1 rounded">{{ $energy_id }}</code></p>
                        <p><strong>Certificate ID:</strong> <code class="bg-green-100 px-2 py-1 rounded">{{ $cert_id }}</code></p>
                    </div>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-800 mb-2">✅ Status</h3>
                    <div class="space-y-2 text-sm">
                        <p><strong>Steps Completed:</strong> 5/5</p>
                        <p><strong>Final Status:</strong> <span class="bg-green-500 text-white px-2 py-1 rounded text-xs">COMPLETED</span></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step-by-Step Results -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-6 flex items-center">
                <span class="mr-2">🔄</span> Step-by-Step Execution Results
            </h2>
            
            <div class="space-y-4">
                @if(isset($result['results']['step1']))
                <!-- Step 1 -->
                <div class="border-l-4 border-blue-500 bg-blue-50 p-4 rounded-r-lg">
                    <div class="flex items-center mb-2">
                        <span class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs mr-3">1</span>
                        <h4 class="font-semibold text-blue-800">Submit Energy Data</h4>
                        <span class="ml-auto bg-blue-500 text-white px-2 py-1 rounded text-xs">SUCCESS</span>
                    </div>
                    <p class="text-blue-700 text-sm ml-9">{{ $result['results']['step1']['message'] ?? 'Energy data submitted to blockchain' }}</p>
                </div>
                @endif

                @if(isset($result['results']['step2']))
                <!-- Step 2 -->
                <div class="border-l-4 border-green-500 bg-green-50 p-4 rounded-r-lg">
                    <div class="flex items-center mb-2">
                        <span class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-xs mr-3">2</span>
                        <h4 class="font-semibold text-green-800">Verify Energy Data</h4>
                        <span class="ml-auto bg-green-500 text-white px-2 py-1 rounded text-xs">SUCCESS</span>
                    </div>
                    <p class="text-green-700 text-sm ml-9">{{ $result['results']['step2']['message'] ?? 'Energy data verified by issuer' }}</p>
                </div>
                @endif

                @if(isset($result['results']['step3']))
                <!-- Step 3 -->
                <div class="border-l-4 border-yellow-500 bg-yellow-50 p-4 rounded-r-lg">
                    <div class="flex items-center mb-2">
                        <span class="w-6 h-6 bg-yellow-500 text-white rounded-full flex items-center justify-center text-xs mr-3">3</span>
                        <h4 class="font-semibold text-yellow-800">Request Certificate</h4>
                        <span class="ml-auto bg-yellow-500 text-white px-2 py-1 rounded text-xs">SUCCESS</span>
                    </div>
                    <p class="text-yellow-700 text-sm ml-9">{{ $result['results']['step3']['message'] ?? 'Certificate request submitted' }}</p>
                </div>
                @endif

                @if(isset($result['results']['step4']))
                <!-- Step 4 -->
                <div class="border-l-4 border-purple-500 bg-purple-50 p-4 rounded-r-lg">
                    <div class="flex items-center mb-2">
                        <span class="w-6 h-6 bg-purple-500 text-white rounded-full flex items-center justify-center text-xs mr-3">4</span>
                        <h4 class="font-semibold text-purple-800">Issue Certificate</h4>
                        <span class="ml-auto bg-purple-500 text-white px-2 py-1 rounded text-xs">SUCCESS</span>
                    </div>
                    <p class="text-purple-700 text-sm ml-9">{{ $result['results']['step4']['message'] ?? 'Certificate issued by authority' }}</p>
                </div>
                @endif

                @if(isset($result['results']['step5']))
                <!-- Step 5 -->
                <div class="border-l-4 border-red-500 bg-red-50 p-4 rounded-r-lg">
                    <div class="flex items-center mb-2">
                        <span class="w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs mr-3">5</span>
                        <h4 class="font-semibold text-red-800">Complete Certificate</h4>
                        <span class="ml-auto bg-red-500 text-white px-2 py-1 rounded text-xs">SUCCESS</span>
                    </div>
                    <p class="text-red-700 text-sm ml-9">{{ $result['results']['step5']['message'] ?? 'Certificate process completed' }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- What's Next -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 flex items-center text-blue-800">
                <span class="mr-2">🎯</span> What's Next?
            </h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="bg-white rounded-lg p-4 shadow-sm">
                    <h4 class="font-semibold text-gray-800 mb-2">📋 View Certificate Details</h4>
                    <p class="text-gray-600 text-sm mb-3">Check the complete certificate information stored on blockchain</p>
                    @if(isset($cert_id))
                    <a href="{{ route('blockchain.view-certificate', ['cert_id' => $cert_id]) }}" 
                       class="bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700 transition duration-200 inline-block">
                        View Certificate
                    </a>
                    @endif
                </div>

                <div class="bg-white rounded-lg p-4 shadow-sm">
                    <h4 class="font-semibold text-gray-800 mb-2">⚡ View Energy Data</h4>
                    <p class="text-gray-600 text-sm mb-3">Check the energy production data that was verified</p>
                    @if(isset($energy_id))
                    <a href="{{ route('blockchain.view-energy', ['energy_id' => $energy_id]) }}" 
                       class="bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700 transition duration-200 inline-block">
                        View Energy Data
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-4 justify-center">
            <a href="{{ route('blockchain.complete-workflow-form') }}" 
               class="bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Run Another Workflow
            </a>
            
            <a href="{{ route('blockchain.dashboard') }}" 
               class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                </svg>
                Back to Dashboard
            </a>

            <a href="{{ route('welcome') }}" 
               class="bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-700 transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                Home
            </a>
        </div>

        <!-- Blockchain Info -->
        <div class="bg-gray-50 rounded-lg p-4 mt-8 text-center">
            <p class="text-sm text-gray-600">
                <strong>🔗 Blockchain Network:</strong> 
                All data is permanently stored on Hyperledger Fabric blockchain network with CouchDB state database
            </p>
            <p class="text-xs text-gray-500 mt-1">
                Transaction hash and block information available in blockchain explorer
            </p>
        </div>
    </div>
</div>
@endsection