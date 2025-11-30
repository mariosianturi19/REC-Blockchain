@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white rounded-lg p-6 mb-8">
            <h1 class="text-2xl font-bold mb-2">🚀 Complete 5-Step REC Workflow</h1>
            <p class="text-green-100">Execute all steps automatically: Submit → Verify → Request → Issue → Complete</p>
        </div>

        <!-- Workflow Overview -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <span class="mr-2">📋</span> What will happen:
            </h3>
            <div class="space-y-2 text-sm">
                <div class="flex items-center">
                    <span class="w-6 h-6 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs mr-3">1</span>
                    <span>Submit energy data to blockchain (PENDING)</span>
                </div>
                <div class="flex items-center">
                    <span class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-xs mr-3">2</span>
                    <span>Issuer automatically verifies data (VERIFIED)</span>
                </div>
                <div class="flex items-center">
                    <span class="w-6 h-6 bg-yellow-500 text-white rounded-full flex items-center justify-center text-xs mr-3">3</span>
                    <span>Request REC certificate (CERTIFICATE_REQUESTED)</span>
                </div>
                <div class="flex items-center">
                    <span class="w-6 h-6 bg-purple-500 text-white rounded-full flex items-center justify-center text-xs mr-3">4</span>
                    <span>Issuer issues certificate (CERTIFICATE_ISSUED)</span>
                </div>
                <div class="flex items-center">
                    <span class="w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs mr-3">5</span>
                    <span>Complete certificate process (COMPLETED)</span>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <form action="{{ route('blockchain.complete-workflow') }}" method="POST" class="space-y-6">
                @csrf
                
                <!-- Amount KWh -->
                <div>
                    <label for="amount_kwh" class="block text-sm font-medium text-gray-700 mb-2">
                        ⚡ Energy Amount (kWh) *
                    </label>
                    <input type="number" 
                           id="amount_kwh" 
                           name="amount_kwh" 
                           min="1" 
                           step="0.01"
                           value="{{ old('amount_kwh', '1000') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           placeholder="Enter energy amount in kWh"
                           required>
                    @error('amount_kwh')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Source Type -->
                <div>
                    <label for="source_type" class="block text-sm font-medium text-gray-700 mb-2">
                        🌱 Renewable Energy Source *
                    </label>
                    <select id="source_type" 
                            name="source_type" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            required>
                        <option value="">Select energy source</option>
                        <option value="Solar" {{ old('source_type') === 'Solar' ? 'selected' : '' }}>☀️ Solar</option>
                        <option value="Wind" {{ old('source_type') === 'Wind' ? 'selected' : '' }}>💨 Wind</option>
                        <option value="Hydro" {{ old('source_type') === 'Hydro' ? 'selected' : '' }}>🌊 Hydro</option>
                        <option value="Biomass" {{ old('source_type') === 'Biomass' ? 'selected' : '' }}>🌿 Biomass</option>
                    </select>
                    @error('source_type')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Location -->
                <div>
                    <label for="location" class="block text-sm font-medium text-gray-700 mb-2">
                        📍 Generation Location *
                    </label>
                    <input type="text" 
                           id="location" 
                           name="location" 
                           value="{{ old('location', 'Jakarta, Indonesia') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           placeholder="Enter generation location"
                           required>
                    @error('location')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Timestamp -->
                <div>
                    <label for="timestamp" class="block text-sm font-medium text-gray-700 mb-2">
                        🕒 Generation Timestamp *
                    </label>
                    <input type="datetime-local" 
                           id="timestamp" 
                           name="timestamp" 
                           value="{{ old('timestamp', now()->format('Y-m-d\TH:i')) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                           required>
                    @error('timestamp')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Warning -->
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>Important:</strong> This will execute all 5 steps automatically. 
                                The process may take 10-15 seconds to complete as it interacts with the blockchain network.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex space-x-4">
                    <button type="submit" 
                            class="flex-1 bg-gradient-to-r from-green-600 to-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:from-green-700 hover:to-blue-700 transition duration-200 flex items-center justify-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Execute Complete Workflow
                    </button>
                    
                    <a href="{{ route('blockchain.dashboard') }}" 
                       class="bg-gray-500 text-white py-3 px-6 rounded-lg font-semibold hover:bg-gray-600 transition duration-200">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Configuration Info -->
        <div class="bg-blue-50 rounded-lg p-4 mt-6">
            <h4 class="font-semibold text-blue-800 mb-2">📝 Configuration:</h4>
            <div class="text-sm text-blue-700 space-y-1">
                <p><strong>Generator ID:</strong> {{ config('app.default_generator_id') }}</p>
                <p><strong>Issuer ID:</strong> {{ config('app.default_issuer_id') }}</p>
                <p><strong>Blockchain API:</strong> {{ config('app.blockchain_api_url') }}</p>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-update timestamp every minute
setInterval(function() {
    const timestampInput = document.getElementById('timestamp');
    if (timestampInput && !timestampInput.value) {
        timestampInput.value = new Date().toISOString().slice(0, 16);
    }
}, 60000);

// Show loading state on form submit
document.querySelector('form').addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = `
        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        Processing Workflow...
    `;
    submitBtn.disabled = true;
});
</script>
@endsection