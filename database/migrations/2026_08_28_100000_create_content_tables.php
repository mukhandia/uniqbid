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
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location');
            $table->string('role');
            $table->string('item');
            $table->text('quote');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category')->default('general');
            $table->string('question_en');
            $table->text('answer_en');
            $table->string('question_sw')->nullable();
            $table->text('answer_sw')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
            $table->index('category');
        });

        Schema::create('legal_settings', function (Blueprint $table) {
            $table->id();
            $table->string('version')->default('2.1');
            $table->string('effective_date')->default('1 July 2026');
            $table->timestamps();
        });

        Schema::create('legal_sections', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('legal_clauses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_section_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['legal_section_id', 'sort_order']);
        });

        Schema::create('how_it_works_settings', function (Blueprint $table) {
            $table->id();
            $table->string('page_title_en');
            $table->string('page_title_sw');
            $table->text('intro_en');
            $table->text('intro_sw');
            $table->string('example_heading_en');
            $table->string('example_heading_sw');
            $table->string('example_intro_en');
            $table->string('example_intro_sw');
            $table->text('example_note_en');
            $table->text('example_note_sw');
            $table->string('what_you_pay_title_en');
            $table->string('what_you_pay_title_sw');
            $table->text('what_you_pay_body_en');
            $table->text('what_you_pay_body_sw');
            $table->string('transparency_title_en');
            $table->string('transparency_title_sw');
            $table->text('transparency_body_en');
            $table->text('transparency_body_sw');
            $table->timestamps();
        });

        Schema::create('how_it_works_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('step_number');
            $table->string('title_en');
            $table->string('title_sw');
            $table->text('description_en');
            $table->text('description_sw');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        Schema::create('how_it_works_example_rows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('amount');
            $table->boolean('is_unique')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('how_it_works_example_rows');
        Schema::dropIfExists('how_it_works_steps');
        Schema::dropIfExists('how_it_works_settings');
        Schema::dropIfExists('legal_clauses');
        Schema::dropIfExists('legal_sections');
        Schema::dropIfExists('legal_settings');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('testimonials');
    }
};
