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
        Schema::table('orders', function (Blueprint $table) {
            // Change status column to support all blockchain status values
            $table->enum('status', [
                'pending_payment',
                'awaiting_confirmation', 
                'payment_verified',  // ✅ ADDED: Status yang hilang di database
                'completed',
                'cancelled',
                // New blockchain status values
                'pending',
                'verified', 
                'requested',
                'issued',
                'purchase_requested',
                'purchased'
            ])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Revert to original enum values
            $table->enum('status', [
                'pending_payment',
                'awaiting_confirmation',
                'completed',
                'cancelled'
            ])->change();
        });
    }
};
