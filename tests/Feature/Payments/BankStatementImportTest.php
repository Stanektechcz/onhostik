<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Payments\Models\BankStatementLine;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentStateMachineStates as S;
use Onhost\Domain\Payments\Statements\BankStatementFile;
use Onhost\Platform\Audit\AuditEvent;

/*
 * J-R7 option B (package M3): finance uploads the statement file downloaded from Česká spořitelna's internet banking (camt.053 XML
 * or ABO/GPC) in the staff console; every credit line is recorded once (`external_id` cs:…) and matched by variable symbol and
 * amount like a Fio line, everything else waits in the manual list. Nothing is written to the bank. The files here are synthetic,
 * built from the published formats (no real statement was available, J-R7 input 5).
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

/** @return array{0:string,1:string} variable symbol + intent id of a bank top-up */
function bsiTopUp(object $test, object $owner, string $key, int $amount): array
{
    $test->actingAs($owner, 'sanctum');
    $response = $test->postJson('/v1/payments/init', ['amount' => $amount, 'currency' => 'CZK', 'provider' => 'bank'], ['Idempotency-Key' => $key])->assertCreated();

    return [(string) $response->json('instructions.variable_symbol'), (string) $response->json('payment_intent_id')];
}

/** One GPC transaction line (075), 128 bytes, fields at the positions of the format. */
function bsiGpcLine(string $doc, int $hellers, string $code, string $vs, string $name, string $date = '120926', string $counter = '0000001234567890'): string
{
    $name = substr(str_pad((string) iconv('UTF-8', 'CP1250', $name), 20), 0, 20);

    return '075'.'0000002000145399'.$counter.str_pad($doc, 13, '0', STR_PAD_LEFT).str_pad((string) $hellers, 12, '0', STR_PAD_LEFT).$code
        .str_pad($vs, 10, '0', STR_PAD_LEFT).'0008000308'.'0000000000'.$date.$name.'0'.'0203'.$date;
}

function bsiGpc(array $lines): string
{
    $header = str_pad('074'.'0000002000145399'.'ONHOST S.R.O.       '.'110926', 128, '0');

    return implode("\r\n", array_merge([$header], $lines))."\r\n";
}

/** @param list<string> $entries */
function bsiCamt(array $entries): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
        .'<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02"><BkToCstmrStmt><GrpHdr><MsgId>M1</MsgId><CreDtTm>2026-09-12T08:00:00</CreDtTm></GrpHdr>'
        .'<Stmt><Id>S1</Id><Acct><Id><IBAN>CZ6508000000002000145399</IBAN></Id></Acct>'.implode('', $entries).'</Stmt></BkToCstmrStmt></Document>';
}

function bsiEntry(string $ref, string $amount, string $direction, string $status, string $details): string
{
    return "<Ntry><Amt Ccy=\"CZK\">{$amount}</Amt><CdtDbtInd>{$direction}</CdtDbtInd><Sts>{$status}</Sts><BookgDt><Dt>2026-09-12</Dt></BookgDt>"
        ."<AcctSvcrRef>{$ref}</AcctSvcrRef><NtryDtls>{$details}</NtryDtls></Ntry>";
}

function bsiUpload(object $test, string $content, string $key, string $name = 'vypis.gpc'): TestResponse
{
    return $test->post('/v1/staff/payments/bank/statements', ['file' => UploadedFile::fake()->createWithContent($name, $content)], ['Idempotency-Key' => $key, 'Accept' => 'application/json']);
}

