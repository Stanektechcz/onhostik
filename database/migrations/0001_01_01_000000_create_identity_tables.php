<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identity plane (blueprint §61.2): users, external identities (OIDC/Keycloak),
 * MFA methods, step-up grants, trusted devices, sessions, personal access tokens
 * (Sanctum, string principal ids), service accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('email', 190)->unique();
            $table->string('name', 190);
            $table->string('password')->nullable();
            $table->string('locale', 5)->default('cs');
            $table->string('timezone', 64)->default('Europe/Prague');
            $table->boolean('is_staff')->default(false)->index();
            $table->string('state', 24)->default('active')->index(); // invited | active | suspended | deleted
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 64)->nullable();
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->text('totp_secret')->nullable();          // encrypted
            $table->timestamp('totp_confirmed_at')->nullable();
            $table->text('recovery_codes')->nullable();       // encrypted json of hashed codes
            $table->timestamp('mfa_required_from')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email', 190)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('email_verification_tokens', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('user_id', 40)->index();
            $table->string('token_hash', 64)->unique();
            $table->string('purpose', 32)->default('verify'); // verify | set_password | invite
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('user_id', 40)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('identities', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('user_id', 40)->index();
            $table->string('provider', 40);            // keycloak | github | gitlab
            $table->string('subject', 190);            // OIDC sub
            $table->string('issuer', 250)->nullable();
            $table->string('email', 190)->nullable();
            $table->json('claims')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'subject']);
        });

        Schema::create('webauthn_credentials', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('user_id', 40)->index();
            $table->string('name', 120);
            $table->text('credential_id');
            $table->string('credential_id_hash', 64)->unique();
            $table->text('public_key');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->string('aaguid', 64)->nullable();
            $table->json('transports')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mfa_challenges', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('user_id', 40)->index();
            $table->string('purpose', 32); // login | step_up | webauthn_register
            $table->text('payload')->nullable();  // encrypted challenge data
            $table->string('ip', 64)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('step_up_grants', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('user_id', 40)->index();
            $table->string('method', 24);  // totp | webauthn | password | recovery
            $table->string('session_id', 100)->nullable()->index();
            $table->string('ip', 64)->nullable();
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trusted_devices', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('user_id', 40)->index();
            $table->string('token_hash', 64)->unique();
            $table->string('label', 120)->nullable();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_accounts', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->nullable()->index(); // null = staff/system account
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('state', 24)->default('active');
            $table->string('created_by', 40)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('tokenable_type', 120);
            $table->string('tokenable_id', 40);
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('organization_id', 40)->nullable()->index();
            $table->unsignedInteger('rate_limit_per_minute')->default(120);
            $table->string('last_used_ip', 64)->nullable();
            $table->string('created_by', 40)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    public function down(): void
    {
        foreach (['personal_access_tokens', 'service_accounts', 'trusted_devices', 'step_up_grants', 'mfa_challenges', 'webauthn_credentials', 'identities', 'sessions', 'email_verification_tokens', 'password_reset_tokens', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
