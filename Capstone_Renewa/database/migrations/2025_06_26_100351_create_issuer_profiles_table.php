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
        Schema::create('issuer_profiles', function (Blueprint $table) {
            $table->id();

            // Kolom kunci untuk relasi One-to-One dengan tabel 'users'
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Kolom untuk data spesifik institusi
            $table->string('company_name');
            $table->string('nib')->unique(); // NIB harus unik
            $table->string('legal_document_path');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issuer_profiles');
    }
};