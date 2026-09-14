<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Audit §5u-4: one personal calendar-subscription token per staff user; only its SHA-256 is stored. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oncall_feed_tokens', function (Blueprint $table): void {
            $table->string('user_id', 40)->primary();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oncall_feed_tokens');
    }
};
