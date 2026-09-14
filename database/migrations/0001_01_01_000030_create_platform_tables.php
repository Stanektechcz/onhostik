<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kernel tables: idempotency, transactional outbox, hash-chained audit, provider call log, feature flags. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 220);
            $table->string('scope', 120);
            $table->string('request_hash', 64)->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('result')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->unique(['key', 'scope']);
        });

        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('aggregate_type', 80);
            $table->string('aggregate_id', 80);
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('name', 120)->index();
            $table->json('payload')->nullable();
            $table->string('correlation_id', 128)->nullable();
            $table->timestamp('available_at')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('actor_type', 24);
            $table->string('actor_id', 40)->nullable()->index();
            $table->string('on_behalf_of', 40)->nullable();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('project_id', 40)->nullable();
            $table->string('resource_type', 80)->nullable();
            $table->string('resource_id', 80)->nullable()->index();
            $table->string('permission', 120)->nullable();
            $table->string('action', 120)->index();
            $table->string('result', 24);
            $table->string('request_id', 40)->nullable();
            $table->string('correlation_id', 128)->nullable()->index();
            $table->string('before_hash', 64)->nullable();
            $table->string('after_hash', 64)->nullable();
            $table->json('detail')->nullable();
            $table->text('reason')->nullable();
            $table->string('ticket_ref', 80)->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 250)->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('step_up_method', 24)->nullable();
            $table->json('approval_ids')->nullable();
            $table->string('prev_hash', 64);
            $table->string('hash', 64)->unique();
            $table->timestamp('created_at')->index();
        });

        Schema::create('security_events', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('kind', 80)->index();     // login_failed | mfa_failed | token_created | anomaly | provider_auth_failed
            $table->string('severity', 12)->default('info');
            $table->string('user_id', 40)->nullable()->index();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('ip', 64)->nullable();
            $table->json('detail')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_by', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('provider_calls', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('provider', 40)->index();
            $table->string('instance_key', 80)->index();
            $table->string('action', 120);
            $table->string('method', 10);
            $table->string('path', 500);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('body_code', 60)->nullable();
            $table->boolean('ok')->index();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('operation_id', 40)->nullable()->index();
            $table->string('correlation_id', 128)->nullable();
            $table->string('actor', 120)->nullable();
            $table->longText('request')->nullable();
            $table->longText('response')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->string('description', 250)->nullable();
            $table->boolean('enabled')->default(false);
            $table->json('organizations')->nullable(); // allow-list of organization ids for beta products
            $table->json('rules')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['feature_flags', 'provider_calls', 'security_events', 'audit_events', 'outbox_messages', 'idempotency_keys'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
