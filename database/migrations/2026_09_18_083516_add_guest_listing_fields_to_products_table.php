<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('seller_user_id')->nullable()->after('subcategory_id')->constrained('users')->nullOnDelete();
            $table->string('approval_status')->default('approved')->after('channel')->index();
            $table->text('rejection_reason')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_user_id');
            $table->dropColumn(['approval_status', 'rejection_reason', 'approved_at']);
        });
    }
};
