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
            // Certificate request tracking fields
            $table->boolean('certificate_requested')->default(false)->after('status');
            $table->string('certificate_id')->nullable()->after('certificate_requested');
            $table->string('certificate_status')->nullable()->after('certificate_id');
            $table->json('certificate_response')->nullable()->after('certificate_status');
            $table->timestamp('certificate_requested_at')->nullable()->after('certificate_response');
            
            // Add index for better query performance
            $table->index(['status', 'certificate_requested']);
            $table->index('certificate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('energy_reports', function (Blueprint $table) {
            $table->dropIndex(['status', 'certificate_requested']);
            $table->dropIndex(['certificate_id']);
            $table->dropColumn([
                'certificate_requested',
                'certificate_id', 
                'certificate_status',
                'certificate_response',
                'certificate_requested_at'
            ]);
        });
    }
};
