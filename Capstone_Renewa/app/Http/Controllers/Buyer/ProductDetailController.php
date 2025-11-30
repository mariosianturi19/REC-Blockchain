<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\PowerPlant;
use Illuminate\Http\Request;

class ProductDetailController extends Controller
{
    /**
     * Menampilkan halaman detail untuk satu pembangkit listrik.
     */
    public function show(Request $request, PowerPlant $powerPlant)
    {
        // 1. Hitung total energi yang tersedia dengan nama kolom yang spesifik
        $availableMwh = $powerPlant->certificates()
                                  ->where('certificates.status', 'available_for_sale')
                                  ->sum('certificates.amount_mwh');

        // 2. Tentukan minimal pembelian berdasarkan kategori dari URL
        $category = $request->query('category', 'Signature');
        $minPurchase = match ($category) {
            'Retail' => 10,
            'Enterprise' => 200,
            default => 0,
        };

        // 3. Kirim semua variabel yang dibutuhkan oleh view (TIDAK ADA $data)
        return view('buyer.product-detail', [
            'powerPlant' => $powerPlant,
            'availableMwh' => $availableMwh,
            'category' => $category,
            'minPurchase' => $minPurchase,
        ]);
    }
}