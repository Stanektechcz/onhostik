<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\WalletLedger\AutoTopup;
use Onhost\Domain\WalletLedger\Models\AutoTopupSetting;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;

/*
 * The two ceilings a customer sets on automatic top-ups. Neither held: the day counter answered 0 or 1, so a cap of two
 * never tripped, and the monthly limit was stored and never read — a renewal loop could charge a stored card every hour
 * of every day. Both are counted on the charges that were really made.
 */

function autoTopupCharge(string $organizationId, int $minor, string $state, Carbon $at): void
{
    $intent = PaymentIntent::query()->create(['organization_id' => $organizationId, 'provider' => 'comgate', 'purpose' => 'topup', 'reference_type' => 'wallet', 'reference_id' => $organizationId,
        'amount_minor' => $minor, 'currency' => 'CZK', 'state' => $state, 'method' => 'stored', 'idempotency_key' => 'auto-topup:test:'.Str::random(12)]);
    $intent->forceFill(['created_at' => $at])->save();
}

it('stops at the daily cap and at the monthly limit the customer set, counted on the charges really made', function () {
    [, $org] = $this->customerWithOrganization();
    AutoTopupSetting::query()->create(['organization_id' => $org->id, 'wallet_id' => 'wal_'.$org->id, 'enabled' => true, 'threshold_minor' => 50000, 'amount_minor' => 200000, 'max_per_day' => 2, 'monthly_limit_minor' => 800000, 'last_triggered_at' => now()]);
    $this->travelTo(Carbon::parse('2026-10-15 12:00:00', 'UTC'));
    $topup = app(AutoTopup::class);
    $ctx = CommandContext::system('renewal guard');

    // two charges today already: the third is not made (the old counter said "one", and one is less than two — for ever)
    autoTopupCharge($org->id, 200000, 'PAID', now()->subHours(3));
    autoTopupCharge($org->id, 200000, 'FAILED', now()->subHour()); // a declined card is an attempt too
    expect($topup->attempt($org, Money::minor(150000, 'CZK'), $ctx))->toMatchArray(['status' => 'limited', 'reason' => 'daily cap reached']);

    // a new day, the same month: 4 000 Kč went through, a renewal of 4 500 Kč would make 8 500 of the 8 000 the customer allowed
    $this->travelTo(Carbon::parse('2026-10-16 12:00:00', 'UTC'));
    autoTopupCharge($org->id, 200000, 'PAID', now()->subDays(5));
    expect($topup->attempt($org, Money::minor(450000, 'CZK'), $ctx))->toMatchArray(['status' => 'limited', 'reason' => 'monthly limit reached']);
    // within the limit it goes on to the card (none is stored in this test, which is what it then says)
    expect($topup->attempt($org, Money::minor(150000, 'CZK'), $ctx)['status'])->toBe('unsupported');

    // the month turns at the seller's seat: 23:30 UTC on 31 October is November in Prague, and November is empty
    $this->travelTo(Carbon::parse('2026-10-31 23:30:00', 'UTC'));
    expect($topup->attempt($org, Money::minor(700000, 'CZK'), $ctx)['status'])->toBe('unsupported');
    expect(PaymentIntent::query()->where('organization_id', $org->id)->count())->toBe(3); // nothing was charged by this test
});
