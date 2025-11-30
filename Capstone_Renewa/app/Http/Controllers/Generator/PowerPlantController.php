<?php

namespace App\Http\Controllers\Generator;

use App\Http\Controllers\Controller;
use App\Models\PowerPlant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PowerPlantController extends Controller
{
    /**
     * Memperbarui informasi pembangkit listrik.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PowerPlant  $powerPlant
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, PowerPlant $powerPlant)
    {
        // 1. Otorisasi: Pastikan pengguna yang login adalah pemilik pembangkit ini.
        if ($powerPlant->user_id !== Auth::id()) {
            abort(403, 'UNAUTHORIZED ACTION.');
        }

        // 2. Validasi: Tambahkan aturan untuk semua kolom yang bisa diedit.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'energy_type' => ['required', 'string'],
            'capacity' => ['required', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ]);

        // 3. Update data di database dengan semua data yang divalidasi
        $powerPlant->update($validated);

        // 4. Kembalikan ke dasbor dengan pesan sukses
        return redirect()->route('generator.dashboard')->with('success', 'Informasi pembangkit berhasil diperbarui.');
    }
}
