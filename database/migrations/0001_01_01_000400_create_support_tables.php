<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Unified Support Plane (blueprint §68) and AI assistant runs/handoffs (§69). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_sla_policies', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('key', 40)->unique();      // standard | business | ha
            $table->string('name', 120);
            $table->unsignedInteger('version')->default(1);
            $table->json('targets');                  // {p1:{first:15,next:60,resolve:240}, …} minutes
            $table->boolean('business_hours_only')->default(false);
            $table->json('business_hours')->nullable(); // {days:[1..5], from:'08:00', to:'18:00', tz:'Europe/Prague'}
            $table->timestamps();
        });

        Schema::create('support_queues', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('key', 40)->unique();      // l1 | billing | domains | cloud | web | games | mail | apps | security | abuse
            $table->string('name', 120);
            $table->json('skills');                   // skills served, e.g. ["PROXMOX","DNSSEC"]
            $table->string('escalates_to', 40)->nullable();
            $table->string('state', 12)->default('active');
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('number', 20)->unique();   // TK-2026-0001
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('user_id', 40)->nullable()->index();
            $table->string('email', 190)->index();
            $table->string('name', 190)->nullable();
            $table->string('subject', 250);
            $table->string('category', 40)->nullable()->index(); // topic id (dostupnost, fakturace, dns …)
            $table->string('priority', 4)->default('p3'); // p1 | p2 | p3 | p4
            $table->string('state', 20)->default('NEW')->index();
            $table->string('channel', 12)->default('portal'); // portal | email | chat | ai | api | incident
            $table->string('queue_id', 40)->nullable()->index();
            $table->string('assignee_id', 40)->nullable()->index();
            $table->string('service_id', 40)->nullable()->index();
            $table->string('domain_id', 40)->nullable();
            $table->string('incident_id', 40)->nullable()->index();
            $table->json('required_skills')->nullable();
            $table->json('tags')->nullable();
            $table->string('sla_policy_id', 40)->nullable();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('next_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('last_staff_message_at')->nullable();
            $table->timestamp('waiting_since')->nullable();
            $table->unsignedInteger('paused_minutes')->default(0);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedSmallInteger('reopen_count')->default(0);
            $table->unsignedSmallInteger('escalation_level')->default(0);
            $table->text('ai_summary')->nullable();
            $table->unsignedTinyInteger('csat_score')->nullable();
            $table->string('csat_comment', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['state', 'priority']);
        });

        Schema::create('support_messages', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('ticket_id', 40)->index();
            $table->string('author_type', 12);        // customer | staff | ai | system
            $table->string('author_id', 40)->nullable();
            $table->string('author_name', 190)->nullable();
            $table->string('visibility', 10)->default('public'); // public | internal
            $table->text('body');
            $table->json('attachments')->nullable();   // [{name,size,mime,path,sha256,scanned}]
            $table->string('source_message_id', 250)->nullable(); // inbound e-mail Message-ID
            $table->timestamps();
        });

        Schema::create('support_sla_events', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('ticket_id', 40)->index();
            $table->string('kind', 20);               // first_response | next_response | resolution | p1_ack
            $table->timestamp('due_at');
            $table->boolean('met')->nullable();
            $table->timestamp('measured_at')->nullable();
            $table->integer('delta_minutes')->nullable(); // negative = early
            $table->timestamps();
        });

        Schema::create('support_macros', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('key', 60)->unique();
            $table->string('name', 120);
            $table->string('category', 40)->nullable();
            $table->json('body');                     // {cs: …, en: …}
            $table->json('actions')->nullable();      // e.g. {state: WAITING_CUSTOMER}
            $table->timestamps();
        });

        Schema::create('support_ai_runs', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('user_id', 40)->nullable()->index();
            $table->string('session_id', 80)->nullable()->index();
            $table->string('ticket_id', 40)->nullable()->index();
            $table->string('provider', 40);           // rules | openai_compatible | anthropic
            $table->string('model', 80)->nullable();
            $table->string('topic', 40)->nullable();
            $table->boolean('confident')->default(false);
            $table->string('outcome', 20);            // answered | handoff | refused | action_proposed
            $table->json('tools_called')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->text('summary')->nullable();
            $table->json('transcript')->nullable();   // [{role, content, at}]
            $table->timestamps();
        });

        Schema::create('support_handoffs', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('ai_run_id', 40)->index();
            $table->string('ticket_id', 40)->index();
            $table->string('reason', 60);             // user_request | low_confidence | policy | security | legal | repeated_failure
            $table->json('diagnostics')->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_articles', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('slug', 190)->unique();
            $table->string('category', 60)->index();
            $table->json('title');                    // {cs, en}
            $table->json('excerpt')->nullable();
            $table->json('body');                     // {cs: [[heading, paragraph]…], en: …}
            $table->json('tags')->nullable();
            $table->string('state', 12)->default('published'); // draft | published | archived
            $table->string('author', 120)->nullable();
            $table->unsignedSmallInteger('read_minutes')->default(3);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['knowledge_articles', 'support_handoffs', 'support_ai_runs', 'support_macros', 'support_sla_events', 'support_messages', 'support_tickets', 'support_queues', 'support_sla_policies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
