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
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('blockchain_cert_id')->nullable()->after('certificate_uid');
            $table->string('blockchain_status')->nullable()->after('blockchain_cert_id');
            $table->text('blockchain_response')->nullable()->after('blockchain_status');
            $table->string('blockchain_purchase_status')->nullable()->after('blockchain_response');
            $table->text('blockchain_purchase_response')->nullable()->after('blockchain_purchase_status');
            $table->text('blockchain_confirm_response')->nullable()->after('blockchain_purchase_response');
            $table->text('blockchain_purchase_error')->nullable()->after('blockchain_confirm_response');
            $table->text('blockchain_confirm_error')->nullable()->after('blockchain_purchase_error');
            $table->text('blockchain_reject_reason')->nullable()->after('blockchain_confirm_error');
            $table->text('blockchain_error')->nullable()->after('blockchain_reject_reason');
            
            $table->index('blockchain_cert_id');
            $table->index('blockchain_status');
            $table->index('blockchain_purchase_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropIndex(['blockchain_cert_id']);
            $table->dropIndex(['blockchain_status']);
            $table->dropIndex(['blockchain_purchase_status']);
            $table->dropColumn([
                'blockchain_cert_id',
                'blockchain_status',
                'blockchain_response',
                'blockchain_purchase_status',
                'blockchain_purchase_response',
                'blockchain_confirm_response',
                'blockchain_purchase_error',
                'blockchain_confirm_error',
                'blockchain_reject_reason',
                'blockchain_error'
            ]);
        });
    }
};