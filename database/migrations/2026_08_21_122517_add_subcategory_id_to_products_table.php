<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('subcategory_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unsignedInteger('reserved_stock')->default(0)->after('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subcategory_id');
            $table->dropColumn('reserved_stock');
        });
    }
};
