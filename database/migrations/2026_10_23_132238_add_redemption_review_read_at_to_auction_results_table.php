<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_results', function (Blueprint $table) {
            $table->timestamp('redemption_review_read_at')->nullable()->after('redemption_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('auction_results', function (Blueprint $table) {
            $table->dropColumn('redemption_review_read_at');
        });
    }
};
