<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-0044 (permission program D21, the S1-07 red-team MEDIUMs of the breach register): an owner recovery keeps its state
 * (`pending` — so the partial unique index `owner_recoveries_one_pending` and the hold stay exactly as they are) and gains a phase
 * while it waits:
 *
 *  · `contested` — the person being recovered objected to a transfer (in the hijack case: the account in the attacker's hands);
 *    staff review it, and only a second person continues it (`review_evidence`, `reviewed_by`, `reviewed_at`);
 *  · `offered` — a transfer's notice period ran out and the heir was offered the ownership; it completes when the heir accepts it
 *    in person with a fresh step-up (`ownership_transfers.recovery_id` names the recovery the offer belongs to).
 *
 * `cancel_reason` keeps why anybody other than the person recovered stopped it. All columns are nullable and nothing existing is
 * rewritten: a recovery opened before this release reads as phase null (waiting), no review, no reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_recoveries', function (Blueprint $table): void {
            $table->string('phase', 16)->nullable();               // null (waiting for its date) | contested | offered
            $table->timestamp('contested_at')->nullable();
            $table->string('contested_by', 40)->nullable();
            $table->text('review_evidence')->nullable();           // what staff checked before continuing a contested recovery
            $table->string('reviewed_by', 40)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('cancel_reason')->nullable();
        });
        Schema::table('ownership_transfers', function (Blueprint $table): void {
            $table->string('recovery_id', 40)->nullable()->index(); // an offer made by an owner recovery: only support withdraws it
        });
    }

    public function down(): void
    {
        Schema::table('ownership_transfers', function (Blueprint $table): void {
            $table->dropIndex(['recovery_id']);
            $table->dropColumn('recovery_id');
        });
        Schema::table('owner_recoveries', function (Blueprint $table): void {
            $table->dropColumn(['phase', 'contested_at', 'contested_by', 'review_evidence', 'reviewed_by', 'reviewed_at', 'cancel_reason']);
        });
    }
};
