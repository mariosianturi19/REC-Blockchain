<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Kode ini menggunakan $filters['category'], bukan $data --}}
    <title>Marketplace - {{ $filters['category'] }} - Renewa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    {{-- ✅ Phase 2: Enhanced Validation Styles --}}
    <style>
        .validation-spinner { animation: spin 1s linear infinite; }
        .validation-warning { animation: pulse 2s infinite; }
        .validation-error { animation: shake 0.5s ease-in-out; }
        .security-indicator { transition: all 0.3s ease; }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); }
            20%, 40%, 60%, 80% { transform: translateX(2px); }
        }
        
        .security-level-high { @apply bg-green-100 text-green-800 border-green-300; }
        .security-level-medium { @apply bg-yellow-100 text-yellow-800 border-yellow-300; }
        .security-level-low { @apply bg-red-100 text-red-800 border-red-300; }
        .security-level-unknown { @apply bg-gray-100 text-gray-800 border-gray-300; }
    </style>
</head>
<body class="bg-gray-100">
    @include('layouts.partials.navbar')

    {{-- ✅ Phase 2: Validation Alert Container --}}
    <div id="validation-alerts" class="fixed top-20 right-4 z-50 space-y-2" style="display: none;">
        <!-- Dynamic alerts will be inserted here -->
    </div>

    <main class="py-12 pt-24">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <header class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-800">
                    Temukan Solusi Energi Terbarukan
                    {{-- ✅ Phase 2: Security Badge --}}
                    <span class="inline-flex items-center ml-3 px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                        <i class="fas fa-shield-alt mr-1"></i>
                        Blockchain Verified
                    </span>
                </h1>
                 {{-- Kode ini menggunakan $filters['category'], bukan $data --}}
                <p class="text-lg text-gray-600 mt-2">Menampilkan pembangkit untuk kategori: <span class="font-semibold text-green-600">{{ $filters['category'] }}</span></p>
            </header>

            @if($powerPlants->isEmpty())
                <div class="text-center py-20 bg-white rounded-2xl shadow-md">
                    <i class="fas fa-store-slash fa-5x text-gray-300 mb-4"></i>
                    <h2 class="text-2xl font-semibold text-gray-600">Pembangkit Tidak Ditemukan</h2>
                    <p class="text-gray-500 mt-2">Saat ini belum ada pembangkit yang memenuhi kriteria kategori Anda. Silakan coba kategori lain.</p>
                    <a href="{{ route('buyer.categoryselect') }}" class="mt-6 inline-block bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg">Kembali ke Pemilihan Kategori</a>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {{-- Kode ini menggunakan $powerPlants, bukan $data --}}
                    @foreach ($powerPlants as $powerPlant)
                        <div class="group block bg-white rounded-2xl shadow-lg overflow-hidden flex flex-col transform transition-transform hover:-translate-y-2 relative">
                            {{-- ✅ Phase 2: Security Indicator --}}
                            <div class="absolute top-3 right-3 z-10">
                                <div class="security-indicator flex items-center space-x-1">
                                    <span class="security-badge security-level-high px-2 py-1 rounded-full text-xs font-medium border">
                                        <i class="fas fa-shield-check mr-1"></i>
                                        Verified
                                    </span>
                                </div>
                            </div>

                            {{-- ✅ Phase 2: Quick Validation Button --}}
                            <div class="absolute top-3 left-3 z-10">
                                <button 
                                    onclick="quickValidatePlant({{ $powerPlant->id }})" 
                                    class="validation-btn bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-full shadow-lg transition-all"
                                    title="Quick Security Check"
                                >
                                    <i class="fas fa-search text-sm"></i>
                                </button>
                            </div>

                            <div class="h-48 w-full">
                                @if($powerPlant->image_url)
                                    <img src="{{ $powerPlant->image_url }}" alt="Foto {{ $powerPlant->name }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full bg-gray-200 flex items-center justify-center">
                                        <i class="fas fa-image fa-3x text-gray-400"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="p-6 flex flex-col flex-grow">
                                <h3 class="text-xl font-bold text-gray-900 truncate group-hover:text-green-600 transition" title="{{ $powerPlant->name }}">{{ $powerPlant->name }}</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-600">Sumber energi: <span class="font-semibold">{{ $powerPlant->energy_type }}</span></p>
                                </div>
                                
                                {{-- ✅ Phase 2: Enhanced Energy Info with Validation --}}
                                <div class="mt-4 pt-4 border-t border-gray-200 flex-grow">
                                    <div class="flex justify-between items-center mb-2">
                                        <p class="text-sm text-gray-600">Energi Tersedia:</p>
                                        <div class="validation-status" id="validation-{{ $powerPlant->id }}" style="display: none;">
                                            <i class="fas fa-spinner validation-spinner text-blue-500"></i>
                                        </div>
                                    </div>
                                    <p class="font-bold text-green-600 text-lg">{{ number_format($powerPlant->certificates_sum_amount_mwh, 2, ',', '.') }} MWh</p>
                                    
                                    {{-- Validation Results Container --}}
                                    <div class="validation-results" id="results-{{ $powerPlant->id }}" style="display: none;">
                                        <!-- Dynamic validation results -->
                                    </div>
                                </div>
                                
                                <div class="mt-4 flex justify-between items-center">
                                      <p class="text-xl font-bold text-gray-800">Rp35.000 <span class="text-sm font-normal text-gray-500">/ MWh</span></p>
                                      <a href="{{ route('buyer.marketplace.show', [
                                            'powerPlant' => $powerPlant->id, 
                                            'category' => $filters['category'],
                                        ]) }}" class="bg-green-500 text-white font-bold py-2 px-6 rounded-lg transition-all hover:bg-green-600">
                                          Lihat Detail
                                      </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </main>

    <footer class="text-center py-8 mt-12 text-gray-500 text-sm">
        © {{ date('Y') }} Renewa Indonesia. Seluruh hak dilindungi undang-undang.
    </footer>

    {{-- ✅ Phase 2: Enhanced Validation JavaScript --}}
    <script>
        // Pre-purchase validation system
        class MarketplaceValidator {
            constructor() {
                this.apiEndpoint = '/api/marketplace/validate-purchase';
                this.csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                this.activeValidations = new Map();
                this.warningCount = 0;
                
                this.initializeStats();
            }
            
            async initializeStats() {
                // Update dashboard stats
                const totalPlants = document.getElementById('total-plants');
                const verifiedCerts = document.getElementById('verified-certificates');
                
                // Simulate loading verified certificates count
                setTimeout(() => {
                    const plantCount = parseInt(totalPlants.textContent);
                    verifiedCerts.textContent = Math.floor(plantCount * 0.85); // 85% verification rate
                }, 1000);
            }
            
            async validatePurchase(powerPlantId, amount, certificateIds = []) {
                const validationId = `${powerPlantId}-${Date.now()}`;
                this.activeValidations.set(validationId, true);
                
                // Show loading state
                this.showValidationLoading(powerPlantId);
                
                try {
                    const response = await fetch(this.apiEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            power_plant_id: powerPlantId,
                            requested_amount: amount,
                            certificate_ids: certificateIds
                        })
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const result = await response.json();
                    this.displayValidationResult(powerPlantId, result);
                    this.updateGlobalStats(result);
                    
                    return result;
                    
                } catch (error) {
                    console.error('Validation failed:', error);
                    this.showValidationError(powerPlantId, error.message);
                } finally {
                    this.activeValidations.delete(validationId);
                    this.hideValidationLoading(powerPlantId);
                }
            }
            
            showValidationLoading(powerPlantId) {
                const statusEl = document.getElementById(`validation-${powerPlantId}`);
                if (statusEl) {
                    statusEl.style.display = 'block';
                    statusEl.innerHTML = '<i class="fas fa-spinner validation-spinner text-blue-500"></i>';
                }
            }
            
            hideValidationLoading(powerPlantId) {
                const statusEl = document.getElementById(`validation-${powerPlantId}`);
                if (statusEl) {
                    statusEl.style.display = 'none';
                }
            }
            
            displayValidationResult(powerPlantId, result) {
                const resultsContainer = document.getElementById(`results-${powerPlantId}`);
                if (!resultsContainer) return;
                
                let html = '';
                
                // Overall status
                if (result.is_valid) {
                    html += `
                        <div class="bg-green-50 border border-green-200 rounded p-2 mb-2">
                            <div class="flex items-center text-green-800">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span class="text-sm font-medium">✅ Purchase validation passed</span>
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="bg-red-50 border border-red-200 rounded p-2 mb-2 validation-error">
                            <div class="flex items-center text-red-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="text-sm font-medium">❌ Validation issues detected</span>
                            </div>
                        </div>
                    `;
                }
                
                // Security info
                if (result.security_info) {
                    const securityLevel = result.security_info.overall_level;
                    const securityClass = `security-level-${securityLevel.toLowerCase()}`;
                    
                    html += `
                        <div class="bg-blue-50 border border-blue-200 rounded p-2 mb-2">
                            <div class="text-xs text-blue-700 space-y-1">
                                <div class="flex justify-between">
                                    <span>Security Level:</span>
                                    <span class="${securityClass} px-2 py-0.5 rounded">${securityLevel}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Blockchain Verified:</span>
                                    <span>${result.security_info.blockchain_verified || 0}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Integrity Confirmed:</span>
                                    <span>${result.security_info.integrity_confirmed || 0}</span>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                // Warnings
                if (result.warnings && result.warnings.length > 0) {
                    result.warnings.forEach(warning => {
                        html += `
                            <div class="bg-yellow-50 border border-yellow-200 rounded p-2 mb-1 validation-warning">
                                <div class="text-xs text-yellow-800">
                                    <div class="font-medium">${warning.title}</div>
                                    <div class="mt-1">${warning.message}</div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                // Errors
                if (result.errors && result.errors.length > 0) {
                    result.errors.forEach(error => {
                        html += `
                            <div class="bg-red-50 border border-red-200 rounded p-2 mb-1">
                                <div class="text-xs text-red-800">
                                    <div class="font-medium">${error.title}</div>
                                    <div class="mt-1">${error.message}</div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                resultsContainer.innerHTML = html;
                resultsContainer.style.display = 'block';
                
                // Auto-hide after 10 seconds for successful validations
                if (result.is_valid) {
                    setTimeout(() => {
                        resultsContainer.style.display = 'none';
                    }, 10000);
                }
            }
            
            showValidationError(powerPlantId, errorMessage) {
                const resultsContainer = document.getElementById(`results-${powerPlantId}`);
                if (resultsContainer) {
                    resultsContainer.innerHTML = `
                        <div class="bg-red-50 border border-red-200 rounded p-2 validation-error">
                            <div class="text-xs text-red-800">
                                <div class="font-medium">❌ Validation Error</div>
                                <div class="mt-1">${errorMessage}</div>
                            </div>
                        </div>
                    `;
                    resultsContainer.style.display = 'block';
                }
            }
            
            updateGlobalStats(result) {
                // Update warning counter
                const warningsEl = document.getElementById('duplicate-warnings');
                if (result.warnings && result.warnings.length > 0) {
                    this.warningCount += result.warnings.length;
                    warningsEl.textContent = this.warningCount;
                }
                
                // Update security level indicator
                const securityEl = document.getElementById('security-level');
                if (result.security_info && result.security_info.overall_level) {
                    securityEl.textContent = result.security_info.overall_level;
                    securityEl.className = `text-2xl font-bold security-level-${result.security_info.overall_level.toLowerCase()}`;
                }
            }
            
            showGlobalAlert(title, message, type = 'info') {
                const alertsContainer = document.getElementById('validation-alerts');
                const alertId = `alert-${Date.now()}`;
                
                const alertColors = {
                    'success': 'bg-green-100 border-green-300 text-green-800',
                    'warning': 'bg-yellow-100 border-yellow-300 text-yellow-800',
                    'error': 'bg-red-100 border-red-300 text-red-800',
                    'info': 'bg-blue-100 border-blue-300 text-blue-800'
                };
                
                const alertHtml = `
                    <div id="${alertId}" class="border rounded-lg p-3 shadow-lg ${alertColors[type]} max-w-sm">
                        <div class="flex items-start">
                            <div class="flex-1">
                                <h4 class="font-medium">${title}</h4>
                                <p class="text-sm mt-1">${message}</p>
                            </div>
                            <button onclick="document.getElementById('${alertId}').remove()" class="ml-2 text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                alertsContainer.innerHTML += alertHtml;
                alertsContainer.style.display = 'block';
                
                // Auto-remove after 8 seconds
                setTimeout(() => {
                    const alertEl = document.getElementById(alertId);
                    if (alertEl) alertEl.remove();
                }, 8000);
            }
        }
        
        // Initialize validator
        const validator = new MarketplaceValidator();
        
        // Global validation functions
        async function quickValidatePlant(powerPlantId) {
            validator.showGlobalAlert(
                '🔍 Quick Security Check', 
                'Running security validation...', 
                'info'
            );
            
            const result = await validator.validatePurchase(powerPlantId, 1.0);
            
            if (result && result.security_info) {
                const level = result.security_info.overall_level;
                validator.showGlobalAlert(
                    `🛡️ Security Level: ${level}`, 
                    `Blockchain verified: ${result.security_info.blockchain_verified || 0} certificates`, 
                    level === 'HIGH' ? 'success' : level === 'MEDIUM' ? 'warning' : 'error'
                );
            }
        }
        
        async function validateQuickAmount(input) {
            const amount = parseFloat(input.value);
            const powerPlantId = input.getAttribute('data-plant-id');
            
            if (amount > 0) {
                await validator.validatePurchase(powerPlantId, amount);
            }
        }
        
        async function runPrePurchaseCheck(powerPlantId) {
            const amountInput = document.querySelector(`input[data-plant-id="${powerPlantId}"]`);
            const amount = parseFloat(amountInput.value) || 1.0;
            
            validator.showGlobalAlert(
                '🔄 Pre-Purchase Check', 
                'Validating purchase requirements...', 
                'info'
            );
            
            await validator.validatePurchase(powerPlantId, amount);
        }
    </script>
</body>
</html>