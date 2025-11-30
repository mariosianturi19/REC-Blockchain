<?php
// resources/views/buyer/register.blade.php
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Renewa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    @vite('resources/css/app.css')
    
    <style>
        /* Custom gradient backgrounds */
        .auth-gradient {
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 25%, #f0f9ff 75%, #eff6ff 100%);
        }
        
        /* Glass morphism effect */
        .glass-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }
        
        /* Enhanced input styling */
        .modern-input, .modern-select {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(229, 231, 235, 0.6);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .modern-input:focus, .modern-select:focus {
            background: rgba(255, 255, 255, 0.95);
            border-color: rgb(34, 197, 94);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
            transform: translateY(-1px);
        }
        
        /* Button hover effects */
        .modern-btn {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .modern-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .modern-btn:hover::before {
            left: 100%;
        }
        
        .modern-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(16, 185, 129, 0.3);
        }
        
        /* Floating animation */
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(2deg); }
        }
        
        /* Logo pulse effect */
        .logo-pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        /* Section dividers */
        .section-divider {
            position: relative;
            margin: 2rem 0;
        }
        
        .section-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(229, 231, 235, 0.8), transparent);
        }
        
        .section-title {
            background: rgba(255, 255, 255, 0.9);
            padding: 0 1rem;
            margin: 0 auto;
            width: fit-content;
            position: relative;
            z-index: 1;
        }
        
        /* Custom scrollbar */
        .form-container {
            max-height: 85vh;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(34, 197, 94, 0.3) transparent;
        }
        
        .form-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .form-container::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .form-container::-webkit-scrollbar-thumb {
            background: rgba(34, 197, 94, 0.3);
            border-radius: 3px;
        }
        
        .form-container::-webkit-scrollbar-thumb:hover {
            background: rgba(34, 197, 94, 0.5);
        }
        
        /* Loading animation */
        .loading-dots {
            display: inline-block;
        }
        
        .loading-dots::after {
            content: '';
            animation: loadingDots 1.5s infinite;
        }
        
        @keyframes loadingDots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-out;
        }

        .modal-content {
            background: white;
            margin: 2rem;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            animation: slideIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 2rem 2rem 1rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            position: sticky;
            top: 0;
            background: white;
            border-radius: 20px 20px 0 0;
            z-index: 10;
        }

        .modal-body {
            padding: 2rem;
            line-height: 1.7;
        }

        .modal-footer {
            padding: 1rem 2rem 2rem 2rem;
            border-top: 1px solid #e5e7eb;
            position: sticky;
            bottom: 0;
            background: white;
            border-radius: 0 0 20px 20px;
        }

        .close {
            color: #9ca3af;
            font-size: 28px;
            font-weight: bold;
            line-height: 1;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover,
        .close:focus {
            color: #ef4444;
        }

        /* Prose styling for modal content */
        .prose h2 {
            color: #1f2937;
            font-size: 1.5rem;
            font-weight: 700;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }

        .prose h2:first-child {
            margin-top: 0;
        }

        .prose p {
            margin-bottom: 1rem;
            color: #4b5563;
        }

        .prose ul {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }

        .prose li {
            margin-bottom: 0.5rem;
            color: #4b5563;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center auth-gradient p-4">
    <!-- Background decorations -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute top-10 left-10 w-32 h-32 bg-green-200 rounded-full opacity-20 floating"></div>
        <div class="absolute top-20 right-20 w-24 h-24 bg-blue-200 rounded-full opacity-20 floating" style="animation-delay: -1s;"></div>
        <div class="absolute bottom-20 left-20 w-40 h-40 bg-green-100 rounded-full opacity-30 floating" style="animation-delay: -2s;"></div>
        <div class="absolute bottom-10 right-10 w-28 h-28 bg-blue-100 rounded-full opacity-25 floating" style="animation-delay: -0.5s;"></div>
    </div>

    <div class="w-full max-w-4xl relative z-10">
        <!-- Logo and Brand -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-green-500 to-green-600 rounded-2xl mb-4 logo-pulse">
                <i class="fas fa-leaf text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Bergabung dengan Renewa</h1>
            <p class="text-gray-600">Daftar untuk memulai perjalanan energi hijau Anda</p>
        </div>

        <!-- Registration Form -->
        <div class="glass-card rounded-3xl overflow-hidden">
            <div class="form-container p-8">
                <form method="POST" action="{{ route('buyer.register') }}" class="space-y-8">
                    @csrf

                    <!-- Personal Information Section -->
                    <div>
                        <div class="section-divider">
                            <h3 class="section-title text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-user mr-3 text-green-600"></i>
                                Informasi Pribadi
                            </h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Name -->
                            <div class="space-y-2">
                                <label for="name" class="block text-sm font-semibold text-gray-700">
                                    Nama Lengkap *
                                </label>
                                <input 
                                    id="name" 
                                    type="text" 
                                    name="name" 
                                    value="{{ old('name') }}" 
                                    required 
                                    autofocus
                                    class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400"
                                    placeholder="Masukkan nama lengkap"
                                >
                                @error('name')
                                    <p class="text-red-500 text-sm flex items-center">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div class="space-y-2">
                                <label for="email" class="block text-sm font-semibold text-gray-700">
                                    Email *
                                </label>
                                <input 
                                    id="email" 
                                    type="email" 
                                    name="email" 
                                    value="{{ old('email') }}" 
                                    required
                                    class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400"
                                    placeholder="nama@email.com"
                                >
                                @error('email')
                                    <p class="text-red-500 text-sm flex items-center">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <!-- Phone -->
                            <div class="space-y-2">
                                <label for="phone" class="block text-sm font-semibold text-gray-700">
                                    Nomor Telepon *
                                </label>
                                <input 
                                    id="phone" 
                                    type="text" 
                                    name="phone" 
                                    value="{{ old('phone') }}" 
                                    required
                                    class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400"
                                    placeholder="08xxxxxxxxxx"
                                >
                                @error('phone')
                                    <p class="text-red-500 text-sm flex items-center">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <!-- NIK -->
                            <div class="space-y-2">
                                <label for="nik" class="block text-sm font-semibold text-gray-700">
                                    NIK *
                                </label>
                                <input 
                                    id="nik" 
                                    type="text" 
                                    name="nik" 
                                    value="{{ old('nik') }}" 
                                    required
                                    maxlength="16"
                                    class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400"
                                    placeholder="16 digit NIK"
                                >
                                @error('nik')
                                    <p class="text-red-500 text-sm flex items-center">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Security Section -->
                    <div>
                        <div class="section-divider">
                            <h3 class="section-title text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-lock mr-3 text-green-600"></i>
                                Keamanan Akun
                            </h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Password -->
                            <div class="space-y-2">
                                <label for="password" class="block text-sm font-semibold text-gray-700">
                                    Password *
                                </label>
                                <div class="relative">
                                    <input 
                                        id="password" 
                                        type="password" 
                                        name="password" 
                                        required
                                        class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400 pr-12"
                                        placeholder="Minimal 8 karakter"
                                    >
                                    <button 
                                        type="button" 
                                        onclick="togglePassword('password', 'eyeIcon1')" 
                                        class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                                    >
                                        <i id="eyeIcon1" class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <span id="passwordStrength"></span>
                                </div>
                                @error('password')
                                    <p class="text-red-500 text-sm flex items-center">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <!-- Confirm Password -->
                            <div class="space-y-2">
                                <label for="password_confirmation" class="block text-sm font-semibold text-gray-700">
                                    Konfirmasi Password *
                                </label>
                                <div class="relative">
                                    <input 
                                        id="password_confirmation" 
                                        type="password" 
                                        name="password_confirmation" 
                                        required
                                        class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400 pr-12"
                                        placeholder="Ulangi password"
                                    >
                                    <button 
                                        type="button" 
                                        onclick="togglePassword('password_confirmation', 'eyeIcon2')" 
                                        class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                                    >
                                        <i id="eyeIcon2" class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="text-xs mt-1" id="passwordMatch"></div>
                                @error('password_confirmation')
                                    <p class="text-red-500 text-sm flex items-center">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Address Section -->
                    <div>
                        <div class="section-divider">
                            <h3 class="section-title text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-map-marker-alt mr-3 text-green-600"></i>
                                Informasi Alamat
                            </h3>
                        </div>
                        
                        <div class="space-y-6">
                            <!-- Street Address -->
                            <div class="space-y-2">
                                <label for="address" class="block text-sm font-semibold text-gray-700">
                                    Nama Jalan/No. Rumah *
                                </label>
                                <input 
                                    id="address" 
                                    type="text" 
                                    name="address" 
                                    value="{{ old('address') }}" 
                                    required
                                    class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400"
                                    placeholder="Jl. Contoh No. 123"
                                >
                                @error('address')
                                    <p class="text-red-500 text-sm flex items-center">
                                        <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Province -->
                                <div class="space-y-2">
                                    <label for="province" class="block text-sm font-semibold text-gray-700">
                                        Provinsi *
                                    </label>
                                    <select 
                                        id="province" 
                                        name="province" 
                                        required
                                        class="modern-select w-full px-4 py-3 rounded-xl focus:outline-none"
                                    >
                                        <option value="">Pilih Provinsi</option>
                                    </select>
                                    @error('province')
                                        <p class="text-red-500 text-sm flex items-center">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <!-- Regency -->
                                <div class="space-y-2">
                                    <label for="regency" class="block text-sm font-semibold text-gray-700">
                                        Kabupaten/Kota *
                                    </label>
                                    <select 
                                        id="regency" 
                                        name="regency" 
                                        required
                                        class="modern-select w-full px-4 py-3 rounded-xl focus:outline-none"
                                        disabled
                                    >
                                        <option value="">Pilih Kabupaten/Kota</option>
                                    </select>
                                    @error('regency')
                                        <p class="text-red-500 text-sm flex items-center">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <!-- District -->
                                <div class="space-y-2">
                                    <label for="district" class="block text-sm font-semibold text-gray-700">
                                        Kecamatan *
                                    </label>
                                    <select 
                                        id="district" 
                                        name="district" 
                                        required
                                        class="modern-select w-full px-4 py-3 rounded-xl focus:outline-none"
                                        disabled
                                    >
                                        <option value="">Pilih Kecamatan</option>
                                    </select>
                                    @error('district')
                                        <p class="text-red-500 text-sm flex items-center">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>

                                <!-- Village -->
                                <div class="space-y-2">
                                    <label for="village" class="block text-sm font-semibold text-gray-700">
                                        Kelurahan *
                                    </label>
                                    <select 
                                        id="village" 
                                        name="village" 
                                        required
                                        class="modern-select w-full px-4 py-3 rounded-xl focus:outline-none"
                                        disabled
                                    >
                                        <option value="">Pilih Kelurahan</option>
                                    </select>
                                    @error('village')
                                        <p class="text-red-500 text-sm flex items-center">
                                            <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                                        </p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="p-6 bg-gray-50 rounded-xl">
                        <label class="flex items-start space-x-3">
                            <input 
                                type="checkbox" 
                                name="terms" 
                                required
                                class="w-5 h-5 text-green-600 bg-white border-gray-300 rounded focus:ring-green-500 focus:ring-2 mt-0.5"
                            >
                            <span class="text-sm text-gray-700 leading-relaxed">
                                Saya menyetujui 
                                <button type="button" onclick="openModal('termsModal')" class="text-green-600 hover:text-green-700 font-semibold underline cursor-pointer">
                                    Syarat dan Ketentuan
                                </button>
                                serta 
                                <button type="button" onclick="openModal('privacyModal')" class="text-green-600 hover:text-green-700 font-semibold underline cursor-pointer">
                                    Kebijakan Privasi
                                </button>
                                yang berlaku.
                            </span>
                        </label>
                        @error('terms')
                            <p class="text-red-500 text-sm mt-2 flex items-center">
                                <i class="fas fa-exclamation-circle mr-1"></i>{{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-6">
                        <button 
                            type="submit" 
                            id="submitBtn"
                            class="modern-btn w-full py-4 px-6 rounded-xl font-semibold text-white text-lg"
                        >
                            <i class="fas fa-user-plus mr-2"></i>
                            <span id="btnText">Daftar Sekarang</span>
                        </button>
                    </div>
                </form>

                <!-- Login Link -->
                <div class="text-center mt-8 pt-6 border-t border-gray-200">
                    <p class="text-gray-600 text-sm">
                        Sudah punya akun? 
                        <a href="{{ route('login') }}" class="text-green-600 hover:text-green-700 font-semibold transition-colors">
                            Masuk sekarang
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Back to Home -->
        <div class="text-center mt-6">
            <a href="{{ route('welcome') }}" class="text-gray-500 hover:text-gray-700 text-sm transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke beranda
            </a>
        </div>
    </div>

    <!-- Terms Modal -->
    <div id="termsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="flex justify-between items-center">
                    <h2 class="text-2xl font-bold text-gray-900 flex items-center">
                        <i class="fas fa-file-contract mr-3 text-green-600"></i>
                        Syarat dan Ketentuan
                    </h2>
                    <span class="close" onclick="closeModal('termsModal')">&times;</span>
                </div>
            </div>
            <div class="modal-body prose">
                <h2>1. Penerimaan Syarat</h2>
                <p>Dengan mengakses dan menggunakan platform Renewa, Anda menyetujui untuk terikat oleh syarat dan ketentuan ini. Jika Anda tidak menyetujui syarat-syarat ini, mohon untuk tidak menggunakan layanan kami.</p>
                
                <h2>2. Tentang Layanan</h2>
                <p>Renewa adalah platform digital yang menyediakan layanan perdagangan Renewable Energy Certificate (REC) di Indonesia. Kami memfasilitasi transaksi antara penerbit dan pembeli sertifikat energi terbarukan.</p>
                
                <h2>3. Pendaftaran dan Akun</h2>
                <p>Untuk menggunakan layanan kami, Anda harus:</p>
                <ul>
                    <li>Berusia minimal 18 tahun atau memiliki persetujuan dari wali yang sah</li>
                    <li>Memberikan informasi yang akurat dan lengkap saat pendaftaran</li>
                    <li>Menjaga kerahasiaan informasi akun Anda</li>
                    <li>Bertanggung jawab atas semua aktivitas yang terjadi dalam akun Anda</li>
                </ul>
                
                <h2>4. Kewajiban Pengguna</h2>
                <p>Sebagai pengguna platform Renewa, Anda wajib:</p>
                <ul>
                    <li>Menggunakan layanan sesuai dengan hukum yang berlaku</li>
                    <li>Tidak melakukan aktivitas yang dapat merugikan platform atau pengguna lain</li>
                    <li>Memperbarui informasi profil secara berkala untuk memastikan akurasi data</li>
                    <li>Melaporkan setiap aktivitas mencurigakan yang Anda temukan</li>
                </ul>
                
                <h2>5. Transaksi dan Pembayaran</h2>
                <p>Semua transaksi yang dilakukan melalui platform ini tunduk pada ketentuan yang berlaku. Kami tidak bertanggung jawab atas sengketa yang timbul antara pembeli dan penjual, namun akan membantu mediasi jika diperlukan.</p>
                
                <h2>6. Privasi dan Keamanan Data</h2>
                <p>Kami berkomitmen untuk melindungi privasi dan keamanan data personal Anda sesuai dengan Kebijakan Privasi yang dapat diakses terpisah. Data Anda akan digunakan sesuai dengan tujuan yang telah dijelaskan dalam kebijakan tersebut.</p>
                
                <h2>7. Pembatasan Tanggung Jawab</h2>
                <p>Renewa tidak bertanggung jawab atas kerugian langsung maupun tidak langsung yang timbul dari penggunaan platform ini, kecuali yang diwajibkan oleh hukum yang berlaku.</p>
                
                <h2>8. Perubahan Syarat dan Ketentuan</h2>
                <p>Kami berhak untuk mengubah syarat dan ketentuan ini sewaktu-waktu. Perubahan akan diberitahukan melalui platform dan mulai berlaku sejak tanggal yang ditentukan.</p>
                
                <h2>9. Penghentian Layanan</h2>
                <p>Kami berhak untuk menghentikan atau membatasi akses Anda ke platform jika terjadi pelanggaran terhadap syarat dan ketentuan ini.</p>
                
                <h2>10. Hukum yang Berlaku</h2>
               <p>Syarat dan ketentuan ini tunduk pada hukum Republik Indonesia. Setiap sengketa yang timbul akan diselesaikan melalui pengadilan yang berwenang di Jakarta.</p>
               
               <h2>11. Hubungi Kami</h2>
               <p>Jika Anda memiliki pertanyaan tentang syarat dan ketentuan ini, silakan hubungi kami melalui:</p>
               <ul>
                   <li>Email: info@renewa.id</li>
                   <li>Telepon: +62-21-1234-5678</li>
                   <li>Alamat: Jakarta, Indonesia</li>
               </ul>
               
               <p class="text-sm text-gray-500 mt-6">
                   <strong>Terakhir diperbarui:</strong> 25 Mei 2025
               </p>
           </div>
           <div class="modal-footer">
               <button onclick="closeModal('termsModal')" class="modern-btn px-6 py-2 rounded-xl font-semibold text-white">
                   <i class="fas fa-check mr-2"></i>Saya Mengerti
               </button>
           </div>
       </div>
   </div>

   <!-- Privacy Modal -->
   <div id="privacyModal" class="modal">
       <div class="modal-content">
           <div class="modal-header">
               <div class="flex justify-between items-center">
                   <h2 class="text-2xl font-bold text-gray-900 flex items-center">
                       <i class="fas fa-shield-alt mr-3 text-green-600"></i>
                       Kebijakan Privasi
                   </h2>
                   <span class="close" onclick="closeModal('privacyModal')">&times;</span>
               </div>
           </div>
           <div class="modal-body prose">
               <h2>1. Informasi yang Kami Kumpulkan</h2>
               <p>Kami mengumpulkan berbagai jenis informasi untuk memberikan layanan terbaik kepada Anda:</p>
               <ul>
                   <li><strong>Informasi Personal:</strong> Nama lengkap, alamat email, nomor telepon, NIK, dan alamat lengkap</li>
                   <li><strong>Informasi Transaksi:</strong> Data pembelian, riwayat transaksi, dan preferensi pembayaran</li>
                   <li><strong>Informasi Teknis:</strong> Alamat IP, jenis browser, sistem operasi, dan data penggunaan platform</li>
                   <li><strong>Informasi Komunikasi:</strong> Korespondensi melalui email, chat, atau saluran komunikasi lainnya</li>
               </ul>
               
               <h2>2. Bagaimana Kami Menggunakan Informasi Anda</h2>
               <p>Informasi yang kami kumpulkan digunakan untuk:</p>
               <ul>
                   <li>Menyediakan dan memelihara layanan platform</li>
                   <li>Memverifikasi identitas dan mencegah penipuan</li>
                   <li>Memproses transaksi dan pembayaran</li>
                   <li>Mengirimkan notifikasi terkait akun dan transaksi</li>
                   <li>Memberikan dukungan pelanggan</li>
                   <li>Meningkatkan kualitas layanan berdasarkan feedback pengguna</li>
                   <li>Mematuhi kewajiban hukum dan regulasi yang berlaku</li>
               </ul>
               
               <h2>3. Perlindungan Data</h2>
               <p>Kami menerapkan berbagai langkah keamanan untuk melindungi informasi personal Anda:</p>
               <ul>
                   <li>Enkripsi data menggunakan teknologi SSL/TLS terbaru</li>
                   <li>Sistem firewall dan monitoring keamanan 24/7</li>
                   <li>Akses terbatas pada data personal hanya untuk karyawan yang memerlukan</li>
                   <li>Audit keamanan rutin oleh pihak ketiga independen</li>
                   <li>Backup data reguler dengan enkripsi tingkat tinggi</li>
               </ul>
               
               <h2>4. Berbagi Informasi dengan Pihak Ketiga</h2>
               <p>Kami tidak akan menjual, menyewakan, atau membagikan informasi personal Anda kepada pihak ketiga, kecuali dalam situasi berikut:</p>
               <ul>
                   <li>Dengan persetujuan eksplisit dari Anda</li>
                   <li>Untuk memenuhi kewajiban hukum atau perintah pengadilan</li>
                   <li>Kepada penyedia layanan yang membantu operasional platform (dengan perjanjian kerahasiaan)</li>
                   <li>Dalam kasus merger, akuisisi, atau penjualan aset perusahaan</li>
               </ul>
               
               <h2>5. Cookies dan Teknologi Pelacakan</h2>
               <p>Kami menggunakan cookies dan teknologi serupa untuk:</p>
               <ul>
                   <li>Mengingat preferensi dan pengaturan Anda</li>
                   <li>Menganalisis penggunaan platform untuk perbaikan layanan</li>
                   <li>Memberikan konten yang dipersonalisasi</li>
                   <li>Mencegah aktivitas penipuan dan spam</li>
               </ul>
               <p>Anda dapat mengatur preferensi cookies melalui pengaturan browser Anda.</p>
               
               <h2>6. Hak-Hak Anda</h2>
               <p>Sebagai pengguna, Anda memiliki hak untuk:</p>
               <ul>
                   <li><strong>Akses:</strong> Meminta salinan data personal yang kami simpan</li>
                   <li><strong>Koreksi:</strong> Memperbarui atau memperbaiki informasi yang tidak akurat</li>
                   <li><strong>Penghapusan:</strong> Meminta penghapusan data personal dalam kondisi tertentu</li>
                   <li><strong>Portabilitas:</strong> Meminta transfer data ke platform lain</li>
                   <li><strong>Keberatan:</strong> Menolak pemrosesan data untuk tujuan tertentu</li>
                   <li><strong>Pembatasan:</strong> Membatasi pemrosesan data dalam situasi tertentu</li>
               </ul>
               
               <h2>7. Penyimpanan Data</h2>
               <p>Kami menyimpan data personal Anda selama:</p>
               <ul>
                   <li>Akun Anda aktif dan menggunakan layanan kami</li>
                   <li>Diperlukan untuk memenuhi kewajiban hukum</li>
                   <li>Diperlukan untuk menyelesaikan sengketa atau menegakkan perjanjian</li>
               </ul>
               <p>Data yang tidak lagi diperlukan akan dihapus atau dianonimkan secara aman.</p>
               
               <h2>8. Transfer Data Internasional</h2>
               <p>Data Anda mungkin diproses di server yang berlokasi di luar Indonesia. Dalam hal ini, kami memastikan tingkat perlindungan yang sama melalui perjanjian perlindungan data yang sesuai.</p>
               
               <h2>9. Privasi Anak-Anak</h2>
               <p>Layanan kami tidak ditujukan untuk anak-anak di bawah 18 tahun. Kami tidak secara sengaja mengumpulkan informasi personal dari anak-anak tanpa persetujuan orang tua.</p>
               
               <h2>10. Perubahan Kebijakan Privasi</h2>
               <p>Kami dapat memperbarui kebijakan privasi ini dari waktu ke waktu. Perubahan material akan diberitahukan melalui email atau notifikasi pada platform.</p>
               
               <h2>11. Hubungi Kami</h2>
               <p>Untuk pertanyaan terkait kebijakan privasi atau untuk menggunakan hak-hak Anda, hubungi kami di:</p>
               <ul>
                   <li>Email: privacy@renewa.id</li>
                   <li>Telepon: +62-21-1234-5678</li>
                   <li>Alamat: Jakarta, Indonesia</li>
               </ul>
               
               <p class="text-sm text-gray-500 mt-6">
                   <strong>Terakhir diperbarui:</strong> 25 Mei 2025
               </p>
           </div>
           <div class="modal-footer">
               <button onclick="closeModal('privacyModal')" class="modern-btn px-6 py-2 rounded-xl font-semibold text-white">
                   <i class="fas fa-check mr-2"></i>Saya Mengerti
               </button>
           </div>
       </div>
   </div>

   <script>
       // Modal functions
       function openModal(modalId) {
           const modal = document.getElementById(modalId);
           modal.classList.add('show');
           document.body.style.overflow = 'hidden';
       }

       function closeModal(modalId) {
           const modal = document.getElementById(modalId);
           modal.classList.remove('show');
           document.body.style.overflow = 'auto';
       }

       // Close modal when clicking outside
       window.addEventListener('click', function(event) {
           if (event.target.classList.contains('modal')) {
               event.target.classList.remove('show');
               document.body.style.overflow = 'auto';
           }
       });

       // Close modal with Escape key
       document.addEventListener('keydown', function(event) {
           if (event.key === 'Escape') {
               const openModals = document.querySelectorAll('.modal.show');
               openModals.forEach(modal => {
                   modal.classList.remove('show');
               });
               document.body.style.overflow = 'auto';
           }
       });

       function togglePassword(inputId, iconId) {
           const passwordInput = document.getElementById(inputId);
           const eyeIcon = document.getElementById(iconId);
           
           if (passwordInput.type === 'password') {
               passwordInput.type = 'text';
               eyeIcon.className = 'fas fa-eye-slash';
           } else {
               passwordInput.type = 'password';
               eyeIcon.className = 'fas fa-eye';
           }
       }

       function checkPasswordStrength(password) {
           let strength = 0;
           let feedback = [];
           
           if (password.length >= 8) strength += 1;
           else feedback.push('minimal 8 karakter');
           
           if (/[a-z]/.test(password)) strength += 1;
           else feedback.push('huruf kecil');
           
           if (/[A-Z]/.test(password)) strength += 1;
           else feedback.push('huruf besar');
           
           if (/[0-9]/.test(password)) strength += 1;
           else feedback.push('angka');
           
           if (/[^A-Za-z0-9]/.test(password)) strength += 1;
           else feedback.push('karakter khusus');
           
           const strengthEl = document.getElementById('passwordStrength');
           
           if (password.length === 0) {
               strengthEl.innerHTML = '';
               return;
           }
           
           let strengthText = '';
           let strengthClass = '';
           
           switch (strength) {
               case 0:
               case 1:
                   strengthText = `<i class="fas fa-times text-red-500 mr-1"></i>Lemah`;
                   strengthClass = 'text-red-500';
                   break;
               case 2:
               case 3:
                   strengthText = `<i class="fas fa-minus text-yellow-500 mr-1"></i>Sedang`;
                   strengthClass = 'text-yellow-500';
                   break;
               case 4:
               case 5:
                   strengthText = `<i class="fas fa-check text-green-500 mr-1"></i>Kuat`;
                   strengthClass = 'text-green-500';
                   break;
           }
           
           if (feedback.length > 0 && strength < 4) {
               strengthText += ` (perlu: ${feedback.join(', ')})`;
           }
           
           strengthEl.innerHTML = strengthText;
           strengthEl.className = `text-xs mt-1 ${strengthClass}`;
       }

       function checkPasswordMatch() {
           const password = document.getElementById('password').value;
           const confirmPassword = document.getElementById('password_confirmation').value;
           const matchEl = document.getElementById('passwordMatch');
           
           if (confirmPassword.length === 0) {
               matchEl.innerHTML = '';
               return;
           }
           
           if (password === confirmPassword) {
               matchEl.innerHTML = '<i class="fas fa-check text-green-500 mr-1"></i>Password cocok';
               matchEl.className = 'text-xs mt-1 text-green-500';
               document.getElementById('password_confirmation').classList.remove('border-red-500');
           } else {
               matchEl.innerHTML = '<i class="fas fa-times text-red-500 mr-1"></i>Password tidak cocok';
               matchEl.className = 'text-xs mt-1 text-red-500';
               document.getElementById('password_confirmation').classList.add('border-red-500');
           }
       }

       function showNotification(message, type = 'error') {
           const notification = document.createElement('div');
           notification.className = `fixed top-4 right-4 p-4 rounded-xl text-white font-semibold z-50 transform transition-all duration-300 ${
               type === 'error' ? 'bg-red-500' : 'bg-green-500'
           }`;
           notification.innerHTML = `
               <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'} mr-2"></i>
               ${message}
           `;
           
           document.body.appendChild(notification);
           
           // Slide in animation
           setTimeout(() => {
               notification.style.transform = 'translateX(0)';
           }, 100);
           
           setTimeout(() => {
               notification.style.transform = 'translateX(100%)';
               setTimeout(() => notification.remove(), 300);
           }, 3000);
       }

       // NIK validation
       document.getElementById('nik').addEventListener('input', function(e) {
           let value = e.target.value.replace(/\D/g, '');
           if (value.length > 16) {
               value = value.slice(0, 16);
           }
           e.target.value = value;
       });

       // Phone validation
       document.getElementById('phone').addEventListener('input', function(e) {
           let value = e.target.value.replace(/\D/g, '');
           if (value.length > 15) {
               value = value.slice(0, 15);
           }
           e.target.value = value;
       });

       // Password strength checking
       document.getElementById('password').addEventListener('input', function(e) {
           checkPasswordStrength(e.target.value);
           checkPasswordMatch();
       });

       // Password confirmation checking
       document.getElementById('password_confirmation').addEventListener('input', function() {
           checkPasswordMatch();
       });

       // Fetch Provinces
       fetch('https://flavianusputratama.github.io/api-wilayah-indonesia/api/provinces.json')
           .then(response => response.json())
           .then(provinces => {
               const provinceSelect = document.getElementById('province');
               provinces.forEach(province => {
                   const option = document.createElement('option');
                   option.value = province.name;
                   option.textContent = province.name;
                   option.dataset.id = province.id;
                   provinceSelect.appendChild(option);
               });
           })
           .catch(error => {
               console.error('Error fetching provinces:', error);
               showNotification('Gagal memuat data provinsi', 'error');
           });

       // Fetch Regencies
       document.getElementById('province').addEventListener('change', function() {
           const selectedOption = this.options[this.selectedIndex];
           const provinceId = selectedOption.dataset.id;
           const regencySelect = document.getElementById('regency');
           const districtSelect = document.getElementById('district');
           const villageSelect = document.getElementById('village');
           
           // Reset dependent dropdowns
           regencySelect.innerHTML = '<option value="">Pilih Kabupaten/Kota</option>';
           districtSelect.innerHTML = '<option value="">Pilih Kecamatan</option>';
           villageSelect.innerHTML = '<option value="">Pilih Kelurahan</option>';
           
           regencySelect.disabled = !provinceId;
           districtSelect.disabled = true;
           villageSelect.disabled = true;

           if (provinceId) {
               regencySelect.innerHTML = '<option value="">Memuat...</option>';
               
               fetch(`https://flavianusputratama.github.io/api-wilayah-indonesia/api/regencies/${provinceId}.json`)
                   .then(response => response.json())
                   .then(regencies => {
                       regencySelect.innerHTML = '<option value="">Pilih Kabupaten/Kota</option>';
                       regencies.forEach(regency => {
                           const option = document.createElement('option');
                           option.value = regency.name;
                           option.textContent = regency.name;
                           option.dataset.id = regency.id;
                           regencySelect.appendChild(option);
                      });
                      regencySelect.disabled = false;
                  })
                  .catch(error => {
                      console.error('Error fetching regencies:', error);
                      regencySelect.innerHTML = '<option value="">Error memuat data</option>';
                      showNotification('Gagal memuat data kabupaten/kota', 'error');
                  });
          }
      });

      // Fetch Districts
      document.getElementById('regency').addEventListener('change', function() {
          const selectedOption = this.options[this.selectedIndex];
          const regencyId = selectedOption.dataset.id;
          const districtSelect = document.getElementById('district');
          const villageSelect = document.getElementById('village');
          
          // Reset dependent dropdowns
          districtSelect.innerHTML = '<option value="">Pilih Kecamatan</option>';
          villageSelect.innerHTML = '<option value="">Pilih Kelurahan</option>';
          
          districtSelect.disabled = !regencyId;
          villageSelect.disabled = true;

          if (regencyId) {
              districtSelect.innerHTML = '<option value="">Memuat...</option>';
              
              fetch(`https://flavianusputratama.github.io/api-wilayah-indonesia/api/districts/${regencyId}.json`)
                  .then(response => response.json())
                  .then(districts => {
                      districtSelect.innerHTML = '<option value="">Pilih Kecamatan</option>';
                      districts.forEach(district => {
                          const option = document.createElement('option');
                          option.value = district.name;
                          option.textContent = district.name;
                          option.dataset.id = district.id;
                          districtSelect.appendChild(option);
                      });
                      districtSelect.disabled = false;
                  })
                  .catch(error => {
                      console.error('Error fetching districts:', error);
                      districtSelect.innerHTML = '<option value="">Error memuat data</option>';
                      showNotification('Gagal memuat data kecamatan', 'error');
                  });
          }
      });

      // Fetch Villages
      document.getElementById('district').addEventListener('change', function() {
          const selectedOption = this.options[this.selectedIndex];
          const districtId = selectedOption.dataset.id;
          const villageSelect = document.getElementById('village');
          
          // Reset village dropdown
          villageSelect.innerHTML = '<option value="">Pilih Kelurahan</option>';
          villageSelect.disabled = !districtId;

          if (districtId) {
              villageSelect.innerHTML = '<option value="">Memuat...</option>';
              
              fetch(`https://flavianusputratama.github.io/api-wilayah-indonesia/api/villages/${districtId}.json`)
                  .then(response => response.json())
                  .then(villages => {
                      villageSelect.innerHTML = '<option value="">Pilih Kelurahan</option>';
                      villages.forEach(village => {
                          const option = document.createElement('option');
                          option.value = village.name;
                          option.textContent = village.name;
                          villageSelect.appendChild(option);
                      });
                      villageSelect.disabled = false;
                  })
                  .catch(error => {
                      console.error('Error fetching villages:', error);
                      villageSelect.innerHTML = '<option value="">Error memuat data</option>';
                      showNotification('Gagal memuat data kelurahan', 'error');
                  });
          }
      });

      // Form validation before submit
      function validateForm() {
          const requiredFields = document.querySelectorAll('input[required], select[required]');
          let isValid = true;
          let firstInvalidField = null;

          requiredFields.forEach(field => {
              if (!field.value.trim()) {
                  field.classList.add('border-red-500');
                  if (!firstInvalidField) {
                      firstInvalidField = field;
                  }
                  isValid = false;
              } else {
                  field.classList.remove('border-red-500');
              }
          });

          // Check password confirmation
          const password = document.getElementById('password').value;
          const confirmPassword = document.getElementById('password_confirmation').value;
          
          if (password !== confirmPassword) {
              document.getElementById('password_confirmation').classList.add('border-red-500');
              if (!firstInvalidField) {
                  firstInvalidField = document.getElementById('password_confirmation');
              }
              isValid = false;
          }

          // Check password strength
          if (password.length < 8) {
              document.getElementById('password').classList.add('border-red-500');
              if (!firstInvalidField) {
                  firstInvalidField = document.getElementById('password');
              }
              isValid = false;
          }

          // Check NIK length
          const nik = document.getElementById('nik').value;
          if (nik.length !== 16) {
              document.getElementById('nik').classList.add('border-red-500');
              if (!firstInvalidField) {
                  firstInvalidField = document.getElementById('nik');
              }
              isValid = false;
              showNotification('NIK harus 16 digit', 'error');
          }

          // Check terms checkbox
          const termsCheckbox = document.querySelector('input[name="terms"]');
          if (!termsCheckbox.checked) {
              showNotification('Mohon setujui syarat dan ketentuan', 'error');
              isValid = false;
          }

          if (!isValid && firstInvalidField) {
              firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
              firstInvalidField.focus();
              showNotification('Mohon lengkapi semua field yang wajib diisi', 'error');
          }

          return isValid;
      }

      // Form submission with loading state
      document.querySelector('form').addEventListener('submit', function(e) {
          if (!validateForm()) {
              e.preventDefault();
              return;
          }

          const submitBtn = document.getElementById('submitBtn');
          const btnText = document.getElementById('btnText');
          
          submitBtn.disabled = true;
          submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
          btnText.innerHTML = '<span class="loading-dots">Mendaftar</span>';
          
          // Add spinner
          const spinner = document.createElement('i');
          spinner.className = 'fas fa-spinner fa-spin mr-2';
          btnText.insertBefore(spinner, btnText.firstChild);
      });

      // Add entrance animation
      document.addEventListener('DOMContentLoaded', function() {
          const card = document.querySelector('.glass-card');
          card.style.opacity = '0';
          card.style.transform = 'translateY(20px)';
          
          setTimeout(() => {
              card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
              card.style.opacity = '1';
              card.style.transform = 'translateY(0)';
          }, 100);
      });

      // Auto-scroll to error fields
      window.addEventListener('load', function() {
          const firstError = document.querySelector('.text-red-500');
          if (firstError) {
              const field = firstError.closest('.space-y-2')?.querySelector('input, select');
              if (field) {
                  setTimeout(() => {
                      field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                      field.focus();
                  }, 500);
              }
          }
      });

      // Real-time validation feedback
      const inputs = document.querySelectorAll('input, select');
      inputs.forEach(input => {
          input.addEventListener('blur', function() {
              if (this.hasAttribute('required') && !this.value.trim()) {
                  this.classList.add('border-red-500');
              } else {
                  this.classList.remove('border-red-500');
              }
          });

          input.addEventListener('input', function() {
              if (this.classList.contains('border-red-500') && this.value.trim()) {
                  this.classList.remove('border-red-500');
              }
          });
      });

      // Auto-format phone number
      document.getElementById('phone').addEventListener('input', function(e) {
          let value = e.target.value.replace(/\D/g, '');
          
          // Auto add country code if starting with 8
          if (value.startsWith('8') && value.length > 1) {
              value = '0' + value;
          }
          
          if (value.length > 15) {
              value = value.slice(0, 15);
          }
          
          e.target.value = value;
      });

      // Email validation
      document.getElementById('email').addEventListener('blur', function() {
          const email = this.value;
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          
          if (email && !emailRegex.test(email)) {
              this.classList.add('border-red-500');
              showNotification('Format email tidak valid', 'error');
          } else {
              this.classList.remove('border-red-500');
          }
      });

      // Prevent form resubmission on page refresh
      if (window.history.replaceState) {
          window.history.replaceState(null, null, window.location.href);
      }
  </script>
</body>
</html>