it('imports a GPC statement: credits are recorded once and matched, debits skipped, a second upload adds nothing', function () {
    [$owner] = $this->customerWithOrganization();
    [$vs, $intentId] = bsiTopUp($this, $owner, 'bsi-topup-1', 1500);
    $file = bsiGpc([
        bsiGpcLine('7001', 150000, '2', $vs, 'Jan Novák'),
        '078'.str_pad('Dobiti kreditu', 125),
        bsiGpcLine('7002', 35000, '1', '', 'Dodavatel s.r.o.'),
        bsiGpcLine('7003', 9900, '2', '77777777', 'Neznámý plátce'),
        bsiGpcLine('7004', 9900, '5', '77777777', 'Storno'),
    ]);
    $this->actingAs($this->staff('billing_operator'), 'sanctum');

    $first = bsiUpload($this, $file, 'bsi-gpc-1')->assertOk();
    expect($first->json('format'))->toBe('gpc')->and($first->json('lines'))->toBe(2)->and($first->json('recorded'))->toBe(2)
        ->and($first->json('matched'))->toBe(1)->and($first->json('unmatched'))->toBe(1)->and($first->json('skipped'))->toBe(2)->and($first->json('duplicates'))->toBe(0);
    expect(PaymentIntent::query()->findOrFail($intentId)->state)->toBe(S::SUCCEEDED);
    $line = BankStatementLine::query()->where('variable_symbol', $vs)->firstOrFail();
    expect($line->external_id)->toStartWith('cs:gpc:')->and($line->state)->toBe('matched')->and((int) $line->amount_minor)->toBe(150000)
        ->and($line->counterparty)->toContain('Jan Novák')->and($line->message)->toBe('Dobiti kreditu')->and($line->booked_at->toDateString())->toBe('2026-09-12');

    // the same file again (a re-download, an overlapping period): nothing new, nothing paid twice
    $again = bsiUpload($this, $file, 'bsi-gpc-2')->assertOk();
    expect($again->json('recorded'))->toBe(0)->and($again->json('duplicates'))->toBe(2)->and(BankStatementLine::query()->count())->toBe(2);
    $this->actingAs($owner, 'sanctum');
    $this->getJson('/v1/wallet')->assertOk()->assertJsonPath('data.spendable.minor', 150000);
});

it('imports a camt.053 statement: booked credits only, a batch entry per transaction, the symbol from the reference', function () {
    [$owner] = $this->customerWithOrganization();
    [$vs, $intentId] = bsiTopUp($this, $owner, 'bsi-topup-2', 800);
    $file = bsiCamt([
        bsiEntry('CS-1', '800.00', 'CRDT', 'BOOK', '<TxDtls><Refs><AcctSvcrRef>CS-1-A</AcctSvcrRef><EndToEndId>NOTPROVIDED</EndToEndId></Refs><RltdPties><Dbtr><Nm>Jana Malá</Nm></Dbtr><DbtrAcct><Id><IBAN>CZ5508000000000123456789</IBAN></Id></DbtrAcct></RltdPties><RmtInf><Strd><CdtrRefInf><Ref>'.$vs.'</Ref></CdtrRefInf></Strd></RmtInf></TxDtls>'),
        bsiEntry('CS-2', '350.00', 'DBIT', 'BOOK', '<TxDtls><Refs><AcctSvcrRef>CS-2-A</AcctSvcrRef></Refs></TxDtls>'),
        bsiEntry('CS-3', '120.00', 'CRDT', 'PDNG', '<TxDtls><Refs><AcctSvcrRef>CS-3-A</AcctSvcrRef></Refs></TxDtls>'),
        bsiEntry('CS-4', '300.00', 'CRDT', 'BOOK', '<TxDtls><Refs><AcctSvcrRef>CS-4-A</AcctSvcrRef><EndToEndId>/VS/4242/SS//KS/0308</EndToEndId></Refs><Amt Ccy="CZK">100.00</Amt></TxDtls>'
            .'<TxDtls><Refs><AcctSvcrRef>CS-4-B</AcctSvcrRef></Refs><AmtDtls><TxAmt><Amt Ccy="CZK">200.00</Amt></TxAmt></AmtDtls><RmtInf><Ustrd>platba VS: 0005151</Ustrd></RmtInf></TxDtls>'),
    ]);
    $this->actingAs($this->staff('billing_operator'), 'sanctum');

    $first = bsiUpload($this, $file, 'bsi-camt-1', 'vypis.xml')->assertOk();
    expect($first->json('format'))->toBe('camt.053')->and($first->json('lines'))->toBe(3)->and($first->json('recorded'))->toBe(3)
        ->and($first->json('matched'))->toBe(1)->and($first->json('skipped'))->toBe(2);
    expect(PaymentIntent::query()->findOrFail($intentId)->state)->toBe(S::SUCCEEDED)
        ->and(BankStatementLine::query()->where('external_id', 'cs:camt:CZ6508000000002000145399:CS-1-A')->value('state'))->toBe('matched')
        ->and(BankStatementLine::query()->where('external_id', 'cs:camt:CZ6508000000002000145399:CS-4-A')->value('variable_symbol'))->toBe('4242')
        ->and(BankStatementLine::query()->where('external_id', 'cs:camt:CZ6508000000002000145399:CS-4-B')->value('variable_symbol'))->toBe('5151')
        ->and((int) BankStatementLine::query()->where('external_id', 'cs:camt:CZ6508000000002000145399:CS-4-B')->value('amount_minor'))->toBe(20000);

    $again = bsiUpload($this, $file, 'bsi-camt-2', 'vypis.xml')->assertOk();
    expect($again->json('recorded'))->toBe(0)->and($again->json('duplicates'))->toBe(3)->and(BankStatementLine::query()->count())->toBe(3);
});

