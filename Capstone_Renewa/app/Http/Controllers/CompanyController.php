<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    /**
     * Cari perusahaan berdasarkan nama dan kembalikan URL redirect dalam format JSON.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
        ]);

        $company = Company::where('name', 'LIKE', '%' . $request->company_name . '%')->first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Perusahaan tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'redirect_url' => route('rec.show.company', ['company' => $company])
        ]);
    }

    /**
     * Tampilkan detail perusahaan dan riwayat pembelian REC.
     * ✅ FIXED: Load semua orders Enterprise dengan certificates dan power plant info
     */
    public function show(Company $company)
    {
        // ✅ Load semua orders Enterprise (tidak hanya completed) dengan relasi lengkap
        $company->load(['user.orders' => function ($query) {
            $query->where('category', 'Enterprise')
                  ->with(['certificates.energyReport.powerPlant'])
                  ->orderBy('created_at', 'desc');
        }]);

        return view('company-detail', compact('company'));
    }
}
