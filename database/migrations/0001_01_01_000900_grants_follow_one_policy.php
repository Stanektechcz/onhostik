<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-0042 (permission program S1-02, D21): three new records, nothing existing is changed.
 *
 *  · access_snapshots — what a person could do in an organization just before a removal or a role change (I10): the membership,
 *    every binding of theirs there, their project roles and single-service shares, as one JSON document. Kept 90 days
 *    (onhost.grants.snapshot_retention_days), restorable once, pruned by onhost:access:expire afterwards.
 *  · ownership_transfers — an offer of the organization's ownership that the heir accepts, declines or lets lapse; the owner may
 *    cancel it meanwhile (I4, audit TD-9: ownership moved without the heir's consent).
 *  · owner_recoveries — a customer owner who lost access, recovered by support only after a second person and a notice period
 *    every member can see and any org_admin can cancel (D21). The reason and the ticket are the evidence; never deleted. An MFA
 *    reset reaches every organization the person owns or manages, so its recovery is one row in each of them, tied by group_id.
 *
 * One pending ownership offer and one pending owner recovery per organization, kept by the database (review round 1: two requests
 * could both pass the look for a pending one and leave an orphan nobody could cancel). A partial unique index is plain SQL on
 * both engines the platform runs (SQLite, PostgreSQL); the services turn the refusal into a 409.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_snapshots', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40);
            $table->string('user_id', 40);
            $table->string('reason', 40);                       // member_removed | role_changed | project_role_* | service_access_* | ownership_transferred | invitation_widened | before_restore
            $table->json('access');                             // AccessSnapshots::capture()
            $table->string('taken_by', 40)->nullable();         // the person whose change it preceded; null for the system
            $table->timestamp('expires_at');
            $table->timestamp('restored_at')->nullable();
            $table->string('restored_by', 40)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'user_id']);
            $table->index('expires_at');
        });

        Schema::create('ownership_transfers', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('from_user_id', 40);                 // the owner who offered it
            $table->string('to_user_id', 40);                   // the member it is offered to
            $table->string('state', 16)->default('pending');     // pending | accepted | declined | cancelled | expired
            $table->timestamp('expires_at');
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_by', 40)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'state']);
        });
        DB::statement("CREATE UNIQUE INDEX ownership_transfers_one_pending ON ownership_transfers (organization_id) WHERE state = 'pending'");

        Schema::create('owner_recoveries', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('organization_id', 40)->index();
            $table->string('group_id', 40)->nullable()->index();  // the recovery opened on the organization support named; its rows in the other organizations point to it
            $table->string('owner_user_id', 40);                // the person recovered: the owner of the named organization when it was opened
            $table->string('mode', 16);                         // mfa_reset | transfer
            $table->string('new_owner_user_id', 40)->nullable(); // mode transfer: the member who becomes the owner
            $table->string('state', 16)->default('pending');     // pending | cancelled | completed
            $table->text('reason');
            $table->string('ticket_ref', 60);
            $table->string('requested_by', 40)->nullable();
            $table->json('approval_ids')->nullable();           // the second person's approval the opening consumed
            $table->timestamp('not_before');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 40)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('completed_by', 40)->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'state']);
        });
        DB::statement("CREATE UNIQUE INDEX owner_recoveries_one_pending ON owner_recoveries (organization_id) WHERE state = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_recoveries');
        Schema::dropIfExists('ownership_transfers');
        Schema::dropIfExists('access_snapshots');
    }
};
