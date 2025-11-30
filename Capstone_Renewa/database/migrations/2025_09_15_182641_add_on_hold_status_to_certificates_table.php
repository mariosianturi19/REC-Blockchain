<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Menambahkan 'on_hold' ke dalam daftar ENUM pada kolom 'status'
        // Ini adalah sintaks SQL standar untuk mengubah definisi kolom ENUM
        DB::statement("ALTER TABLE certificates CHANGE COLUMN status status ENUM('pending_verification', 'available_for_sale', 'on_hold', 'sold', 'retired') NOT NULL DEFAULT 'pending_verification'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Mengembalikan definisi ENUM ke kondisi semula jika migrasi di-rollback
        DB::statement("ALTER TABLE certificates CHANGE COLUMN status status ENUM('pending_verification', 'available_for_sale', 'sold', 'retired') NOT NULL DEFAULT 'pending_verification'");
    }
};