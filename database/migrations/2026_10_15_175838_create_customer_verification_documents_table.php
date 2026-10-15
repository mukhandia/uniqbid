<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });

        Schema::table('customer_controls', function (Blueprint $table) {
            $table->timestamp('verification_submitted_at')->nullable()->after('verification_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_controls', function (Blueprint $table) {
            $table->dropColumn('verification_submitted_at');
        });

        Schema::dropIfExists('customer_verification_documents');
    }
};
