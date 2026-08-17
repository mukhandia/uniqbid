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
        Schema::table('auction_results', function (Blueprint $table) {
            $table->foreignId('auction_bid_id')
                ->nullable()
                ->after('auction_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('sale_payment_status')->default('awaiting_payment')->after('sale_price');
            $table->timestamp('sale_payment_paid_at')->nullable()->after('sale_payment_status');
            $table->string('fulfilment_status')->default('pending')->after('sale_payment_paid_at');
            $table->timestamp('fulfilment_due_at')->nullable()->after('fulfilment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auction_results', function (Blueprint $table) {
            $table->dropForeign(['auction_bid_id']);
            $table->dropColumn([
                'auction_bid_id',
                'sale_payment_status',
                'sale_payment_paid_at',
                'fulfilment_status',
                'fulfilment_due_at',
            ]);
        });
    }
};
