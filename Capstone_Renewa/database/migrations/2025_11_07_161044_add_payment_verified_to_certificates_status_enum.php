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
        // Add payment_verified to the status enum in certificates table
        DB::statement("ALTER TABLE `certificates` CHANGE `status` `status` ENUM('pending_verification','available_for_sale','on_hold','payment_verified','sold','retired','completed') NOT NULL DEFAULT 'pending_verification'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove payment_verified from the status enum
        DB::statement("ALTER TABLE `certificates` CHANGE `status` `status` ENUM('pending_verification','available_for_sale','on_hold','sold','retired','completed') NOT NULL DEFAULT 'pending_verification'");
    }
};
