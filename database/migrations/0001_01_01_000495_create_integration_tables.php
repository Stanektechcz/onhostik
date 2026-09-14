<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer integrations that drive services from outside the panel: Discord (linked accounts for slash commands and
 * confirmation buttons) and action hooks (signed URLs that run one predefined service action, usable from Discord
 * bots, CI or any automation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_links', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('user_id', 40)->index();
            $table->string('discord_user_id', 40)->nullable()->index();
            $table->string('discord_username', 120)->nullable();
            $table->string('discord_guild_id', 40)->nullable();
            $table->string('code', 16)->nullable()->index();      // link code typed into /onhost link
            $table->timestamp('code_expires_at')->nullable();
            $table->string('state', 12)->default('pending');     // pending | linked | revoked
            $table->string('locale', 5)->default('cs');
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('commands')->default(0);
            $table->timestamps();
        });

        Schema::create('action_hooks', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('service_id', 40)->index();
            $table->string('created_by', 40)->nullable();
            $table->string('name', 80);
            $table->string('action', 40);                        // one service action (backup, deploy.run, power …)
            $table->json('params');
            $table->string('token_hash', 64)->unique();          // sha256 of the URL token shown once
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_operation_id', 40)->nullable();
            $table->string('last_result', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_hooks');
        Schema::dropIfExists('discord_links');
    }
};
