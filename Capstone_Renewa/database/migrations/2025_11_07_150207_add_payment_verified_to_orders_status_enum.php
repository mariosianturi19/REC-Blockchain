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
        // Add payment_verified to the status enum in orders table
        DB::statement("ALTER TABLE `orders` CHANGE `status` `status` ENUM('pending_payment','awaiting_confirmation','payment_verified','completed','cancelled','pending','verified','requested','issued','purchase_requested','purchased') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove payment_verified from the status enum
        DB::statement("ALTER TABLE `orders` CHANGE `status` `status` ENUM('pending_payment','awaiting_confirmation','completed','cancelled','pending','verified','requested','issued','purchase_requested','purchased') NOT NULL");
    }
};
