<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * K5 (TASK-0158), owner decisions I-R6 and J-R3: a genuine double commission no longer stops the deploy at 000950.
 *
 * 000950 refuses to build its unique index over a group of (invoice, kind) that holds more than one commission and does not look
 * like payout remainders, and it stops before the code that could correct a single commission is live — finance could only
 * cancel the newer row on the release the migration itself blocks. 000950 has already run on staging copies and in CI and is
 * never edited (J-R3); this migration sorts before it and runs first on a database that has not run it yet:
 *
 *  · it adds `duplicate_of_id` (the commission that stands), `duplicate_invoice_id` (the invoice the set-aside row was accrued for)
 *    and `cancels_id` (on the reversal of a commission-only cancellation: the commission it cancels; unique, so one cancellation
 *    per commission);
 *  · in every group 000950 would refuse it applies the owner's rule decided in advance (I-R6: the NEWER row goes) only as far as a
 *    migration may: each newer row is SET ASIDE — its invoice link moves to `duplicate_invoice_id`, so it no longer claims the
 *    invoice, and an unpaid one (`payable`) becomes `duplicate`, which no payout takes and the maturing job never flips. Amounts,
 *    partners and dates stay as they were. The correction itself — the newer row `cancelled` and a reversal row, or a minus on the
 *    next payouts for a row already paid out — is finance's command on the bus (`partner.commission.cancel_duplicate`: step-up and
 *    a second person), run on the released code;
 *  · a newer row that sits in an open payout (`allocated`) cannot be held safely (a rejected payout would make it payable again):
 *    the migration stops before it changes anything and names the group and the payout — finance finishes or rejects that payout on
 *    the running release, then the deploy runs again;
 *  · groups that look like payout remainders are left to 000950 (the same test, copied — a migration does not load another).
 *
 * On a database that already ran 000950 the unique index keeps (invoice, kind) single, so there is nothing to set aside and only
 * the columns are added. Row volume: one row per paid client invoice of an attributed client — small. Old code on the new schema
 * keeps working (new columns are nullable; a `duplicate` row is simply not payable for it either). Rollback re-attaches the
 * invoice of every set-aside row, turns `duplicate` back into `payable` and drops the columns; a cancellation's reversal row stays
 * (written by the bus, a record of its own).
 */
return new class extends Migration
{
    private const DUPLICATE = 'duplicate'; // Onhost\Domain\Partners\Models\PartnerCommission::DUPLICATE — a migration does not load domain code

    public function up(): void
    {
        [$aside, $refused] = $this->classify();
        if ($refused !== []) {
            $message = 'partner_commissions: '.count($refused).' double commission group(s) have a newer row in an open payout — finish or reject that payout first (finance), then run the migration again: '.implode(', ', $refused);
            Log::error($message);

            throw new RuntimeException($message);
        }

        Schema::table('partner_commissions', function (Blueprint $table): void {
            $table->string('duplicate_of_id', 40)->nullable()->index();
            $table->string('duplicate_invoice_id', 40)->nullable()->index();
            $table->string('cancels_id', 40)->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX partner_commissions_cancels_unique ON partner_commissions (cancels_id) WHERE cancels_id IS NOT NULL');

        foreach ($aside as $row) {
            DB::table('partner_commissions')->where('id', $row['id'])->update([
                'duplicate_of_id' => $row['of'], 'duplicate_invoice_id' => $row['invoice'], 'invoice_id' => null,
                'state' => $row['state'] === 'payable' ? self::DUPLICATE : $row['state'], 'updated_at' => now(),
            ]);
        }
        if ($aside !== []) {
            $note = 'partner_commissions: '.count($aside).' newer row(s) of a double commission set aside (I-R6) — finance cancels each through the bus (partner.commission.cancel_duplicate): '.implode(', ', array_column($aside, 'id'));
            Log::warning($note, ['rows' => $aside]);
            fwrite(STDERR, $note.PHP_EOL);
        }
    }

    public function down(): void
    {
        DB::table('partner_commissions')->whereNotNull('duplicate_invoice_id')->orderBy('id')->select('id', 'duplicate_invoice_id', 'state')->chunk(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('partner_commissions')->where('id', $row->id)->update(['invoice_id' => $row->duplicate_invoice_id, 'state' => $row->state === self::DUPLICATE ? 'payable' : $row->state]);
            }
        });
        DB::statement('DROP INDEX IF EXISTS partner_commissions_cancels_unique');
        Schema::table('partner_commissions', function (Blueprint $table): void {
            $table->dropIndex(['duplicate_of_id']);
            $table->dropIndex(['duplicate_invoice_id']);
            $table->dropColumn(['duplicate_of_id', 'duplicate_invoice_id', 'cancels_id']);
        });
    }

    /**
     * The newer rows of every group 000950 would refuse, and the groups that cannot be set aside.
     *
     * @return array{0: list<array{id: string, of: string, invoice: string, state: string}>, 1: list<string>}
     */
    private function classify(): array
    {
        $fragments = Schema::hasColumn('partner_commissions', 'fragment'); // 000950 already ran: its fragments are not doubles
        $base = fn () => DB::table('partner_commissions')->whereNotNull('invoice_id')->when($fragments, fn ($q) => $q->where('fragment', false));
        $groups = $base()->select('invoice_id', 'kind')->groupBy('invoice_id', 'kind')->havingRaw('count(*) > 1')->get();
        $aside = [];
        $refused = [];
        foreach ($groups as $group) {
            $rows = $base()->where('invoice_id', $group->invoice_id)->where('kind', $group->kind)->orderBy('created_at')->orderBy('id')
                ->get(['id', 'partner_id', 'base_minor', 'rate_pct', 'currency', 'amount_minor', 'payout_id', 'state']);
            if ($this->looksLikeRemainders((string) $group->kind, $rows)) {
                continue; // 000950 marks them fragments
            }
            $oldest = $rows->first();
            $newer = $rows->slice(1);
            $open = $newer->firstWhere('state', 'allocated');
            if ($open !== null) {
                $refused[] = $group->invoice_id.'/'.$group->kind.' (payout '.$open->payout_id.')';

                continue;
            }
            foreach ($newer as $row) {
                $aside[] = ['id' => (string) $row->id, 'of' => (string) $oldest->id, 'invoice' => (string) $group->invoice_id, 'state' => (string) $row->state];
            }
        }

        return [$aside, $refused];
    }

    /**
     * 000950's test, unchanged: the same partner, base, rate and currency, positive amounts, every row but one linked to a payout,
     * together not more than base × rate (one haler per row of rounding), and never a reversal.
     *
     * @param  Collection<int, stdClass>  $rows
     */
    private function looksLikeRemainders(string $kind, $rows): bool
    {
        $first = $rows->first();
        $same = $rows->every(fn ($r) => $r->partner_id === $first->partner_id && (int) $r->base_minor === (int) $first->base_minor && (int) $r->rate_pct === (int) $first->rate_pct && $r->currency === $first->currency);
        $positive = $rows->every(fn ($r) => (int) $r->amount_minor > 0);
        $linked = $rows->filter(fn ($r) => $r->payout_id !== null)->count() >= $rows->count() - 1;
        $earned = intdiv(2 * (int) $first->base_minor * (int) $first->rate_pct + 100, 200);
        $fits = (int) $rows->sum('amount_minor') <= $earned + $rows->count();

        return $kind !== 'reversal' && $same && $positive && $linked && $fits;
    }
};
