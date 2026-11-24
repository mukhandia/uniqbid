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
        Schema::create('auction_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->unsignedInteger('amount');
            $table->string('status')->default('pending_payment');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['auction_id', 'status', 'amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auction_bids');
    }
};
