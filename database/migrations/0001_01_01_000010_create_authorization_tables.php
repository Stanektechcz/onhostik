<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capability-based RBAC/ABAC (blueprint §61.3–§61.6). Roles are sets of
 * permissions; bindings attach a role to a principal at a scope. High-risk
 * permissions require step-up, critical ones a second approver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_definitions', function (Blueprint $table): void {
            $table->string('key', 120)->primary();
            $table->string('description', 250);
            $table->string('risk', 12)->default('normal'); // normal | high | critical
            $table->boolean('step_up')->default(false);
            $table->boolean('four_eyes')->default(false);
            $table->string('audience', 12)->default('customer'); // customer | staff | both
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('scope_type', 20)->default('organization'); // global | organization | project
            $table->boolean('is_staff')->default(false);
            $table->boolean('assignable')->default(true);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->string('role_key', 80);
            $table->string('permission_key', 120);
            $table->primary(['role_key', 'permission_key']);
        });

        Schema::create('policy_bindings', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('principal_type', 24);   // user | service_account
            $table->string('principal_id', 40);
            $table->string('role_key', 80);
            $table->string('scope_type', 20);       // global | organization | project | resource
            $table->string('scope_id', 40)->nullable();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('granted_by', 40)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['principal_type', 'principal_id']);
            $table->unique(['principal_type', 'principal_id', 'role_key', 'scope_type', 'scope_id'], 'policy_bindings_unique');
        });

        Schema::create('jit_elevations', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('user_id', 40)->index();
            $table->string('role_key', 80);
            $table->string('scope_type', 20)->default('global');
            $table->string('scope_id', 40)->nullable();
            $table->text('reason');
            $table->string('ticket_ref', 80)->nullable();
            $table->unsignedInteger('ttl_minutes')->default(60);
            $table->string('state', 20)->default('requested'); // requested | approved | rejected | expired | revoked
            $table->string('approver_id', 40)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('approvals', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('action', 120);            // command name, e.g. billing.refund
            $table->string('subject_type', 80)->nullable();
            $table->string('subject_id', 40)->nullable();
            $table->string('organization_id', 40)->nullable()->index();
            $table->json('payload')->nullable();      // redacted description of what will happen
            $table->string('payload_hash', 64);
            $table->string('requested_by', 40)->index();
            $table->text('reason')->nullable();
            $table->string('state', 20)->default('pending'); // pending | approved | rejected | consumed | expired
            $table->string('decided_by', 40)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('access_reviews', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('period', 20);  // 2026-Q3
            $table->string('reviewer_id', 40)->nullable();
            $table->string('state', 20)->default('open'); // open | completed
            $table->json('findings')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['access_reviews', 'approvals', 'jit_elevations', 'policy_bindings', 'role_permissions', 'roles', 'permission_definitions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
