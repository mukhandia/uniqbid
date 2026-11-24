<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('channel')->default('none')->index()->after('is_returnable');
            $table->unsignedInteger('auction_sort_order')->default(0)->index()->after('channel');
        });

        // Existing catalogue items stay in the shop until an admin moves them.
        DB::table('products')->update(['channel' => 'shop']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['channel', 'auction_sort_order']);
        });
    }
};
