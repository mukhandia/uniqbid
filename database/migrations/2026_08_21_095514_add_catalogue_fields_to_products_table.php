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
        Schema::table('products', function (Blueprint $table) {
            $table->string('subcategory')->nullable()->after('category')->index();
            $table->string('gender_age')->nullable()->after('subcategory');
            $table->string('price_tier')->nullable()->after('gender_age')->index();
            $table->boolean('is_returnable')->default(true)->after('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['subcategory', 'gender_age', 'price_tier', 'is_returnable']);
        });
    }
};
