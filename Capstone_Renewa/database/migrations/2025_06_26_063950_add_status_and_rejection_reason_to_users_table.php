<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Menambahkan kolom 'status' setelah kolom 'role'
            // 'approved' = akun aktif, 'pending' = menunggu verifikasi admin, 'rejected' = ditolak
            $table->string('status')->default('approved')->after('role');

            // Menambahkan kolom untuk menyimpan alasan penolakan
            $table->text('rejection_reason')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hapus kolom jika migrasi di-rollback
            $table->dropColumn(['status', 'rejection_reason']);
        });
    }
};