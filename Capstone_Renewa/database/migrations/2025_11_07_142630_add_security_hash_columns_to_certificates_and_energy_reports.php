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
        // Add security hash columns to certificates table
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('certificate_hash', 64)->nullable()->after('blockchain_response');
            $table->string('serial_number', 50)->nullable()->after('certificate_hash');
            $table->json('ownership_proof')->nullable()->after('serial_number');
            $table->json('security_validation')->nullable()->after('ownership_proof');
            
            // Add indexes for performance
            $table->index('certificate_hash');
            $table->index('serial_number');
        });

        // Add security hash columns to energy_reports table
        Schema::table('energy_reports', function (Blueprint $table) {
            $table->string('energy_data_hash', 64)->nullable()->after('blockchain_verification_response');
            $table->boolean('anti_duplication_verified')->default(false)->after('energy_data_hash');
            
            // Add index for performance
            $table->index('energy_data_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['certificate_hash']);
            $table->dropIndex(['serial_number']);
            $table->dropColumn(['certificate_hash', 'serial_number', 'ownership_proof', 'security_validation']);
        });

        Schema::table('energy_reports', function (Blueprint $table) {
            $table->dropIndex(['energy_data_hash']);
            $table->dropColumn(['energy_data_hash', 'anti_duplication_verified']);
        });
    }
};
