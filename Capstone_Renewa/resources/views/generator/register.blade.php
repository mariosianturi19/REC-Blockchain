<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Generator - Renewa</title>
    <meta name="description" content="Daftarkan pembangkit energi terbarukan Anda di platform Renewa.">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        /* Mengadopsi semua gaya dari halaman pendaftaran Buyer */
        .auth-gradient { background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 25%, #f0f9ff 75%, #eff6ff 100%); }
        .glass-card { background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.2); }
        .modern-input, .modern-select, .modern-file-input-wrapper { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(8px); border: 1px solid rgba(229, 231, 235, 0.6); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .modern-input:focus, .modern-select:focus, .modern-file-input-wrapper:focus-within { background: rgba(255, 255, 255, 0.95); border-color: rgb(34, 197, 94); box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1); transform: translateY(-1px); }
        .modern-btn { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; }
        .modern-btn::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent); transition: left 0.5s; }
        .modern-btn:hover::before { left: 100%; }
        .modern-btn:hover { transform: translateY(-2px); box-shadow: 0 15px 35px rgba(16, 185, 129, 0.3); }
        .floating { animation: floating 3s ease-in-out infinite; }
        @keyframes floating { 0%, 100% { transform: translateY(0px) rotate(0deg); } 50% { transform: translateY(-10px) rotate(2deg); } }
        .logo-pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        .section-divider { position: relative; margin: 2rem 0; }
        .section-divider::before { content: ''; position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(229, 231, 235, 0.8), transparent); }
        .section-title { background: rgba(255, 255, 255, 0.9); padding: 0 1rem; margin: 0 auto; width: fit-content; position: relative; z-index: 1; }
        .form-container { max-height: 85vh; overflow-y: auto; scrollbar-width: thin; scrollbar-color: rgba(34, 197, 94, 0.3) transparent; }
        .form-container::-webkit-scrollbar { width: 6px; }
        .form-container::-webkit-scrollbar-track { background: transparent; }
        .form-container::-webkit-scrollbar-thumb { background: rgba(34, 197, 94, 0.3); border-radius: 3px; }
        .form-container::-webkit-scrollbar-thumb:hover { background: rgba(34, 197, 94, 0.5); }
        .loading-dots { display: inline-block; }
        .loading-dots::after { content: ''; animation: loadingDots 1.5s infinite; }
        @keyframes loadingDots { 0%, 20% { content: '.'; } 40% { content: '..'; } 60%, 100% { content: '...'; } }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center auth-gradient p-4">
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute top-10 left-10 w-32 h-32 bg-green-200 rounded-full opacity-20 floating"></div>
        <div class="absolute top-20 right-20 w-24 h-24 bg-blue-200 rounded-full opacity-20 floating" style="animation-delay: -1s;"></div>
        <div class="absolute bottom-20 left-20 w-40 h-40 bg-green-100 rounded-full opacity-30 floating" style="animation-delay: -2s;"></div>
        <div class="absolute bottom-10 right-10 w-28 h-28 bg-blue-100 rounded-full opacity-25 floating" style="animation-delay: -0.5s;"></div>
    </div>

    <div class="w-full max-w-4xl relative z-10">
        <div class="text-center mb-8">
            <a href="{{ route('welcome') }}" class="inline-block">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-green-500 to-green-600 rounded-2xl mb-4 logo-pulse">
                    <i class="fas fa-leaf text-white text-2xl"></i>
                </div>
            </a>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Pendaftaran Akun Generator</h1>
            <p class="text-gray-600">Daftarkan pembangkit energi terbarukan Anda untuk verifikasi.</p>
        </div>

        <div class="glass-card rounded-3xl overflow-hidden">
            <div class="form-container p-8">
                <form method="POST" action="{{ route('generator.register') }}" enctype="multipart/form-data" class="space-y-8">
                    @csrf

                    <div>
                        <div class="section-divider">
                            <h3 class="section-title text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-user-tie mr-3 text-green-600"></i>
                                Informasi Kontak (PIC/Perusahaan)
                            </h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label for="name" class="block text-sm font-semibold text-gray-700">Nama Lengkap PIC *</label>
                                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400" placeholder="Masukkan nama lengkap">
                            </div>
                            <div class="space-y-2">
                                <label for="email" class="block text-sm font-semibold text-gray-700">Email *</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" required class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400" placeholder="nama@email.com">
                            </div>
                            <div class="md:col-span-2 space-y-2">
                                <label for="phone" class="block text-sm font-semibold text-gray-700">Nomor Telepon *</label>
                                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400" placeholder="08xxxxxxxxxx">
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="section-divider">
                            <h3 class="section-title text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-industry mr-3 text-green-600"></i>
                                Informasi Pembangkit
                            </h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label for="power_plant_name" class="block text-sm font-semibold text-gray-700">Nama Pembangkit *</label>
                                <input id="power_plant_name" type="text" name="power_plant_name" value="{{ old('power_plant_name') }}" required class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400" placeholder="Contoh: PLTA Cirata">
                            </div>
                            <div class="space-y-2">
                                <label for="energy_type" class="block text-sm font-semibold text-gray-700">Jenis Energi *</label>
                                <select id="energy_type" name="energy_type" class="modern-select w-full px-4 py-3 rounded-xl focus:outline-none" required>
                                    <option value="" disabled {{ old('energy_type') ? '' : 'selected' }}>Pilih Jenis Energi</option>
                                    <option value="PLTP" {{ old('energy_type') == 'PLTP' ? 'selected' : '' }}>PLTP (Panas Bumi)</option>
                                    <option value="PLTA" {{ old('energy_type') == 'PLTA' ? 'selected' : '' }}>PLTA (Air)</option>
                                    <option value="PLTM" {{ old('energy_type') == 'PLTM' ? 'selected' : '' }}>PLTM (Mikrohidro)</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label for="capacity" class="block text-sm font-semibold text-gray-700">Kapasitas (MW) *</label>
                                <input id="capacity" type="number" step="0.01" name="capacity" value="{{ old('capacity') }}" required class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400" placeholder="Contoh: 100.5">
                            </div>
                            <div class="space-y-2">
                                <label for="operational_permit_document" class="block text-sm font-semibold text-gray-700">Unggah Izin Operasi (PDF) *</label>
                                <input id="operational_permit_document" type="file" name="operational_permit_document" required accept=".pdf" class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-green-100 file:text-green-700 hover:file:bg-green-200">
                                <p class="text-xs text-gray-500">Maksimal 2MB.</p>
                            </div>
                            <div class="md:col-span-2 space-y-2">
                                <label for="image_url" class="block text-sm font-semibold text-gray-700">URL Gambar Pembangkit</label>
                                <input id="image_url" type="url" name="image_url" value="{{ old('image_url') }}" class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400" placeholder="https://contoh.com/gambar.jpg">
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="section-divider">
                            <h3 class="section-title text-xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-lock mr-3 text-green-600"></i>
                                Keamanan Akun
                            </h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label for="password" class="block text-sm font-semibold text-gray-700">Password *</label>
                                <div class="relative">
                                    <input id="password" type="password" name="password" required class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400 pr-12" placeholder="Minimal 8 karakter">
                                    <button type="button" onclick="togglePassword('password', 'eyeIcon1')" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                                        <i id="eyeIcon1" class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="passwordStrength" class="text-xs text-gray-500 mt-1"></div>
                            </div>
                            <div class="space-y-2">
                                <label for="password_confirmation" class="block text-sm font-semibold text-gray-700">Konfirmasi Password *</label>
                                <div class="relative">
                                    <input id="password_confirmation" type="password" name="password_confirmation" required class="modern-input w-full px-4 py-3 rounded-xl focus:outline-none placeholder-gray-400 pr-12" placeholder="Ulangi password">
                                    <button type="button" onclick="togglePassword('password_confirmation', 'eyeIcon2')" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                                        <i id="eyeIcon2" class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div id="passwordMatch" class="text-xs mt-1"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6 bg-gray-50 rounded-xl">
                        <label class="flex items-start space-x-3">
                            <input type="checkbox" name="terms" required class="w-5 h-5 text-green-600 bg-white border-gray-300 rounded focus:ring-green-500 focus:ring-2 mt-0.5">
                            <span class="text-sm text-gray-700 leading-relaxed">
                                Saya menyatakan bahwa data yang diisi adalah benar dan dapat dipertanggungjawabkan.
                            </span>
                        </label>
                    </div>

                    <div class="pt-6">
                        <button type="submit" id="submitBtn" class="modern-btn w-full py-4 px-6 rounded-xl font-semibold text-white text-lg">
                            <span id="btnText">Ajukan Pendaftaran</span>
                        </button>
                    </div>
                </form>

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
         <div class="text-center mt-6">
            <a href="{{ route('welcome') }}" class="text-gray-500 hover:text-gray-700 text-sm transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke beranda
            </a>
        </div>
    </div>

    <script>
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
           if (password.length >= 8) strength += 1; else feedback.push('minimal 8 karakter');
           if (/[a-z]/.test(password)) strength += 1; else feedback.push('huruf kecil');
           if (/[A-Z]/.test(password)) strength += 1; else feedback.push('huruf besar');
           if (/[0-9]/.test(password)) strength += 1; else feedback.push('angka');
           if (/[^A-Za-z0-9]/.test(password)) strength += 1; else feedback.push('karakter khusus');
           
           const strengthEl = document.getElementById('passwordStrength');
           if (password.length === 0) {
               strengthEl.innerHTML = ''; return;
           }
           
           let strengthText = ''; let strengthClass = '';
           switch (strength) {
               case 0: case 1:
                   strengthText = `<i class="fas fa-times text-red-500 mr-1"></i>Lemah`; strengthClass = 'text-red-500'; break;
               case 2: case 3:
                   strengthText = `<i class="fas fa-minus text-yellow-500 mr-1"></i>Sedang`; strengthClass = 'text-yellow-500'; break;
               case 4: case 5:
                   strengthText = `<i class="fas fa-check text-green-500 mr-1"></i>Kuat`; strengthClass = 'text-green-500'; break;
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
               matchEl.innerHTML = ''; return;
           }
           if (password === confirmPassword) {
               matchEl.innerHTML = '<i class="fas fa-check text-green-500 mr-1"></i>Password cocok';
               matchEl.className = 'text-xs mt-1 text-green-500';
           } else {
               matchEl.innerHTML = '<i class="fas fa-times text-red-500 mr-1"></i>Password tidak cocok';
               matchEl.className = 'text-xs mt-1 text-red-500';
           }
       }

        document.getElementById('password').addEventListener('input', e => {
            checkPasswordStrength(e.target.value);
            checkPasswordMatch();
        });
        document.getElementById('password_confirmation').addEventListener('input', checkPasswordMatch);

        document.querySelector('form').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
            btnText.innerHTML = '<span class="loading-dots">Mengajukan</span>';
        });

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
    </script>
</body>
</html>