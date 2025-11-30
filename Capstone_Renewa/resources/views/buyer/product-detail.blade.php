<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail: {{ $powerPlant->name }} - Renewa</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    @include('layouts.partials.navbar')

    <main class="py-12 pt-24">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">

            {{-- ====================================================== --}}
            {{-- === BLOK BARU UNTUK MENAMPILKAN SEMUA JENIS ERROR === --}}
            {{-- ====================================================== --}}
            @if(session('error'))
                <div class="mb-6 max-w-4xl mx-auto p-4 bg-red-100 border border-red-200 rounded-xl text-red-700 text-sm">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-6 max-w-4xl mx-auto p-4 bg-red-100 border border-red-200 rounded-xl text-red-700 text-sm">
                    <strong>Terdapat kesalahan:</strong>
                    <ul class="list-disc list-inside mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            {{-- ====================================================== --}}

            <div class="bg-white rounded-2xl shadow-xl overflow-hidden max-w-4xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2">
                    <div class="w-full h-64 md:h-full bg-gray-200">
                        @if($powerPlant->image_url)
                            <img src="{{ $powerPlant->image_url }}" alt="Foto {{ $powerPlant->name }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fas fa-image fa-5x text-gray-400"></i>
                            </div>
                        @endif
                    </div>

                    <div class="p-8 flex flex-col justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">{{ $powerPlant->name }}</h1>
                            <p class="text-sm text-gray-500 mt-1">Dihasilkan oleh: <span class="font-medium">{{ $powerPlant->user->name }}</span></p>

                            <div class="mt-6 pt-4 border-t">
                                <p class="text-md text-gray-700">Energi Tersedia:</p>
                                <p class="text-4xl font-extrabold text-green-600">{{ number_format($availableMwh, 2, ',', '.') }} <span class="text-2xl font-semibold">MWh</span></p>
                            </div>
                        </div>

                        <div class="mt-8">
                            <form action="{{ route('buyer.checkout.process') }}" method="POST"> 
                                @csrf
                                <input type="hidden" name="power_plant_id" value="{{ $powerPlant->id }}">
                                <input type="hidden" name="min_purchase" value="{{ $minPurchase }}">
                                <input type="hidden" name="category" value="{{ $category }}">
                                
                                <div class="mb-4">
                                    <label for="quantity" class="block text-sm font-medium text-gray-700">Jumlah Pembelian (MWh)</label>
                                    <input type="number" name="quantity" id="quantity" step="1" min="{{ $minPurchase }}" max="{{ floor($availableMwh) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Min. {{ $minPurchase }} MWh" required>
                                </div>
                                
                                <div class="p-4 bg-gray-50 rounded-lg flex justify-between items-center">
                                    <span class="text-gray-600">Total Harga:</span>
                                    <span id="total-price" class="text-2xl font-bold text-gray-800">Rp 0</span>
                                </div>

                                <button type="submit" class="mt-6 w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg transition-all text-lg">
                                    Lanjutkan ke Pembayaran
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const quantityInput = document.getElementById('quantity');
            const totalPriceDisplay = document.getElementById('total-price');
            const pricePerMwh = 35000;

            function calculateTotal() {
                const quantity = parseFloat(quantityInput.value) || 0;
                const total = quantity * pricePerMwh;
                totalPriceDisplay.textContent = 'Rp ' + total.toLocaleString('id-ID');
            }
            
            calculateTotal();
            quantityInput.addEventListener('input', calculateTotal);
        });
    </script>
</body>
</html>