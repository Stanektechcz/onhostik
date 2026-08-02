<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable canned chat answers.
 *
 * The built-in knowledge base lives in code (versioned, reviewable); this table
 * lets support staff add and tune answers without a deploy. Rows are merged into
 * the same matcher, with a priority so a curated answer can outrank the built-in
 * one for the same phrasing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_answers')) {
            return;
        }

        Schema::create('chat_answers', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 32)->index();   // matches AiChatbotService::CATEGORIES keys
            $table->string('question');                // canonical phrasing, shown in predictions
            $table->text('keywords');                  // comma-separated match terms
            $table->text('answer');
            $table->json('links')->nullable();         // [{label,url}]
            $table->json('follow_ups')->nullable();    // [{label,message}]
            $table->unsignedSmallInteger('priority')->default(0); // higher wins ties
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('hits')->default(0); // how often it matched
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_answers');
    }
};
