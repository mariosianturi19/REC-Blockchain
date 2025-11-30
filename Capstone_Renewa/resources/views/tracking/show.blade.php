@extends('layouts.app')

@section('title', 'REC Certificate Tracking')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">REC Certificate Tracking</h1>
                    <p class="text-gray-600 mt-2">Certificate ID: {{ $certificate->certificate_id }}</p>
                </div>
                <div class="flex items-center space-x-2">
                    @if($blockchainStatus['verified'])
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            Blockchain Verified
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            Not Verified
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Certificate Details -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Certificate Information</h2>
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Certificate ID:</span>
                        <span class="font-medium">{{ $certificate->certificate_id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Energy Amount:</span>
                        <span class="font-medium">{{ number_format($certificate->energy_amount) }} MWh</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Generation Date:</span>
                        <span class="font-medium">{{ $certificate->generation_date->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Issue Date:</span>
                        <span class="font-medium">{{ $certificate->issue_date->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status:</span>
                        <span class="px-2 py-1 rounded text-sm font-medium 
                            @if($certificate->status === 'active') bg-green-100 text-green-800
                            @elseif($certificate->status === 'retired') bg-gray-100 text-gray-800
                            @else bg-yellow-100 text-yellow-800 @endif">
                            {{ ucfirst($certificate->status) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Blockchain Status -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Blockchain Status</h2>
                <div class="space-y-4">
                    @if($blockchainStatus['verified'])
                        <div class="flex justify-between">
                            <span class="text-gray-600">Transaction Hash:</span>
                            <span class="font-mono text-sm truncate">{{ $blockchainStatus['transactionHash'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Block Number:</span>
                            <span class="font-medium">{{ $blockchainStatus['blockNumber'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Timestamp:</span>
                            <span class="font-medium">{{ $blockchainStatus['timestamp'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Network:</span>
                            <span class="font-medium">{{ $blockchainStatus['network'] }}</span>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.728-.833-2.498 0L4.316 15.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Not on Blockchain</h3>
                            <p class="mt-1 text-sm text-gray-500">This certificate has not been recorded on the blockchain yet.</p>
                            @if(auth()->user() && auth()->user()->hasRole('admin'))
                                <button onclick="addToBlockchain('{{ $certificate->certificate_id }}')" 
                                        class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                    Add to Blockchain
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Generator Information -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Generator Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Generator Name:</span>
                        <span class="font-medium">{{ $certificate->generator->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Location:</span>
                        <span class="font-medium">{{ $certificate->generator->location }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Technology:</span>
                        <span class="font-medium">{{ $certificate->generator->technology_type }}</span>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Capacity:</span>
                        <span class="font-medium">{{ number_format($certificate->generator->capacity) }} MW</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Commissioning Date:</span>
                        <span class="font-medium">{{ $certificate->generator->commissioning_date->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction History -->
        @if($certificate->transactions->count() > 0)
        <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Transaction History</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($certificate->transactions as $transaction)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $transaction->created_at->format('M d, Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    @if($transaction->type === 'issue') bg-blue-100 text-blue-800
                                    @elseif($transaction->type === 'transfer') bg-yellow-100 text-yellow-800
                                    @elseif($transaction->type === 'retire') bg-red-100 text-red-800
                                    @endif">
                                    {{ ucfirst($transaction->type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $transaction->from_entity ?? 'System' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $transaction->to_entity }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ number_format($transaction->amount) }} MWh
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
async function addToBlockchain(certificateId) {
    try {
        const response = await fetch(`/api/certificates/${certificateId}/add-to-blockchain`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        if (response.ok) {
            location.reload();
        } else {
            alert('Failed to add certificate to blockchain');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while adding to blockchain');
    }
}
</script>
@endpush
@endsection