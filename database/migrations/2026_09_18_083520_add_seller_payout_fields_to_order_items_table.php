<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('seller_user_id')->nullable()->after('product_id')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('commission_amount')->default(0)->after('line_total');
            $table->unsignedInteger('seller_payout_amount')->default(0)->after('commission_amount');
            $table->string('seller_payout_status')->default('none')->after('seller_payout_amount')->index();
            $table->timestamp('seller_payout_paid_at')->nullable()->after('seller_payout_status');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_user_id');
            $table->dropColumn([
                'commission_amount',
                'seller_payout_amount',
                'seller_payout_status',
                'seller_payout_paid_at',
            ]);
        });
    }
};
