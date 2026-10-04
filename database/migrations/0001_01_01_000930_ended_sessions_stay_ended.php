<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * TASK-0067 (PR #53 review, MEDIUM): when a person's web sessions or consoles were ended (SessionKill — an MFA reset, an owner taken
 * out by a recovery, a removal), the mark lived only in the cache. A Redis restart, an eviction under memory pressure or a
 * `cache:clear` on deploy revived every session and console it had ended. The mark is now a row here (SessionEnds); the cache keeps
 * a copy as the fast path of the per-request check.
 *
 * One row per person, kind (`web`, `console`) and scope (`*` everywhere, or an organization id): a later end moves `ended_at`
 * forward. A new table; no existing row is written. Rolling back drops it — the marks then fall back to nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_ends', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id', 40);
            $table->string('kind', 16);                // web | console
            $table->string('scope', 40)->default('*'); // * = everywhere, otherwise the organization the end was for
            $table->timestamp('ended_at');
            $table->timestamps();
            $table->unique(['user_id', 'kind', 'scope']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_ends');
    }
};
