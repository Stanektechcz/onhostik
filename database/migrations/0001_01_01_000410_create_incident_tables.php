<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Status, incidents, maintenance (blueprint §73) and the SLA/SLO engine (§66): probes, measurements, windows, credits. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_components', function (Blueprint $table): void {
            $table->string('key', 40)->primary();     // web-cz1 | managed | apps-cz1 | cloud-cz1 | games-cz1 | dns | domains | mail | payments | portal | ai
            $table->string('name', 120);
            $table->string('group', 40)->nullable();
            $table->string('region_code', 16)->nullable();
            $table->string('sla_class', 16)->default('standard');
            $table->string('state', 20)->default('operational'); // operational | degraded | partial_outage | major_outage | maintenance
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('public')->default(true);
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('number', 20)->unique();   // INC-2026-0031
            $table->string('title', 250);
            $table->string('severity', 4)->default('p3'); // p1 | p2 | p3 | p4
            $table->string('state', 20)->default('DETECTED')->index();
            $table->string('visibility', 10)->default('public'); // public | internal
            $table->boolean('security')->default(false);   // security incident: separate handling, no exploit detail on the status page
            $table->text('impact')->nullable();
            $table->json('components');                 // status component keys
            $table->json('affected_services')->nullable(); // service ids (customer notifications, SLA credits)
            $table->json('affected_organizations')->nullable();
            $table->string('commander_id', 40)->nullable();
            $table->string('source', 20)->default('manual'); // manual | probes | provider | customer
            $table->timestamp('started_at');
            $table->timestamp('detected_at');
            $table->timestamp('mitigated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('sla_relevant')->default(true);
            $table->json('postmortem')->nullable();      // {summary, timeline, root_cause, actions:[{owner,deadline,verified}], published_at}
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('incident_updates', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('incident_id', 40)->index();
            $table->string('state', 20);
            $table->text('note');
            $table->boolean('public')->default(true);
            $table->string('author_id', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('maintenances', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('number', 20)->unique();   // MNT-2026-0007
            $table->string('title', 250);
            $table->json('components');
            $table->json('affected_services')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->text('impact')->nullable();
            $table->text('rollback')->nullable();
            $table->string('owner_id', 40)->nullable();
            $table->string('approved_by', 40)->nullable();
            $table->string('change_ticket', 40)->nullable();
            $table->string('sla_treatment', 12)->default('excluded'); // excluded | counted
            $table->string('state', 16)->default('planned'); // planned | approved | in_progress | completed | cancelled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sla_probes', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('key', 60)->unique();      // portal-https-cz | dns-soa-sk …
            $table->string('component_key', 40)->index();
            $table->string('kind', 10);               // http | dns | tcp | icmp
            $table->string('target', 250);
            $table->string('location', 40);           // probe-cz-external | probe-sk-external | probe-eu-external
            $table->json('expected')->nullable();     // {status:200, body_contains:'ok', latency_ms:800, dnssec:true}
            $table->unsignedSmallInteger('interval_seconds')->default(60);
            $table->string('token_hash', 64)->nullable(); // bearer token the external agent uses to report
            $table->string('state', 12)->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sla_measurements', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('probe_id', 40)->index();
            $table->string('component_key', 40);
            $table->string('location', 40);
            $table->timestamp('measured_at');
            $table->boolean('ok');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('detail', 250)->nullable();
            $table->timestamps();
            $table->index(['component_key', 'measured_at']);
            $table->unique(['probe_id', 'measured_at']);
        });

        Schema::create('slo_windows', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('component_key', 40)->index();
            $table->string('window', 8);              // 1h | 6h | 3d | 30d
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->unsignedInteger('good')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->decimal('objective', 7, 4);
            $table->decimal('availability_pct', 7, 4)->nullable();
            $table->decimal('budget_consumed_pct', 8, 2)->nullable();
            $table->decimal('burn_rate', 8, 2)->nullable();
            $table->string('policy_state', 24)->nullable(); // normal | review_high_risk | reliability_priority | freeze
            $table->timestamp('computed_at');
            $table->timestamps();
            $table->unique(['component_key', 'window', 'window_end']);
        });

        Schema::create('sla_credit_policies', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('key', 40);
            $table->unsignedInteger('version')->default(1);
            $table->string('sla_class', 16);
            $table->json('bands');                    // [{below: 99.99, credit_percent: 10}, {below: 99.9, credit_percent: 25}, {below: 99.0, credit_percent: 50}]
            $table->unsignedTinyInteger('cap_percent')->default(50);
            $table->string('claim', 8)->default('auto'); // auto | manual
            $table->json('eligibility')->nullable();
            $table->json('exclusions')->nullable();
            $table->string('state', 12)->default('active');
            $table->timestamps();
            $table->unique(['key', 'version']);
        });

        Schema::create('sla_credits', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('incident_id', 40)->nullable()->index();
            $table->string('service_id', 40)->nullable()->index();
            $table->string('policy_id', 40)->nullable();
            $table->decimal('availability_pct', 7, 4)->nullable();
            $table->unsignedTinyInteger('credit_percent');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('state', 12)->default('candidate'); // candidate | approved | issued | rejected
            $table->string('invoice_id', 40)->nullable();      // credit note (DK)
            $table->json('calculation');                       // auditable inputs: window, downtime seconds, monthly price, band
            $table->string('approved_by', 40)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'incident_id', 'service_id']);
        });
    }

    public function down(): void
    {
        foreach (['sla_credits', 'sla_credit_policies', 'slo_windows', 'sla_measurements', 'sla_probes', 'maintenances', 'incident_updates', 'incidents', 'status_components'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
