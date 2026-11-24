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
            $table->dropForeign(['auction_bid_id']);
        });

        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('auction_bid_id')->nullable()->change();
            $table->foreign('auction_bid_id')->references('id')->on('auction_bids')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->after('auction_bid_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropForeign(['auction_bid_id']);
        });

        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('auction_bid_id')->nullable(false)->change();
            $table->foreign('auction_bid_id')->references('id')->on('auction_bids')->cascadeOnDelete();
        });
    }
};
