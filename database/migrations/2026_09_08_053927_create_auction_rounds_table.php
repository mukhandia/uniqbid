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
        Schema::create('auction_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('outcome')->nullable();
            $table->foreignId('winning_bid_id')->nullable()->constrained('auction_bids')->nullOnDelete();
            $table->timestamps();

            $table->unique(['auction_id', 'round']);
            $table->index(['auction_id', 'outcome']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auction_rounds');
    }
};
