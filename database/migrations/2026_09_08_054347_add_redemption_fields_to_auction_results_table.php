<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_results', function (Blueprint $table) {
            $table->string('outcome_choice')->nullable()->after('fulfilment_status');
            $table->unsignedInteger('redemption_amount')->nullable()->after('outcome_choice');
            $table->string('redemption_payout_status')->nullable()->after('redemption_amount');
            $table->timestamp('redeemed_at')->nullable()->after('redemption_payout_status');
        });
    }

    public function down(): void
    {
        Schema::table('auction_results', function (Blueprint $table) {
            $table->dropColumn([
                'outcome_choice',
                'redemption_amount',
                'redemption_payout_status',
                'redeemed_at',
            ]);
        });
    }
};
