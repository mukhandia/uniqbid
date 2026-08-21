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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone')->index();
            $table->string('customer_email')->nullable();
            $table->string('delivery_method')->index();
            $table->text('delivery_address')->nullable();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('delivery_fee')->default(0);
            $table->unsignedInteger('total');
            $table->string('status')->default('paid')->index();
            $table->string('payment_provider')->default('mpesa_demo');
            $table->string('payment_reference')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('dispatched_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
