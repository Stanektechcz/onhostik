<?php

declare(strict_types=1);

use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\WalletRefund;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

/*
 * What may leave the wallet as money (`WalletService::refundableBalance`, the cap of `refund()`): the purchased credit that
 * is still unspent — never the credit that came back from a correction (`returnToCredit`), bonus credit or a staff credit.
 *
 * The spend order is the platform's one rule for it (the bonus wallet follows it too): money the customer paid in is spent
 * FIRST, credit that cannot be paid out is spent after it. So the purchased part of a wallet is replayed from the ledger in the
 * order the money moved; a refund can never exceed it, nor what is available.
 */

/** Money spent from the credit on a service (a direct charge, revenue + VAT as a renewal books it). */
function refundableSpend(WalletService $wallets, object $org, int $czk, string $key, object $ctx): void
{
    $wallets->charge($org, Money::decimal((string) $czk, 'CZK'), 'web', $key, $ctx, 'subscription', $key, Money::zero('CZK'));
}

/** Credit that comes back from a correction (the unused rest of a cancelled service): non-refundable by design. */
function refundableReturn(WalletService $wallets, object $org, int $czk, string $key, object $ctx): void
{
    $gross = Money::decimal((string) $czk, 'CZK');
    $wallets->returnToCredit($org, $gross, WalletService::revenueReturn($gross, Money::zero('CZK')), $key, $ctx, 'service', $key);
}

it('refunds at most the unspent purchased credit: 1000 bought, 800 spent, 800 returned leaves 200 to pay out, not 1000', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb-top', $ctx, 'pi_rb', bankProvider: 'comgate');
    refundableSpend($wallets, $org, 800, 'rb-spend', $ctx);
    refundableReturn($wallets, $org, 800, 'rb-return', $ctx);

    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(100000)
        ->and($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(20000);
    expect(fn () => $wallets->refund($org, Money::decimal('300', 'CZK'), 'customer request', 'rb-refund-too-much', $ctx, 'source', 'pi_rb'))
        ->toThrow(DomainError::class, 'Refund exceeds the refundable purchased credit');
    expect(WalletRefund::query()->where('organization_id', $org->id)->count())->toBe(0);

    $wallets->refund($org, Money::decimal('200', 'CZK'), 'customer request', 'rb-refund', $ctx, 'source', 'pi_rb');
    expect($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(0)
        ->and($wallets->balances($org, 'CZK')['available']->minor)->toBe(80000) // the returned 800 stays as credit to spend
        ->and(app(LedgerService::class)->verifyInvariant()['balanced'])->toBeTrue();
});

it('spends the purchased credit first even when returned credit was there before it', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    refundableReturn($wallets, $org, 800, 'rb2-return', $ctx);
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb2-top', $ctx, bankProvider: 'comgate');
    expect($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(100000); // nothing spent yet: all that was bought

    refundableSpend($wallets, $org, 800, 'rb2-spend', $ctx);

    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(100000)
        ->and($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(20000);
});

it('takes what purchased credit does not cover from the other credit, and a later top-up pays a debt first', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb3-top', $ctx, bankProvider: 'comgate');
    refundableReturn($wallets, $org, 500, 'rb3-return', $ctx);
    refundableSpend($wallets, $org, 1200, 'rb3-spend', $ctx);
    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(30000)
        ->and($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(0); // the 300 left is returned credit

    // metered usage may take the wallet below zero; the next top-up covers that debt before anything becomes refundable
    $wallets->charge($org, Money::decimal('500', 'CZK'), 'web', 'rb3-usage', $ctx, allowNegative: true);
    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(-20000);
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb3-top-2', $ctx, bankProvider: 'comgate');
    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(80000)
        ->and($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(80000);
});

it('never offers more than is available: money held for an order is not refundable', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb4-top', $ctx, bankProvider: 'comgate');
    $wallets->hold($org, Money::decimal('600', 'CZK'), 'order', 'rb4-hold', $ctx, 'order', 'ord_rb4');

    expect($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(40000);
    expect(fn () => $wallets->refund($org, Money::decimal('500', 'CZK'), 'customer request', 'rb4-refund', $ctx))->toThrow(DomainError::class);
});

it('does not turn bonus credit or a staff credit into money', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $wallets->topup($org, Money::decimal('100', 'CZK'), 'card', 'rb5-top', $ctx, bankProvider: 'comgate');
    $wallets->topup($org, Money::decimal('500', 'CZK'), 'promo', 'rb5-bonus', $ctx, promo: true);
    $wallets->adjust($org, Money::decimal('300', 'CZK'), 'goodwill', 'rb5-goodwill', $ctx);
    // an order of 350: the main wallet (100 bought + 300 goodwill) covers it; the bonus wallet stays untouched
    $hold = $wallets->hold($org, Money::decimal('350', 'CZK'), 'order', 'rb5-hold', $ctx, 'order', 'ord_rb5');
    $wallets->capture($hold, 'web', $ctx);
    expect($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(0); // the 100 bought went into the order first

    // an order bigger than the main wallet pulls bonus credit in: it is spent, never refundable
    $wallets->topup($org, Money::decimal('200', 'CZK'), 'card', 'rb5-top-2', $ctx, bankProvider: 'comgate');
    $big = $wallets->hold($org, Money::decimal('400', 'CZK'), 'order', 'rb5-hold-2', $ctx, 'order', 'ord_rb5b');
    $wallets->capture($big, 'web', $ctx);
    expect($wallets->balances($org, 'CZK')['promo']->minor)->toBe(50000 - 15000)
        ->and($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(0)
        ->and(app(LedgerService::class)->verifyInvariant()['balanced'])->toBeTrue();
});

it('counts a refund once: the same request again is the same refund, and the cap follows what was really paid out', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb6-top', $ctx, 'pi_rb6', bankProvider: 'comgate');
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb6-top', $ctx, 'pi_rb6', bankProvider: 'comgate'); // the gateway's callback twice

    $first = $wallets->refund($org, Money::decimal('400', 'CZK'), 'customer request', 'rb6-refund', $ctx, 'source', 'pi_rb6');
    $again = $wallets->refund($org, Money::decimal('400', 'CZK'), 'customer request', 'rb6-refund', $ctx, 'source', 'pi_rb6');
    expect($again->id)->toBe($first->id)
        ->and(WalletRefund::query()->where('organization_id', $org->id)->count())->toBe(1)
        ->and($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(60000);

    refundableSpend($wallets, $org, 500, 'rb6-spend', $ctx);
    expect($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(10000)
        ->and($wallets->balances($org, 'CZK')['available']->minor)->toBe(10000);

    $wallets->adjust($org, Money::decimal('-50', 'CZK'), 'correction', 'rb6-debit', $ctx); // a staff debit takes purchased credit like any spend
    expect($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(5000);
});

it('keeps the refundable credit of each currency apart', function () {
    [$owner, $org] = $this->customerWithOrganization();
    [$wallets, $ctx] = [app(WalletService::class), $this->contextFor($owner, $org)];
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'card', 'rb7-czk', $ctx, bankProvider: 'comgate');
    $wallets->topup($org, Money::decimal('40', 'EUR'), 'card', 'rb7-eur', $ctx, bankProvider: 'comgate');
    $wallets->charge($org, Money::decimal('30', 'EUR'), 'web', 'rb7-eur-spend', $ctx);

    expect($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(100000)
        ->and($wallets->refundableBalance($org->id, 'EUR')->minor)->toBe(1000);
});
