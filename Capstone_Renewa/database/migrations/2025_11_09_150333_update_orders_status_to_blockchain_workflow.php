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
            // ✅ Ubah kolom status dari ENUM ke VARCHAR untuk blockchain workflow
            $table->string('status', 50)->change();
        });
        
        // ✅ Update existing records to use new blockchain workflow status
        DB::statement("
            UPDATE orders 
            SET status = CASE 
                WHEN status = 'pending_payment' THEN 'CERTIFICATE_REQUESTED'
                WHEN status = 'awaiting_confirmation' THEN 'CERTIFICATE_PAID'
                WHEN status = 'payment_verified' THEN 'CERTIFICATE_PAID'
                WHEN status = 'completed' THEN 'COMPLETED'
                WHEN status = 'cancelled' THEN 'CANCELLED'
                ELSE status
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Rollback to old ENUM values
            $table->enum('status', [
                'pending_payment',
                'awaiting_confirmation',
                'payment_verified',
                'completed',
                'cancelled',
                'pending',
                'verified',
                'requested',
                'issued',
                'purchase_requested',
                'purchased'
            ])->change();
        });
    }
};
