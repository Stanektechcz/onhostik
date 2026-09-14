<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Product resources behind a service (data plane objects), backups and restores. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_machines', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->unique();
            $table->unsignedInteger('vmid')->nullable();
            $table->string('node', 80)->nullable();
            $table->unsignedSmallInteger('cores')->default(1);
            $table->string('cpu_class', 16)->default('shared'); // shared | performance | dedicated
            $table->unsignedInteger('memory_mb')->default(1024);
            $table->unsignedInteger('disk_gb')->default(20);
            $table->string('image', 60)->nullable();
            $table->string('hostname', 253)->nullable();
            $table->string('ipv4_address_id', 40)->nullable();
            $table->string('ipv6_address_id', 40)->nullable();
            $table->json('ssh_keys')->nullable();
            $table->json('cloud_init')->nullable();
            $table->json('firewall')->nullable();
            $table->boolean('agent')->default(true);
            $table->string('state', 24)->default('unknown');
            $table->json('last_status')->nullable();
            $table->timestamps();
        });

        Schema::create('vm_snapshots', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('virtual_machine_id', 40)->index();
            $table->string('name', 80);
            $table->string('description', 250)->nullable();
            $table->string('created_by', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('websites', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('domain', 253);
            $table->json('aliases')->nullable();
            $table->string('executor', 16); // ispconfig | aapanel
            $table->string('php_version', 8)->default('8.3');
            $table->string('docroot', 190)->nullable();
            $table->string('ssl_state', 16)->default('none'); // none | pending | active | failed
            $table->boolean('https_forced')->default(true);
            $table->unsignedInteger('remote_client_id')->nullable();
            $table->unsignedInteger('remote_site_id')->nullable();
            $table->string('remote_node', 80)->nullable();
            $table->string('system_user', 64)->nullable();
            $table->string('state', 24)->default('pending');
            $table->json('quota')->nullable();
            $table->timestamps();
        });

        Schema::create('website_databases', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('website_id', 40)->index();
            $table->string('name', 64);
            $table->string('username', 64);
            $table->string('host', 120)->default('localhost');
            $table->string('secret_ref', 200)->nullable();
            $table->unsignedInteger('remote_id')->nullable();
            $table->timestamps();
        });

        Schema::create('game_servers', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->unique();
            $table->string('egg_key', 60);
            $table->unsignedInteger('nest_id')->nullable();
            $table->unsignedInteger('egg_id')->nullable();
            $table->unsignedInteger('ptero_id')->nullable();
            $table->string('ptero_uuid', 40)->nullable();
            $table->string('ptero_identifier', 16)->nullable();
            $table->unsignedInteger('ptero_user_id')->nullable();
            $table->unsignedInteger('ptero_node_id')->nullable();
            $table->json('allocation')->nullable();
            $table->unsignedInteger('memory_mb')->default(2048);
            $table->unsignedSmallInteger('cpu_pct')->default(100);
            $table->unsignedInteger('disk_mb')->default(10240);
            $table->json('startup')->nullable();
            $table->string('state', 24)->default('pending');
            $table->json('last_status')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_domains', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('domain', 253);
            $table->unsignedInteger('remote_client_id')->nullable();
            $table->unsignedInteger('remote_id')->nullable();
            $table->string('remote_node', 80)->nullable();
            $table->string('dkim_selector', 32)->nullable();
            $table->text('dkim_public')->nullable();
            $table->boolean('sending_enabled')->default(true);
            $table->string('state', 24)->default('pending');
            $table->timestamps();
            $table->unique(['service_id', 'domain']);
        });

        Schema::create('mailboxes', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('mail_domain_id', 40)->index();
            $table->string('address', 253)->unique();
            $table->string('name', 120)->nullable();
            $table->unsignedInteger('quota_mb')->default(2048);
            $table->unsignedInteger('remote_id')->nullable();
            $table->string('state', 24)->default('pending');
            $table->timestamps();
        });

        Schema::create('mail_aliases', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('mail_domain_id', 40)->index();
            $table->string('source', 253);
            $table->string('destination', 253);
            $table->unsignedInteger('remote_id')->nullable();
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->unique();
            $table->string('name', 80);
            $table->string('git_repo', 250)->nullable();
            $table->string('git_branch', 120)->default('main');
            $table->string('git_provider', 16)->nullable(); // github | gitlab | gitea
            $table->string('runtime', 32)->default('node-22');
            $table->string('build_command', 250)->nullable();
            $table->string('start_command', 250)->nullable();
            $table->unsignedSmallInteger('port')->default(3000);
            $table->string('healthcheck_path', 120)->default('/');
            $table->json('env')->nullable();          // non-secret variables
            $table->json('secret_refs')->nullable();  // names -> secret refs, never values
            $table->string('namespace', 63)->nullable();
            $table->json('domains')->nullable();
            $table->unsignedSmallInteger('replicas')->default(1);
            $table->string('current_deployment_id', 40)->nullable();
            $table->string('state', 24)->default('pending');
            $table->timestamps();
        });

        Schema::create('builds', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('application_id', 40)->index();
            $table->string('commit_sha', 64)->nullable();
            $table->string('state', 16)->default('queued'); // queued | running | succeeded | failed | cancelled
            $table->string('image_digest', 120)->nullable();
            $table->string('image_ref', 250)->nullable();
            $table->string('sbom_path', 250)->nullable();
            $table->json('scan_result')->nullable();
            $table->string('log_path', 250)->nullable();
            $table->string('triggered_by', 40)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deployments', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('application_id', 40)->index();
            $table->string('build_id', 40)->nullable();
            $table->string('state', 16)->default('pending'); // pending | rolling | healthy | failed | rolled_back
            $table->string('strategy', 16)->default('rolling');
            $table->string('image_digest', 120)->nullable();
            $table->string('rollback_of', 40)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('database_instances', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->unique();
            $table->string('engine', 16);   // postgresql | mariadb | redis
            $table->string('version', 16);
            $table->string('virtual_machine_id', 40)->nullable();
            $table->string('host', 253)->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('admin_secret_ref', 200)->nullable();
            $table->boolean('pitr')->default(true);
            $table->boolean('external_access')->default(false);
            $table->json('allowlist')->nullable();
            $table->string('state', 24)->default('pending');
            $table->timestamps();
        });

        Schema::create('database_users', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('database_instance_id', 40)->index();
            $table->string('username', 64);
            $table->json('privileges')->nullable();
            $table->string('secret_ref', 200)->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_endpoints', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->unique();
            $table->string('model', 120);
            $table->string('gateway_key_ref', 200)->nullable();
            $table->unsignedInteger('rate_limit_rpm')->default(600);
            $table->json('allowed_models')->nullable();
            $table->string('state', 24)->default('pending');
            $table->timestamps();
        });

        Schema::create('gpu_allocations', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('node_id', 40)->index();
            $table->unsignedTinyInteger('gpu_index');
            $table->string('mode', 16)->default('passthrough'); // passthrough | shared
            $table->string('state', 16)->default('allocated');
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->string('provider_instance_id', 40)->nullable();
            $table->string('kind', 16); // vm | site | game | db | mail | zone
            $table->string('remote_id', 190)->nullable();
            $table->string('remote_datastore', 80)->nullable();
            $table->string('remote_namespace', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verify_status', 16)->nullable(); // ok | failed | pending
            $table->boolean('protected')->default(false);
            $table->boolean('offsite')->default(false);
            $table->timestamp('immutable_until')->nullable();
            $table->timestamp('retention_until')->nullable()->index();
            $table->string('state', 16)->default('running'); // running | completed | failed | expired
            $table->string('operation_id', 40)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('restore_jobs', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('backup_id', 40)->index();
            $table->string('service_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->string('target', 16)->default('in_place'); // in_place | new_instance | test
            $table->string('state', 16)->default('requested');
            $table->string('operation_id', 40)->nullable();
            $table->string('requested_by', 40)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_policies', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->nullable()->index();
            $table->string('product_key', 60)->nullable()->index();
            $table->json('schedule');   // {"daily":"02:30"} or cron
            $table->json('retention');  // {"daily":7,"weekly":4,"monthly":6}
            $table->boolean('offsite')->default(true);
            $table->json('restore_test')->nullable(); // {"cadence":"monthly"}
            $table->string('state', 12)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['backup_policies', 'restore_jobs', 'backups', 'gpu_allocations', 'ai_endpoints', 'database_users', 'database_instances', 'deployments', 'builds', 'applications', 'mail_aliases', 'mailboxes', 'mail_domains', 'website_databases', 'websites', 'game_servers', 'vm_snapshots', 'virtual_machines'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
