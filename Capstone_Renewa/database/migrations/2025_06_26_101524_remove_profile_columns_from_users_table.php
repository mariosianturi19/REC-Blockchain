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
            // Kolom-kolom yang akan kita hapus
            $columnsToDrop = [
                'company_name',
                'nib',
                'legal_document_path',
                'power_plant_name',
                'energy_type',
                'capacity',
                'operational_permit_path',
            ];

            // Loop dan periksa setiap kolom sebelum menghapusnya
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Method down tetap sama, untuk mengembalikan kolom jika diperlukan
        Schema::table('users', function (Blueprint $table) {
            $table->string('company_name')->nullable();
            $table->string('nib')->nullable();
            $table->string('legal_document_path')->nullable();
            $table->string('power_plant_name')->nullable();
            $table->string('energy_type')->nullable();
            $table->float('capacity', 8, 2)->nullable();
            $table->string('operational_permit_path')->nullable();
        });
    }
};