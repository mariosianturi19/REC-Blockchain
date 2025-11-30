<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lengkapi Data Perusahaan - Renewa</title>
    
    {{-- Aset CSS dan JS dari Vite & CDN --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    
    {{-- Memuat Navbar --}}
    @include('layouts.partials.navbar')

    <main class="py-12 pt-24">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl rounded-2xl">
                <div class="p-8">
                    <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-2">
                        Lengkapi Data Perusahaan
                    </h2>
                    <p class="mb-6 text-gray-600">Karena Anda memilih kategori pembelian Enterprise, mohon lengkapi data perusahaan Anda sesuai dengan dokumen yang berlaku.</p>
                    
                    <form method="POST" action="{{ route('buyer.checkout.company.store') }}">
                        @csrf

                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Nama Perusahaan') }}</label>
                            <input id="name" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm" type="text" name="name" value="{{ old('name') }}" required autofocus />
                            @error('name')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4">
                             <label for="address" class="block text-sm font-medium text-gray-700">{{ __('Alamat Perusahaan') }}</label>
                            <input id="address" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm" type="text" name="address" value="{{ old('address') }}" required />
                             @error('address')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4">
                            <label for="phone_number" class="block text-sm font-medium text-gray-700">{{ __('Nomor Telepon Perusahaan') }}</label>
                            <input id="phone_number" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm" type="text" name="phone_number" value="{{ old('phone_number') }}" required />
                             @error('phone_number')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4">
                            <label for="nib" class="block text-sm font-medium text-gray-700">{{ __('NIB (Nomor Induk Berusaha)') }}</label>
                            <input id="nib" class="block mt-1 w-full rounded-md border-gray-300 shadow-sm" type="text" name="nib" value="{{ old('nib') }}" required />
                             @error('nib')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end mt-6">
                             <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('Simpan dan Lanjutkan Pembelian') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>