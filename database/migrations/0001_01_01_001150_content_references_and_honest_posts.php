<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Onhost\Domain\Content\StarterPosts;

/**
 * I-R17 / I-R18 (owner, 2026-10-08, TASK-0149): customer references live in the database and are edited in the administration;
 * a post counts its writes (`revision`, the idempotency key of an edit); the prototype's posts with invented people and figures are
 * archived (not deleted) and three honest starter posts are published. Deploy runs migrations, not seeders, so the public blog of an
 * existing installation changes here. Re-running is harmless: published prototype posts only, missing starter slugs only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_references')) {
            Schema::create('content_references', function (Blueprint $table): void {
                $table->string('id', 40)->primary();
                $table->json('quote');                               // {cs, en}
                $table->string('who', 120);                          // the person quoted
                $table->json('role')->nullable();                    // {cs, en}: role and company
                $table->unsignedInteger('sort')->default(0)->index();
                $table->boolean('published')->default(false)->index();
                $table->boolean('demo')->default(false);             // a sample row seeded outside production
                $table->string('published_by', 40)->nullable();      // the member of staff who published it (a demo row shows in production only then)
                $table->timestamp('published_at')->nullable();
                $table->unsignedInteger('revision')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('posts', 'revision')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->unsignedInteger('revision')->default(0);
            });
        }

        StarterPosts::retirePrototypePosts();
        StarterPosts::publishMissing();
    }

    public function down(): void
    {
        Schema::dropIfExists('content_references');
        if (Schema::hasColumn('posts', 'revision')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->dropColumn('revision');
            });
        }
    }
};
