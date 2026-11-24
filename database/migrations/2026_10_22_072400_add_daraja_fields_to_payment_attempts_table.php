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
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->string('checkout_request_id')->nullable()->unique()->after('reference');
            $table->string('merchant_request_id')->nullable()->after('checkout_request_id');
            $table->string('mpesa_receipt')->nullable()->after('merchant_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropUnique(['checkout_request_id']);
            $table->dropColumn(['checkout_request_id', 'merchant_request_id', 'mpesa_receipt']);
        });
    }
};
