<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demonstrable consent (audit H123).
 *
 * GDPR art. 7(1) requires the controller to be able to DEMONSTRATE that
 * consent was given. A validated checkbox proves nothing after the fact —
 * you need who consented, to what, to which VERSION of the document, and
 * when. Terms change; a consent to the 2024 wording is not consent to the
 * 2026 wording.
 *
 * Append-only by intent: records are never updated, a withdrawal is a new
 * row with granted = false.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consent_records')) {
            return;
        }

        Schema::create('consent_records', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable()->index();

            // terms | privacy | marketing | cookies
            $table->string('type', 32)->index();
            // Version of the document consented to, e.g. "2026-01".
            $table->string('document_version', 32);
            $table->boolean('granted')->default(true);

            // Evidence of the act itself.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            // Where it happened: checkout, registration, cookie banner…
            $table->string('source', 64)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['type', 'document_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
