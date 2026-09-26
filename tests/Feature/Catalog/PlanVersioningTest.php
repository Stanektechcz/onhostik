<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Price;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\TestCase;

/*
 * Versions of a plan from the administration (Brain card H01: "verzovat tarif a dostupnost"). A plan is never edited:
 * a change of limits or prices is a new version for new orders, those who bought keep theirs, and a version that
 * turned out wrong is taken off sale by putting the earlier one back — all with a fresh step-up, a second person (owner
 * decision 13), a reason and a trail.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

/** A write with a key of its own (a header set with withHeader() stays for later requests of the test). */
function planVersionPost(TestCase $test, string $uri, array $data = []): TestResponse
{
    return $test->withHeader('Idempotency-Key', (string) Str::ulid())->postJson($uri, $data);
}

/** A price or plan change takes a second person (owner decision 13): refused, approved by somebody else, repeated as it was. */
function planVersionApproved(TestCase $test, string $uri, array $data = []): TestResponse
{
    $id = (string) planVersionPost($test, $uri, $data)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    secondPersonApproves($id);

    return planVersionPost($test, $uri, $data);
}

function planVersionQuote(array $org): int
{
    return app(QuoteService::class)->quote([['product_key' => 'vps', 'plan_key' => 'compute-4']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c', 'vat_status' => 'unknown'], 1, null, $org[1])->subtotal_minor;
}

it('publishes a new version for new orders only, shows who stays on the old one, and rolls back', function () {
    $customer = $this->customerWithOrganization();
    $plan = Plan::query()->where('key', 'compute-4')->firstOrFail();
    $v1 = $plan->currentVersion();
    // an existing customer on version 1
    $service = Service::query()->create(['organization_id' => $customer[1]->id, 'product_key' => 'vps', 'family' => 'cloud', 'name' => 'Compute 4', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'plan_version_id' => $v1->id, 'desired_spec' => [], 'entitlements' => $v1->entitlements, 'sla_class' => 'standard']);
    Subscription::query()->create(['organization_id' => $customer[1]->id, 'service_id' => $service->id, 'plan_version_id' => $v1->id, 'currency' => 'CZK', 'period' => 'month', 'amount_minor' => 44900, 'state' => 'active', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(), 'next_renewal_at' => now()->addMonth()]);

    $owner = $this->staff('platform_owner');
    $this->actingAs($owner, 'sanctum');
    $uri = '/v1/staff/pricing/plans/vps/compute-4/versions';
    $change = ['reason' => 'Zdražení energií od října', 'entitlements' => ['ram_mb' => 12288], 'prices' => [['currency' => 'CZK', 'period' => 'month', 'amount' => '499'], ['currency' => 'CZK', 'period' => 'year', 'amount' => '4990']]];

    // what every new customer pays is not changed on a stale session
    planVersionPost($this, $uri, $change)->assertStatus(403)->assertJsonPath('error', 'step_up_required');
    expect(PlanVersion::query()->where('plan_id', $plan->id)->count())->toBe(1);
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    $published = planVersionApproved($this, $uri, $change)->assertCreated()->json();
    expect($published['version'])->toBe(2)->and($published['plan']['current_version'])->toBe(2);
    $v2 = PlanVersion::query()->where('plan_id', $plan->id)->where('version', 2)->sole();
    expect($v2->entitlements['ram_mb'])->toBe(12288)->and($v2->entitlements['vcpu'])->toBe($v1->entitlements['vcpu']) // what was not mentioned is carried over
        ->and($v2->created_by)->toBe($owner->id)->and($v1->fresh()->effective_to)->not->toBeNull();
    $czkMonth = Price::query()->where('plan_version_id', $v2->id)->where('currency', 'CZK')->where('period', 'month')->sole();
    expect($czkMonth->amount_minor)->toBe(49900)->and($czkMonth->renewal_amount_minor)->toBe(49900)
        ->and(Price::query()->where('plan_version_id', $v2->id)->count())->toBe(Price::query()->where('plan_version_id', $v1->id)->count()) // no currency or period vanished by omission
        ->and(Price::query()->where('plan_version_id', $v2->id)->where('currency', 'EUR')->where('period', 'month')->sole()->amount_minor)->toBe(Price::query()->where('plan_version_id', $v1->id)->where('currency', 'EUR')->where('period', 'month')->sole()->amount_minor)
        ->and(Price::query()->where('plan_version_id', $v1->id)->where('state', 'active')->count())->toBe(Price::query()->where('plan_version_id', $v1->id)->count()); // the old prices still rate and renew those who bought them

    // new orders get version 2; the customer who bought version 1 keeps it
    expect(planVersionQuote($customer))->toBe(49900)
        ->and($service->fresh()->plan_version_id)->toBe($v1->id)->and($service->fresh()->entitlements['ram_mb'])->toBe(8192)
        ->and(Subscription::query()->where('service_id', $service->id)->sole()->amount_minor)->toBe(44900);

    // the history is the impact: who is on which version
    $history = $this->getJson($uri)->assertOk()->json('data');
    expect($history['current_version'])->toBe(2)->and(collect($history['versions'])->firstWhere('version', 1))->toMatchArray(['on_sale' => false, 'services' => 1, 'subscriptions' => 1])
        ->and(collect($history['versions'])->firstWhere('version', 2))->toMatchArray(['on_sale' => true, 'services' => 0]);

    // the trail: who, why, what changed — and finance hears about it
    $audit = AuditEvent::query()->where('action', 'catalog.plan.publish')->where('resource_id', $plan->id)->sole();
    expect($audit->actor_id)->toBe($owner->id)->and(json_encode($audit->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->toContain('Zdražení energií')->toContain('ram_mb')->toContain('CZK/month');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'catalog.plan.version_published')->sole()->title)->toContain('vps/compute-4')->toContain('v2');

    // the version was a mistake: version 1 goes back on sale, version 2 stays for whoever bought it, numbers are never reused
    planVersionApproved($this, "{$uri}/1/activate", ['reason' => 'Ceník v2 vydán omylem'])->assertOk()->assertJsonPath('plan.current_version', 1);
    expect(planVersionQuote($customer))->toBe(44900)->and(PlanVersion::query()->where('plan_id', $plan->id)->count())->toBe(2)->and($v1->fresh()->effective_to)->toBeNull();
    planVersionPost($this, "{$uri}/1/activate", ['reason' => 'ještě jednou'])->assertStatus(422)->assertJsonPath('error', 'plan_version_unchanged');
    planVersionPost($this, "{$uri}/9/activate", ['reason' => 'neexistuje'])->assertNotFound();
    expect(planVersionApproved($this, $uri, ['reason' => 'Druhý pokus o ceník', 'prices' => [['currency' => 'CZK', 'period' => 'month', 'amount' => '479']]])->assertCreated()->json('version'))->toBe(3);
});

it('refuses a version that would break the plan: unknown keys, retyped values, a vanished period, a slipped decimal place, no change, no reason', function () {
    $owner = $this->staff('platform_owner');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum');
    $uri = '/v1/staff/pricing/plans/vps/compute-4/versions';
    $plan = Plan::query()->where('key', 'compute-4')->firstOrFail();

    $refused = [
        'plan_key_unknown' => ['reason' => 'nový parametr', 'entitlements' => ['gpu' => 1]],                                         // the schema belongs to the code that provisions it
        'plan_value_invalid' => ['reason' => 'překlep v paměti', 'entitlements' => ['ram_mb' => 'hodně']],
    ];
    planVersionPost($this, $uri, ['reason' => 'půl jádra navíc', 'entitlements' => ['vcpu' => 4.5]])->assertStatus(422)->assertJsonPath('error', 'plan_value_invalid'); // a whole number stays whole: adapters cast it
    $refused += [
        'price_period_unknown' => ['reason' => 'týdenní platba', 'prices' => [['currency' => 'USD', 'period' => 'month', 'amount' => '20']]],
        'price_change_large' => ['reason' => 'uklouzla desetinná čárka', 'prices' => [['currency' => 'CZK', 'period' => 'month', 'amount' => '4490']]],
        'price_invalid' => ['reason' => 'zdarma omylem', 'prices' => [['currency' => 'CZK', 'period' => 'month', 'amount' => '0']]],
        'plan_version_unchanged' => ['reason' => 'nic se nemění', 'entitlements' => ['ram_mb' => 8192], 'prices' => [['currency' => 'CZK', 'period' => 'month', 'amount' => '449']]],
    ];
    foreach ($refused as $error => $body) {
        planVersionPost($this, $uri, $body)->assertStatus(422)->assertJsonPath('error', $error);
    }
    planVersionPost($this, $uri, ['entitlements' => ['ram_mb' => 16384]])->assertStatus(422); // no reason, no version
    planVersionPost($this, '/v1/staff/pricing/plans/vps/nope/versions', ['reason' => 'neznámý tarif', 'entitlements' => ['ram_mb' => 1]])->assertNotFound();
    expect(PlanVersion::query()->where('plan_id', $plan->id)->count())->toBe(1)->and((int) $plan->fresh()->current_version)->toBe(1);

    // a large change is possible — said out loud
    expect(Approval::query()->count())->toBe(0); // every refusal above came before anybody was asked to approve
    planVersionApproved($this, $uri, ['reason' => 'Nová generace hardwaru', 'confirm_large_change' => true, 'prices' => [['currency' => 'CZK', 'period' => 'month', 'amount' => '899']]])->assertCreated();

    // and nobody without the right to manage the catalogue gets near it
    $support = $this->staff('support_l2');
    app(StepUpService::class)->grant($support, 'totp', null, '127.0.0.1');
    $this->actingAs($support, 'sanctum');
    $this->getJson($uri)->assertForbidden();
    planVersionPost($this, $uri, ['reason' => 'podpora mění ceník', 'entitlements' => ['ram_mb' => 4096]])->assertForbidden();
    planVersionPost($this, "{$uri}/1/activate", ['reason' => 'podpora vrací ceník'])->assertForbidden();
});

it('serves the settings page to staff only', function () {
    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $this->get('/sprava/nastaveni/tarify')->assertOk()->assertSee('Tarify a jejich verze')->assertSee('/staff/pricing/plans/', false);
    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer, 'sanctum');
    $this->get('/sprava/nastaveni/tarify')->assertRedirect('/panel');
});