it('refuses an XML statement with a DOCTYPE or an entity before parsing it (XXE)', function () {
    $this->actingAs($this->staff('billing_operator'), 'sanctum');
    $xxe = '<?xml version="1.0"?>'."\n".'<!DOCTYPE Document [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
        .'<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02"><BkToCstmrStmt><Stmt><Ntry><Amt Ccy="CZK">1.00</Amt><CdtDbtInd>CRDT</CdtDbtInd><Sts>BOOK</Sts><AcctSvcrRef>&x;</AcctSvcrRef></Ntry></Stmt></BkToCstmrStmt></Document>';
    $laughs = '<?xml version="1.0"?><!DOCTYPE lolz [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;&lol;&lol;">]><Document>&lol2;</Document>';

    bsiUpload($this, $xxe, 'bsi-xxe-1', 'x.xml')->assertUnprocessable()->assertJsonPath('error', 'bank_statement_unsafe');
    bsiUpload($this, $laughs, 'bsi-xxe-2', 'y.xml')->assertUnprocessable()->assertJsonPath('error', 'bank_statement_unsafe');
    // a DOCTYPE hidden from the byte test by another encoding
    $utf16 = '<?xml version="1.0" encoding="UTF-16"?>'.mb_convert_encoding('<!DOCTYPE d [<!ENTITY x SYSTEM "file:///etc/passwd">]><Document>&x;</Document>', 'UTF-16LE', 'UTF-8');
    bsiUpload($this, $utf16, 'bsi-xxe-3', 'z.xml')->assertUnprocessable()->assertJsonPath('error', 'bank_statement_unsafe');
    expect(BankStatementLine::query()->count())->toBe(0);
});

it('refuses a file that is too large, too long, malformed or in an unknown format', function () {
    $this->actingAs($this->staff('billing_operator'), 'sanctum');

    bsiUpload($this, str_repeat('x', BankStatementFile::MAX_BYTES + 10), 'bsi-big', 'big.gpc')->assertUnprocessable();
    $long = bsiGpc(array_map(fn (int $i) => bsiGpcLine((string) (100000 + $i), 100, '1', '', 'Odchozi'), range(1, BankStatementFile::MAX_LINES + 1)));
    bsiUpload($this, $long, 'bsi-long', 'long.gpc')->assertUnprocessable()->assertJsonPath('error', 'bank_statement_too_long');
    bsiUpload($this, "Datum;Castka;VS\n12.09.2026;100;123\n", 'bsi-csv', 'george.csv')->assertUnprocessable()->assertJsonPath('error', 'bank_statement_format');
    bsiUpload($this, bsiGpc(['075'.str_repeat('x', 125)]), 'bsi-bad', 'bad.gpc')->assertUnprocessable()->assertJsonPath('error', 'bank_statement_gpc');
    bsiUpload($this, '<Document><Foo/></Document>', 'bsi-notcamt', 'n.xml')->assertUnprocessable()->assertJsonPath('error', 'bank_statement_format');
    expect(BankStatementLine::query()->count())->toBe(0);
});

it('needs billing.reconcile and keeps the file content out of the audit log', function () {
    [$owner] = $this->customerWithOrganization();
    $file = bsiGpc([bsiGpcLine('8001', 1000, '2', '9999', 'Platce')]);

    $this->actingAs($owner, 'sanctum');
    bsiUpload($this, $file, 'bsi-perm-1')->assertForbidden();
    $this->actingAs($this->staff('network_admin'), 'sanctum');
    bsiUpload($this, $file, 'bsi-perm-2')->assertForbidden();
    expect(BankStatementLine::query()->count())->toBe(0);

    $this->actingAs($this->staff('billing_operator'), 'sanctum');
    bsiUpload($this, $file, 'bsi-perm-3')->assertOk();
    $audit = AuditEvent::query()->where('action', 'payments.bank.statement.import')->latest('created_at')->firstOrFail();
    $json = json_encode($audit->toArray());
    expect($json)->not->toContain('075000000')->toContain(hash('sha256', $file));
});
