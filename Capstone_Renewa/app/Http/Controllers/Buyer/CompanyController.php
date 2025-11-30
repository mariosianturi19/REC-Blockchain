<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Cek jika pengguna sudah punya data perusahaan
        if (Auth::user()->company) {
            return redirect()->route('checkout.summary'); // Lanjutkan ke ringkasan pesanan jika sudah ada
        }
        return view('buyer.company-details');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'nib' => 'required|string|max:255',
        ]);

        Company::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'address' => $request->address,
            'phone_number' => $request->phone_number,
            'nib' => $request->nib,
        ]);

        return redirect()->route('checkout.summary'); // Arahkan ke ringkasan pesanan setelah berhasil
    }
}