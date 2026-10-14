<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP1a (phase V, V-R1): the sending reputation the platform watches. `mail_reputation_checks` is the last answer of every
 * (blocklist, target) pair — the cache that keeps each pair to one question per interval and puts the oldest first;
 * `mail_reputation_listings` is the history: a listing opens when a list starts answering, and is cleared — never deleted —
 * when it stops. Targets are the platform's own sending addresses and its customers' mail domains; no message content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_reputation_checks', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('list_key', 40);
            $table->string('target_kind', 8); // ipv4 | ipv6 | domain
            $table->string('target', 255);
            $table->string('outcome', 10); // listed | clean | refused | failed
            $table->string('code', 15)->nullable();
            $table->timestamp('checked_at')->index();
            $table->unique(['list_key', 'target']);
        });

        Schema::create('mail_reputation_listings', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('list_key', 40);
            $table->string('zone', 120); // the public zone, never a DQS key
            $table->string('list_name', 80);
            $table->string('target_kind', 8);
            $table->string('target', 255);
            $table->string('weight', 8); // major | minor | info
            $table->string('code', 15);
            $table->string('state', 10)->default('open'); // open | cleared
            $table->string('node_id', 40)->nullable()->index();
            $table->json('service_ids')->nullable(); // services whose mail domain this is
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'weight']);
            $table->index(['list_key', 'target', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_reputation_listings');
        Schema::dropIfExists('mail_reputation_checks');
    }
};
