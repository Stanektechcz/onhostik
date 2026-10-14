<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-0142: a consent document version can be prepared as a DRAFT (owner decision I-R4/4A: the 2026-09 texts stay in force
 * until an attorney confirms the new ones). A draft is never current, never offered at checkout and never served; the owner
 * publishes it with `php artisan onhost:legal:publish <version> --apply` (Onhost\Domain\Orders\LegalDocuments). Every row that
 * exists today is a published version, hence the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_documents', function (Blueprint $table): void {
            $table->string('state', 16)->default('active');
            $table->timestamp('published_at')->nullable();
            $table->string('published_by', 160)->nullable(); // who confirmed the text (the attorney's sign-off as the owner recorded it)
        });
    }

    public function down(): void
    {
        Schema::table('consent_documents', function (Blueprint $table): void {
            $table->dropColumn(['state', 'published_at', 'published_by']);
        });
    }
};
