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
        Schema::create('power_plants', function (Blueprint $table) {
            $table->id(); // Kolom ID otomatis untuk setiap pembangkit

            // Kolom kunci untuk menghubungkan ke tabel 'users'
            // Jika seorang user dihapus, semua data pembangkitnya juga akan terhapus (onDelete('cascade'))
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Kolom untuk data spesifik pembangkit
            $table->string('name'); // Nama pembangkit, misal: "PLTA Saguling"
            $table->string('energy_type'); // PLTP, PLTA, atau PLTM
            $table->float('capacity', 8, 2); // Kapasitas dalam MW, misal: 150.50
            $table->string('operational_permit_path'); // Path ke file Izin Operasi yang diunggah

            $table->timestamps(); // Kolom created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('power_plants');
    }
};