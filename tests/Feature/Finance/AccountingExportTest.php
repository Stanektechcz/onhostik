<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Support\Carbon;
use Onhost\Domain\Invoicing\AccountingExport;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentRefund;
use Onhost\Platform\Audit\AuditEvent;

/*
 * R2 (phase R, owner decision P-R36): the month's documents and payments as CSV for the accountant — finance only, the accounting
 * month at the seller's seat, the buyer as the document states it, Czech spreadsheet conventions, never a formula a spreadsheet
 * would run, and the read recorded in the staff trail.
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-15 12:00:00', 'UTC')); // first (TASK-0192)
    $this->seed(LegalEntitySeeder::class);
});

function aceDocument(Organization $org, string $number, string $type, string $issuedAtUtc, int $total, array $extra = []): Invoice
{
    return Invoice::query()->create(array_merge([
        'legal_entity' => 'onhost-cz', 'series' => substr($number, 0, 2), 'type' => $type, 'number' => $number, 'organization_id' => $org->id, 'currency' => 'CZK',
        'state' => Invoice::PAID, 'subtotal_minor' => $total, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => $total, 'paid_minor' => $total,
        'issued_at' => Carbon::parse($issuedAtUtc, 'UTC'), 'due_at' => Carbon::parse($issuedAtUtc, 'UTC')->addDays(14), 'paid_at' => Carbon::parse($issuedAtUtc, 'UTC'),
        'buyer' => ['name' => $org->name, 'ico' => $org->ico, 'dic' => $org->dic, 'country' => 'CZ'], 'seller' => ['name' => 'ONhost'],
    ], $extra));
}

/** @return list<list<string>> rows of the CSV without the BOM and the header */
function aceRows(string $csv): array
{
    expect(str_starts_with($csv, "\u{FEFF}"))->toBeTrue();
    $lines = array_values(array_filter(explode("\r\n", substr($csv, 3)), fn (string $l) => $l !== ''));

    return array_map(fn (string $l) => str_getcsv($l, ';', '"', ''), array_slice($lines, 1));
}

it('exports the month\'s documents at the seller\'s seat, with the buyer the document states and no formula', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Skladomat s.r.o.', 'ico' => '12345678']);
    aceDocument($org, 'FV-2026-0001', 'invoice', '2026-10-01 08:00:00', 10769, ['payment_reference' => '2026000001']);
    aceDocument($org, 'FV-2026-0002', 'invoice', '2026-10-31 22:30:00', 25000); // 23:30 in Prague: still October
    aceDocument($org, 'FV-2026-0003', 'invoice', '2026-10-31 23:30:00', 9900); // 00:30 on 1 November in Prague: November
    aceDocument($org, 'FV-2026-0000', 'invoice', '2026-09-30 21:59:00', 100); // 23:59 on 30 September in Prague: September
    $draft = aceDocument($org, 'FV-DRAFT', 'invoice', '2026-10-10 08:00:00', 500, ['state' => Invoice::DRAFT]);
    $credit = aceDocument($org, 'DN-2026-0001', 'credit_note', '2026-10-20 09:00:00', -5000, ['corrects_invoice_id' => Invoice::query()->where('number', 'FV-2026-0001')->value('id')]);
    $org->forceFill(['name' => '=HYPERLINK("http://evil.example","x")'])->save(); // renamed later: the export keeps the name on the document
    aceDocument($org, 'FV-2026-0004', 'receipt', '2026-10-25 10:00:00', 1234);

    $this->actingAs($this->staff('billing_operator'), 'sanctum');
    $response = $this->get('/v1/staff/finance/exports/documents?month=2026-10')->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertHeader('Content-Disposition', 'attachment; filename="doklady-2026-10.csv"');
    $csv = (string) $response->getContent();
    expect(explode("\r\n", substr($csv, 3))[0])->toStartWith('Číslo dokladu;Druh;Stav;Vystaveno;DUZP;Splatnost');
    $rows = collect(aceRows($csv))->keyBy(0);

    expect($rows->keys()->all())->toBe(['FV-2026-0001', 'DN-2026-0001', 'FV-2026-0004', 'FV-2026-0002'])
        ->and($rows['FV-2026-0001'][3])->toBe('01.10.2026')->and($rows['FV-2026-0001'][7])->toBe('Skladomat s.r.o.')->and($rows['FV-2026-0001'][8])->toBe('12345678')
        ->and($rows['FV-2026-0001'][15])->toBe('107,69')->and($rows['FV-2026-0001'][14])->toBe('0,00')->and($rows['FV-2026-0001'][17])->toBe('2026000001')
        ->and($rows['FV-2026-0002'][3])->toBe('31.10.2026')
        ->and($rows['DN-2026-0001'][15])->toBe('-50,00')->and($rows['DN-2026-0001'][18])->toBe('FV-2026-0001')
        ->and($rows['FV-2026-0004'][7])->toBe("'".'=HYPERLINK("http://evil.example","x")') // the spreadsheet shows it, never runs it
        ->and($csv)->not->toContain('FV-DRAFT')->not->toContain($draft->id)->and($credit->exists)->toBeTrue();

    // the read is in the staff trail
    expect(AuditEvent::query()->where('action', 'staff.read.accounting_export')->count())->toBe(1);
});

