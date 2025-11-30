<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Generator Dashboard - {{ config('app.name', 'Laravel') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .modal-overlay { background-color: rgba(0, 0, 0, 0.5); transition: opacity 0.3s ease; }
    </style>
</head>
<body class="font-sans antialiased bg-gray-100">
    <div class="min-h-screen">
        @include('layouts.partials.navbar')

        <header class="bg-white shadow-md mt-20">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <h2 class="font-semibold text-2xl text-gray-800 leading-tight">
                    <i class="fas fa-industry mr-3 text-purple-500"></i>
                    <span>Generator Dashboard</span>
                </h2>
                <p class="text-sm text-gray-500 mt-1">Pusat pengelolaan pembangkit dan pelaporan produksi energi.</p>
            </div>
        </header>

        <main>
            <div class="py-12">
                <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    
                    @if(session('success'))
                        <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded-r-lg shadow-md" role="alert">
                            <p class="font-bold">Sukses</p>
                            <p>{{ session('success') }}</p>
                        </div>
                    @endif
                     @if(session('error'))
                        <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-r-lg shadow-md" role="alert">
                            <p class="font-bold">Error</p>
                            <p>{{ session('error') }}</p>
                        </div>
                    @endif

                    @if($powerPlant)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="md:col-span-2 bg-white p-6 rounded-2xl shadow-lg">
                            <div class="flex justify-between items-start">
                                <h3 class="text-xl font-bold text-gray-800">{{ $powerPlant->name }}</h3>
                                <button id="editButton" class="text-sm text-blue-600 hover:text-blue-800 font-semibold flex items-center">
                                    <i class="fas fa-edit mr-2"></i>Edit Info
                                </button>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                                <div><dt class="text-gray-500">Jenis Energi</dt><dd class="font-semibold text-gray-700">{{ $powerPlant->energy_type }}</dd></div>
                                <div><dt class="text-gray-500">Kapasitas</dt><dd class="font-semibold text-gray-700">{{ $powerPlant->capacity }} MW</dd></div>
                                <div><dt class="text-gray-500">Pemilik</dt><dd class="font-semibold text-gray-700">{{ $user->name }}</dd></div>
                                <div><dt class="text-gray-500">Status Akun</dt><dd class="font-semibold text-green-600">Aktif</dd></div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-br from-purple-500 to-indigo-600 text-white flex flex-col justify-center items-center p-6 rounded-2xl shadow-lg">
                             <h3 class="text-lg font-bold mb-4">Aksi Cepat</h3>
                             <button id="reportButton" class="w-full bg-white/30 hover:bg-white/40 text-white font-bold py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-105">
                                 <i class="fas fa-file-invoice-dollar mr-2"></i>Lapor Produksi
                             </button>
                        </div>
                    </div>
                    @endif
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white p-6 rounded-2xl shadow-lg">
                            <div class="flex items-center">
                                <div class="bg-purple-100 text-purple-600 p-3 rounded-full mr-4">
                                    <i class="fas fa-bolt fa-fw"></i>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Total Energi Dilaporkan</dt>
                                    <dd class="text-2xl font-bold text-gray-800">{{ number_format($totalEnergyGenerated, 2, ',', '.') }} MWh</dd>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white p-6 rounded-2xl shadow-lg">
                            <div class="flex items-center">
                                <div class="bg-green-100 text-green-600 p-3 rounded-full mr-4">
                                    <i class="fas fa-certificate fa-fw"></i>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Total Sertifikat Terbit</dt>
                                    <dd class="text-2xl font-bold text-gray-800">{{ number_format($totalCertificatesIssued, 2, ',', '.') }} MWh</dd>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white p-6 rounded-2xl shadow-lg">
                            <div class="flex items-center">
                                <div class="bg-yellow-100 text-yellow-600 p-3 rounded-full mr-4">
                                    <i class="fas fa-hourglass-half fa-fw"></i>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">Laporan Pending</dt>
                                    <dd class="text-2xl font-bold text-gray-800">{{ $totalReportsPending }}</dd>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-b border-gray-200 mb-6">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button @click="activeTab = 'reports'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'reports', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'reports' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm focus:outline-none">
                                Kelola Laporan Produksi
                            </button>
                        </nav>
                    </div>

                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-2xl">
                        <div x-show="activeTab === 'reports'" x-cloak class="p-6 md:p-8">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-2xl font-bold text-gray-800">Laporan Produksi Energi</h3>
                                <button onclick="openModal()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                                    <i class="fas fa-plus mr-2"></i>Tambah Laporan
                                </button>
                            </div>
                            @if($energyReports->isEmpty())
                                <div class="text-center py-16">
                                    <i class="fas fa-history fa-4x text-gray-300 mb-4"></i>
                                    <p class="text-lg text-gray-500">Belum ada riwayat laporan yang cocok dengan filter Anda.</p>
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">ID & Periode</th>
                                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Jumlah (MWh)</th>
                                                <th class="px-6 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Catatan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach ($energyReports as $report)
                                                <tr class="hover:bg-purple-50/50 transition duration-300">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">Laporan #{{ $report->id }}</div>
                                                        <div class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($report->reporting_period_start)->format('M Y') }}</div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-800">{{ number_format($report->amount_mwh, 2, ',', '.') }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm">
                                                        @if($report->status == 'approved')
                                                            <span class="px-3 py-1 inline-flex leading-5 font-semibold rounded-full bg-green-100 text-green-800">Disetujui</span>
                                                        @elseif($report->status == 'rejected')
                                                            <span class="px-3 py-1 inline-flex leading-5 font-semibold rounded-full bg-red-100 text-red-800">Ditolak</span>
                                                        @else
                                                            <span class="px-3 py-1 inline-flex leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 max-w-xs truncate" title="{{ $report->rejection_reason ?? '' }}">
                                                        {{ $report->rejection_reason ?? '-' }}
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

    @if($powerPlant)
    <div id="reportModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden opacity-0">
        <div id="reportModalCard" class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl m-4 transform transition-all duration-300 scale-95 opacity-0">
            <form action="{{ route('generator.reports.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="power_plant_id" value="{{ $powerPlant->id }}">
                <div class="flex justify-between items-center p-6 border-b"><h3 class="text-2xl font-bold">Lapor Produksi Energi</h3><button type="button" id="closeReportModalButton" class="text-gray-400 hover:text-gray-600">&times;</button></div>
                <div class="p-8 space-y-6">
                    <div><label for="reporting_period_start" class="block text-sm font-medium">Periode Mulai</label><input type="date" name="reporting_period_start" class="mt-1 block w-full rounded-md" required></div>
                    <div><label for="reporting_period_end" class="block text-sm font-medium">Periode Selesai</label><input type="date" name="reporting_period_end" class="mt-1 block w-full rounded-md" required></div>
                    
                    <div>
                        <label for="amount_mwh" class="block text-sm font-medium">Jumlah Energi (MWh)</label>
                        <input type="number" step="0.01" name="amount_mwh" id="amount_mwh" class="mt-1 block w-full rounded-md" required>
                    </div>
                    
                    <div><label for="supporting_document" class="block text-sm font-medium">Dokumen Pendukung (PDF, Opsional)</label><input type="file" name="supporting_document" class="mt-1 block w-full text-sm"></div>
                </div>
                <div class="px-8 py-4 bg-gray-50 border-t flex justify-end"><button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg">Ajukan Laporan</button></div>
            </form>
        </div>
    </div>
    @endif
    
    @if($powerPlant)
    <div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden opacity-0">
        <div id="editModalCard" class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl m-4 transform transition-all duration-300 scale-95 opacity-0">
            <form action="{{ route('generator.power-plant.update', $powerPlant->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="flex justify-between items-center p-6 border-b"><h3 class="text-2xl font-bold">Edit Info Pembangkit</h3><button type="button" id="closeEditModalButton" class="text-gray-400 hover:text-gray-600">&times;</button></div>
                <div class="p-8 space-y-6">
                    <div><label for="edit_name" class="block text-sm font-medium">Nama Pembangkit</label><input type="text" name="name" id="edit_name" value="{{ $powerPlant->name }}" class="mt-1 block w-full rounded-md" required></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div><label for="edit_energy_type" class="block text-sm font-medium">Jenis Energi</label><select name="energy_type" id="edit_energy_type" class="mt-1 block w-full rounded-md" required><option value="PLTP" {{ $powerPlant->energy_type == 'PLTP' ? 'selected' : '' }}>PLTP</option><option value="PLTA" {{ $powerPlant->energy_type == 'PLTA' ? 'selected' : '' }}>PLTA</option><option value="PLTM" {{ $powerPlant->energy_type == 'PLTM' ? 'selected' : '' }}>PLTM</option></select></div>
                        <div><label for="edit_capacity" class="block text-sm font-medium">Kapasitas (MW)</label><input type="number" step="0.01" name="capacity" id="edit_capacity" value="{{ $powerPlant->capacity }}" class="mt-1 block w-full rounded-md" required></div>
                    </div>
                    <div><label for="edit_image_url" class="block text-sm font-medium">URL Gambar</label><input type="url" name="image_url" id="edit_image_url" value="{{ $powerPlant->image_url }}" class="mt-1 block w-full rounded-md" placeholder="https://..."></div>
                </div>
                <div class="px-8 py-4 bg-gray-50 border-t flex justify-end"><button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg">Simpan Perubahan</button></div>
            </form>
        </div>
    </div>
    @endif

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Logika untuk Modal Lapor Produksi
        const reportModal = document.getElementById('reportModal');
        if (reportModal) {
            const reportButton = document.getElementById('reportButton');
            const closeReportModalButton = document.getElementById('closeReportModalButton');
            const reportModalCard = document.getElementById('reportModalCard');

            reportButton.addEventListener('click', () => openModal(reportModal, reportModalCard));
            closeReportModalButton.addEventListener('click', () => closeModal(reportModal, reportModalCard));
            reportModal.addEventListener('click', (e) => { if (e.target === reportModal) closeModal(reportModal, reportModalCard); });
        }

        // Logika untuk Modal Edit Info
        const editModal = document.getElementById('editModal');
        if (editModal) {
            const editButton = document.getElementById('editButton');
            const closeEditModalButton = document.getElementById('closeEditModalButton');
            const editModalCard = document.getElementById('editModalCard');

            editButton.addEventListener('click', () => openModal(editModal, editModalCard));
            closeEditModalButton.addEventListener('click', () => closeModal(editModal, editModalCard));
            editModal.addEventListener('click', (e) => { if (e.target === editModal) closeModal(editModal, editModalCard); });
        }
        
        // Fungsi generik untuk membuka modal
        function openModal(modal, card) {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                card.classList.remove('scale-95', 'opacity-0');
                card.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        // Fungsi generik untuk menutup modal
        function closeModal(modal, card) {
            card.classList.remove('scale-100', 'opacity-100');
            card.classList.add('scale-95', 'opacity-0');
            modal.classList.add('opacity-0');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }

        // Fungsi untuk menutup modal dengan tombol Escape
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if(reportModal && !reportModal.classList.contains('hidden')) closeModal(reportModal, reportModal.querySelector('#reportModalCard'));
                if(editModal && !editModal.classList.contains('hidden')) closeModal(editModal, editModal.querySelector('#editModalCard'));
            }
        });
    });
    </script>
</body>
</html>