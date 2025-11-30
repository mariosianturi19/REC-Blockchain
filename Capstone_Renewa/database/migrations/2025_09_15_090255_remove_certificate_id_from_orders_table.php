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
        Schema::table('orders', function (Blueprint $table) {
            // 1. Hapus dulu foreign key constraint-nya
            $table->dropForeign(['certificate_id']);
            
            // 2. Baru hapus kolomnya
            $table->dropColumn('certificate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Jika butuh rollback, buat kembali kolomnya
            $table->foreignId('certificate_id')->constrained('certificates')->after('buyer_id');
        });
    }
};