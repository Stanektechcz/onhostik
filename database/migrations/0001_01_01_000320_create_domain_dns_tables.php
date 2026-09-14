<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Domain Platform (blueprint §47.1) and canonical DNS with versioning/rollback (§48, §S39). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('project_id', 40)->nullable();
            $table->string('fqdn_ascii', 253)->unique();
            $table->string('fqdn_unicode', 253);
            $table->string('tld', 32)->index();
            $table->string('registrar_provider', 24)->default('wedos');
            $table->string('registrar_remote_id', 80)->nullable();
            $table->string('state', 24)->default('PENDING_REGISTRATION')->index();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('auto_renew')->default(true);
            $table->unsignedTinyInteger('renewal_period')->default(1);
            $table->string('auto_renew_priority', 12)->default('domain');
            $table->string('dns_provider', 24)->default('powerdns'); // powerdns | external | wedos_zone
            $table->string('dns_zone_id', 40)->nullable();
            $table->json('nameservers')->nullable();
            $table->string('registrant_contact_id', 40)->nullable();
            $table->string('admin_contact_id', 40)->nullable();
            $table->string('nsset_id', 40)->nullable();
            $table->string('keyset_ref', 80)->nullable();
            $table->boolean('dnssec')->default(false);
            $table->boolean('transfer_lock')->default(true);
            $table->string('privacy_mode', 16)->default('registry_default');
            $table->boolean('critical')->default(false); // ONhost infrastructure domain (Critical Domain Policy §46.7)
            $table->string('subscription_id', 40)->nullable();
            $table->string('order_item_id', 40)->nullable();
            $table->json('registry_status')->nullable();
            $table->timestamp('last_reconciled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('registrar_contacts', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('registrar_provider', 24)->default('wedos');
            $table->string('remote_id', 80)->nullable()->index();
            $table->string('schema', 16)->default('generic'); // cz | eu | sk | pl | generic
            $table->string('kind', 16)->default('registrant'); // registrant | admin | tech
            $table->string('name', 190);
            $table->string('organization_name', 190)->nullable();
            $table->string('email', 190);
            $table->string('phone', 40)->nullable();
            $table->string('street', 190)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('CZ');
            $table->string('ico', 20)->nullable();
            $table->string('dic', 20)->nullable();
            $table->string('privacy', 16)->default('hidden'); // registry disclosure preference
            $table->string('state', 16)->default('draft'); // draft | synced | error
            $table->json('registry_fields')->nullable();
            $table->timestamps();
        });

        Schema::create('registrar_objects', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('kind', 16); // nsset | keyset
            $table->string('handle', 80)->unique();
            $table->string('registrar_provider', 24)->default('wedos');
            $table->json('nameservers')->nullable();
            $table->json('keys')->nullable();
            $table->string('tech_contact_id', 40)->nullable();
            $table->boolean('shared')->default(false); // ONhost default NSSET
            $table->string('state', 16)->default('draft');
            $table->timestamps();
        });

        Schema::create('registrar_operations', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('domain_id', 40)->nullable()->index();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('operation_id', 40)->nullable()->index();
            $table->string('command', 60);
            $table->string('cltrid', 120)->unique();
            $table->string('state', 20)->default('SENT'); // SENT | SUCCEEDED | PENDING_REGISTRY | FAILED | UNKNOWN
            $table->string('vendor_code', 12)->nullable();
            $table->string('vendor_message', 250)->nullable();
            $table->string('normalized_error', 60)->nullable();
            $table->json('request')->nullable();  // redacted
            $table->json('response')->nullable(); // redacted
            $table->boolean('test_mode')->default(false);
            $table->timestamp('sent_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('registrar_notifications', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('registrar_provider', 24)->default('wedos');
            $table->string('remote_id', 80)->unique();
            $table->string('kind', 60)->nullable();
            $table->string('fqdn', 253)->nullable()->index();
            $table->json('payload')->nullable();
            $table->string('state', 16)->default('received'); // received | processed | acked | dead
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 250)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('registrar_credit_snapshots', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('registrar_provider', 24)->default('wedos');
            $table->bigInteger('balance_minor');
            $table->string('currency', 3);
            $table->bigInteger('renewals_30d_minor')->nullable();
            $table->unsignedInteger('renewals_30d_count')->nullable();
            $table->unsignedSmallInteger('runway_days')->nullable();
            $table->boolean('below_minimum')->default(false);
            $table->timestamp('taken_at')->index();
        });

        Schema::create('domain_consents', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('domain_id', 40)->index();
            $table->string('consent_id', 40)->nullable();
            $table->string('registrar_operation_id', 40)->nullable();
            $table->string('tld', 32);
            $table->string('registry_terms_url', 250)->nullable();
            $table->string('registrar_terms_url', 250)->nullable();
            $table->string('document_version', 40)->nullable();
            $table->string('document_hash', 64)->nullable();
            $table->string('language', 5)->default('cs');
            $table->string('person', 190);
            $table->string('user_id', 40)->nullable();
            $table->string('registrant_contact_id', 40)->nullable();
            $table->string('ip', 64)->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();
        });

        Schema::create('domain_renewal_jobs', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('domain_id', 40)->index();
            $table->string('organization_id', 40)->index();
            $table->unsignedTinyInteger('period_years')->default(1);
            $table->string('state', 20)->default('SCHEDULED'); // SCHEDULED | HOLD_PLACED | SENT | PENDING_REGISTRY | SUCCEEDED | FAILED | SKIPPED
            $table->string('wallet_hold_id', 40)->nullable();
            $table->string('operation_id', 40)->nullable();
            $table->timestamp('due_at')->index();
            $table->timestamp('scheduled_for')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 250)->nullable();
            $table->json('notices_sent')->nullable(); // [60,30,14,7,3,1]
            $table->timestamps();
        });

        Schema::create('domain_transfer_secrets', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('domain_id', 40)->index();
            $table->text('auth_info'); // encrypted, removed after use (§46.5)
            $table->string('direction', 8); // in | out
            $table->string('requested_by', 40)->nullable();
            $table->string('step_up_method', 24)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dns_zones', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('domain_id', 40)->nullable()->index();
            $table->string('name', 253)->unique(); // canonical ascii without trailing dot
            $table->string('provider', 24)->default('powerdns'); // powerdns | wedos_zone | ispconfig
            $table->string('provider_instance_id', 40)->nullable();
            $table->unsignedBigInteger('serial')->default(0);
            $table->unsignedInteger('version')->default(0);
            $table->boolean('dnssec')->default(false);
            $table->json('dnssec_ds')->nullable();
            $table->string('kind', 12)->default('primary'); // primary | secondary
            $table->string('state', 16)->default('pending'); // pending | active | error | deleted
            $table->json('nameservers')->nullable();
            $table->json('secondary_providers')->nullable(); // optional WEDOS Zone AXFR/TSIG
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dns_records', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('zone_id', 40)->index();
            $table->string('name', 253);        // relative: @, www, _dmarc
            $table->string('type', 10);
            $table->text('content');
            $table->unsignedInteger('ttl')->default(3600);
            $table->unsignedSmallInteger('prio')->nullable();
            $table->string('managed_by', 16)->default('customer'); // customer | system
            $table->boolean('protected')->default(false); // MX/NS/system records need extra confirmation (S39)
            $table->string('comment', 250)->nullable();
            $table->timestamps();
            $table->index(['zone_id', 'name', 'type']);
        });

        Schema::create('dns_zone_versions', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('zone_id', 40)->index();
            $table->unsignedInteger('version');
            $table->unsignedBigInteger('serial');
            $table->json('records');
            $table->string('committed_by', 40)->nullable();
            $table->string('reason', 250)->nullable();
            $table->timestamp('committed_at');
            $table->timestamps();
            $table->unique(['zone_id', 'version']);
        });

        Schema::create('dns_changes', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('zone_id', 40)->index();
            $table->string('op', 8); // add | update | delete
            $table->json('record');
            $table->json('previous')->nullable();
            $table->string('requested_by', 40)->nullable();
            $table->string('reason', 250)->nullable();
            $table->string('state', 12)->default('pending'); // pending | committed | discarded | failed
            $table->string('operation_id', 40)->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dns_templates', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->nullable()->index(); // null = global ONhost template
            $table->string('key', 60);
            $table->json('name');
            $table->json('records'); // with placeholders {ipv4}, {ipv6}, {domain}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['dns_templates', 'dns_changes', 'dns_zone_versions', 'dns_records', 'dns_zones', 'domain_transfer_secrets', 'domain_renewal_jobs', 'domain_consents', 'registrar_credit_snapshots', 'registrar_notifications', 'registrar_operations', 'registrar_objects', 'registrar_contacts', 'domains'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
