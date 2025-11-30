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
        Schema::create('energy_reports', function (Blueprint $table) {
            $table->id();

            // Kolom kunci untuk menghubungkan ke pembangkit yang melapor
            $table->foreignId('power_plant_id')->constrained()->onDelete('cascade');

            // Periode pelaporan
            $table->date('reporting_period_start');
            $table->date('reporting_period_end');
            
            // Jumlah energi yang dilaporkan dalam MWh
            $table->decimal('amount_mwh', 15, 2); // Angka besar dengan 2 desimal

            // Path ke dokumen bukti (jika ada)
            $table->string('supporting_document_path')->nullable();

            // Status laporan untuk alur kerja
            $table->enum('status', ['pending_verification', 'approved', 'rejected'])->default('pending_verification');
            
            // Catatan dari Issuer jika laporan ditolak
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('energy_reports');
    }
};