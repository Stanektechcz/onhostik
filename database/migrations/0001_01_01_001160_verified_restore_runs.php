<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W2-4 "Ověřená obnova": one row per verified restore — the newest complete backup set of a web service restored into an
 * isolated copy of its own (never the live site), checked, measured (RPO, RTO), removed again — and the protocol the customer
 * downloads. Only facts with their timestamps: every step is pass, fail or not_verified, and what could not be suppressed in
 * the copy is written down next to what was.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('verified_restore_runs')) {
            return;
        }
        Schema::create('verified_restore_runs', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('service_id', 40);
            $table->string('operation_id', 40)->nullable()->unique();
            $table->string('backup_id', 40)->nullable();
            $table->string('trigger', 16)->default('schedule');      // schedule | staff
            $table->string('state', 16)->default('running');         // running | finished
            $table->string('outcome', 16)->nullable();                // passed | failed (the restore and its checks; cleanup is its own step)
            $table->timestamp('backup_taken_at')->nullable();         // when the restored set was finished (the RPO reference)
            $table->timestamp('started_at')->nullable();              // the RTO clock starts here
            $table->timestamp('checks_done_at')->nullable();          // ... and stops here
            $table->timestamp('finished_at')->nullable();             // protocol written (after the cleanup)
            $table->unsignedInteger('rpo_seconds')->nullable();
            $table->unsignedInteger('rto_seconds')->nullable();
            $table->json('steps')->nullable();                        // [{key, status: pass|fail|not_verified, at, detail}]
            $table->json('suppression')->nullable();                  // {applied: [...], not_applied: [...]}
            $table->json('target')->nullable();                       // {service_id, domain, databases: [{remote_id, name}]} — never a password
            $table->json('skipped_sets')->nullable();                 // newer sets passed over as incomplete: [{backup_id, taken_at, reason}]
            $table->timestamps();
            $table->index(['service_id', 'started_at']);
            $table->index(['state', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verified_restore_runs');
    }
};
