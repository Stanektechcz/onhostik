<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Payments\Models\BankStatementLine;
use Onhost\Domain\Payments\Models\PaymentIntent;

/*
 * F2 (navrhy 2026-10-15): checkout -> bank transfer -> settlement end to end. A guest orders a web hosting with payment by bank
 * transfer, the order waits for the money, and `onhost:bank:sync` downloads the incoming transfer from the Fio API (faked with
 * Http::fake, nothing reaches the network), matches it by variable symbol and amount and settles intent, order and invoice. A
 * second sync pays nothing twice; a transfer of the wrong amount leaves the order waiting.
 */

const CPF_FIO_TOKEN = 'example-fio-token-cpf'; // a test value, never a credential

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Notification::fake();
    Mail::fake();
    Http::preventStrayRequests();
    config(['onhost.payments.bank.fio_token' => CPF_FIO_TOKEN]);
});

/** Fio `last` endpoint answering the transactions in `$bank['tx']`, counting downloads. */
function cpfFioFake(array &$bank): void
{
    $bank += ['tx' => [], 'calls' => 0];
    Http::fake(function (HttpRequest $request) use (&$bank) {
        if (str_starts_with($request->url(), 'https://fioapi.fio.cz/v1/rest/last/'.CPF_FIO_TOKEN.'/transactions.json')) {
            $bank['calls']++;

            return Http::response(['accountStatement' => [
                'info' => ['accountId' => '2000145399', 'bankId' => '2010', 'currency' => 'CZK'],
                'transactionList' => ['transaction' => $bank['tx']],
            ]]);
        }

        return Http::response(['error' => 'unexpected call'], 500);
    });
}

/** One Fio transaction row (columns as the Fio JSON names them). */
function cpfFioTx(string $id, float $amount, string $vs): array
{
    return [
        'column22' => ['value' => $id], 'column0' => ['value' => now()->toDateString()], 'column1' => ['value' => $amount],
        'column14' => ['value' => 'CZK'], 'column5' => ['value' => $vs], 'column10' => ['value' => 'Petra Platná'],
        'column2' => ['value' => '123456789'], 'column3' => ['value' => '0800'], 'column16' => ['value' => 'objednavka'],
    ];
}

function cpfGuestBankOrder(object $test, string $email, string $key)
{
    return $test->withHeaders(['Referer' => 'http://localhost', 'Accept-Language' => 'cs', 'Idempotency-Key' => $key])->postJson('/v1/checkout/guest', [
        'customer' => ['email' => $email, 'name' => 'Petra Platná', 'country' => 'CZ', 'street' => 'Vodičkova 7', 'city' => 'Praha', 'postal_code' => '110 00'],
        'items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'start']],
        'commit_months' => 1, 'currency' => 'CZK',
        'consents' => ['terms' => ['version' => '2026-09'], 'privacy' => [], 'dpa' => [], 'withdrawal_waiver' => [], 'registrar_terms' => ['person' => 'Petra Platná'], 'registry_terms_cz' => ['person' => 'Petra Platná']],
        'payment' => ['mode' => 'bank'], 'terms' => true,
    ]);
}

/** @return array{0:Order,1:PaymentIntent,2:string} */
function cpfPlacedBankOrder(object $test, string $email, string $key): array
{
    $placed = cpfGuestBankOrder($test, $email, $key)->assertCreated();
    expect($placed->json('state'))->toBe(OrderStateMachine::PENDING_PAYMENT);
    $order = Order::query()->findOrFail($placed->json('order_id'));
    $intent = PaymentIntent::query()->findOrFail($order->payment_intent_id);
    $vs = (string) ($intent->raw['instructions']['variable_symbol'] ?? '');
    expect($intent->provider)->toBe('bank')->and($vs)->not->toBe('');

    return [$order, $intent, $vs];
}

it('settles a guest bank-transfer order when the Fio sync brings the matching transfer, and pays nothing twice', function () {
    $bank = [];
    cpfFioFake($bank);
    [$order, $intent, $vs] = cpfPlacedBankOrder($this, 'petra@checkout.cz', 'cpf-bank-1');

    // nothing arrived yet: the order keeps waiting
    $this->artisan('onhost:bank:sync')->assertExitCode(0);
    expect($bank['calls'])->toBe(1)
        ->and($order->refresh()->state)->toBe(OrderStateMachine::PENDING_PAYMENT)
        ->and($intent->refresh()->isSucceeded())->toBeFalse();

    // the transfer arrives with the order's variable symbol and exact amount
    $bank['tx'] = [cpfFioTx('90000001', $intent->amount_minor / 100, $vs)];
    $this->artisan('onhost:bank:sync')->assertExitCode(0);

    expect($intent->refresh()->state)->toBe('SUCCEEDED')
        ->and($order->refresh()->state)->toBe(OrderStateMachine::PAID)
        ->and($order->invoice_id)->not->toBeNull()
        ->and(Invoice::query()->findOrFail($order->invoice_id)->state)->toBe(Invoice::PAID)
        ->and(BankStatementLine::query()->where('external_id', 'fio:90000001')->value('state'))->toBe('matched');

    // the same line downloaded again (overlapping period) records and settles nothing new
    $this->artisan('onhost:bank:sync')->assertExitCode(0);
    expect($bank['calls'])->toBe(3)
        ->and(BankStatementLine::query()->count())->toBe(1)
        ->and($order->refresh()->state)->toBe(OrderStateMachine::PAID);
});

it('leaves the order waiting when the transfer does not match the amount', function () {
    $bank = [];
    cpfFioFake($bank);
    [$order, $intent, $vs] = cpfPlacedBankOrder($this, 'malo@checkout.cz', 'cpf-bank-2');

    $bank['tx'] = [cpfFioTx('90000002', ($intent->amount_minor - 100) / 100, $vs)];
    $this->artisan('onhost:bank:sync')->assertExitCode(0);

    expect($intent->refresh()->isSucceeded())->toBeFalse()
        ->and($order->refresh()->state)->toBe(OrderStateMachine::PENDING_PAYMENT)
        ->and(BankStatementLine::query()->where('external_id', 'fio:90000002')->value('state'))->toBe('unmatched');
});
