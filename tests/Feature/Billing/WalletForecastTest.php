<?php

declare(strict_types=1);

use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\WalletLedger\WalletForecast;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Prediction: the renewals ahead are walked against the available credit (gross, with the customer's VAT), so the
 * customer learns the day the credit stops covering a renewal — and is warned two weeks ahead, once a day, by
 * notification and mail. Automatic top-ups silence the warning.
 */

it('predicts the day the credit runs out and warns the customer two weeks ahead, once per day', function () {
    $this->seed(TaxRuleSeeder::class);
    [$user, $org] = $this->customerWithOrganization();
    $ctx = new CommandContext('user', $user->id, $org->id, null, '127.0.0.1', 'test', null, 'test');
    app(WalletService::class)->topup($org, Money::minor(100000, 'CZK'), 'manual', 'forecast-topup-1', $ctx);
    $make = fn (string $label, int $net, int $days) => Subscription::query()->create(['organization_id' => $org->id, 'service_id' => Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Web', 'label' => $label, 'hostname' => "{$label}.example.test", 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'standard', 'activated_at' => now()])->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => $net, 'state' => 'active', 'auto_renew' => true, 'current_period_start' => now()->subMonth()->addDays($days), 'current_period_end' => now()->addDays($days), 'next_renewal_at' => now()->addDays($days)]);
    $make('shop', 100000, 5); // 1 000 Kč net → 1 210 Kč gross in five days
    $make('blog', 50000, 20); // 500 Kč net → 605 Kč gross in twenty days

    $forecast = app(WalletForecast::class)->forecast($org, 'CZK');
    expect($forecast['available']->minor)->toBe(100000)
        ->and($forecast['monthly_burn']->minor)->toBe(181500)
        ->and($forecast['renewals_30d']->minor)->toBe(181500)
        ->and($forecast['shortfall_30d']->minor)->toBe(81500)
        ->and($forecast['days'])->toBe(5) // the first renewal (1 210 Kč) already exceeds the 1 000 Kč credit
        ->and($forecast['depletes_at'])->toStartWith(now()->addDays(5)->toDateString())
        ->and($forecast['next_renewal']['amount']->minor)->toBe(121000)
        ->and($forecast['auto_topup'])->toBeFalse()->and($forecast['subscriptions'])->toBe(2);

    $this->actingAs($user, 'sanctum');
    $this->getJson('/v1/wallet')->assertOk()->assertJsonPath('data.forecast.days', 5)->assertJsonPath('data.forecast.subscriptions', 2);

    // the daily pass: one warning today, none on a second run the same day
    expect(app(WalletForecast::class)->warnLow())->toBe(['checked' => 1, 'warned' => 1]);
    expect(app(WalletForecast::class)->warnLow())->toBe(['checked' => 1, 'warned' => 0]);
    $event = OutboxMessage::query()->where('name', 'wallet.runway.low')->where('organization_id', $org->id)->firstOrFail();
    expect($event->payload['days'])->toBe(5)->and($event->payload['shortfall']['minor'])->toBe(81500);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('audience', 'customer')->where('title', 'Kredit vystačí ještě 5 dní')->exists())->toBeTrue();
    $mail = MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'wallet-runway')->firstOrFail();
    expect($mail->to)->toBe($org->billing_email)->and($mail->vars['dni'])->toBe('5')->and($mail->vars['chybi'])->toContain('815');

    // enough credit: no depletion date, nothing to warn about
    app(WalletService::class)->topup($org, Money::minor(1000000, 'CZK'), 'manual', 'forecast-topup-2', $ctx);
    $org->forceFill(['settings' => []])->save();
    $rich = app(WalletForecast::class)->forecast($org, 'CZK');
    expect($rich['days'])->toBeNull()->and($rich['depletes_at'])->toBeNull()->and($rich['shortfall_30d']->minor)->toBe(0);
    expect(app(WalletForecast::class)->warnLow())->toBe(['checked' => 1, 'warned' => 0]);
});
