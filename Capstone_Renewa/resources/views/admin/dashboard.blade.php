<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Admin Dashboard - {{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Fonts & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />

    <style>
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.5);
            transition: opacity 0.3s ease;
        }
        .navbar-scrolled {
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }
    </style>

</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen">
        @include('layouts.partials.navbar')

        <!-- Page Heading -->
        <header class="bg-white shadow-md mt-20">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    <i class="fas fa-shield-alt mr-3 text-green-500"></i>
                    <span>Admin Dashboard</span>
                </h2>
                <p class="text-sm text-gray-500 mt-1">Pusat verifikasi dan pengelolaan pengguna platform.</p>
            </div>
        </header>

        <!-- Page Content -->
        <main>
            <div class="py-12">
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    <!-- Session Messages -->
                    @if (session('success'))
                        <div class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-r-lg shadow-md" role="alert" data-aos="fade-right">
                            <p class="font-bold">Sukses</p>
                            <p>{{ session('success') }}</p>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-r-lg shadow-md" role="alert" data-aos="fade-right">
                            <p class="font-bold">Error</p>
                            <p>{{ session('error') }}</p>
                        </div>
                    @endif

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <div class="bg-gradient-to-br from-amber-400 to-orange-500 text-white p-6 rounded-2xl shadow-lg transform transition-transform hover:scale-105" data-aos="fade-up" data-aos-delay="100">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium uppercase tracking-wider">Permintaan Verifikasi</p>
                                    <p class="text-3xl font-bold">{{ $pendingUsers->count() }}</p>
                                </div>
                                <div class="bg-white/30 p-3 rounded-full">
                                    <i class="fas fa-user-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-green-400 to-teal-500 text-white p-6 rounded-2xl shadow-lg transform transition-transform hover:scale-105" data-aos="fade-up" data-aos-delay="200">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium uppercase tracking-wider">Pengguna Aktif</p>
                                    <p class="text-3xl font-bold">{{ $stats['approved'] ?? 0 }}</p>
                                </div>
                                <div class="bg-white/30 p-3 rounded-full">
                                    <i class="fas fa-user-check fa-2x"></i>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-blue-400 to-indigo-500 text-white p-6 rounded-2xl shadow-lg transform transition-transform hover:scale-105" data-aos="fade-up" data-aos-delay="300">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-medium uppercase tracking-wider">Total Pengguna</p>
                                    <p class="text-3xl font-bold">{{ $stats['total'] ?? 0 }}</p>
                                </div>
                                <div class="bg-white/30 p-3 rounded-full">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Verification Table -->
                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl" data-aos="fade-up" data-aos-delay="400">
                        <div class="p-6 md:p-8 text-gray-900">
                            <h3 class="text-2xl font-bold text-gray-800 mb-6">Pengguna Menunggu Persetujuan</h3>
                            @if($pendingUsers->isEmpty())
                                <div class="text-center py-16">
                                    <i class="fas fa-check-circle fa-4x text-green-300 mb-4"></i>
                                    <p class="text-lg text-gray-500">Tidak ada pengguna yang menunggu verifikasi saat ini.</p>
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Pengguna</th>
                                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Detail</th>
                                                <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach ($pendingUsers as $user)
                                                <tr class="hover:bg-green-50/50 transition duration-300">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="flex-shrink-0 h-10 w-10">
                                                                <span class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-bold">
                                                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                                                </span>
                                                            </div>
                                                            <div class="ml-4">
                                                                <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                                                <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                            @if($user->role == 'issuer') bg-blue-100 text-blue-800 @else bg-purple-100 text-purple-800 @endif">
                                                            {{ $user->role }}
                                                        </span>
                                                        <div class="text-sm text-gray-500 mt-1">Daftar: {{ $user->created_at->format('d M Y') }}</div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                                        <button data-userid="{{ $user->id }}" class="view-details-btn inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-transform hover:scale-105">
                                                            <i class="fas fa-search-plus mr-2"></i>Lihat Detail
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- ===== MODAL UNTUK DETAIL VERIFIKASI ===== -->
    <div id="detailsModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden opacity-0">
        <div id="modalCard" class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl m-4 transform transition-all duration-300 scale-95 opacity-0">
            <!-- Modal Header -->
            <div class="flex justify-between items-center p-6 border-b">
                <h3 class="text-2xl font-bold text-gray-800">Detail Verifikasi</h3>
                <button id="closeModalButton" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div id="modalBody" class="p-8">
                <!-- Placeholder untuk loading -->
                <div id="modalLoader" class="text-center py-10">
                    <i class="fas fa-spinner fa-spin fa-3x text-green-500"></i>
                    <p class="mt-2 text-gray-500">Memuat data...</p>
                </div>
                <!-- Konten akan diisi oleh JavaScript -->
                <div id="modalContent" class="hidden"></div>
            </div>
        </div>
    </div>
    <!-- ========================================== -->

    <!-- AOS JavaScript -->
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 600,
            easing: 'ease-in-out',
            once: true,
        });

        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('detailsModal');
            const modalCard = document.getElementById('modalCard');
            const closeModalButton = document.getElementById('closeModalButton');
            const modalLoader = document.getElementById('modalLoader');
            const modalContent = document.getElementById('modalContent');
            const viewButtons = document.querySelectorAll('.view-details-btn');

            function openModal() {
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.classList.remove('opacity-0');
                    modalCard.classList.remove('scale-95', 'opacity-0');
                    modalCard.classList.add('scale-100', 'opacity-100');
                }, 10);
            }

            function closeModal() {
                modalCard.classList.remove('scale-100', 'opacity-100');
                modalCard.classList.add('scale-95', 'opacity-0');
                modal.classList.add('opacity-0');
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modalLoader.classList.remove('hidden'); // Reset loader
                    modalContent.classList.add('hidden');   // Sembunyikan konten lama
                    modalContent.innerHTML = '';            // Kosongkan konten
                }, 300);
            }

            async function fetchUserDetails(userId) {
                openModal();
                try {
                    const url = `{{ route('admin.users.getJsonDetails', ['userId' => ':userId']) }}`.replace(':userId', userId);
                    const response = await fetch(url);
                    
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || 'Gagal memuat data dari server.');
                    }
                    
                    const data = await response.json();

                    let detailsHtml = '';

                    // Tampilkan detail spesifik berdasarkan peran
                    if (data.role === 'issuer') {
                        detailsHtml = `
                            <h4 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">Data Institusi</h4>
                            <div><dt class="text-sm font-medium text-gray-500">Nama Institusi</dt><dd class="mt-1 text-md text-gray-900">${data.company_name}</dd></div>
                            <div><dt class="text-sm font-medium text-gray-500">NIB</dt><dd class="mt-1 text-md text-gray-900">${data.nib}</dd></div>
                        `;
                    } else if (data.role === 'generator') {
                        detailsHtml = `
                            <h4 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">Informasi Pembangkit</h4>
                            <div><dt class="text-sm font-medium text-gray-500">Nama Pembangkit</dt><dd class="mt-1 text-md text-gray-900">${data.power_plant_name}</dd></div>
                            <div><dt class="text-sm font-medium text-gray-500">Jenis Energi</dt><dd class="mt-1 text-md text-gray-900">${data.energy_type}</dd></div>
                            <div><dt class="text-sm font-medium text-gray-500">Kapasitas</dt><dd class="mt-1 text-md text-gray-900">${data.capacity}</dd></div>
                        `;
                    }

                    // Gabungkan semua menjadi satu template
                    modalContent.innerHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                            <div class="space-y-4">
                                <h4 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">Data Penanggung Jawab (PIC)</h4>
                                <div><dt class="text-sm font-medium text-gray-500">Nama</dt><dd class="mt-1 text-md text-gray-900">${data.name}</dd></div>
                                <div><dt class="text-sm font-medium text-gray-500">Email</dt><dd class="mt-1 text-md text-gray-900">${data.email}</dd></div>
                                <div><dt class="text-sm font-medium text-gray-500">Telepon</dt><dd class="mt-1 text-md text-gray-900">${data.phone}</dd></div>
                            </div>
                            <div class="space-y-4">
                                ${detailsHtml}
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">${data.document_label || 'Dokumen'}</dt>
                                    <dd class="mt-1"><a href="${data.document_url}" target="_blank" class="text-blue-600 hover:underline inline-flex items-center p-2 bg-blue-50 rounded-lg"><i class="fas fa-file-pdf fa-lg mr-2"></i><span>Lihat Dokumen PDF</span></a></dd>
                                </div>
                            </div>
                        </div>
                        <div class="mt-10 pt-6 border-t flex justify-end space-x-4">
                            <form action="${data.approve_url}" method="POST" onsubmit="return confirm('Anda yakin ingin MENYETUJUI pendaftaran ini?');">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg">Setujui</button>
                            </form>
                            <form action="${data.reject_url}" method="POST" onsubmit="return confirm('Anda yakin ingin MENOLAK pendaftaran ini?');">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-lg">Tolak</button>
                            </form>
                        </div>
                    `;

                    modalLoader.classList.add('hidden');
                    modalContent.classList.remove('hidden');

                } catch (error) {
                    modalContent.innerHTML = `<p class="text-red-500 text-center font-semibold">${error.message}</p>`;
                    modalLoader.classList.add('hidden');
                    modalContent.classList.remove('hidden');
                }
            }

            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.dataset.userid;
                    fetchUserDetails(userId);
                });
            });

            closeModalButton.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeModal();
                }
            });
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        });
    </script>
</body>
</html>