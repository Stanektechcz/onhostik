<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E2 (vlna 2, owner decision E2): "tell me when {product} is available" — double opt-in. One row per (e-mail, product);
 * the confirm token and the address the request came from are kept only as hashes. The consent version names the exact text
 * the person agreed to. Unconfirmed rows go after 30 days, confirmed ones 12 months after the confirmation
 * (onhost:waitlist:prune). The purpose is that one notice only — no marketing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_signups', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('email', 190);
            $table->string('product', 64);
            $table->string('locale', 5)->default('cs');
            $table->string('state', 12)->default('pending'); // pending | confirmed
            $table->string('consent_version', 20);
            $table->char('token_hash', 64)->nullable()->unique();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('token_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['email', 'product']);
            $table->index(['state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_signups');
    }
};
