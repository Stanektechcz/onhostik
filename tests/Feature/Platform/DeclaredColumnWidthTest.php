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

    // The same request again is the SAME request. A finished request is replayed by the HTTP layer (`IdempotencyKey` middleware);
    // the bus has to recognise it by itself when that layer has nothing to replay: the process died after the credit was
    // committed and before the answer was stored, or the answer was a 5xx (never stored). The second the request was sent in
    // used to be part of the command key — without the stored answer the retry was a new command, and the credit was given twice.
    $this->travel(70)->seconds();
    DB::table('idempotency_keys')->where('key', 'like', 'http:%')->delete();
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

it('keeps a conversation of the staff assistant under the id the console really sends', function () {
    [, $org] = $this->customerWithOrganization();
    $agent = $this->staff('support_l2');
    $this->actingAs($agent, 'sanctum');

    // the console names a conversation `admin-` + a timestamp in base 36 (14 characters); the platform puts `staff:<user>:<org>:` in
    // front of it — 82 characters into a column of 80. SQLite did not mind; on PostgreSQL every question of a support agent answered 500.
    $this->postJson('/v1/staff/assistant/chat', ['text' => 'Co vidíš na účtu zákazníka?', 'organization_id' => $org->id, 'session_id' => 'admin-'.base_convert((string) (time() * 1000), 10, 36)])->assertOk();
    // and the longest id the API accepts — from the console, and from a customer's own client (`<user>:` goes in front of theirs)
    $this->postJson('/v1/staff/assistant/chat', ['text' => 'A ještě jednou.', 'organization_id' => $org->id, 'session_id' => str_repeat('s', 80)])->assertOk();
    [$customer, $own] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum');
    $this->withHeaders(['X-Organization' => $own->id])->postJson('/v1/assistant/chat', ['text' => 'Kolik mám kreditu?', 'session_id' => str_repeat('c', 80)])->assertOk();

    expect(WidthGuard::measure('assistant conversations'))->toBe([]);
});
