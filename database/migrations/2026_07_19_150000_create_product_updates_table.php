<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app changelog — "co je nového" (audit I130).
 *
 * Distinct from `service_changelogs`, which records what happened to ONE
 * customer's service (plan changes, suspensions). This is the product release
 * feed: what the OnHost team shipped, shown to everybody.
 *
 * `changelog_seen_at` on users is what turns it from a page nobody visits into
 * a badge — it is the only way to know whether an entry is new TO YOU.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_updates')) {
            Schema::create('product_updates', function (Blueprint $table): void {
                $table->id();

                $table->string('title');
                $table->text('body');

                // 'feature' | 'improvement' | 'fix' | 'security'
                $table->string('category', 20)->default('feature');
                $table->string('version', 30)->nullable();

                $table->boolean('is_published')->default(false);
                $table->timestamp('published_at')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                // The panel list is "published, newest first" on every load.
                $table->index(['is_published', 'published_at']);
            });
        }

        if (! Schema::hasColumn('users', 'changelog_seen_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('changelog_seen_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'changelog_seen_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('changelog_seen_at');
            });
        }

        Schema::dropIfExists('product_updates');
    }
};
