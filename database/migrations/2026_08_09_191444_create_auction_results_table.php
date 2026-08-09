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
        Schema::create('auction_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category')->nullable()->index();
            $table->text('image');
            $table->unsignedInteger('sale_price');
            $table->string('buyer_display');
            $table->date('sold_on')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auction_results');
    }
};
