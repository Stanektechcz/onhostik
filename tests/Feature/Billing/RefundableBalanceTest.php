<?php

declare(strict_types=1);

use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\LedgerTransaction;
use Onhost\Domain\WalletLedger\RefundableCredit;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Currency;
use Onhost\Platform\Money\Money;

/*
 * The spend order of the account credit (owner decision G-R3, 2026-10-05): money the customer paid in is spent FIRST, credit
 * that came back from a correction (`returnToCredit`), bonus credit and staff credit after it; what neither covers is a debt
 * that later credit pays first. `RefundableCredit::replay` is that rule and stays as it is; `RefundableCredit::of` replays the
 * ledger of one wallet and answers how much purchased credit is still unspent.
 *
 * Since G-R4 that amount is never paid out — the wallet has no cash refund (G4NoCashRefundTest). These cases pin the order
 * itself (TASK-0099), which is what the open statutory exception in ROZHODNUTI.md G-R4 would be capped by.
 */

/** Money spent from the credit on a service (a direct charge, revenue + VAT as a renewal books it). */
function refundableSpend(WalletService $wallets, object $org, int $czk, string $key, object $ctx): void
{
    $wallets->charge($org, Money::decimal((string) $czk, 'CZK'), 'web', $key, $ctx, 'subscription', $key, Money::zero('CZK'));
}

/** Credit that comes back from a correction (the unused rest of a cancelled service): never purchased credit. */
function refundableReturn(WalletService $wallets, object $org, int $czk, string $key, object $ctx): void
{
    $gross = Money::decimal((string) $czk, 'CZK');
    $wallets->returnToCredit($org, $gross, WalletService::revenueReturn($gross, Money::zero('CZK')), $key, $ctx, 'service', $key);
}

function refundablePurchased(object $org, Currency $currency = Currency::CZK): int
{
    return RefundableCredit::of($org->id, $currency);
}

it('spends purchased credit first: 1000 bought, 800 spent, 800 returned leaves 200 purchased, not 1000', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb-top', $ctx, 'pi_rb', bankProvider: 'comgate');
    refundableSpend($wallets, $org, 800, 'rb-spend', $ctx);
    refundableReturn($wallets, $org, 800, 'rb-return', $ctx);

    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(100000)
        ->and(refundablePurchased($org))->toBe(20000)
        ->and(app(LedgerService::class)->verifyInvariant()['balanced'])->toBeTrue();
});

it('spends the purchased credit first even when returned credit was there before it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    refundableReturn($wallets, $org, 800, 'rb2-return', $ctx);
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb2-top', $ctx, bankProvider: 'comgate');
    expect(refundablePurchased($org))->toBe(100000); // nothing spent yet: all that was bought

    refundableSpend($wallets, $org, 800, 'rb2-spend', $ctx);

    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(100000)
        ->and(refundablePurchased($org))->toBe(20000);
});

it('takes what purchased credit does not cover from the other credit, and a later top-up pays a debt first', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb3-top', $ctx, bankProvider: 'comgate');
    refundableReturn($wallets, $org, 500, 'rb3-return', $ctx);
    refundableSpend($wallets, $org, 1200, 'rb3-spend', $ctx);
    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(30000)
        ->and(refundablePurchased($org))->toBe(0); // the 300 left is returned credit

    // metered usage may take the wallet below zero; the next top-up covers that debt before anything counts as purchased
    $wallets->charge($org, Money::decimal('500', 'CZK'), 'web', 'rb3-usage', $ctx, allowNegative: true);
    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(-20000);
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb3-top-2', $ctx, bankProvider: 'comgate');
    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(80000)
        ->and(refundablePurchased($org))->toBe(80000);
});

