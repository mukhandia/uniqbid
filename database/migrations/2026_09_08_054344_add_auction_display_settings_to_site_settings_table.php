<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('auction_display_mode')->default('auto')->after('map_url');
            $table->unsignedSmallInteger('auction_shuffle_interval_seconds')->default(45)->after('auction_display_mode');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['auction_display_mode', 'auction_shuffle_interval_seconds']);
        });
    }
};
