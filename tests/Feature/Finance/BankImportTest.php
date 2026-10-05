<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Payments\BankStatementImporter;
use Onhost\Domain\Payments\Models\BankStatementLine;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\Payments\Models\PaymentStateMachineStates as S;
use Onhost\Domain\Payments\Models\ReconciliationItem;

/* Bank transfers have no webhook: statement lines (Fio API or typed in by finance) are matched to the pending intent by symbol + amount. */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

/** @return array{0:string,1:string} variable symbol + intent id of a bank top-up */
function bankTopUpIntent($test, $owner, string $key, int $amount = 5000): array
{
    $test->actingAs($owner, 'sanctum');
    $response = $test->postJson('/v1/payments/init', ['amount' => $amount, 'currency' => 'CZK', 'provider' => 'bank'], ['Idempotency-Key' => $key])->assertCreated();

    return [(string) $response->json('instructions.variable_symbol'), (string) $response->json('payment_intent_id')];
}

it('records a statement line by hand, settles the matching top-up once and credits the wallet', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$vs, $intentId] = bankTopUpIntent($this, $owner, 'bank-topup-1');
    $finance = $this->staff('billing_operator');
    $this->actingAs($finance, 'sanctum');

    $first = $this->postJson('/v1/staff/payments/bank/lines', ['variable_symbol' => $vs, 'amount' => '5000', 'currency' => 'CZK', 'external_id' => 'stmt-1', 'counterparty' => 'Test s.r.o.'], ['Idempotency-Key' => 'bank-line-1'])->assertCreated();
    expect($first->json('result'))->toBe('matched')->and($first->json('payment_intent_id'))->toBe($intentId)->and($first->json('payment_state'))->toBe(S::SUCCEEDED)->and($first->json('purpose'))->toBe('topup');
    expect(PaymentIntent::query()->findOrFail($intentId)->state)->toBe(S::SUCCEEDED)
        ->and(BankStatementLine::query()->where('external_id', 'stmt-1')->value('state'))->toBe('matched')
        ->and(Invoice::query()->where('organization_id', $org->id)->where('type', 'receipt')->exists())->toBeTrue();

    // the same statement line again → nothing is credited twice
    $again = $this->postJson('/v1/staff/payments/bank/lines', ['variable_symbol' => $vs, 'amount' => '5000', 'currency' => 'CZK', 'external_id' => 'stmt-1'], ['Idempotency-Key' => 'bank-line-2'])->assertCreated();
    expect($again->json('result'))->toBe('already_matched')->and(BankStatementLine::query()->count())->toBe(1);

    $this->actingAs($owner, 'sanctum');
    $this->getJson('/v1/wallet')->assertOk()->assertJsonPath('data.spendable.minor', 500000);

    $this->actingAs($finance, 'sanctum');
    $overview = $this->getJson('/v1/staff/payments/bank')->assertOk();
    expect($overview->json('data.pending'))->toBe([])->and($overview->json('data.lines.0.external_id'))->toBe('stmt-1')->and($overview->json('data.fio_configured'))->toBeFalse();
});