it('exports the money that came in and the refunds that went out in the month, with the document they belong to', function () {
    [, $org] = $this->customerWithOrganization([], ['name' => 'Skladomat s.r.o.', 'ico' => '12345678']);
    $invoice = aceDocument($org, 'FV-2026-0101', 'invoice', '2026-10-02 08:00:00', 10769, ['payment_reference' => '2026000101']);
    $note = aceDocument($org, 'DN-2026-0101', 'credit_note', '2026-10-21 08:00:00', -2000);
    $paid = fn (string $id, string $provider, int $minor, string $at, array $extra = []) => tap(PaymentIntent::query()->create(array_merge([
        'organization_id' => $org->id, 'provider' => $provider, 'purpose' => 'invoice', 'reference_type' => 'invoice', 'reference_id' => $invoice->id,
        'amount_minor' => $minor, 'currency' => 'CZK', 'state' => 'SUCCEEDED', 'method' => $provider === 'bank' ? 'transfer' : 'card', 'idempotency_key' => 'ace-'.$id,
    ], $extra)), fn (PaymentIntent $p) => $p->forceFill(['paid_at' => Carbon::parse($at, 'UTC')])->save());
    $card = $paid('card', 'comgate', 10769, '2026-10-02 09:00:00');
    $paid('topup', 'bank', 50000, '2026-10-30 10:00:00', ['purpose' => 'topup', 'reference_type' => 'wallet', 'reference_id' => $org->id]);
    $paid('late', 'bank', 7000, '2026-11-02 10:00:00', ['purpose' => 'topup', 'reference_type' => 'wallet', 'reference_id' => $org->id]); // November
    PaymentIntent::query()->create(['organization_id' => $org->id, 'provider' => 'bank', 'purpose' => 'topup', 'reference_type' => 'wallet', 'reference_id' => $org->id,
        'amount_minor' => 9999, 'currency' => 'CZK', 'state' => 'PENDING_CUSTOMER', 'idempotency_key' => 'ace-unpaid']); // asked for, never paid
    PaymentRefund::query()->create(['payment_intent_id' => $card->id, 'amount_minor' => 2000, 'currency' => 'CZK', 'state' => 'succeeded', 'idempotency_key' => 'ace-refund',
        'credit_note_id' => $note->id, 'confirmed_at' => Carbon::parse('2026-10-22 09:00:00', 'UTC')]);
    PaymentRefund::query()->create(['payment_intent_id' => $card->id, 'amount_minor' => 300, 'currency' => 'CZK', 'state' => 'pending', 'idempotency_key' => 'ace-refund-pending']);

    $this->actingAs($this->staff('billing_finance_admin'), 'sanctum');
    $rows = aceRows((string) $this->get('/v1/staff/finance/exports/payments?month=2026-10')->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="platby-2026-10.csv"')->getContent());

    expect(array_map(fn (array $r) => [$r[0], $r[1], $r[2], $r[5], $r[9]], $rows))->toBe([
        ['02.10.2026', 'příjem', 'comgate', '107,69', 'FV-2026-0101'],
        ['22.10.2026', 'vrácení', 'comgate', '-20,00', 'DN-2026-0101'],
        ['30.10.2026', 'příjem', 'bank', '500,00', ''],
    ])->and($rows[0][7])->toBe('Skladomat s.r.o.')->and($rows[0][10])->toBe('2026000101');
});

it('refuses whoever is not finance, a month that is not one or lies ahead, and an unknown kind', function () {
    $this->actingAs($this->staff('support_l1'), 'sanctum');
    $this->getJson('/v1/staff/finance/exports/documents?month=2026-10')->assertForbidden();
    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum');
    $this->getJson('/v1/staff/finance/exports/documents?month=2026-10')->assertForbidden();

    $this->actingAs($this->staff('billing_operator'), 'sanctum');
    $this->getJson('/v1/staff/finance/exports/documents?month=2026-13')->assertStatus(422)->assertJsonPath('error', 'export_month_invalid');
    $this->getJson('/v1/staff/finance/exports/documents?month=2026-12')->assertStatus(422)->assertJsonPath('error', 'export_month_invalid');
    $this->getJson('/v1/staff/finance/exports/documents')->assertStatus(422);
    $this->getJson('/v1/staff/finance/exports/everything?month=2026-10')->assertNotFound();
    expect($this->get('/v1/staff/finance/exports/payments?month=2026-11')->assertOk()->getContent())->toStartWith("\u{FEFF}Datum;Pohyb;"); // the running month is fine
});

it('never turns a cell into a formula, and quotes what has to be quoted', function () {
    expect(AccountingExport::cell('=1+1'))->toBe("'=1+1")->and(AccountingExport::cell('+420'))->toBe("'+420")->and(AccountingExport::cell('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(AccountingExport::cell('-50,00'))->toBe('-50,00')->and(AccountingExport::cell('-cmd'))->toBe("'-cmd")
        ->and(AccountingExport::cell('a;b'))->toBe('"a;b"')->and(AccountingExport::cell('say "hi"'))->toBe('"say ""hi"""')->and(AccountingExport::cell("x\ny"))->toBe("\"x\ny\"");
});
