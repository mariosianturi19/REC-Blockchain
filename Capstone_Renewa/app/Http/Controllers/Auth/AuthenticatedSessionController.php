<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Menampilkan view untuk form login.
     */
    public function create()
    {
        // View ini akan kita buat di langkah berikutnya
        return view('auth.login');
    }

    /**
     * Menangani permintaan login yang masuk.
     */
    public function store(Request $request)
    {
        // 1. Validasi input
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // 2. Coba autentikasi
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            
            $request->session()->regenerate();
            
            $user = Auth::user();

            // 3. Periksa status akun SEBELUM redirect
            if ($user->status !== 'approved') {
                $statusMessage = $user->status === 'pending'
                    ? 'Akun Anda sedang dalam proses verifikasi. Silakan tunggu konfirmasi dari admin.'
                    : 'Maaf, pendaftaran Anda telah ditolak.';
                
                Auth::logout(); // Logout pengguna
                
                return back()->withErrors(['email' => $statusMessage])->onlyInput('email');
            }

            // 4. Logika Pengalihan Cerdas (Smart Redirect)
            $redirectPath = match ($user->role) {
                'admin'   => route('admin.dashboard'),
                'buyer'   => route('welcome'), // Pastikan nama rute ini benar
                'issuer'  => route('issuer.dashboard'),
                'generator' => '/generator/dashboard', // Ganti dengan nama rute jika sudah ada
                default   => '/',
            };

            return redirect()->intended($redirectPath);
        }

        // Jika kredensial salah
        return back()->withErrors([
            'email' => 'Kredensial yang diberikan tidak cocok dengan data kami.',
        ])->onlyInput('email');
    }
    
    /**
     * Menghancurkan sesi autentikasi (logout).
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
