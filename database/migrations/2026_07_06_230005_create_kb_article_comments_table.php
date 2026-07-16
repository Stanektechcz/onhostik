<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kb_article_comments')) {
            Schema::create('kb_article_comments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('kb_article_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('body');
                $table->boolean('is_approved')->default(false);
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['kb_article_id', 'is_approved']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_comments');
    }
};
