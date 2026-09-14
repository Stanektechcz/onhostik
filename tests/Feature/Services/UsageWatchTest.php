<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Usage watch (audit §5e-1): a hosting service near its disk quota is told about the next plan with today's price
 * (once per level and day), the panel rows and the summary carry the measurement, and with the auto-upgrade policy on
 * the platform orders the next plan from credit at 95 % and says which order did it.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

/** One ISPConfig fake for the whole test (fakes stack, so the quota is a variable the test moves). @param  array{used:int, hard:int}  $quota */
function usageWatchSite(array &$quota): void
{
    Http::fake(function ($request) use (&$quota) {
        if (! str_starts_with($request->url(), ISP)) {
            return null;
        }
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $answer = match ($function) {
            'login' => 'sess-usage',
            'quota_get_by_user' => [['domain_id' => 7, 'domain' => 'shop.cz', 'used' => $quota['used'], 'hard' => $quota['hard'], 'soft' => $quota['hard'], 'files' => 1200]],
            'sites_web_domain_get' => ['domain_id' => 7, 'domain' => 'shop.cz', 'server_id' => 1, 'hd_quota' => (int) ($quota['hard'] / 1024), 'apache_directives' => '', 'nginx_directives' => ''],
            'sites_web_domain_update' => true,
            'server_get_php_versions' => ['8.3'],
            default => false,
        };

        return Http::response(['code' => 'ok', 'message' => '', 'response' => $answer]);
    });
}

it('warns at 85 %, escalates at 95 %, marks the panel rows, and orders the next plan from credit when the policy allows it', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['street' => 'Dlouhá 1', 'city' => 'Praha', 'postal_code' => '11000', 'billing_email' => 'billing@example.cz']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::decimal('5000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    $service = featureWebService($org, 'ispconfig');
    $start = app(CatalogService::class)->resolve('web-hosting', 'start', 'CZK', 'month');
    $service->forceFill(['plan_version_id' => $start['version']->id, 'entitlements' => $start['version']->entitlements])->save();
    $subscription = Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $start['version']->id, 'price_id' => $start['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => $start['price']->renewalAmount()->minor,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(15), 'current_period_end' => now()->addDays(15), 'next_renewal_at' => now()->addDays(8), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    $service->forceFill(['subscription_id' => $subscription->id])->save();
    $limitKb = 10 * 1024 * 1024; // the Start plan: 10 GB
    $quota = ['used' => (int) ($limitKb * 0.5), 'hard' => $limitKb];
    usageWatchSite($quota);
    $watch = app(UsageWatch::class);

    // 50 %: nothing to say, the measurement is stored
    expect($watch->run())->toMatchArray(['checked' => 1, 'warned' => 0, 'critical' => 0, 'upgraded' => 0, 'errors' => 0]);
    $service->refresh();
    expect($service->tags['usage']['level'])->toBe('ok')->and($service->tags['usage']['metrics']['disk']['pct'])->toBe(50);

    // 88 %: a warning with the next plan and today's price, once per day
    $quota['used'] = (int) ($limitKb * 0.88);
    expect($watch->run()['warned'])->toBe(1);
    app(OutboxPublisher::class)->relayPending();
    $notice = Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Služba % využívá 88 % (prostor)')->first();
    expect($notice)->not->toBeNull()->and($notice->body)->toContain('Vyšší tarif Standard stojí')->and($notice->severity)->toBe('warn')
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'service-usage-high')->count())->toBe(1);
    expect($watch->run()['warned'])->toBe(0); // the same day, the same level: silence
    $this->actingAs($owner, 'sanctum');
    $summary = $this->getJson("/v1/services/{$service->id}")->assertOk()->json('data.summary');
    expect($summary['usage']['level'])->toBe('warn')->and($summary['usage']['metrics']['disk']['pct'])->toBe(88)->and($summary['policy']['auto_upgrade'])->toBeFalse();
    $panel = $this->actingAs($owner)->get('/surfaces/onhost-panel.js')->assertOk()->getContent();
    expect($panel)->toContain('kapacita 88 % (prostor)')->toContain('"usage":{"level":"warn","pct":88,"metric":"disk"}');

    // 96 % without the policy: a hot notice, no order
    $quota['used'] = (int) ($limitKb * 0.96);
    expect($watch->run())->toMatchArray(['critical' => 1, 'upgraded' => 0]);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('severity', 'hot')->where('title', 'like', 'Služba % využívá 96 %%')->exists())->toBeTrue()
        ->and(Order::query()->where('organization_id', $org->id)->count())->toBe(0);

    // the customer switches the policy on (through the bus) and the next run orders the Standard plan from credit
    $this->actingAs($owner, 'sanctum')->putJson("/v1/services/{$service->id}/policy", ['auto_upgrade' => true])->assertOk()->assertJsonPath('policy.auto_upgrade', true);
    expect(Service::query()->findOrFail($service->id)->tags['policy']['auto_upgrade'])->toBeTrue();
    $service->refresh();
    $service->forceFill(['tags' => array_merge($service->tags, ['usage' => array_merge($service->tags['usage'], ['notified_on' => now()->subDay()->toDateString()])])])->save(); // a new day
    expect($watch->run())->toMatchArray(['critical' => 1, 'upgraded' => 1]);
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $order = Order::query()->where('organization_id', $org->id)->orderByDesc('created_at')->firstOrFail();
    expect($order->source)->toBe('auto')->and($order->state)->toBeIn([OrderStateMachine::PAID, OrderStateMachine::PROVISIONING, OrderStateMachine::ACTIVE])
        ->and(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Tarif služby % automaticky navýšen na Standard')->exists())->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    driveOperations();
    $standard = app(CatalogService::class)->resolve('web-hosting', 'standard', 'CZK', 'month');
    expect(Service::query()->findOrFail($service->id)->plan_version_id)->toBe($standard['version']->id)->and($subscription->refresh()->amount_minor)->toBe($standard['price']->renewalAmount()->minor);
    expect($watch->run()['upgraded'])->toBe(0); // one automatic upgrade per day at most
});
