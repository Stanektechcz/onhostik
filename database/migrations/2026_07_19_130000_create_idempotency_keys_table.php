<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency-Key support for API writes (audit J136).
 *
 * Without it, a client whose connection drops mid-request cannot safely
 * retry: it has no way to know whether the order/top-up went through, so it
 * either risks a duplicate charge or risks losing the request entirely.
 *
 * The stored response is replayed for a repeated key, so a retry is a no-op
 * that returns the original outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('idempotency_keys')) {
            return;
        }

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();

            // Scoped per token: one client's key must never collide with
            // another's, and must never replay another client's response.
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->string('idempotency_key', 128);
            $table->string('method', 10);
            $table->string('path', 255);

            // Fingerprint of the request body: the same key with a DIFFERENT
            // payload is a client bug and must be rejected, not replayed.
            $table->string('request_hash', 64);

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['token_id', 'idempotency_key'], 'idem_token_key_unique');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
