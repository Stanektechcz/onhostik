<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-0097, owner decision R7: a partner commission becomes payable 30 days after the client paid the invoice, and one
 * commission per (invoice, kind) is kept by the database.
 *
 *  · payable_at — when the commission may be paid out (invoice paid + 30 days); null on a row that never had a date.
 *  · cancelled_at — the commission was given back in full inside the window (state `cancelled`, never payable).
 *  · fragment — the remainder a payout split off a commission (PartnerPayouts::allocate); it carries the invoice and the kind
 *    of the commission it came from, so the unique index leaves fragments out. Existing duplicates of (invoice, kind) are
 *    exactly such remainders: the oldest row stays the commission, the rest are marked fragments. Nothing is deleted.
 *
 * A commission accrued in the last 30 days that is still `payable` (not in any payout) waits out the rest of its window
 * (`pending`): R7 applies to money not yet paid out. Allocated and paid rows, and reversals, keep their state.
 *
 * Row volume: one row per paid client invoice of an attributed client — small; the index is built in one statement. The
 * partial unique index is plain SQL on both engines the platform runs (SQLite, PostgreSQL), as in 000900. Old code on the new
 * schema keeps working (new columns are nullable or defaulted); rollback restores the states the old code knows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_commissions', function (Blueprint $table): void {
            $table->timestamp('payable_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('fragment')->default(false);
            $table->index(['state', 'payable_at']);
        });

        $duplicates = DB::table('partner_commissions')->whereNotNull('invoice_id')->select('invoice_id', 'kind')->groupBy('invoice_id', 'kind')->havingRaw('count(*) > 1')->get();
        foreach ($duplicates as $group) {
            $ids = DB::table('partner_commissions')->where('invoice_id', $group->invoice_id)->where('kind', $group->kind)->orderBy('created_at')->orderBy('id')->pluck('id')->all();
            DB::table('partner_commissions')->whereIn('id', array_slice($ids, 1))->update(['fragment' => true]);
        }

        $graceDays = 30; // R7; the domain constant is Onhost\Domain\Partners\CommissionGrace::DAYS — a migration does not load domain code
        $windowStart = Carbon::now()->subDays($graceDays);
        DB::table('partner_commissions')->whereNotNull('invoice_paid_at')->orderBy('id')->select('id', 'invoice_paid_at', 'state', 'kind')->chunk(500, function ($rows) use ($graceDays, $windowStart): void {
            foreach ($rows as $row) {
                $paidAt = Carbon::parse($row->invoice_paid_at);
                $patch = ['payable_at' => $paidAt->copy()->addDays($graceDays)];
                if ($row->state === 'payable' && $row->kind !== 'reversal' && $paidAt->greaterThan($windowStart)) {
                    $patch['state'] = 'pending';
                }
                DB::table('partner_commissions')->where('id', $row->id)->update($patch);
            }
        });

        DB::statement('CREATE UNIQUE INDEX partner_commissions_invoice_kind_unique ON partner_commissions (invoice_id, kind) WHERE fragment = false');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS partner_commissions_invoice_kind_unique');
        DB::table('partner_commissions')->whereIn('state', ['pending', 'cancelled'])->update(['state' => 'payable']); // a cancelled commission and its cancelled reversal net to zero
        Schema::table('partner_commissions', function (Blueprint $table): void {
            $table->dropIndex(['state', 'payable_at']);
            $table->dropColumn(['payable_at', 'cancelled_at', 'fragment']);
        });
    }
};
