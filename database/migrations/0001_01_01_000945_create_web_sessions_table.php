<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * TASK-0070 (audit 2026-10, package C11): the account's "Sessions and devices" page listed only the browser that was looking at
 * it — production keeps sessions in Redis, where nothing says which sessions a person has. One row per signed-in web session
 * (WebSessions): opened at sign-in, touched while it is used, ended by a sign-out, by the person from another device, or by
 * SessionKill. WebSessionGate logs a session out on its next request once its row is ended — the row is the truth, not a cache.
 *
 * A new table; no existing row is written. Sessions opened before this release get their row on their next request. Rolling
 * back drops it — the page then shows the current browser only again, and ending one other session is no longer possible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_sessions', function (Blueprint $table): void {
            $table->string('id', 40)->primary();      // a random id of our own, never the framework's session id
            $table->string('user_id', 40);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 250)->nullable();
            $table->timestamp('signed_in_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason', 40)->nullable(); // signed_out | ended_by_user | ended_all | password_changed
            $table->timestamps();
            $table->index(['user_id', 'ended_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_sessions');
    }
};
