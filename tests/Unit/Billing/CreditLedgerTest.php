<?php

declare(strict_types=1);

use App\Domains\Billing\Exceptions\InsufficientCreditException;
use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->customer = Customer::factory()->create();
    $this->ledger = app(CreditLedger::class);
});

it('increases the balance on deposit', function (): void {
    $this->ledger->deposit($this->customer, Money::ofMinor(10_000, 'CZK'), 'Top-up');

    expect($this->ledger->getBalance($this->customer)->getMinorAmount()->toInt())->toBe(10_000);
});

it('decreases the balance on deduction and records a running balance', function (): void {
    $this->ledger->deposit($this->customer, Money::ofMinor(10_000, 'CZK'), 'Top-up');
    $transaction = $this->ledger->deduct($this->customer, Money::ofMinor(4_000, 'CZK'), 'Invoice payment');

    expect($this->ledger->getBalance($this->customer)->getMinorAmount()->toInt())->toBe(6_000)
        ->and($transaction->balance_after->getMinorAmount()->toInt())->toBe(6_000)
        ->and($transaction->amount->getMinorAmount()->toInt())->toBe(-4_000);
});

it('never allows an overdraw', function (): void {
    $this->ledger->deposit($this->customer, Money::ofMinor(1_000, 'CZK'), 'Top-up');

    expect(fn () => $this->ledger->deduct($this->customer, Money::ofMinor(5_000, 'CZK'), 'Too much'))
        ->toThrow(InsufficientCreditException::class);

    expect($this->ledger->getBalance($this->customer)->getMinorAmount()->toInt())->toBe(1_000);
});

it('rejects a currency that does not match the customer ledger currency', function (): void {
    expect(fn () => $this->ledger->deposit($this->customer, Money::ofMinor(1_000, 'EUR'), 'Wrong currency'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects non-positive operation amounts', function (): void {
    expect(fn () => $this->ledger->deposit($this->customer, Money::ofMinor(0, 'CZK'), 'Zero'))
        ->toThrow(InvalidArgumentException::class);
});

it('supports signed admin adjustments', function (): void {
    $this->ledger->deposit($this->customer, Money::ofMinor(5_000, 'CZK'), 'Top-up');
    $this->ledger->adjust($this->customer, Money::ofMinor(-2_000, 'CZK'), 'Correction', createdBy: $this->customer->user_id);

    expect($this->ledger->getBalance($this->customer)->getMinorAmount()->toInt())->toBe(3_000);
});

/*
 * Model-level append-only guards. NOTE: the DB-level triggers from the
 * credit_transactions migration are MySQL-only and intentionally NOT
 * exercised here (SQLite test database) — see docs/setup.md.
 */
it('forbids updating a ledger row at the model level', function (): void {
    $this->ledger->deposit($this->customer, Money::ofMinor(1_000, 'CZK'), 'Top-up');

    $transaction = CreditTransaction::firstOrFail();
    $transaction->description = 'tampered';

    expect(fn () => $transaction->save())->toThrow(RuntimeException::class);
});

it('forbids deleting a ledger row at the model level', function (): void {
    $this->ledger->deposit($this->customer, Money::ofMinor(1_000, 'CZK'), 'Top-up');

    expect(fn () => CreditTransaction::firstOrFail()->delete())->toThrow(RuntimeException::class);
});
