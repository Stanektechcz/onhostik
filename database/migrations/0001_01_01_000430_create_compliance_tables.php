<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Compliance-by-design (blueprint §23, §25): regulatory timers, DSA notice-and-action, GDPR/Data Act requests, legal holds. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cyber_incidents', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('incident_id', 40)->nullable()->index(); // linked service incident (optional)
            $table->string('number', 20)->unique();                 // SEC-2026-0003
            $table->string('title', 250);
            $table->string('severity', 4)->default('p2');
            $table->timestamp('detected_at');
            $table->json('affected_services')->nullable();
            $table->json('jurisdictions');                          // ["CZ","SK","EU"]
            $table->boolean('personal_data_breach')->default(false);
            $table->boolean('life_safety_crime_suspicion')->default(false);
            $table->boolean('nis2_scope')->default(false);
            $table->string('state', 20)->default('OPEN');          // OPEN | CONTAINED | REPORTED | CLOSED
            $table->string('owner_id', 40)->nullable();
            $table->json('evidence')->nullable();                   // [{name, sha256, stored_at, legal_hold}]
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_timers', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('case_type', 24);       // cyber_incident | abuse_case | data_request
            $table->string('case_id', 40)->index();
            $table->string('timer', 32);           // NIS2_EARLY_WARNING | NIS2_NOTIFICATION | NIS2_FINAL_REPORT | GDPR_72H | DSA_ART18_PROMPT | DATA_ACT_SWITCHING
            $table->string('owner_id', 40)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('deadline_at')->index();
            $table->timestamp('warned_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('authority_reference', 120)->nullable();
            $table->json('evidence')->nullable();
            $table->string('state', 12)->default('running'); // running | met | missed | waived
            $table->timestamps();
            $table->unique(['case_type', 'case_id', 'timer']);
        });

        Schema::create('abuse_cases', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('number', 20)->unique();   // ABU-2026-0012
            $table->json('reporter');                 // {name, email, organization, trusted_flagger}
            $table->string('category', 40);           // illegal_content | copyright | phishing | malware | spam | csam | terrorism | other
            $table->text('allegation');
            $table->string('target_url', 500)->nullable();
            $table->string('service_id', 40)->nullable()->index();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('jurisdiction', 8)->nullable();
            $table->json('evidence')->nullable();     // [{kind, sha256, captured_at, path}]
            $table->boolean('art18')->default(false); // life/safety suspicion → authority notice
            $table->string('state', 20)->default('RECEIVED'); // RECEIVED | TRIAGED | CUSTOMER_NOTIFIED | ACTIONED | DISMISSED | APPEALED | CLOSED
            $table->string('decision', 20)->nullable(); // action | no_action
            $table->text('decision_reason')->nullable();
            $table->string('action_taken', 40)->nullable(); // content_removed | service_suspended | warning | none
            $table->string('ticket_id', 40)->nullable();
            $table->string('handled_by', 40)->nullable();
            $table->timestamp('customer_notified_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('appeal_deadline_at')->nullable();
            $table->timestamps();
        });

        Schema::create('data_requests', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('kind', 12);               // export | deletion | switching
            $table->string('state', 16)->default('requested'); // requested | processing | ready | completed | rejected | cancelled
            $table->string('requested_by', 40)->nullable();
            $table->string('reason', 250)->nullable();
            $table->string('file_path', 250)->nullable();
            $table->string('download_token_hash', 64)->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('meta')->nullable();         // sizes, counts, legal hold blocks, anonymised tables
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['data_requests', 'abuse_cases', 'compliance_timers', 'cyber_incidents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
