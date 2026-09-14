<?php

declare(strict_types=1);

use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\Models\Budget;
use Onhost\Domain\WalletLedger\Models\CreditLine;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;

it('tops up, holds, captures and keeps the ledger balanced', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $wallets = app(WalletService::class);
    $ctx = $this->contextFor($owner, $org);

    $wallets->topup($org, Money::decimal('1500', 'CZK'), 'card', 'topup-1', $ctx, 'pi_test', bankProvider: 'comgate');
    $wallets->topup($org, Money::decimal('1500', 'CZK'), 'card', 'topup-1', $ctx, 'pi_test', bankProvider: 'comgate'); // duplicate webhook

    $b = $wallets->balances($org, 'CZK');
    expect($b['posted']->minor)->toBe(150000)->and($b['available']->minor)->toBe(150000);

    $hold = $wallets->hold($org, Money::decimal('1290', 'CZK'), 'order', 'hold-1', $ctx, 'order', 'ord_1');
    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(21000);

    $wallets->capture($hold, 'cloud', $ctx, Money::decimal('1290', 'CZK'), Money::decimal('223.88', 'CZK'));
    $b = $wallets->balances($org, 'CZK');
    expect($b['posted']->minor)->toBe(21000)->and($b['reserved']->minor)->toBe(0);

    $ledger = app(LedgerService::class);
    expect($ledger->verifyInvariant()['balanced'])->toBeTrue()
        ->and($ledger->balance(LedgerService::revenueAccount('cloud', 'CZK'), 'CZK')->minor)->toBe(129000 - 22388)
        ->and($ledger->balance(LedgerService::vatAccount('CZK'), 'CZK')->minor)->toBe(22388)
        ->and($ledger->balance(LedgerService::bankAccount('comgate', 'CZK'), 'CZK')->minor)->toBe(150000);
});

it('refuses a hold beyond the available balance and releases holds', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $wallets = app(WalletService::class);
    $ctx = $this->contextFor($owner, $org);
    $wallets->topup($org, Money::decimal('1000', 'CZK'), 'bank', 'topup-2', $ctx);

    $hold = $wallets->hold($org, Money::decimal('900', 'CZK'), 'order', 'hold-a', $ctx);
    expect(fn () => $wallets->hold($org, Money::decimal('200', 'CZK'), 'order', 'hold-b', $ctx))
        ->toThrow(DomainError::class, 'Insufficient wallet balance');

    $wallets->release($hold, 'provisioning failed', $ctx);
    expect($wallets->balances($org, 'CZK')['available']->minor)->toBe(100000);
    $second = $wallets->hold($org, Money::decimal('200', 'CZK'), 'order', 'hold-b', $ctx);
    expect($second->state)->toBe('active');
});

it('honours the approved B2B credit line and the domain renewal reserve', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $wallets = app(WalletService::class);
    $ctx = $this->contextFor($owner, $org);
    CreditLine::query()->create(['organization_id' => $org->id, 'currency' => 'CZK', 'limit_minor' => 500000, 'state' => 'approved', 'approved_by' => 'usr_fin', 'approved_at' => now()]);
    $hold = $wallets->hold($org, Money::decimal('4000', 'CZK'), 'order', 'hold-cl', $ctx);
    expect($hold->state)->toBe('active');

    $org->forceFill(['settings' => ['domain_reserve' => ['CZK' => 80000]]])->save();
    expect(fn () => $wallets->hold($org, Money::decimal('500', 'CZK'), 'order', 'hold-res', $ctx))->toThrow(DomainError::class);
    $domainHold = $wallets->hold($org, Money::decimal('500', 'CZK'), 'domain_renewal', 'hold-dom', $ctx, priority: 'domain');
    expect($domainHold->priority)->toBe('domain');
});

it('keeps promo credit non-refundable and refunds only purchased credit', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $wallets = app(WalletService::class);
    $ctx = $this->contextFor($owner, $org);
    $wallets->topup($org, Money::decimal('300', 'CZK'), 'promo', 'promo-1', $ctx, promo: true);
    $wallets->topup($org, Money::decimal('700', 'CZK'), 'card', 'topup-3', $ctx, bankProvider: 'comgate');

    expect($wallets->spendable($org, 'CZK')->minor)->toBe(100000)
        ->and($wallets->refundableBalance($org->id, 'CZK')->minor)->toBe(70000);
    expect(fn () => $wallets->refund($org, Money::decimal('800', 'CZK'), 'test', 'rf-1', $ctx))->toThrow(DomainError::class);
    $refund = $wallets->refund($org, Money::decimal('700', 'CZK'), 'customer request', 'rf-2', $ctx, 'source', 'pi_x');
    expect($refund->state)->toBe('pending')->and($wallets->balances($org, 'CZK')['available']->minor)->toBe(0);
});

it('enforces hard budgets and reverses transactions cleanly', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $wallets = app(WalletService::class);
    $ledger = app(LedgerService::class);
    $ctx = $this->contextFor($owner, $org);
    $wallets->topup($org, Money::decimal('5000', 'CZK'), 'bank', 'topup-4', $ctx);
    Budget::query()->create(['organization_id' => $org->id, 'currency' => 'CZK', 'limit_minor' => 100000, 'hard' => true, 'alert_thresholds' => [50, 100], 'period_start' => now()->startOfMonth()->toDateString()]);

    $hold = $wallets->hold($org, Money::decimal('900', 'CZK'), 'order', 'b-1', $ctx);
    $wallets->capture($hold, 'web', $ctx);
    expect(fn () => $wallets->hold($org, Money::decimal('200', 'CZK'), 'order', 'b-2', $ctx))->toThrow(DomainError::class, 'hard budget');

    $tx = $ledger->post('adjustment', 'CZK', [
        ['account' => LedgerService::expenseAccount('adjustment', 'CZK'), 'debit' => 1000],
        ['account' => LedgerService::walletAccount($org->id, 'CZK'), 'credit' => 1000],
    ], 'adj-1', $org->id);
    $ledger->reverse($tx, 'rev-1', 'entered by mistake');
    expect($ledger->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(500000 - 90000)
        ->and($ledger->verifyInvariant()['balanced'])->toBeTrue();
});

it('rejects unbalanced ledger transactions', function () {
    $ledger = app(LedgerService::class);
    $ledger->post('topup', 'CZK', [['account' => 'asset:bank:x:CZK', 'debit' => 100], ['account' => 'liability:wallet:org_a:CZK', 'credit' => 90]], 'bad-1');
})->throws(DomainError::class, 'unbalanced');
