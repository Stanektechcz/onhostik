<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Loyalty\Models\Referral;
use Onhost\Domain\Loyalty\ReferralService;
use Onhost\Domain\Orders\OrderRiskService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Risk\RiskWeights;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Settings\SettingsStore;

/*
 * One risk model (audit §5n-4): both loops read and teach one weight table; a signal that fires in both (a disposable
 * mailbox) learns from either loop's decisions; the legacy per-loop tables are merged once; staff tune any signal from
 * the console's risk tuning and reset the whole table.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('shares one weight table between the order and the referral loop and merges the legacy tables once', function () {
    $risk = app(RiskWeights::class);
    $orders = app(OrderRiskService::class);
    $referrals = app(ReferralService::class);
    expect($risk->weights())->toBe(RiskWeights::DEFAULTS)->and($orders->weights())->toBe(OrderRiskService::WEIGHTS)->and($referrals->weights())->toBe(ReferralService::WEIGHTS);
    expect(OrderRiskService::WEIGHTS_SETTING)->toBe(RiskWeights::SETTING)->and(ReferralService::WEIGHTS_SETTING)->toBe(RiskWeights::SETTING);
    foreach (array_keys(OrderRiskService::WEIGHTS + ReferralService::WEIGHTS) as $signal) {
        expect(RiskWeights::DEFAULTS)->toHaveKey($signal);
    }

    // a tuned installation keeps what the two loops learned: the legacy tables are merged into the shared one on first read
    $settings = app(SettingsStore::class);
    $settings->forget(RiskWeights::SETTING);
    $settings->set('orders.risk.weights', ['disposable_email' => 70, 'bogus' => 99]);
    $settings->set('loyalty.referral.weights', ['same_address' => 65]);
    expect($orders->weights()['disposable_email'])->toBe(70)->and($referrals->weights()['same_address'])->toBe(65)->and($referrals->weights()['disposable_email'])->toBe(70);
    expect($settings->get(RiskWeights::SETTING))->toBe(['disposable_email' => 70, 'same_address' => 65]);

    // one decision teaches both loops: a reject in the referral loop makes the shared signal heavier for orders too
    $referrals->learn(['disposable_email', 'same_address'], 'reject');
    expect($orders->weights()['disposable_email'])->toBe(75)->and($referrals->weights())->toMatchArray(['disposable_email' => 75, 'same_address' => 70]);
    $risk->learn(['disposable_email'], 'release', 10, 'test');
    expect($orders->weights()['disposable_email'])->toBe(65)->and($referrals->weights()['disposable_email'])->toBe(65)->and($risk->feedback()['disposable_email'])->toBe(['released' => 1, 'rejected' => 1]);
    expect($risk->learn(['nope'], 'reject'))->toBe($risk->weights())->and($risk->learn(['disposable_email'], 'maybe'))->toBe($risk->weights()); // unknown signals and decisions teach nothing

    // the referral loop scores a disposable mailbox with the shared weight
    [$referrerOwner, $referrer] = $this->customerWithOrganization(['email' => 'jan@firma.cz'], ['name' => 'Firma Jan s.r.o.']);
    $code = $referrals->code($referrer);
    [$referredOwner, $referred] = $this->customerWithOrganization(['email' => 'temp@mailinator.com'], ['name' => 'Temp s.r.o.']);
    $referral = $referrals->attach($referred, $code, '203.0.113.7', CommandContext::system('test'));
    expect($referral)->toBeInstanceOf(Referral::class);
    $assessed = $referrals->assess($referral, Organization::query()->findOrFail($referrer->id), Organization::query()->findOrFail($referred->id));
    expect($assessed['signals'])->toBe(['disposable_email'])->and($assessed['score'])->toBe(65);

    // staff tune any signal of either loop from the console; unknown ones are refused; a reset restores every default
    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $this->withHeader('Idempotency-Key', 'rt-1')->putJson('/v1/staff/automation/order.risk/tuning', ['weights' => ['same_address' => 80, 'new_account' => 30]])->assertOk();
    expect($referrals->weights()['same_address'])->toBe(80)->and($orders->weights()['new_account'])->toBe(30);
    $this->withHeader('Idempotency-Key', 'rt-2')->putJson('/v1/staff/automation/order.risk/tuning', ['weights' => ['no_such_signal' => 50]])->assertStatus(422)->assertJsonPath('error', 'risk_signal_unknown');
    $tuning = $orders->tuning();
    expect($tuning['shared'])->toHaveKey('same_address')->and($tuning['referral'])->toBe(['hold_score' => ReferralService::HOLD_SCORE, 'refuse_score' => ReferralService::REFUSE_SCORE]);
    $this->withHeader('Idempotency-Key', 'rt-3')->putJson('/v1/staff/automation/order.risk/tuning', ['reset' => true])->assertOk();
    expect($risk->weights())->toBe(RiskWeights::DEFAULTS)->and($risk->feedback())->toBe([])->and($settings->get('orders.risk.weights'))->toBeNull();
});
