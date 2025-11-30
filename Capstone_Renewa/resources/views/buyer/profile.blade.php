<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renewa - Profil Pengguna</title>
    <meta name="description" content="Kelola profil dan informasi akun Anda di platform Renewa">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <script src="https://cdn.tailwindcss.com"></script>
    @vite('resources/css/app.css')
    
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        html { scroll-behavior: smooth; }
        .navbar-scrolled { backdrop-filter: blur(10px); background-color: rgba(255, 255, 255, 0.9); transition: all 0.3s ease; }
        .glass-card { background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.2); }
        .modern-input, .modern-select { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(8px); border: 1px solid rgba(229, 231, 235, 0.6); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .modern-input:focus, .modern-select:focus { background: rgba(255, 255, 255, 0.95); border-color: rgb(34, 197, 94); box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1); transform: translateY(-1px); }
        .modern-input:disabled, .modern-select:disabled { background: rgba(249, 250, 251, 0.8); color: rgb(107, 114, 128); cursor: not-allowed; }
        .modern-btn { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
        .modern-btn:hover { transform: translateY(-2px); box-shadow: 0 15px 35px rgba(16, 185, 129, 0.3); }
        .modal-overlay { background-color: rgba(0, 0, 0, 0.5); transition: opacity 0.3s ease; }
    </style>
</head>
<body class="bg-gray-50">
    @include('layouts.partials.navbar')

    <main class="py-12 pt-24">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm flex items-center" data-aos="fade-down">
                    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                </div>
            @endif

            @if(session('error') || $errors->any())
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm" data-aos="fade-down">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle mr-2 mt-1"></i>
                        <div>
                            <p class="font-bold">Terjadi Kesalahan</p>
                            <ul class="list-disc pl-5">
                                @if(session('error'))
                                    <li>{{ session('error') }}</li>
                                @endif
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <div class="glass-card rounded-3xl p-8" data-aos="fade-up">
                <div class="flex justify-between items-center mb-8">
                    <h2 class="text-3xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-user-circle mr-3 text-green-600"></i>Profil Pengguna
                    </h2>
                    <button id="editButton" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors flex items-center">
                        <i class="fas fa-edit mr-2"></i>Edit Profil
                    </button>
                </div>
                
                {{-- PERBAIKAN 1: SEMUA INPUT ADA DI DALAM SATU FORM INI --}}
                <form id="profileForm" action="{{ route('buyer.profile.update') }}" method="POST" class="space-y-8">
                    @csrf
                    
                    {{-- Informasi Pribadi --}}
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-user mr-3 text-green-600"></i>Informasi Pribadi
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Nama Lengkap</label>
                                <input type="text" name="name" class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none" value="{{ $user->name }}" disabled>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Email</label>
                                <input type="email" name="email" class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none" value="{{ $user->email }}" disabled>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">No. Telepon</label>
                                <input type="text" name="phone" class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none" value="{{ $user->phone }}" disabled>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">NIK</label>
                                <input type="text" name="nik" class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none" value="{{ $user->nik }}" disabled>
                            </div>
                        </div>
                    </div>

                    {{-- Informasi Alamat --}}
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-map-marker-alt mr-3 text-green-600"></i>Informasi Alamat
                        </h3>
                        <div class="space-y-6">
                             <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Nama Jalan/No. Rumah</label>
                                <input type="text" name="address" class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none" value="{{ $user->address }}" disabled>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Provinsi</label>
                                    <select name="province" id="province" class="modern-select w-full px-4 py-3 rounded-xl focus:outline-none" disabled>
                                        <option value="">Pilih Provinsi</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Kabupaten/Kota</label>
                                    <select name="regency" id="regency" class="modern-select w-full px-4 py-3 rounded-xl focus:outline-none" disabled>
                                        <option value="">Pilih Kabupaten/Kota</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Kecamatan</label>
                                    <select name="district" id="district" class="modern-select w-full px-4 py-3 rounded-xl focus:outline-none" disabled>
                                        <option value="">Pilih Kecamatan</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-semibold text-gray-700">Kelurahan</label>
                                    <select name="village" id="village" class="modern-select w-full px-4 py-3 rounded-xl focus:outline-none" disabled>
                                        <option value="">Pilih Kelurahan</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Informasi Perusahaan --}}
                    <div class="pt-8 border-t border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-building mr-3 text-green-600"></i>Informasi Perusahaan
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Nama Perusahaan</label>
                                <input type="text" name="company_name" class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none" value="{{ optional($company)->name }}" disabled>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Nomor Telepon Perusahaan</label>
                                <input type="text" name="company_phone_number" class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none" value="{{ optional($company)->phone_number }}" disabled>
                            </div>
                            <div class="md:col-span-2 space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">Alamat Perusahaan</label>
                                <input type="text" name="company_address" class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none" value="{{ optional($company)->address }}" disabled>
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-gray-700">NIB</label>
                                <input type="text" name="company_nib" class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none" value="{{ optional($company)->nib }}" disabled>
                            </div>
                        </div>
                    </div>
                </form>
                {{-- AKHIR DARI FORM TUNGGAL --}}

                <div class="flex justify-between items-center pt-8 mt-8 border-t border-gray-200">
                    <button type="button" id="changePasswordButton" class="text-green-600 hover:text-green-700 font-medium transition-colors flex items-center">
                        <i class="fas fa-key mr-2"></i>Ubah Password
                    </button>
                    <div class="hidden" id="saveButtonContainer">
                        <button type="button" id="saveButton" class="modern-btn px-8 py-3 rounded-xl font-semibold text-white flex items-center">
                            <i class="fas fa-save mr-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Modal Ubah Password --}}
    <div id="passwordModal" class="fixed inset-0 z-50 flex items-center justify-center modal-overlay hidden opacity-0">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md m-4 transform transition-all duration-300 scale-95 opacity-0" id="passwordModalCard">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-800">Ubah Password</h3>
                <button id="closeModalButton" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times fa-lg"></i></button>
            </div>
            <form action="{{ route('buyer.profile.updatePassword') }}" method="POST" class="space-y-6">
                @csrf
                <div class="space-y-2">
                    <label for="current_password" class="block text-sm font-semibold text-gray-700">Password Lama</label>
                    <input type="password" name="current_password" id="current_password" class="modern-input w-full px-4 py-3 rounded-xl" required>
                </div>
                <div class="space-y-2">
                    <label for="new_password" class="block text-sm font-semibold text-gray-700">Password Baru</label>
                    <input type="password" name="new_password" id="new_password" class="modern-input w-full px-4 py-3 rounded-xl" required>
                </div>
                <div class="space-y-2">
                    <label for="new_password_confirmation" class="block text-sm font-semibold text-gray-700">Konfirmasi Password Baru</label>
                    <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="modern-input w-full px-4 py-3 rounded-xl" required>
                </div>
                <div class="flex justify-end items-center pt-4 space-x-4">
                    <button type="button" id="cancelPasswordChange" class="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-xl">Batal</button>
                    <button type="submit" class="modern-btn px-6 py-2 rounded-xl font-semibold text-white">Simpan Password</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ... (Kode AOS & Navbar Scroll tetap sama) ...
            AOS.init({ duration: 800, easing: 'ease-in-out', once: true, mirror: false, offset: 50 });
            window.addEventListener('scroll', function() {
                const navbar = document.getElementById('navbar');
                if (window.scrollY > 50) navbar.classList.add('navbar-scrolled');
                else navbar.classList.remove('navbar-scrolled');
            });


            // ... (Logika Dropdown Alamat tetap sama) ...
            const userData = {
                province: '{{ $user->province }}',
                regency: '{{ $user->regency }}',
                district: '{{ $user->district }}',
                village: '{{ $user->village }}'
            };

            let isEditMode = false;

            const provinceSelect = document.getElementById('province');
            const regencySelect = document.getElementById('regency');
            const districtSelect = document.getElementById('district');
            const villageSelect = document.getElementById('village');

            async function fetchAndSet(selectElement, url, selectedValue, nextFetchFn) {
                try {
                    const response = await fetch(url);
                    if (!response.ok) throw new Error('Network response was not ok');
                    const data = await response.json();

                    selectElement.innerHTML = `<option value="">Pilih ${selectElement.id.charAt(0).toUpperCase() + selectElement.id.slice(1)}</option>`;
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.name;
                        option.textContent = item.name;
                        option.dataset.id = item.id;
                        selectElement.appendChild(option);
                    });

                    if (selectedValue) {
                        selectElement.value = selectedValue;
                    }

                    if (nextFetchFn && selectElement.value) {
                         const selectedOption = Array.from(selectElement.options).find(opt => opt.value === selectedValue);
                         if(selectedOption) {
                             await nextFetchFn(selectedOption.dataset.id);
                         }
                    }
                } catch (error) {
                    console.error(`Error fetching ${selectElement.id}:`, error);
                }
            }
            
            async function fetchVillages(districtId) {
                await fetchAndSet(villageSelect, `https://www.emsifa.com/api-wilayah-indonesia/api/villages/${districtId}.json`, userData.village);
            }

            async function fetchDistricts(regencyId) {
                await fetchAndSet(districtSelect, `https://www.emsifa.com/api-wilayah-indonesia/api/districts/${regencyId}.json`, userData.district, fetchVillages);
            }

            async function fetchRegencies(provinceId) {
                await fetchAndSet(regencySelect, `https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provinceId}.json`, userData.regency, fetchDistricts);
            }

            async function fetchProvinces() {
                await fetchAndSet(provinceSelect, 'https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json', userData.province, fetchRegencies);
            }
            
            provinceSelect.addEventListener('change', function() {
                fetchRegencies(this.options[this.selectedIndex].dataset.id);
            });
            regencySelect.addEventListener('change', function() {
                fetchDistricts(this.options[this.selectedIndex].dataset.id);
            });
            districtSelect.addEventListener('change', function() {
                fetchVillages(this.options[this.selectedIndex].dataset.id);
            });
            fetchProvinces();

            // Edit/Save functionality
            const editButton = document.getElementById('editButton');
            const saveButtonContainer = document.getElementById('saveButtonContainer');
            const saveButton = document.getElementById('saveButton');
            const allInputs = document.querySelectorAll('#profileForm input, #profileForm select'); // Hanya menargetkan input di dalam form utama

            editButton.addEventListener('click', function() {
                isEditMode = !isEditMode;
                allInputs.forEach(input => input.disabled = !isEditMode);
                
                if (isEditMode) {
                    this.innerHTML = '<i class="fas fa-times mr-2"></i>Batal';
                    this.className = 'px-4 py-2 bg-red-100 hover:bg-red-200 rounded-xl transition-colors flex items-center text-red-700';
                    saveButtonContainer.classList.remove('hidden');
                } else {
                    document.getElementById('profileForm').reset();
                    // Set values back to original from server
                    document.querySelector('input[name="name"]').value = '{{ $user->name }}';
                    document.querySelector('input[name="email"]').value = '{{ $user->email }}';
                    document.querySelector('input[name="phone"]').value = '{{ $user->phone }}';
                    document.querySelector('input[name="nik"]').value = '{{ $user->nik }}';
                    document.querySelector('input[name="address"]').value = '{{ $user->address }}';
                    document.querySelector('input[name="company_name"]').value = '{{ optional($company)->name }}';
                    document.querySelector('input[name="company_phone_number"]').value = '{{ optional($company)->phone_number }}';
                    document.querySelector('input[name="company_address"]').value = '{{ optional($company)->address }}';
                    document.querySelector('input[name="company_nib"]').value = '{{ optional($company)->nib }}';
                    
                    this.innerHTML = '<i class="fas fa-edit mr-2"></i>Edit Profil';
                    this.className = 'px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors flex items-center';
                    saveButtonContainer.classList.add('hidden');
                    fetchProvinces();
                }
            });

            // PERBAIKAN 2: Hanya submit satu form utama
            saveButton.addEventListener('click', function() {
                document.getElementById('profileForm').submit();
            });

            // ... (Logika Modal Password tetap sama) ...
            const passwordModal = document.getElementById('passwordModal');
            const passwordModalCard = document.getElementById('passwordModalCard');
            const changePasswordButton = document.getElementById('changePasswordButton');
            const closeModalButton = document.getElementById('closeModalButton');
            const cancelPasswordChange = document.getElementById('cancelPasswordChange');

            function openPasswordModal() {
                passwordModal.classList.remove('hidden');
                setTimeout(() => {
                    passwordModal.classList.remove('opacity-0');
                    passwordModalCard.classList.remove('scale-95', 'opacity-0');
                    passwordModalCard.classList.add('scale-100', 'opacity-100');
                }, 10);
            }

            function closePasswordModal() {
                passwordModalCard.classList.remove('scale-100', 'opacity-100');
                passwordModalCard.classList.add('scale-95', 'opacity-0');
                passwordModal.classList.add('opacity-0');
                setTimeout(() => passwordModal.classList.add('hidden'), 300);
            }

            changePasswordButton.addEventListener('click', openPasswordModal);
            closeModalButton.addEventListener('click', closePasswordModal);
            cancelPasswordChange.addEventListener('click', closePasswordModal);
            window.addEventListener('keydown', (e) => (e.key === 'Escape' && !passwordModal.classList.contains('hidden')) && closePasswordModal());
            passwordModal.addEventListener('click', (e) => (e.target === passwordModal) && closePasswordModal());
        });
    </script>
</body>
</html>

