<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rules\Password; // <-- Pastikan ini di-import

class AuthController extends Controller
{
    /**
     * Display the buyer login view.
     */
    public function showLoginForm(Request $request)
    {
        // Logika untuk redirect dari navbar
        if ($request->has('source') && $request->source === 'navbar') {
            Session::put('login_redirect_target', 'welcome');
        } else {
            Session::forget('login_redirect_target');
        }
        return view('buyer.login');
    }

    /**
     * Menampilkan halaman profil buyer beserta data perusahaan.
     */
    public function showProfile()
    {
        $user = Auth::user();
        $company = $user->company; // Mengambil data relasi

        // Mengirim 'user' dan 'company' ke view
        return view('buyer.profile', compact('user', 'company'));
    }

    /**
     * Memperbarui informasi profil pengguna dan perusahaan.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        // PERBAIKAN: Gabungkan validasi untuk data personal dan perusahaan
        $validatedData = $request->validate([
            // Validasi Data Personal
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'nik' => ['required', 'string', 'digits:16', Rule::unique('users')->ignore($user->id)],
            'address' => 'required|string|max:255',
            'province' => 'required|string|max:255',
            'regency' => 'required|string|max:255',
            'district' => 'required|string|max:255',
            'village' => 'required|string|max:255',
            
            // Validasi Data Perusahaan (gunakan 'nullable' jika user boleh tidak mengisinya)
            'company_name' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:255',
            'company_phone_number' => 'nullable|string|max:20',
            'company_nib' => 'nullable|string|max:255',
        ]);

        // Update data personal
        $user->update([
            'name' => $validatedData['name'],
            'phone' => $validatedData['phone'],
            'nik' => $validatedData['nik'],
            'address' => $validatedData['address'],
            'province' => $validatedData['province'],
            'regency' => $validatedData['regency'],
            'district' => $validatedData['district'],
            'village' => $validatedData['village'],
        ]);
        
        // PERBAIKAN: Tambahkan logika untuk update data perusahaan
        // Hanya proses jika company_name diisi, sebagai penanda data perusahaan ada
        if ($request->filled('company_name')) {
            $user->company()->updateOrCreate(
                ['user_id' => $user->id], // Kondisi pencarian
                [ // Data untuk diupdate atau dibuat
                    'name' => $validatedData['company_name'],
                    'address' => $validatedData['company_address'],
                    'phone_number' => $validatedData['company_phone_number'],
                    'nib' => $validatedData['company_nib'],
                ]
            );
        }

        return redirect()->route('buyer.profile.show')->with('success', 'Profil Anda berhasil diperbarui!');
    }

    /**
     * Memperbarui password pengguna.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', function ($attribute, $value, $fail) use ($user) {
                if (!Hash::check($value, $user->password)) {
                    $fail('Password lama yang Anda masukkan salah.');
                }
            }],
            'new_password' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Arahkan kembali ke showProfile untuk memuat ulang semua data
        return redirect()->route('buyer.profile.show')->with('success', 'Password Anda berhasil diubah!');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();

            if (Auth::user()->role === 'buyer') {
                // Logika redirect baru dari navbar
                if (Session::has('login_redirect_target') && Session::get('login_redirect_target') === 'welcome') {
                    Session::forget('login_redirect_target');
                    return redirect()->route('welcome');
                }
                
                // Redirect default
                return redirect()->intended(route('buyer.categoryselect'));

            } else {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Kredensial ini tidak memiliki akses sebagai pembeli.',
                ])->onlyInput('email');
            }
        }

        return back()->withErrors([
            'email' => __('Email atau password yang Anda masukkan tidak valid.'),
        ])->onlyInput('email');
    }

    /**
     * Display the buyer registration view.
     */
    public function showRegistrationForm()
    {
        return view('buyer.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:15'],
            'nik' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['required', 'accepted'],
            'address' => ['required', 'string', 'max:255'],
            'province' => ['required', 'string'],
            'regency' => ['required', 'string'],
            'district' => ['required', 'string'],
            'village' => ['required', 'string'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'nik' => $request->nik,
            'address' => $request->address,
            'province' => $request->province,
            'regency' => $request->regency,
            'district' => $request->district,
            'village' => $request->village,
            'password' => Hash::make($request->password),
            'role' => 'buyer',
        ]);

        event(new Registered($user));
        Auth::login($user);

        return redirect('/');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}