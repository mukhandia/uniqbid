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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 12)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->boolean('marketing_consent')->default(false)->after('password');
            $table->timestamp('marketing_consented_at')->nullable()->after('marketing_consent');
            $table->boolean('sms_notifications')->default(true)->after('marketing_consented_at');
            $table->boolean('whatsapp_notifications')->default(false)->after('sms_notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn([
                'phone',
                'phone_verified_at',
                'marketing_consent',
                'marketing_consented_at',
                'sms_notifications',
                'whatsapp_notifications',
            ]);
        });
    }
};
