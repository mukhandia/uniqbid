<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidder_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 20);
            $table->text('body');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('delivered_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['auction_id', 'bidder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bidder_messages');
    }
};
