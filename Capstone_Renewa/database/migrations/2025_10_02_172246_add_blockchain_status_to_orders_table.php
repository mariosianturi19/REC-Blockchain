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
            $table->string('blockchain_status')->nullable()->after('status');
            $table->text('blockchain_response')->nullable()->after('blockchain_status');
            $table->text('blockchain_error')->nullable()->after('blockchain_response');
            
            $table->index('blockchain_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['blockchain_status']);
            $table->dropColumn([
                'blockchain_status',
                'blockchain_response',
                'blockchain_error'
            ]);
        });
    }
};
