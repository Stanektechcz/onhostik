<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * TASK-0040 (permission program P0-13, IF-14; audit P1, P2, P8): a partner payout is paid once, to the right account.
 *
 * partner_payout_accounts: where a partner's commission is paid. A row is written only by the owner's own command (HIGH,
 * step-up, a notice) and is usable from `usable_from` (seven days later); the IBAN used to be a field of every payout
 * request and overwrote `partners.iban` with no step-up. No row is written here for existing partners (owner rule: no mass
 * write): an IBAN a paid payout already went to is read as the confirmed account (grandfathered, program D13).
 *
 * partner_payouts gains who asked, who approved (paying is somebody else's act, four eyes) and a freeze flag set only by
 * `onhost:partners:payout-anomalies --apply` or finance. All columns are nullable additions: old code ignores them, new code
 * reads NULL as "not recorded" (an approval of old keeps its decider in `decided_by`). Row volume: partners and payouts.
 *
 * Review round 1: the moment this runs is the cut-over of grandfathering (program §8 row 41, D13) — one platform row in
 * `system_settings`, written once (a re-run keeps the first) and read by PayoutAccounts; no staff screen writes that key.
 * Only an IBAN paid before it is a confirmed account; a payout the old code left open and paid later never becomes one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            'key' => 'partners.payout_account.grandfathered_before', // PayoutAccounts::GRANDFATHER_SETTING
            'value' => json_encode(now()->toIso8601String(), JSON_THROW_ON_ERROR), 'updated_by' => 'migration:000890', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('partner_payout_accounts', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('partner_id', 40);
            $table->string('iban', 34);
            $table->string('source', 12)->default('owner');       // owner (set through the portal) — grandfathered IBANs are read, never written
            $table->timestamp('usable_from');                      // requested_at + the cooling-off (PayoutAccounts::COOLING_DAYS, 7)
            $table->string('requested_by', 40)->nullable();
            $table->timestamp('cancelled_at')->nullable();         // called off before it was used, or replaced by a newer change
            $table->string('cancelled_by', 40)->nullable();
            $table->timestamps();
            $table->index(['partner_id', 'usable_from']);
        });

        Schema::table('partner_payouts', function (Blueprint $table): void {
            $table->string('payout_account_id', 40)->nullable(); // the account the IBAN was taken from (NULL: grandfathered, offset, or before TASK-0040)
            $table->string('requested_by', 40)->nullable();
            $table->string('approved_by', 40)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->string('frozen_by', 60)->nullable();          // a staff user id, or `cli:partners:payout-anomalies`
            $table->string('frozen_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partner_payouts', function (Blueprint $table): void {
            $table->dropColumn(['payout_account_id', 'requested_by', 'approved_by', 'approved_at', 'frozen_at', 'frozen_by', 'frozen_reason']);
        });
        Schema::dropIfExists('partner_payout_accounts');
        DB::table('system_settings')->where('key', 'partners.payout_account.grandfathered_before')->delete();
    }
};
