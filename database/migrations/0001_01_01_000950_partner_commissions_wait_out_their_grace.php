<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-0097, owner decision R7: a partner commission becomes payable 30 days after the client paid the invoice, and one
 * commission per (invoice, kind) is kept by the database.
 *
 *  · payable_at — when the commission may be paid out (invoice paid + 30 days).
 *  · cancelled_at — the commission was given back in full inside the window (state `cancelled`, never payable).
 *  · fragment — the remainder a payout split off a commission (PartnerPayouts::allocate); it carries the invoice and the kind
 *    of the commission it came from, so the unique index leaves fragments out.
 *
 * R7 applies to commissions created from the deploy on (security review of PR #89): no existing row changes its state; rows
 * with a payment date get `payable_at` for the record only.
 *
 * Existing duplicates of (invoice, kind) are marked fragments only when they look like payout remainders: the same partner,
 * base, rate and currency, positive amounts, every row but one linked to a payout, and together not more than the commission
 * the base and rate give (one haler per row of rounding). Anything else — a genuine double accrual — is reported and the
 * migration stops before it changes anything: the index cannot be built over it, and which row is right is finance's call.
 *
 * Row volume: one row per paid client invoice of an attributed client — small. The partial unique index is plain SQL on both
 * engines the platform runs (SQLite, PostgreSQL), as in 000900. Old code on the new schema keeps working (new columns are
 * nullable or defaulted). Rollback drops the index and the columns.
 */
return new class extends Migration
{
    private const GRACE_DAYS = 30; // R7; the domain constant is Onhost\Domain\Partners\CommissionGrace::DAYS — a migration does not load domain code

    public function up(): void
    {
        [$remainders, $refused] = $this->classifyDuplicates();
        if ($refused !== []) {
            $message = 'partner_commissions: '.count($refused).' (invoice, kind) group(s) hold more than one commission and do not look like payout remainders — resolve them (finance) and run the migration again: '.implode(', ', $refused);
            Log::error($message);

            throw new RuntimeException($message);
        }

        Schema::table('partner_commissions', function (Blueprint $table): void {
            $table->timestamp('payable_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('fragment')->default(false);
            $table->index(['state', 'payable_at']);
        });

        if ($remainders !== []) {
            DB::table('partner_commissions')->whereIn('id', $remainders)->update(['fragment' => true]);
            $note = 'partner_commissions: '.count($remainders).' payout remainder row(s) marked as fragments.';
            Log::info($note, ['ids' => $remainders]);
            fwrite(STDERR, $note.PHP_EOL);
        }

        DB::table('partner_commissions')->whereNotNull('invoice_paid_at')->orderBy('id')->select('id', 'invoice_paid_at')->chunk(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('partner_commissions')->where('id', $row->id)->update(['payable_at' => Carbon::parse($row->invoice_paid_at)->addDays(self::GRACE_DAYS)]);
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

    /**
     * Groups of (invoice, kind) with more than one row, read from the columns the table already has.
     *
     * @return array{0: list<string>, 1: list<string>} the remainder rows to mark, and the groups refused as "invoice/kind"
     */
    private function classifyDuplicates(): array
    {
        $groups = DB::table('partner_commissions')->whereNotNull('invoice_id')->select('invoice_id', 'kind')->groupBy('invoice_id', 'kind')->havingRaw('count(*) > 1')->get();
        $remainders = [];
        $refused = [];
        foreach ($groups as $group) {
            $rows = DB::table('partner_commissions')->where('invoice_id', $group->invoice_id)->where('kind', $group->kind)->orderBy('created_at')->orderBy('id')
                ->get(['id', 'partner_id', 'base_minor', 'rate_pct', 'currency', 'amount_minor', 'payout_id']);
            if (! $this->looksLikeRemainders((string) $group->kind, $rows)) {
                $refused[] = $group->invoice_id.'/'.$group->kind;

                continue;
            }
            array_push($remainders, ...$rows->slice(1)->pluck('id')->map(fn ($id) => (string) $id)->all());
        }

        return [$remainders, $refused];
    }

    /** @param Collection<int, stdClass> $rows */
    private function looksLikeRemainders(string $kind, $rows): bool
    {
        $first = $rows->first();
        $same = $rows->every(fn ($r) => $r->partner_id === $first->partner_id && (int) $r->base_minor === (int) $first->base_minor && (int) $r->rate_pct === (int) $first->rate_pct && $r->currency === $first->currency);
        $positive = $rows->every(fn ($r) => (int) $r->amount_minor > 0);
        $linked = $rows->filter(fn ($r) => $r->payout_id !== null)->count() >= $rows->count() - 1;
        $earned = intdiv(2 * (int) $first->base_minor * (int) $first->rate_pct + 100, 200); // base × rate %, rounded half up
        $fits = (int) $rows->sum('amount_minor') <= $earned + $rows->count();

        return $kind !== 'reversal' && $same && $positive && $linked && $fits;
    }
};
