<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\WalletLedger\Models\WalletTopup;
use Onhost\Domain\WalletLedger\WalletService;
use Tests\WidthGuard;

/*
 * SQLite does not look at the length of a VARCHAR; PostgreSQL — the production database — refuses the statement. A manual
 * credit by staff answered 500 there: the ledger's `reference_id` (60) was given the idempotency key (81 characters). The
 * local suite cannot see that by itself, so this test measures: after the longest request the API accepts, no stored value
 * is longer than its column declares.
 */

it('stores nothing longer than its column declares after the longest request the API takes, and gives a credit once', function () {
    [, $org] = $this->customerWithOrganization();
    $finance = $this->staff('platform_owner');
    $this->actingAs($finance, 'sanctum');
    app(StepUpService::class)->grant($finance, 'totp', null, '127.0.0.1');
    $key = str_repeat('k', 200); // the longest Idempotency-Key the middleware lets through

    $this->withHeader('Idempotency-Key', $key)->postJson("/v1/staff/customers/{$org->id}/wallet/credit", ['amount' => 1000, 'note' => 'platba hotově na pobočce'])->assertCreated();

    $checked = 0;
    foreach (['ledger_transactions', 'ledger_postings', 'wallet_topups', 'wallets', 'audit_events', 'outbox_messages'] as $table) {
        $widths = WidthGuard::of($table); // read from the migrations: SQLite creates a bare `varchar`
        expect($widths)->not->toBeEmpty();
        foreach (DB::table($table)->get() as $row) {
            foreach ($widths as $column => $width) {
                $checked++;
                expect(mb_strlen((string) ($row->{$column} ?? '')))->toBeLessThanOrEqual($width, "{$table}.{$column} holds more than its {$width} characters");
            }
        }
    }
    expect($checked)->toBeGreaterThan(20);

    // the same request a minute later is the SAME request: the second it was sent in used to be part of the key, so a retry
    // (a timeout, a nervous second click) credited the customer twice
    $this->travel(70)->seconds();
    $this->withHeader('Idempotency-Key', $key)->postJson("/v1/staff/customers/{$org->id}/wallet/credit", ['amount' => 1000, 'note' => 'platba hotově na pobočce']);
    expect(WalletTopup::query()->where('organization_id', $org->id)->count())->toBe(1)->and(app(WalletService::class)->spendable($org, 'CZK')->minor)->toBe(100000);

    // without a header the same credit can be given again another time — and a double click within the minute is still one
    $this->withHeaders(['Idempotency-Key' => ''])->postJson("/v1/staff/customers/{$org->id}/wallet/credit", ['amount' => 500, 'note' => 'druhá platba'])->assertCreated();
    $this->withHeaders(['Idempotency-Key' => ''])->postJson("/v1/staff/customers/{$org->id}/wallet/credit", ['amount' => 500, 'note' => 'druhá platba']);
    expect(WalletTopup::query()->where('organization_id', $org->id)->count())->toBe(2);
    $this->travel(2)->minutes();
    $this->withHeaders(['Idempotency-Key' => ''])->postJson("/v1/staff/customers/{$org->id}/wallet/credit", ['amount' => 500, 'note' => 'druhá platba'])->assertCreated();
    expect(WalletTopup::query()->where('organization_id', $org->id)->count())->toBe(3);
});
