<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SN (security news, docs/proposals/security-advisories.md): what the vulnerability feeds said (`security_advisories`, one row per
 * CVE or vendor id, merged across feeds), where each feed stopped (`security_advisory_sources`), the software the platform runs and
 * hosts (`software_inventory`), which of it an advisory hits (`security_advisory_matches`) and the staff case that decides what is
 * done about it (`security_advisory_cases`). Only normalized fields are kept, never a vendor payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_advisory_sources', function (Blueprint $table): void {
            $table->string('key', 20)->primary();
            $table->string('cursor', 100)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 300)->nullable();
            $table->unsignedInteger('fetched')->default(0);
            $table->timestamps();
        });

        Schema::create('security_advisories', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('key', 120)->unique(); // the CVE when there is one, else `source:id`
            $table->json('sources');
            $table->json('aliases')->nullable();
            $table->string('title', 300);
            $table->text('summary')->nullable();
            $table->string('severity', 10)->index(); // critical | high | medium | low | unknown
            $table->decimal('cvss', 3, 1)->nullable();
            $table->boolean('kev')->default(false)->index();
            $table->date('kev_added_on')->nullable();
            $table->json('affected')->nullable();
            $table->json('references')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('source_modified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('software_inventory', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('subject_type', 20); // service | provider_instance | platform
            $table->string('subject_id', 40)->default('');
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('ecosystem', 30);
            $table->string('product', 160);
            $table->string('version', 60);
            $table->string('label', 200);
            $table->json('meta')->nullable();
            $table->timestamp('seen_at')->index();
            $table->timestamps();
            $table->unique(['subject_type', 'subject_id', 'ecosystem', 'product']);
            $table->index(['ecosystem', 'product']);
        });

        Schema::create('security_advisory_cases', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('advisory_id', 40)->unique();
            $table->string('state', 20)->index();
            $table->string('severity', 10);
            $table->boolean('kev')->default(false);
            $table->unsignedInteger('matches_count')->default(0);
            $table->unsignedInteger('plan_revision')->default(1);
            $table->boolean('include_risky')->default(false);
            $table->unsignedSmallInteger('wave')->default(0);
            $table->string('decided_by', 40)->nullable();
            $table->string('decision_note', 1000)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('halted_reason', 300)->nullable();
            $table->timestamp('announced_at')->nullable();
            $table->string('post_id', 40)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('security_advisory_matches', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('advisory_id', 40);
            $table->string('inventory_id', 40);
            $table->string('case_id', 40)->nullable()->index();
            $table->string('subject_type', 20);
            $table->string('subject_id', 40)->default('');
            $table->string('organization_id', 40)->nullable()->index();
            $table->string('ecosystem', 30);
            $table->string('product', 160);
            $table->string('version', 60);
            $table->string('fixed_in', 60)->nullable();
            $table->string('confidence', 10); // exact | probable
            $table->string('class', 1); // A safe fix · B fix that may break · D tell the customer · P platform (ops)
            $table->json('action')->nullable();
            $table->string('state', 12)->default('open'); // open | queued | running | fixed | failed | notified | skipped | gone
            $table->string('operation_id', 40)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['advisory_id', 'inventory_id']);
            $table->index(['case_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_advisory_matches');
        Schema::dropIfExists('security_advisory_cases');
        Schema::dropIfExists('software_inventory');
        Schema::dropIfExists('security_advisories');
        Schema::dropIfExists('security_advisory_sources');
    }
};
