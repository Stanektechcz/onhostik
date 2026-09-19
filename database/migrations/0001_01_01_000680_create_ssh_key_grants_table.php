<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a public key on a shell account belongs to (Brain card H185). The panels keep the key as text on the account;
 * this table keeps what they do not: the fingerprint, the person, who installed it and whether a revocation has
 * reached the panel yet. Only the fingerprint is stored — the key itself stays at the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssh_key_grants', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('service_id', 40)->index();
            $table->string('target_remote_id', 64);              // the shell account at the panel
            $table->string('target_label', 120)->nullable();     // its user name, for people
            $table->string('owner_user_id', 40)->nullable()->index(); // whose key it is; null = nobody we know (staff, automation, an outside person)
            $table->string('installed_by_type', 24)->default('system');
            $table->string('installed_by_id', 40)->nullable();
            $table->string('key_type', 40);
            $table->string('fingerprint', 80)->index();          // SHA256:… as `ssh-keygen -lf` prints it
            $table->string('comment', 190)->nullable();
            $table->string('state', 16)->default('active')->index(); // active | revoking | revoked | replaced
            $table->string('revoke_reason', 190)->nullable();
            $table->string('revoke_operation_id', 40)->nullable()->index();
            $table->unsignedSmallInteger('revoke_attempts')->default(0);
            $table->string('last_error', 300)->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('revoke_requested_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['service_id', 'target_remote_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_key_grants');
    }
};
