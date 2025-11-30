<!-- resources/views/issuer/login.blade.php -->
<x-guest-layout>
    <div class="mb-4 text-center">
        <h2 class="text-2xl font-bold text-gray-800">Masuk sebagai Penerbit REC</h2>
        <p class="text-gray-600">Silakan masuk untuk mengelola sertifikat energi terbarukan Anda</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('issuer.login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                          type="password"
                          name="password"
                          required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Ingat saya') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-4">
            <a class="text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500" href="{{ route('password.request') }}">
                {{ __('Lupa password?') }}
            </a>

            <div class="flex items-center space-x-3">
                <a href="{{ route('issuer.register') }}" class="text-green-600 hover:text-green-700 text-sm">
                    {{ __('Belum punya akun?') }}
                </a>
                
                <x-primary-button class="bg-green-500 hover:bg-green-600 focus:bg-green-600 active:bg-green-700 focus:ring-green-500">
                    {{ __('Masuk') }}
                </x-primary-button>
            </div>
        </div>
    </form>
</x-guest-layout>