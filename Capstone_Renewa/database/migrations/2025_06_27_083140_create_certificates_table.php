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
        Schema::create('certificates', function (Blueprint $table) {
            $table->id(); // ID unik untuk setiap bundel sertifikat

            // Kolom kunci untuk menghubungkan sertifikat ini ke laporan energi asalnya
            $table->foreignId('energy_report_id')->unique()->constrained()->onDelete('cascade');
            
            // Kolom kunci untuk menandai siapa yang menerbitkan sertifikat ini
            $table->foreignId('issuer_id')->constrained('users')->onDelete('cascade');

            // Kolom kunci untuk menandai siapa pemilik sertifikat saat ini (awalnya adalah Generator)
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');

            // ID unik untuk sertifikat, bisa digunakan untuk pelacakan publik
            $table->string('certificate_uid')->unique();

            // Jumlah energi dalam MWh yang diwakili oleh bundel sertifikat ini
            $table->decimal('amount_mwh', 15, 2);

            // Periode produksi energi yang dicakup oleh sertifikat ini
            $table->date('generation_start_date');
            $table->date('generation_end_date');

            // Status siklus hidup sertifikat
            $table->enum('status', ['available_for_sale', 'sold', 'retired'])->default('available_for_sale');

            $table->timestamps(); // Waktu penerbitan (created_at) dan pembaruan
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};