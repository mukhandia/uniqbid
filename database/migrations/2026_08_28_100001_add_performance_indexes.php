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
            $table->index('sale_payment_status');
            $table->index('fulfilment_status');
            $table->index('fulfilment_due_at');
            $table->index(['sale_payment_status', 'fulfilment_status']);
        });

        Schema::table('auction_bids', function (Blueprint $table) {
            $table->index(['status', 'paid_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('paid_at');
            $table->index(['status', 'paid_at']);
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->index(['status', 'ends_at', 'settled_at']);
        });

        Schema::table('customer_controls', function (Blueprint $table) {
            $table->index('verification_status');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->index(['status', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'read_at']);
        });

        Schema::table('customer_controls', function (Blueprint $table) {
            $table->dropIndex(['verification_status']);
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->dropIndex(['status', 'ends_at', 'settled_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'paid_at']);
            $table->dropIndex(['paid_at']);
        });

        Schema::table('auction_bids', function (Blueprint $table) {
            $table->dropIndex(['status', 'paid_at']);
        });

        Schema::table('auction_results', function (Blueprint $table) {
            $table->dropIndex(['sale_payment_status', 'fulfilment_status']);
            $table->dropIndex(['fulfilment_due_at']);
            $table->dropIndex(['fulfilment_status']);
            $table->dropIndex(['sale_payment_status']);
        });
    }
};
