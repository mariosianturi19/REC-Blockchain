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
        Schema::table('energy_reports', function (Blueprint $table) {
            $table->string('blockchain_energy_id')->nullable()->after('status');
            $table->string('blockchain_status')->nullable()->after('blockchain_energy_id');
            $table->text('blockchain_response')->nullable()->after('blockchain_status');
            $table->string('blockchain_verification_status')->nullable()->after('blockchain_response');
            $table->text('blockchain_verification_response')->nullable()->after('blockchain_verification_status');
            $table->text('blockchain_verification_error')->nullable()->after('blockchain_verification_response');
            $table->text('blockchain_error')->nullable()->after('blockchain_verification_error');
            
            $table->index('blockchain_energy_id');
            $table->index('blockchain_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('energy_reports', function (Blueprint $table) {
            $table->dropIndex(['blockchain_energy_id']);
            $table->dropIndex(['blockchain_status']);
            $table->dropColumn([
                'blockchain_energy_id',
                'blockchain_status',
                'blockchain_response',
                'blockchain_verification_status',
                'blockchain_verification_response',
                'blockchain_verification_error',
                'blockchain_error'
            ]);
        });
    }
};