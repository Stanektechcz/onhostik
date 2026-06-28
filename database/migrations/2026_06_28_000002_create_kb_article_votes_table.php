<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_article_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_helpful');
            $table->timestamps();

            $table->unique(['kb_article_id', 'user_id']);
            $table->index(['kb_article_id', 'is_helpful']);
        });

        Schema::create('kb_article_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name', 100)->nullable();
            $table->text('content');
            $table->boolean('is_visible')->default(false);
            $table->timestamps();

            $table->index('kb_article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_reviews');
        Schema::dropIfExists('kb_article_votes');
    }
};
