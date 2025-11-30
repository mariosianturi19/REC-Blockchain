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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // ID Unik untuk transaksi/pesanan
            $table->string('order_uid')->unique();

            // Kolom kunci untuk menghubungkan pesanan ini ke pembeli
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            
            // Kolom kunci untuk menghubungkan pesanan ini ke sertifikat yang dibeli
            $table->foreignId('certificate_id')->constrained()->onDelete('cascade');

            // Detail transaksi
            $table->decimal('total_price', 15, 2);
            $table->string('virtual_account_number')->nullable(); // Untuk simulasi

            // Status alur kerja pesanan
            $table->enum('status', [
                'pending_payment', 
                'awaiting_confirmation', 
                'completed', 
                'cancelled'
            ])->default('pending_payment');
            
            $table->timestamp('payment_confirmed_at')->nullable(); // Waktu pembayaran dikonfirmasi buyer
            $table->timestamp('order_completed_at')->nullable(); // Waktu pesanan diselesaikan admin

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};