it('never counts bonus credit or a staff credit as purchased', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $wallets->topup($org, Money::decimal('100', 'CZK'), 'card', 'rb5-top', $ctx, bankProvider: 'comgate');
    $wallets->topup($org, Money::decimal('500', 'CZK'), 'promo', 'rb5-bonus', $ctx, promo: true);
    $wallets->adjust($org, Money::decimal('300', 'CZK'), 'goodwill', 'rb5-goodwill', $ctx);
    // an order of 350: the main wallet (100 bought + 300 goodwill) covers it; the bonus wallet stays untouched
    $hold = $wallets->hold($org, Money::decimal('350', 'CZK'), 'order', 'rb5-hold', $ctx, 'order', 'ord_rb5');
    $wallets->capture($hold, 'web', $ctx);
    expect(refundablePurchased($org))->toBe(0); // the 100 bought went into the order first

    // an order bigger than the main wallet pulls bonus credit in: it is spent, never purchased
    $wallets->topup($org, Money::decimal('200', 'CZK'), 'card', 'rb5-top-2', $ctx, bankProvider: 'comgate');
    $big = $wallets->hold($org, Money::decimal('400', 'CZK'), 'order', 'rb5-hold-2', $ctx, 'order', 'ord_rb5b');
    $wallets->capture($big, 'web', $ctx);
    expect($wallets->balances($org, 'CZK')['promo']->minor)->toBe(50000 - 15000)
        ->and(refundablePurchased($org))->toBe(0)
        ->and(app(LedgerService::class)->verifyInvariant()['balanced'])->toBeTrue();
});

it('lets a staff debit take purchased credit like any spend, and counts a top-up delivered twice once', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb6-top', $ctx, 'pi_rb6', bankProvider: 'comgate');
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb6-top', $ctx, 'pi_rb6', bankProvider: 'comgate'); // the gateway's callback twice
    expect(refundablePurchased($org))->toBe(100000);

    refundableSpend($wallets, $org, 500, 'rb6-spend', $ctx);
    $wallets->adjust($org, Money::decimal('-50', 'CZK'), 'correction', 'rb6-debit', $ctx);
    expect(refundablePurchased($org))->toBe(45000)
        ->and($wallets->balances($org, 'CZK')['available']->minor)->toBe(45000);
});

it('keeps the purchased credit of each currency apart', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb7-czk', $ctx, bankProvider: 'comgate');
    $wallets->topup($org, Money::decimal('40', 'EUR'), 'card', 'rb7-eur', $ctx, bankProvider: 'comgate');
    $wallets->charge($org, Money::decimal('30', 'EUR'), 'web', 'rb7-eur-spend', $ctx);

    expect(refundablePurchased($org))->toBe(100000)
        ->and(refundablePurchased($org, Currency::EUR))->toBe(1000);
});

it('knows purchased credit by its ledger entry: a reversed top-up takes back its own money, not the other top-up\'s (H2)', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $ledger = app(LedgerService::class);
    $reversedTopup = $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb9-top-a', $ctx, bankProvider: 'comgate');
    $wallets->topup($org, Money::decimal('500', 'CZK'), 'card', 'rb9-top-b', $ctx, bankProvider: 'comgate');

    // the bank takes the first payment back: the ledger reverses its entry, and the top-up row may say so too — the answer must not depend on that row
    $ledger->reverse(LedgerTransaction::query()->findOrFail($reversedTopup->transaction_id), 'rb9-reverse-a', 'payment returned by the bank');
    $reversedTopup->forceFill(['state' => 'reversed'])->save();
    $wallets->refreshCaches($wallets->wallet($org, 'CZK'));

    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(50000)
        ->and(refundablePurchased($org))->toBe(50000);
});

it('treats a reversed spend as credit that is not purchased', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $ledger = app(LedgerService::class);
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb10-top', $ctx, bankProvider: 'comgate');
    $hold = $wallets->hold($org, Money::decimal('600', 'CZK'), 'order', 'rb10-hold', $ctx, 'order', 'ord_rb10');
    $captured = $wallets->capture($hold, 'web', $ctx);
    $ledger->reverse(LedgerTransaction::query()->findOrFail($captured->captured_transaction_id), 'rb10-reverse', 'charge taken back');
    $wallets->refreshCaches($wallets->wallet($org, 'CZK'));

    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(100000)
        ->and(refundablePurchased($org))->toBe(40000);
});

it('replays the rule as decided (G-R3): purchased first, then other credit, then debt paid by later credit', function () {
    expect(RefundableCredit::replay([
        ['credit' => 50000, 'debit' => 0, 'purchased' => false], // returned credit first in time
        ['credit' => 100000, 'debit' => 0, 'purchased' => true],  // then a top-up
        ['credit' => 0, 'debit' => 120000, 'purchased' => false], // a spend takes the 1000 bought, then 200 of the returned
    ]))->toBe(0)
        ->and(RefundableCredit::replay([
            ['credit' => 0, 'debit' => 30000, 'purchased' => false],  // a debt
            ['credit' => 100000, 'debit' => 0, 'purchased' => true],  // the top-up pays it first
        ]))->toBe(70000);
});
