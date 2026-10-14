<?php

declare(strict_types=1);

namespace Onhost\Domain\Invoicing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Platform\Errors\DomainError;

/**
 * R2 (phase R, owner decision P-R36 = SEZNAM UC-4): the month's documents and payments as CSV for the accountant. It works for a
 * VAT payer and a non-payer alike (unlike `onhost:vat:export`, which is the payer's VAT recap): the documents carry the amounts they
 * were issued with, a non-payer's tax column is zero.
 *
 * The month is the accounting month at the seller's seat (AccountingClock, Europe/Prague): 23:30 UTC on 31 October is November.
 * Documents: every issued document (not drafts) by its issue time, with the buyer as the document states it (the snapshot, not
 * today's organization). Payments: money that arrived (payment intents by `paid_at`) and refunds that went out (succeeded, by their
 * confirmation), with the document they paid. Czech spreadsheet conventions: `;`, decimal comma, UTF-8 with BOM; a cell that a
 * spreadsheet would run as a formula (= + - @, tab, CR) starts with an apostrophe — names and references come from customers.
 */
final class AccountingExport
{
    public const KINDS = ['documents', 'payments'];

    private const DOCUMENT_HEADER = ['Číslo dokladu', 'Druh', 'Stav', 'Vystaveno', 'DUZP', 'Splatnost', 'Zaplaceno dne', 'Odběratel', 'IČO', 'DIČ', 'Země', 'Měna',
        'Základ', 'Sleva', 'DPH', 'Celkem', 'Uhrazeno', 'Variabilní symbol', 'Opravuje doklad', 'Objednávka'];

