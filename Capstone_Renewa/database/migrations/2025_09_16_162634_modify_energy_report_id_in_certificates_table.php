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
        Schema::table('certificates', function (Blueprint $table) {
            // Langkah 1: Hapus Foreign Key terlebih dahulu
            // Nama foreign key biasanya: 'namaTabel_namaKolom_foreign'
            $table->dropForeign('certificates_energy_report_id_foreign');

            // Langkah 2: Hapus Unique Index
            $table->dropUnique('certificates_energy_report_id_unique');

            // Langkah 3: Tambahkan kembali Foreign Key (TANPA unique)
            $table->foreign('energy_report_id')
                  ->references('id')
                  ->on('energy_reports')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign('certificates_energy_report_id_foreign');
            $table->unique('energy_report_id');
            $table->foreign('energy_report_id')
                  ->references('id')
                  ->on('energy_reports')
                  ->onDelete('cascade');
        });
    }
};