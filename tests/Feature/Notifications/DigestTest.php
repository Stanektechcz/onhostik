<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Notifications\DigestService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\UptimeMonitor;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;

/*
 * Digests (audit §5e-5): the weekly customer summary and the daily staff summary are assembled from what the
 * platform already knows — renewals ahead, credit, backups, monitors, plan usage; stuck operations, failures,
 * dunning, capacity — and reach the feed and the mailbox.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

it('sends the weekly customer digest with renewals, credit, backups and monitors, and the daily staff digest with the operational counts', function () {
    [$owner, $org] = $this->customerWithOrganization([], ['billing_email' => 'billing@example.cz']);
    $ctx = $this->contextFor($owner, $org);
    $service = featureWebService($org, 'aapanel');
    $standard = app(CatalogService::class)->resolve('web-hosting', 'standard', 'CZK', 'month');
    Subscription::query()->create([
        'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $standard['version']->id, 'price_id' => $standard['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 18900,
        'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(20), 'current_period_end' => now()->addDays(10), 'next_renewal_at' => now()->addDays(3), 'auto_renew' => true, 'renewal_priority' => 'normal',
    ]);
    app(WalletService::class)->topup($org, Money::decimal('1000', 'CZK'), 'card', 'seed', $ctx, bankProvider: 'comgate');
    Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'provider_instance_id' => $service->provider_instance_id, 'kind' => 'scheduled', 'state' => 'available', 'size_bytes' => 4096, 'started_at' => now()->subDays(2), 'finished_at' => now()->subDays(2), 'verified_at' => now()->subDay(), 'protected' => false]);
    Backup::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'provider_instance_id' => $service->provider_instance_id, 'kind' => 'scheduled', 'state' => 'failed', 'size_bytes' => 0, 'started_at' => now()->subHours(3), 'protected' => false]);
    UptimeMonitor::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'url' => 'https://shop.cz/', 'interval_seconds' => 60, 'expected_status' => 200, 'timeout_seconds' => 10, 'enabled' => true, 'notify' => true, 'state' => 'down', 'consecutive_failures' => 4, 'last_status' => 503, 'last_ms' => 900]);
    $service->forceFill(['tags' => array_merge((array) $service->tags, ['usage' => ['level' => 'warn', 'metrics' => ['disk' => ['used' => 9, 'limit' => 10, 'pct' => 90]]]])])->save();

    $digest = app(DigestService::class)->customerWeekly($org);
    expect($digest['title'])->toStartWith('Týdenní přehled')->and($digest['sections'])->toHaveKeys(['Služby', 'Příštích 14 dní', 'Kredit', 'Zálohy (7 dní)', 'Monitoring'])
        ->and($digest['sections']['Příštích 14 dní'][0])->toContain('Obnova')->toContain('228,69 Kč')
        ->and($digest['sections']['Kredit'][0])->toContain('k dispozici 1 000 Kč')
        ->and($digest['sections']['Zálohy (7 dní)'][0])->toBe('2 záloh · 1 ověřených · 1 selhalo')
        ->and($digest['sections']['Monitoring'][0])->toContain('1 právě nedostupné')
        ->and($digest['sections']['Služby'][1])->toContain('blíží se limitu tarifu');

    expect(app(DigestService::class)->weekly())->toMatchArray(['organizations' => 1, 'sent' => 1]);
    expect(Notification::query()->where('organization_id', $org->id)->where('kind', 'digest')->where('title', 'like', 'Týdenní přehled %')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'digest-weekly')->where('to', 'billing@example.cz')->count())->toBe(1);

    // staff: the counts of the board, the failed backup, the addresses from the configuration
    config(['onhost.notifications.staff_digest_to' => 'noc@onhost.cz, nope, finance@onhost.cz']);
    $instance = ProviderInstance::query()->where('key', 'aapanel-managed01')->firstOrFail();
    Operation::query()->create(['organization_id' => $org->id, 'service_id' => $service->id, 'provider_instance_id' => $instance->id, 'kind' => 'service.action', 'workflow' => 'service.action', 'state' => Operation::WAITING, 'queue' => 'default', 'idempotency_key' => 'dg-stalled', 'attempts' => 2, 'queued_at' => now()->subMinutes(3), 'error' => ['message' => 'transient', 'retryable' => true]]);
    $staff = app(DigestService::class)->staffDaily();
    expect($staff['recipients'])->toBe(2)->and($staff['lines'][0])->toContain('1 čeká na uzel')->and($staff['lines'][2])->toContain('1 selhalo')->and($staff['lines'][5])->toContain('1 se blíží');
    expect(Notification::query()->where('audience', 'internal')->where('kind', 'digest')->exists())->toBeTrue()
        ->and(MailOutbox::query()->where('template_key', 'digest-staff')->pluck('to')->sort()->values()->all())->toBe(['finance@onhost.cz', 'noc@onhost.cz']);

    // digest tuning (audit §5f-5): the customer previews it, switches it to monthly (first run of the month only) or off; EN accounts read it in English
    $this->actingAs($owner, 'sanctum');
    $preview = $this->getJson("/v1/organizations/{$org->id}/digest")->assertOk()->json('data');
    expect($preview['frequency'])->toBe('weekly')->and($preview['preview']['sections'])->toHaveKey('Kredit');
    $this->patchJson("/v1/organizations/{$org->id}", ['digest_frequency' => 'monthly'])->assertOk();
    expect(DigestService::frequency($org->fresh()))->toBe('monthly');
    Date::setTestNow(now()->startOfMonth()->addDays(10));
    expect(app(DigestService::class)->weekly())->toMatchArray(['organizations' => 1, 'sent' => 0, 'skipped' => 1]);
    Date::setTestNow(now()->startOfMonth()->addDays(2));
    expect(app(DigestService::class)->weekly())->toMatchArray(['organizations' => 1, 'sent' => 1, 'skipped' => 0]);
    Date::setTestNow();
    $this->patchJson("/v1/organizations/{$org->id}", ['digest_frequency' => 'off'])->assertOk();
    expect(app(DigestService::class)->weekly())->toMatchArray(['sent' => 0, 'skipped' => 1]);
    $this->patchJson("/v1/organizations/{$org->id}", ['digest_frequency' => 'daily'])->assertStatus(422);
    $org->forceFill(['locale' => 'en'])->save();
    $english = app(DigestService::class)->customerWeekly($org->fresh());
    expect($english['title'])->toStartWith('Weekly summary')->and($english['sections'])->toHaveKeys(['Services', 'Next 14 days', 'Credit', 'Backups (7 days)'])->and($english['sections']['Credit'][0])->toContain('available');
});