it('does not pay on a wrong amount, rejects outgoing amounts and refuses customers', function () {
    [$owner] = $this->customerWithOrganization();
    [$vs, $intentId] = bankTopUpIntent($this, $owner, 'bank-topup-2');
    $this->actingAs($this->staff('billing_operator'), 'sanctum');

    $pending = $this->getJson('/v1/staff/payments/bank')->assertOk();
    expect($pending->json('data.pending.0.variable_symbol'))->toBe($vs)->and($pending->json('data.pending.0.purpose'))->toBe('topup')->and($pending->json('data.pending.0.organization'))->toBe('Test s.r.o.');

    $r = $this->postJson('/v1/staff/payments/bank/lines', ['variable_symbol' => $vs, 'amount' => 4999.5, 'currency' => 'CZK'], ['Idempotency-Key' => 'bank-line-3'])->assertCreated();
    expect($r->json('result'))->toBe('amount_mismatch')->and(PaymentIntent::query()->findOrFail($intentId)->state)->toBe(S::PENDING_CUSTOMER)
        ->and(ReconciliationItem::query()->where('kind', 'bank_amount_mismatch')->exists())->toBeTrue();
    $this->postJson('/v1/staff/payments/bank/lines', ['variable_symbol' => '1', 'amount' => -5, 'currency' => 'CZK'], ['Idempotency-Key' => 'bank-line-4'])->assertUnprocessable();
    $unknown = $this->postJson('/v1/staff/payments/bank/lines', ['variable_symbol' => '4242', 'amount' => 10, 'currency' => 'CZK'], ['Idempotency-Key' => 'bank-line-5'])->assertCreated();
    expect($unknown->json('result'))->toBe('unmatched');

    $this->actingAs($owner, 'sanctum');
    $this->postJson('/v1/staff/payments/bank/lines', ['variable_symbol' => $vs, 'amount' => 5000], ['Idempotency-Key' => 'bank-line-6'])->assertForbidden();
});

it('downloads new transfers from the Fio API, records incoming ones once and settles what they pay', function () {
    config()->set('onhost.payments.bank.fio_token', 'fio-test-token');
    [$owner] = $this->customerWithOrganization();
    [$vs, $intentId] = bankTopUpIntent($this, $owner, 'bank-topup-3', 1200);
    $tx = fn (int $id, float $amount, ?string $vs, string $msg): array => ['column22' => ['value' => $id], 'column0' => ['value' => '2026-09-07+0200'], 'column1' => ['value' => $amount], 'column14' => ['value' => 'CZK'], 'column2' => ['value' => '123456789'], 'column3' => ['value' => '0100'], 'column10' => ['value' => 'Jan Novák'], 'column5' => $vs === null ? null : ['value' => $vs], 'column16' => ['value' => $msg]];
    Http::fake([BankStatementImporter::FIO_API.'/last/fio-test-token/transactions.json' => Http::response(['accountStatement' => ['info' => ['accountId' => '2100000001', 'bankId' => '2010', 'currency' => 'CZK'], 'transactionList' => ['transaction' => [
        $tx(1001, 1200.0, $vs, 'Dobiti kreditu'), $tx(1002, -350.0, null, 'Faktura dodavatele'), $tx(1003, 99.0, '77777777', 'Neznama platba'),
    ]]]], 200)]);

    $this->actingAs($this->staff('billing_finance_admin'), 'sanctum');
    $r = $this->postJson('/v1/staff/payments/bank/sync', [], ['Idempotency-Key' => 'bank-sync-1'])->assertOk();
    expect($r->json('fetched'))->toBe(3)->and($r->json('recorded'))->toBe(2)->and($r->json('matched'))->toBe(1)->and($r->json('skipped'))->toBe(1)->and($r->json('account'))->toBe('2100000001/2010');
    expect(PaymentIntent::query()->findOrFail($intentId)->state)->toBe(S::SUCCEEDED)
        ->and(BankStatementLine::query()->where('external_id', 'fio:1001')->value('counterparty'))->toBe('Jan Novák (123456789/0100)')
        ->and(BankStatementLine::query()->where('external_id', 'fio:1003')->value('state'))->toBe('unmatched');

    // the bank replays the same window: nothing is recorded twice
    $r2 = $this->postJson('/v1/staff/payments/bank/sync', [], ['Idempotency-Key' => 'bank-sync-2'])->assertOk();
    expect($r2->json('recorded'))->toBe(0)->and(BankStatementLine::query()->count())->toBe(2);
    Http::assertSentCount(2);

    // without a token the scheduled command only warns
    config()->set('onhost.payments.bank.fio_token', '');
    $this->artisan('onhost:bank:sync')->assertExitCode(0);
    $this->postJson('/v1/staff/payments/bank/sync', [], ['Idempotency-Key' => 'bank-sync-3'])->assertStatus(422)->assertJsonPath('error', 'bank_sync_unconfigured');
});
