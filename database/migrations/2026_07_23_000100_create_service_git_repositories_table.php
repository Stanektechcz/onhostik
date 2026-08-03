<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Git deployment for hosting services — connect a repository, pull it into the
 * document root and optionally redeploy on push (what aaPanel offers natively).
 *
 * The deploy key's PRIVATE half is stored encrypted (see the model cast); only
 * the public half is ever shown. The webhook secret is likewise a credential:
 * it authenticates the provider's push callback and is never rendered after
 * creation beyond a copy-once display.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_git_repositories')) {
            return;
        }

        Schema::create('service_git_repositories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('custom');   // github | gitlab | bitbucket | custom
            $table->string('repository_url', 500);
            $table->string('branch', 100)->default('main');
            $table->string('deploy_path', 500)->nullable();      // relative to the site root
            $table->boolean('auto_deploy')->default(false);
            $table->string('webhook_token', 64)->nullable()->unique();
            $table->text('deploy_key_private')->nullable();      // encrypted
            $table->text('deploy_key_public')->nullable();
            $table->text('post_deploy_commands')->nullable();    // one shell command per line
            $table->string('status', 20)->default('idle');       // idle | deploying | success | failed
            $table->string('last_commit', 60)->nullable();
            $table->text('last_output')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamps();

            $table->unique('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_git_repositories');
    }
};
