<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OAuth2 authorization-code grant (with PKCE) on top of the existing
 * oauth_applications registry. Authorization codes are single-use and
 * short-lived; refresh tokens are long-lived, hashed, and revocable. The
 * issued access token is a Sanctum personal access token, so it works with the
 * REST API and GraphQL endpoint out of the box.
 *
 * Only hashes are stored — the raw code / refresh token is shown once to the
 * client and never persisted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('oauth_authorization_codes')) {
            Schema::create('oauth_authorization_codes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('oauth_application_id')->constrained('oauth_applications')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('code_hash', 64)->unique();
                $table->string('redirect_uri', 2000);
                $table->json('scopes');
                $table->string('code_challenge')->nullable();
                $table->string('code_challenge_method', 16)->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('oauth_refresh_tokens')) {
            Schema::create('oauth_refresh_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('oauth_application_id')->constrained('oauth_applications')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->json('scopes');
                // The Sanctum access token this refresh token currently backs, so
                // rotating the refresh token can revoke the stale access token.
                $table->unsignedBigInteger('access_token_id')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_authorization_codes');
    }
};
