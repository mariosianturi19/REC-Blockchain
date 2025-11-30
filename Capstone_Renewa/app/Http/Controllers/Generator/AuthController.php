<?php

namespace App\Http\Controllers\Generator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PowerPlant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    /**
     * Menampilkan form pendaftaran untuk Generator.
     */
    public function showRegistrationForm()
    {
        return view('generator.register');
    }

    /**
     * Menangani permintaan pendaftaran untuk Generator.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:15'],
            'power_plant_name' => ['required', 'string', 'max:255'],
            'energy_type' => ['required', 'string'],
            'capacity' => ['required', 'numeric'],
            'operational_permit_document' => ['required', 'file', 'mimes:pdf', 'max:2048'],
            'image_url' => ['nullable', 'url', 'max:2048'], // <-- TAMBAHKAN VALIDASI INI
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['required', 'accepted'],
        ]);

        DB::transaction(function () use ($request) {
            $documentPath = $request->file('operational_permit_document')->store('generator_documents', 'public');

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'generator',
                'status' => 'pending',
            ]);

            $user->powerPlants()->create([
                'name' => $request->power_plant_name,
                'energy_type' => $request->energy_type,
                'capacity' => $request->capacity,
                'operational_permit_path' => $documentPath,
                'image_url' => $request->image_url, // <-- SIMPAN DATA BARU INI
            ]);
        });

        return redirect()->route('login')->with('status', 'Pendaftaran Anda sebagai Generator telah diterima. Akun akan diaktifkan setelah proses verifikasi.');
    }
}
