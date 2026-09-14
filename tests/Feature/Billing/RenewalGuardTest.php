<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\WalletLedger\Models\AutoTopupSetting;
use Onhost\Domain\WalletLedger\WalletForecast;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Renewal guard (audit §5e-2): a week before renewals that the credit will not cover, the customer hears how much is
 * missing and by when (once a day); with the automatic top-up switched on the platform tries the stored payment
 * method first and, while no provider charges stored methods, says so instead of staying silent.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

it('warns a week ahead when renewals outrun the credit, stays quiet when the credit covers them, and manages the automatic top-up policy', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz']);
    $ctx = $this->contextFor($owner, $org);
    $service = featureWebService($org, 'aapanel');
    $standard = app(CatalogService::class)->resolve('web-hosting', 'standard', 'CZK', 'year');
    Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $standard['version']->id, 'price_id' => $standard['price']->id, 'currency' => 'CZK', 'period' => 'year', 'amount_minor' => 189000,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(360), 'current_period_end' => now()->addDays(5), 'next_renewal_at' => now()->addDays(5), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    app(WalletService::class)->topup($org, Money::decimal('500', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $guard = app(WalletForecast::class);

    // 500 Kč of credit against a 1 890 Kč + VAT renewal in five days: the customer is told, once
    expect($guard->renewalGuard())->toMatchArray(['checked' => 1, 'underfunded' => 1, 'topped_up' => 0, 'notified' => 1]);
    app(OutboxPublisher::class)->relayPending();
    $notice = Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Na obnovy příštího týdne chybí %')->first();
    expect($notice)->not->toBeNull()->and($notice->body)->toContain('2 286,90 Kč')->toContain('500 Kč')->toContain('1 786,90 Kč')->toContain('zapněte automatické dobití')
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'renewal-underfunded')->count())->toBe(1);
    expect($guard->renewalGuard()['notified'])->toBe(0);

    // the policy through the API: on, with limits; the wallet reports it; the guard now tries the stored method (none live) and says so
    $this->actingAs($owner, 'sanctum');
    $set = $this->putJson('/v1/wallet/auto-topup', ['enabled' => true, 'amount' => 2000, 'threshold' => 500, 'monthly_limit' => 8000])->assertOk()->json();
    expect($set['enabled'])->toBeTrue()->and($set['amount']['minor'])->toBe(200000)->and($set['monthly_limit']['minor'])->toBe(800000)->and($set['supported'])->toBeFalse();
    expect($this->getJson('/v1/wallet')->assertOk()->json('data.auto_topup.enabled'))->toBeTrue()->and($this->getJson('/v1/wallet')->json('data.forecast.auto_topup'))->toBeTrue();
    $this->putJson('/v1/wallet/auto-topup', ['enabled' => true, 'amount' => 50])->assertStatus(422)->assertJsonPath('error', 'auto_topup_amount_too_small');
    Organization::query()->whereKey($org->id)->update(['settings' => null]); // a new day
    expect($guard->renewalGuard())->toMatchArray(['underfunded' => 1, 'topped_up' => 0, 'notified' => 1]);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('body', 'like', '%Automatické dobití se nepodařilo%')->exists())->toBeTrue()
        ->and((bool) AutoTopupSetting::query()->where('organization_id', $org->id)->value('enabled'))->toBeTrue();

    // enough credit: nothing to say
    app(WalletService::class)->topup($org, Money::decimal('3000', 'CZK'), 'card', 'seed-2', $ctx, bankProvider: 'comgate');
    Organization::query()->whereKey($org->id)->update(['settings' => null]);
    expect($guard->renewalGuard())->toMatchArray(['checked' => 1, 'underfunded' => 0, 'notified' => 0]);
});
