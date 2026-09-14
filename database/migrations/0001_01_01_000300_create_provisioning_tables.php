<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control/resource plane (blueprint §5, §60.3): provider capability registry,
 * regions/nodes/capacity, IPAM, canonical services, provider bindings, idempotent
 * operations with attempts, drift cases and integration health.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table): void {
            $table->string('code', 16)->primary(); // cz1, cz2
            $table->string('name', 80);
            $table->string('country', 2)->default('CZ');
            $table->string('datacenter', 120)->nullable();
            $table->string('state', 16)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_instances', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('key', 80)->unique();          // proxmox-cz1, ispconfig-shared01, wedos-wapi
            $table->string('provider', 24)->index();       // proxmox | pbs | ispconfig | aapanel | pterodactyl | powerdns | wedos | kubernetes
            $table->string('name', 120);
            $table->string('region_code', 16)->nullable()->index();
            $table->string('base_url', 250)->nullable();
            $table->string('secret_ref', 200);              // bao://… or env://…
            $table->string('state', 16)->default('active'); // active | maintenance | disabled
            $table->json('capabilities')->nullable();
            $table->string('vendor_version', 40)->nullable();
            $table->string('adapter_version', 20)->nullable();
            $table->json('options')->nullable();            // template vmid, storage, tls ca, egress ip, default nest/egg …
            $table->json('quotas')->nullable();
            $table->json('rate_limits')->nullable();
            $table->json('health')->nullable();
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamp('maintenance_until')->nullable();
            $table->timestamps();
        });

        Schema::create('nodes', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('provider_instance_id', 40)->index();
            $table->string('name', 80);
            $table->string('region_code', 16)->index();
            $table->string('zone', 40)->nullable();
            $table->string('role', 16)->index();            // compute | web | managed | game | mail | dns | k8s | backup
            $table->string('state', 16)->default('active'); // active | drain | maintenance | down
            $table->json('capacity')->nullable();           // cpu_cores, ram_mb, disk_gb, gpus
            $table->json('usage')->nullable();              // cpu_pct, ram_used_mb, disk_used_gb, guests, load
            $table->string('failure_domain', 40)->nullable();
            $table->json('tags')->nullable();
            $table->unsignedInteger('remote_id')->nullable(); // pterodactyl node id, etc.
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['provider_instance_id', 'name']);
        });

        Schema::create('capacity_snapshots', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('node_id', 40)->index();
            $table->string('pool', 24);
            $table->decimal('cpu_pct', 5, 2)->nullable();
            $table->unsignedBigInteger('ram_used_mb')->nullable();
            $table->unsignedBigInteger('ram_total_mb')->nullable();
            $table->unsignedBigInteger('disk_used_gb')->nullable();
            $table->unsignedBigInteger('disk_total_gb')->nullable();
            $table->unsignedInteger('guests')->nullable();
            $table->decimal('load', 8, 2)->nullable();
            $table->timestamp('taken_at')->index();
        });

        Schema::create('ip_pools', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('region_code', 16)->index();
            $table->unsignedTinyInteger('family');          // 4 | 6
            $table->string('cidr', 64);
            $table->string('purpose', 24);                  // vps | shared_ingress | game | mail | dns | infra
            $table->string('gateway', 64)->nullable();
            $table->json('dns')->nullable();
            $table->unsignedSmallInteger('vlan')->nullable();
            $table->string('state', 16)->default('active');
            $table->unsignedInteger('reserve_count')->default(0); // addresses kept for emergencies
            $table->timestamps();
        });

        Schema::create('ip_addresses', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('pool_id', 40)->index();
            $table->string('address', 64)->unique();
            $table->unsignedTinyInteger('family');
            $table->unsignedTinyInteger('prefix_length')->nullable();
            $table->string('state', 16)->default('free'); // free | reserved | allocated | quarantine
            $table->string('service_id', 40)->nullable()->index();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('rdns', 253)->nullable();
            $table->timestamp('reserved_until')->nullable();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('project_id', 40)->nullable()->index();
            $table->string('product_key', 60)->index();
            $table->string('plan_version_id', 40)->nullable();
            $table->string('family', 24)->index();
            $table->string('name', 190);
            $table->string('label', 190)->nullable();
            $table->string('hostname', 253)->nullable();
            $table->string('state', 24)->default('PENDING_PAYMENT')->index();
            $table->string('region_code', 16)->nullable();
            $table->string('provider_instance_id', 40)->nullable()->index();
            $table->string('node_id', 40)->nullable()->index();
            $table->json('desired_spec')->nullable();
            $table->json('actual_spec')->nullable();
            $table->json('entitlements')->nullable();
            $table->string('sla_class', 16)->default('standard');
            $table->string('order_item_id', 40)->nullable();
            $table->string('subscription_id', 40)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspended_reason', 120)->nullable();
            $table->timestamp('terminate_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->timestamp('retention_until')->nullable();
            $table->boolean('legal_hold')->default(false);
            $table->timestamp('last_reconciled_at')->nullable();
            $table->json('health')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('provider_bindings', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('provider_instance_id', 40)->index();
            $table->string('remote_type', 40);
            $table->string('remote_id', 190);
            $table->string('remote_node', 80)->nullable();
            $table->string('adapter_version', 20)->nullable();
            $table->json('ownership')->nullable();  // field => ONHOST_MANAGED | PROVIDER_MANAGED | CUSTOMER_MUTABLE | OBSERVED_ONLY
            $table->json('meta')->nullable();
            $table->string('idempotency_key', 200)->unique();
            $table->string('checksum', 40)->nullable();
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamps();
            $table->unique(['provider_instance_id', 'remote_type', 'remote_id']);
        });

        Schema::create('operations', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('service_id', 40)->nullable()->index();
            $table->string('order_item_id', 40)->nullable()->index();
            $table->string('domain_id', 40)->nullable()->index();
            $table->string('kind', 60)->index();       // provision.vps, lifecycle.suspend, dns.apply, domain.register …
            $table->string('workflow', 120);           // FQCN
            $table->string('state', 20)->default('PENDING')->index(); // PENDING | RUNNING | WAITING | SUCCEEDED | FAILED | CANCELLED | COMPENSATED
            $table->unsignedSmallInteger('step')->default(0);
            $table->unsignedSmallInteger('steps_total')->default(0);
            $table->string('step_label', 190)->nullable();
            $table->string('actor_type', 24)->default('system');
            $table->string('actor_id', 40)->nullable();
            $table->string('idempotency_key', 200)->unique();
            $table->string('correlation_id', 128)->nullable()->index();
            $table->json('desired')->nullable();
            $table->json('context')->nullable();       // step outputs (vmid, remote ids, handles)
            $table->json('result')->nullable();
            $table->json('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_instance_id', 40)->nullable()->index();
            $table->json('external_handle')->nullable();
            $table->string('queue', 40)->default('provisioning:default');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('retry_until')->nullable();
            $table->timestamps();
        });

        Schema::create('operation_attempts', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('operation_id', 40)->index();
            $table->unsignedSmallInteger('attempt');
            $table->unsignedSmallInteger('step');
            $table->string('step_label', 190)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('outcome', 16)->nullable(); // ok | wait | retry | fail | skipped
            $table->string('error', 500)->nullable();
            $table->string('provider_fingerprint', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('resource_drifts', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('service_id', 40)->index();
            $table->string('provider_binding_id', 40)->nullable()->index();
            $table->string('field', 80);
            $table->string('ownership', 24)->default('ONHOST_MANAGED');
            $table->json('expected')->nullable();
            $table->json('actual')->nullable();
            $table->string('classification', 24); // EXPECTED | AUTO_REPAIRABLE | REQUIRES_APPROVAL | SECURITY_SUSPICIOUS
            $table->string('state', 16)->default('open'); // open | repaired | approved | ignored
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by', 40)->nullable();
            $table->string('resolution', 250)->nullable();
            $table->timestamps();
        });

        Schema::create('integration_health', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('provider_instance_id', 40)->unique();
            $table->boolean('up')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->decimal('error_rate_1h', 5, 2)->default(0);
            $table->unsignedInteger('p95_ms')->default(0);
            $table->unsignedInteger('calls_24h')->default(0);
            $table->unsignedInteger('errors_24h')->default(0);
            $table->decimal('budget_used_pct', 5, 2)->default(0);
            $table->string('circuit_state', 12)->default('closed');
            $table->string('last_error', 500)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['integration_health', 'resource_drifts', 'operation_attempts', 'operations', 'provider_bindings', 'services', 'ip_addresses', 'ip_pools', 'capacity_snapshots', 'nodes', 'provider_instances', 'regions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
