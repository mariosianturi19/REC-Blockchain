@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white rounded-lg p-6 mb-8">
            <h1 class="text-3xl font-bold mb-2">🏭 REC Blockchain Dashboard</h1>
            <p class="text-green-100">Complete 5-Step Renewable Energy Certificate Workflow</p>
        </div>

        <!-- Health Check Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <span class="mr-2">🔗</span> Blockchain Connection Status
            </h2>
            
            @if($health['status'] === 'healthy')
                <div class="flex items-center text-green-600">
                    <div class="w-3 h-3 bg-green-500 rounded-full mr-3 animate-pulse"></div>
                    <span class="font-medium">{{ $health['message'] }}</span>
                </div>
                <p class="text-sm text-gray-600 mt-2">API URL: {{ $health['api_url'] }}</p>
            @elseif($health['status'] === 'disabled')
                <div class="flex items-center text-yellow-600">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full mr-3"></div>
                    <span class="font-medium">{{ $health['message'] }}</span>
                </div>
            @else
                <div class="flex items-center text-red-600">
                    <div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
                    <span class="font-medium">{{ $health['message'] }}</span>
                </div>
            @endif
        </div>

        <!-- Quick Actions -->
        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <!-- Complete Workflow -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <span class="mr-2">🚀</span> Complete 5-Step Workflow
                </h3>
                <p class="text-gray-600 mb-4">Execute all 5 steps automatically: Submit → Verify → Request → Issue → Complete</p>
                <a href="{{ route('blockchain.complete-workflow-form') }}" 
                   class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200 inline-block">
                    Start Complete Workflow
                </a>
            </div>

            <!-- Step-by-Step -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <span class="mr-2">📝</span> Step-by-Step Process
                </h3>
                <p class="text-gray-600 mb-4">Execute each step individually for better control and monitoring</p>
                <a href="{{ route('blockchain.submit-energy-form') }}" 
                   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 inline-block">
                    Start Step 1
                </a>
            </div>
        </div>

        <!-- Workflow Steps Overview -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-xl font-semibold mb-6 flex items-center">
                <span class="mr-2">🔄</span> 5-Step REC Workflow Overview
            </h3>
            
            <div class="space-y-4">
                <!-- Step 1 -->
                <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                    <div class="flex-shrink-0 w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center font-semibold mr-4">
                        1
                    </div>
                    <div class="flex-grow">
                        <h4 class="font-semibold text-gray-800">Submit Energy Data</h4>
                        <p class="text-gray-600 text-sm">Generator submits renewable energy production data (PENDING)</p>
                    </div>
                    <a href="{{ route('blockchain.submit-energy-form') }}" 
                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Execute →
                    </a>
                </div>

                <!-- Step 2 -->
                <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                    <div class="flex-shrink-0 w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center font-semibold mr-4">
                        2
                    </div>
                    <div class="flex-grow">
                        <h4 class="font-semibold text-gray-800">Verify Energy Data</h4>
                        <p class="text-gray-600 text-sm">Issuer verifies the submitted energy data (VERIFIED)</p>
                    </div>
                    <a href="{{ route('blockchain.verify-energy-form') }}" 
                       class="text-green-600 hover:text-green-800 text-sm font-medium">
                        Execute →
                    </a>
                </div>

                <!-- Step 3 -->
                <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                    <div class="flex-shrink-0 w-8 h-8 bg-yellow-500 text-white rounded-full flex items-center justify-center font-semibold mr-4">
                        3
                    </div>
                    <div class="flex-grow">
                        <h4 class="font-semibold text-gray-800">Request Certificate</h4>
                        <p class="text-gray-600 text-sm">Generator requests REC certificate (CERTIFICATE_REQUESTED)</p>
                    </div>
                    <a href="{{ route('blockchain.request-certificate-form') }}" 
                       class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                        Execute →
                    </a>
                </div>

                <!-- Step 4 -->
                <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                    <div class="flex-shrink-0 w-8 h-8 bg-purple-500 text-white rounded-full flex items-center justify-center font-semibold mr-4">
                        4
                    </div>
                    <div class="flex-grow">
                        <h4 class="font-semibold text-gray-800">Issue Certificate</h4>
                        <p class="text-gray-600 text-sm">Issuer issues the REC certificate (CERTIFICATE_ISSUED)</p>
                    </div>
                    <a href="{{ route('blockchain.issue-certificate-form') }}" 
                       class="text-purple-600 hover:text-purple-800 text-sm font-medium">
                        Execute →
                    </a>
                </div>

                <!-- Step 5 -->
                <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                    <div class="flex-shrink-0 w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center font-semibold mr-4">
                        5
                    </div>
                    <div class="flex-grow">
                        <h4 class="font-semibold text-gray-800">Complete Certificate</h4>
                        <p class="text-gray-600 text-sm">Generator receives completed certificate (COMPLETED)</p>
                    </div>
                    <a href="{{ route('blockchain.complete-certificate-form') }}" 
                       class="text-red-600 hover:text-red-800 text-sm font-medium">
                        Execute →
                    </a>
                </div>
            </div>
        </div>

        <!-- Data Viewing -->
        <div class="grid md:grid-cols-2 gap-6">
            <!-- View Energy Data -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <span class="mr-2">⚡</span> View Energy Data
                </h3>
                <p class="text-gray-600 mb-4">View all submitted and verified energy data from blockchain</p>
                <a href="{{ route('blockchain.view-energy') }}" 
                   class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-200 inline-block">
                    View Energy Data
                </a>
            </div>

            <!-- View Certificates -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <span class="mr-2">🏆</span> View Certificates
                </h3>
                <p class="text-gray-600 mb-4">Search and view REC certificates from blockchain</p>
                <a href="{{ route('blockchain.view-certificate') }}" 
                   class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition duration-200 inline-block">
                    View Certificates
                </a>
            </div>
        </div>
    </div>
</div>
@endsection