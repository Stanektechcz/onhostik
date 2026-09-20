<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\ChargebackAnalyst;
use Onhost\Domain\Billing\ChargebackService;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Incidents\Models\Incident;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Chargeback analytics (audit §5j-6): reasons clustered per product, node and theme; a cluster above the threshold
 * opens one internal incident on the matching component — and only one while it is open.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('clusters the reasons and opens one internal incident per hot node or product', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = $this->contextFor($owner, $org);
    $plan = app(CatalogService::class)->resolve('game', 'game-8', 'CZK', 'month');
    $chargebacks = app(ChargebackService::class);
    $reasons = ['Server je strašně pomalý a laguje.', 'Výkon je hrozný, pořád to laguje', 'Too slow, unplayable lag every evening'];
    foreach ($reasons as $i => $reason) {
        $service = featureGameService($org, [], 77 + $i, 'e4c1abc'.$i);
        Subscription::query()->create([
            'organization_id' => $org->id, 'service_id' => $service->id, 'plan_version_id' => $plan['version']->id, 'price_id' => $plan['price']->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 30000,
            'state' => Subscription::ACTIVE, 'current_period_start' => now()->subDays(20), 'current_period_end' => now()->addDays(10), 'next_renewal_at' => now()->addDays(10), 'auto_renew' => true, 'renewal_priority' => 'normal',
        ]);
        chargebackPaidStatement($org, $service, 36300, 6300, -19, 10); // a request returns a share of what was paid, so the period is paid
        $chargebacks->request($service, $owner, $reason, $ctx);
    }
    expect(ChargebackAnalyst::theme('Cena je moc vysoká'))->toBe('price')->and(ChargebackAnalyst::theme('Nikdo z podpory neodpovídá'))->toBe('support')->and(ChargebackAnalyst::theme(''))->toBe('other');

    $analyst = app(ChargebackAnalyst::class);
    $analytics = $analyst->analytics(30);
    expect($analytics['total'])->toBe(3)->and($analytics['by_theme'][0])->toMatchArray(['theme' => 'performance', 'count' => 3])
        ->and($analytics['by_node'][0])->toMatchArray(['node' => 'games01', 'count' => 3])->and($analytics['by_product'][0])->toMatchArray(['product_key' => 'game', 'family' => 'game', 'count' => 3]);
    expect(collect($analytics['clusters'])->pluck('key')->all())->toEqualCanonicalizing(['node:'.$analytics['by_node'][0]['node_id'], 'product:game']);

    // two clusters → two internal incidents on the games component; a second run opens nothing new
    $opened = $analyst->run(30);
    expect($opened)->toHaveCount(2);
    $incident = Incident::query()->where('meta->chargeback_cluster', 'product:game')->firstOrFail();
    expect($incident->visibility)->toBe('internal')->and($incident->components)->toBe(['games-cz1'])->and($incident->source)->toBe('chargeback')->and($incident->severity)->toBe('p3')->and($incident->title)->toContain('game');
    expect($analyst->run(30))->toBe([]);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('audience', 'internal')->where('event', 'chargeback.cluster')->count())->toBe(2);

    // the console reads the analytics; below the threshold nothing clusters
    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $data = $this->getJson('/v1/staff/chargebacks/analytics?days=30')->assertOk()->json('data');
    expect($data['threshold'])->toBe(3)->and($data['clusters'])->toHaveCount(2)->and($data['by_theme'][0]['examples'])->toHaveCount(3);
    config()->set('onhost.chargeback.cluster_threshold', 4);
    expect($analyst->analytics(30)['clusters'])->toBe([]);
    $this->withHeader('Idempotency-Key', 'ca-1')->postJson('/v1/staff/chargebacks/analyse')->assertOk()->assertJsonPath('opened', []);
    $this->artisan('onhost:chargebacks:analyse --days=30')->assertSuccessful();
});
