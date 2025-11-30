<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $role  Peran yang diizinkan (misal: 'admin', 'buyer', 'issuer')
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 1. Periksa apakah pengguna sudah login. Jika belum, lempar ke halaman login.
        if (!Auth::check()) {
            // Arahkan ke halaman login utama, karena kita tidak tahu dia seharusnya login sebagai apa
            return redirect()->route('buyer.login');
        }

        $user = Auth::user();

        // 2. Periksa apakah peran pengguna SESUAI dengan peran yang dibutuhkan oleh rute.
        //    Dan periksa apakah status akunnya sudah 'approved'.
        if ($user->role === $role && $user->status === 'approved') {
            // Jika peran dan status sesuai, izinkan akses.
            return $next($request);
        }

        // 3. Jika pengguna adalah 'pending' atau 'rejected', logout dan beri pesan.
        if ($user->status === 'pending') {
            Auth::logout();
            return redirect()->route('buyer.login')->with('error', 'Akun Anda sedang dalam proses verifikasi. Silakan tunggu konfirmasi dari admin.');
        }

        if ($user->status === 'rejected') {
            Auth::logout();
            return redirect()->route('buyer.login')->with('error', 'Maaf, pendaftaran Anda telah ditolak.');
        }

        // 4. Jika peran tidak sesuai (misal: buyer mencoba akses dashboard admin),
        //    tampilkan halaman 403 Forbidden.
        abort(403, 'UNAUTHORIZED ACTION.');
    }
}