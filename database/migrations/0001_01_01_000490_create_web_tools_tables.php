<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web hosting tools on top of the panel executors: git deploy sources and deployments, staging links, uptime
 * monitoring, platform-issued certificates (wildcard via DNS-01), CDN zones and site imports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploy_sources', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->unique();
            $table->string('organization_id', 40)->index();
            $table->string('provider', 20)->default('github'); // github | gitlab | generic
            $table->string('repository', 250); // owner/name or clone URL
            $table->string('branch', 120)->default('main');
            $table->string('clone_url', 500);
            $table->string('public_key', 1000)->nullable(); // deploy key the customer adds to the repository
            $table->string('webhook_secret_hash', 120)->nullable();
            $table->string('build_command', 500)->nullable();
            $table->string('deploy_path', 200)->nullable(); // sub-folder of the repository served as web root
            $table->json('env')->nullable(); // build environment (non-secret)
            $table->json('hooks')->nullable(); // post-deploy commands
            $table->boolean('auto_deploy')->default(true);
            $table->unsignedSmallInteger('keep_releases')->default(5);
            $table->string('last_deployment_id', 40)->nullable();
            $table->timestamp('last_deployed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('git_deployments', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('deploy_source_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->string('triggered_by', 20); // manual | webhook | rollback
            $table->string('ref', 250)->nullable(); // branch or tag requested
            $table->string('sha', 64)->nullable();
            $table->string('message', 250)->nullable();
            $table->string('author', 120)->nullable();
            $table->string('release', 80)->nullable(); // release folder name
            $table->string('state', 16)->default('queued'); // queued | running | succeeded | failed | rolled_back
            $table->string('operation_id', 40)->nullable();
            $table->text('log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
        Schema::create('staging_links', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->unique(); // production
            $table->string('staging_service_id', 40)->unique();
            $table->string('organization_id', 40)->index();
            $table->string('staging_domain', 253);
            $table->string('state', 16)->default('creating'); // creating | ready | syncing | pushing | deleting | failed
            $table->json('databases')->nullable(); // production remote_id => staging remote_id
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_pushed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
        Schema::create('uptime_monitors', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->string('url', 500);
            $table->unsignedSmallInteger('interval_seconds')->default(300);
            $table->unsignedSmallInteger('expected_status')->default(200);
            $table->string('keyword', 120)->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(10);
            $table->boolean('enabled')->default(true);
            $table->boolean('notify')->default(true);
            $table->string('state', 16)->default('pending'); // pending | up | down | paused
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable()->index();
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->unsignedInteger('last_ms')->nullable();
            $table->string('last_error', 250)->nullable();
            $table->timestamps();
        });
        Schema::create('uptime_samples', function (Blueprint $table): void {
            $table->id();
            $table->string('monitor_id', 40);
            $table->timestamp('checked_at');
            $table->boolean('ok');
            $table->unsignedSmallInteger('status')->nullable();
            $table->unsignedInteger('ms')->nullable();
            $table->string('error', 250)->nullable();
            $table->index(['monitor_id', 'checked_at']);
        });
        Schema::create('uptime_incidents', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('monitor_id', 40)->index();
            $table->string('service_id', 40)->index();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('cause', 250)->nullable();
            $table->boolean('notified')->default(false);
            $table->timestamps();
        });
        Schema::create('managed_certificates', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->json('domains');
            $table->boolean('wildcard')->default(false);
            $table->string('issuer', 40)->default('letsencrypt');
            $table->string('state', 16)->default('pending'); // pending | issued | failed | revoked
            $table->string('account_url', 500)->nullable();
            $table->string('order_url', 500)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('renew_after')->nullable()->index();
            $table->string('last_error', 500)->nullable();
            $table->string('secret_ref', 190)->nullable(); // private key + chain in the secret store
            $table->timestamps();
        });
        Schema::create('cdn_zones', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->string('provider', 20)->default('cloudflare');
            $table->string('domain', 253);
            $table->string('zone_id', 80)->nullable();
            $table->json('nameservers')->nullable();
            $table->string('state', 16)->default('pending'); // pending | active | disabled | failed
            $table->json('settings')->nullable(); // ssl mode, http3, always_https, security_level, cache level
            $table->json('proxied_records')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('site_imports', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->string('kind', 20); // cpanel | plesk | url | upload
            $table->string('source', 500)->nullable(); // URL or stored file path
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('state', 16)->default('queued'); // queued | running | succeeded | failed
            $table->string('operation_id', 40)->nullable();
            $table->json('stats')->nullable(); // files, databases, mailboxes imported
            $table->text('log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['site_imports', 'cdn_zones', 'managed_certificates', 'uptime_incidents', 'uptime_samples', 'uptime_monitors', 'staging_links', 'git_deployments', 'deploy_sources'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
