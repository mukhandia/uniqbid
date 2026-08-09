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
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category')->index();
            $table->text('description');
            $table->unsignedInteger('bid_fee');
            $table->unsignedInteger('min_bid')->default(1);
            $table->unsignedInteger('max_bid');
            $table->unsignedInteger('bid_increment')->default(1);
            $table->unsignedInteger('retail_value');
            $table->unsignedInteger('bids_count')->default(0);
            $table->timestamp('ends_at')->index();
            $table->string('status')->default('live')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->json('similar_sales')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