    private const PAYMENT_HEADER = ['Datum', 'Pohyb', 'Kanál', 'Metoda', 'Účel', 'Částka', 'Měna', 'Odběratel', 'IČO', 'Doklad', 'Variabilní symbol', 'ID platby'];

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} [start, end) of the month at the seller's seat, in UTC */
    public static function bounds(string $month): array
    {
        if (preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new DomainError('export_month_invalid', 'month must look like 2026-10.', 422, ['field' => 'month']);
        }
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $month.'-01 00:00:00', AccountingClock::timezone());
        if ($start === null || $start->greaterThan(AccountingClock::now())) {
            throw new DomainError('export_month_invalid', 'month must not lie in the future.', 422, ['field' => 'month']);
        }

        return [$start->utc(), $start->addMonthNoOverflow()->utc()];
    }

    public function csv(string $kind, string $month): string
    {
        [$from, $to] = self::bounds($month);

        return match ($kind) {
            'documents' => self::render(self::DOCUMENT_HEADER, $this->documents($from, $to)),
            'payments' => self::render(self::PAYMENT_HEADER, $this->payments($from, $to)),
            default => throw new DomainError('export_kind_invalid', 'kind must be documents or payments.', 422, ['field' => 'kind']),
        };
    }

    /** @return list<list<string>> */
    private function documents(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];
        $invoices = Invoice::query()->where('state', '!=', Invoice::DRAFT)->whereNotNull('issued_at')
            ->where('issued_at', '>=', $from)->where('issued_at', '<', $to)->orderBy('issued_at')->orderBy('number')->get();
        $corrected = Invoice::query()->whereIn('id', $invoices->pluck('corrects_invoice_id')->filter()->unique()->all())->pluck('number', 'id');
        $orders = DB::table('orders')->whereIn('id', $invoices->pluck('order_id')->filter()->unique()->all())->pluck('number', 'id');
        foreach ($invoices as $invoice) {
            $buyer = (array) ($invoice->buyer ?? []);
            $rows[] = [
                (string) $invoice->number, (string) $invoice->type, (string) $invoice->state,
                self::day($invoice->issued_at), self::day($invoice->supply_date ?? $invoice->issued_at), self::day($invoice->due_at), self::day($invoice->paid_at),
                (string) ($buyer['name'] ?? ''), (string) ($buyer['ico'] ?? ''), (string) ($buyer['dic'] ?? ''), (string) ($buyer['country'] ?? ''), (string) $invoice->currency,
                self::amount((int) $invoice->subtotal_minor), self::amount((int) $invoice->discount_minor), self::amount((int) $invoice->tax_minor), self::amount((int) $invoice->total_minor),
                self::amount((int) $invoice->paid_minor), (string) ($invoice->payment_reference ?? ''),
                (string) ($corrected[$invoice->corrects_invoice_id] ?? ''), (string) ($orders[$invoice->order_id] ?? ''),
            ];
        }

        return $rows;
    }

    /** @return list<list<string>> */
    private function payments(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $in = DB::table('payment_intents')->whereNotNull('paid_at')->where('paid_at', '>=', $from)->where('paid_at', '<', $to)
            ->get(['id', 'organization_id', 'provider', 'method', 'purpose', 'reference_type', 'reference_id', 'amount_minor', 'currency', 'paid_at']);
        $out = DB::table('payment_refunds')->join('payment_intents', 'payment_intents.id', '=', 'payment_refunds.payment_intent_id')
            ->where('payment_refunds.state', 'succeeded')
            ->whereRaw('COALESCE(payment_refunds.confirmed_at, payment_refunds.updated_at) >= ? AND COALESCE(payment_refunds.confirmed_at, payment_refunds.updated_at) < ?', [$from, $to])
            ->get(['payment_refunds.id', 'payment_intents.organization_id', 'payment_intents.provider', 'payment_intents.method', 'payment_refunds.amount_minor', 'payment_refunds.currency',
                'payment_refunds.credit_note_id', DB::raw('COALESCE(payment_refunds.confirmed_at, payment_refunds.updated_at) as moved_at')]);
        $organizations = DB::table('organizations')->whereIn('id', $in->pluck('organization_id')->merge($out->pluck('organization_id'))->filter()->unique()->all())->get(['id', 'name', 'ico'])->keyBy('id');
        $documentIds = $in->where('reference_type', 'invoice')->pluck('reference_id')->merge($out->pluck('credit_note_id'))->filter()->unique()->all();
        $documents = Invoice::query()->whereIn('id', $documentIds)->get(['id', 'number', 'payment_reference'])->keyBy('id');
        $orderInvoices = Invoice::query()->whereIn('order_id', $in->where('reference_type', 'order')->pluck('reference_id')->filter()->unique()->all())
            ->where('type', 'proforma')->get(['order_id', 'number', 'payment_reference'])->keyBy('order_id');

        $rows = [];
        foreach ($in as $p) {
            $document = match ((string) $p->reference_type) {
                'invoice' => $documents->get($p->reference_id),
                'order' => $orderInvoices->get($p->reference_id),
                default => null,
            };
            $org = $organizations->get($p->organization_id);
            $rows[] = ['at' => (string) $p->paid_at, 'row' => [
                self::day($p->paid_at), 'příjem', (string) $p->provider, (string) ($p->method ?? ''), (string) $p->purpose, self::amount((int) $p->amount_minor), (string) $p->currency,
                (string) ($org->name ?? ''), (string) ($org->ico ?? ''), (string) ($document->number ?? ''), (string) ($document->payment_reference ?? ''), (string) $p->id,
            ]];
        }
        foreach ($out as $r) {
            $org = $organizations->get($r->organization_id);
            $note = $r->credit_note_id !== null ? $documents->get($r->credit_note_id) : null;
            $rows[] = ['at' => (string) $r->moved_at, 'row' => [
                self::day($r->moved_at), 'vrácení', (string) $r->provider, (string) ($r->method ?? ''), 'refund', self::amount(-(int) $r->amount_minor), (string) $r->currency,
                (string) ($org->name ?? ''), (string) ($org->ico ?? ''), (string) ($note->number ?? ''), '', (string) $r->id,
            ]];
        }
        usort($rows, fn (array $a, array $b) => [$a['at'], $a['row'][11]] <=> [$b['at'], $b['row'][11]]);

        return array_map(fn (array $r) => $r['row'], $rows);
    }

    /** @param list<string> $header @param list<list<string>> $rows */
    private static function render(array $header, array $rows): string
    {
        $lines = [implode(';', array_map(self::cell(...), $header))];
        foreach ($rows as $row) {
            $lines[] = implode(';', array_map(self::cell(...), $row));
        }

        return "\u{FEFF}".implode("\r\n", $lines)."\r\n";
    }

    /** One CSV cell: never a formula (CSV injection), quoted when it has to be. */
    public static function cell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && preg_match('/^-\d+(,\d+)?$/', $value) !== 1) {
            $value = "'".$value;
        }
        if (strpbrk($value, ";\"\r\n") !== false) {
            $value = '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    private static function amount(int $minor): string
    {
        return ($minor < 0 ? '-' : '').intdiv(abs($minor), 100).','.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function day(mixed $instant): string
    {
        if ($instant === null || $instant === '') {
            return '';
        }

        return CarbonImmutable::parse($instant instanceof \DateTimeInterface ? $instant->format(DATE_ATOM) : (string) $instant, 'UTC')
            ->setTimezone(AccountingClock::timezone())->format('d.m.Y');
    }
}
