<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_results', function (Blueprint $table) {
            $table->string('buyer_image')->nullable()->after('buyer_display');
        });
    }

    public function down(): void
    {
        Schema::table('auction_results', function (Blueprint $table) {
            $table->dropColumn('buyer_image');
        });
    }
};
