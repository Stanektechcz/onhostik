<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Catalog\Commands\CatalogCommand;
use Onhost\Domain\Catalog\Models\Plan;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\ProductOption;
use Onhost\Domain\Catalog\Models\PromoCode;
use Onhost\Domain\Catalog\PricingRules;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Tests\TestCase;

/*
 * Owner decision 13 (2026-09-25): every change of a price or a plan in the admin configuration takes a second person.
 * Before it, a promo code, a discount, an option price, regional pricing and the archive download fee were changed by one
 * person without even a step-up, and a plan version was published with a step-up alone. Withdrawing an offer stays with
 * one person (the emergency brake); a change that would be refused anyway is refused before anybody is asked to approve it,
 * and an approval given against one state of the catalogue is not spent on another.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

function catalogFourEyesStaff(string $role, bool $stepUp = true): User
{
    $user = User::factory()->staff()->create();
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role, 'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null]);
    if ($stepUp) {
        app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
    }

    return $user;
}

/** A write with a key of its own (a header set with withHeader() stays for later requests of the test). */
function catalogFourEyesSend(TestCase $test, User $as, string $method, string $uri, array $body = []): TestResponse
{
    $test->actingAs($as, 'sanctum');

    return $test->withHeader('Idempotency-Key', 'c4e-'.Str::ulid())->json($method, $uri, $body);
}

/** The second person decides through the console, behind a step-up of their own. */
function catalogFourEyesDecide(TestCase $test, User $decider, string $approvalId, string $decision = 'approved'): TestResponse
{
    return catalogFourEyesSend($test, $decider, 'POST', "/v1/staff/approvals/{$approvalId}/decision", ['decision' => $decision] + ($decision === 'rejected' ? ['note' => 'ne'] : []));
}

it('asks somebody else before a promo code, a discount, an option price, regional pricing or the archive fee changes', function () {
    $pm = catalogFourEyesStaff('product_manager');
    $finance = catalogFourEyesStaff('billing_finance_admin');
    $changes = [
        'catalog.pricing.commit_discounts.set' => ['PUT', '/v1/staff/pricing/commit-discounts', ['families' => ['web' => [12 => 5]], 'reason' => 'Podzimní kampaň na roční hosting'], fn () => app(PricingRules::class)->commitDiscountPercent('web', 12) === 5.0],
        'catalog.pricing.domain_discount.set' => ['PUT', '/v1/staff/pricing/domain-discounts', ['tld' => 'cz', 'register' => 15, 'reason' => 'Akce na .cz'], fn () => app(PricingRules::class)->domainDiscount('cz')['percent'] === 15.0],
        'catalog.promo.upsert' => ['PUT', '/v1/staff/pricing/promo-codes', ['code' => 'JARO-2026', 'kind' => 'percent', 'value' => 15, 'reason' => 'Jarní newsletter'], fn () => PromoCode::query()->where('code', 'JARO-2026')->exists()],
        'catalog.option.upsert' => ['PUT', '/v1/staff/pricing/options', ['product_key' => 'web-hosting', 'key' => 'malware_scan', 'kind' => 'addon', 'label' => ['cs' => 'Sken malwaru'], 'price_czk' => 39, 'reason' => 'Nový doplněk'], fn () => ProductOption::query()->where('key', 'malware_scan')->exists()],
        'catalog.pricing.regions.set' => ['PUT', '/v1/staff/pricing/regions', ['regions' => [['key' => 'sk', 'countries' => ['SK'], 'currency' => 'EUR', 'adjust_pct' => -10]], 'reason' => 'Vstup na slovenský trh'], fn () => (app(PricingRules::class)->regions()['sk']['adjust_pct'] ?? null) === -10.0],
        'catalog.lifecycle.set' => ['PUT', '/v1/staff/settings/lifecycle', ['download_fee_minor' => ['CZK' => 90000], 'reason' => 'Nový poplatek za stažení archivu'], fn () => app(DeletionPolicy::class)->downloadFeeMinor('CZK') === 90000],
        'catalog.option.delete' => ['DELETE', '/v1/staff/pricing/options/web-custom/sites', [], fn () => ! ProductOption::query()->where('key', 'sites')->where('product_id', Product::query()->where('key', 'web-custom')->value('id'))->exists()],
    ];

    foreach ($changes as $action => [$method, $uri, $body, $applied]) {
        // one person alone: refused, the request is opened, nothing changes
        $refused = catalogFourEyesSend($this, $pm, $method, $uri, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->assertJsonPath('requirement', 'approval');
        $id = (string) $refused->json('approval_id');
        $row = Approval::query()->findOrFail($id);
        expect($applied())->toBeFalse($action)->and($row->action)->toBe($action)->and(data_get($row->payload, 'permission'))->toBe('catalog.manage')
            ->and($row->reason)->toBe($body['reason'] ?? null); // the approver reads why

        // somebody who could make the change themselves approves; the same request, repeated as it was, goes through once
        catalogFourEyesDecide($this, $finance, $id)->assertOk()->assertJsonPath('state', 'approved');
        catalogFourEyesSend($this, $pm, $method, $uri, $body)->assertOk();
        expect($applied())->toBeTrue($action)->and(Approval::query()->findOrFail($id)->state)->toBe('consumed')
            ->and(AuditEvent::query()->where('action', $action)->where('result', 'succeeded')->latest('id')->firstOrFail()->approval_ids)->toBe([$id]);
    }
});

it('publishes or rolls back a plan version only with a second person, and an approval asked against version N is not spent on version N+1', function () {
    $pm = catalogFourEyesStaff('product_manager');
    $pm2 = catalogFourEyesStaff('product_manager');
    $finance = catalogFourEyesStaff('billing_finance_admin');
    $plan = Plan::query()->where('key', 'compute-4')->firstOrFail();
    $uri = '/v1/staff/pricing/plans/vps/compute-4/versions';
    $mine = ['reason' => 'Zdražení energií od října', 'prices' => [['currency' => 'CZK', 'period' => 'month', 'amount' => '499']]];
    $theirs = ['reason' => 'Víc paměti v Compute 4', 'entitlements' => ['ram_mb' => 12288]];

    $first = (string) catalogFourEyesSend($this, $pm, 'POST', $uri, $mine)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect(PlanVersion::query()->where('plan_id', $plan->id)->count())->toBe(1);
    catalogFourEyesDecide($this, $finance, $first)->assertOk();

    // meanwhile somebody else publishes version 2 with an approval of their own
    $other = (string) catalogFourEyesSend($this, $pm2, 'POST', $uri, $theirs)->assertForbidden()->json('approval_id');
    catalogFourEyesDecide($this, $finance, $other)->assertOk();
    catalogFourEyesSend($this, $pm2, 'POST', $uri, $theirs)->assertCreated()->assertJsonPath('version', 2);

    // the first approval was given against version 1: repeating the request now opens a new one, the old one stays unspent
    $again = catalogFourEyesSend($this, $pm, 'POST', $uri, $mine)->assertForbidden()->assertJsonPath('error', 'approval_required');
    expect((string) $again->json('approval_id'))->not->toBe($first)->and(Approval::query()->findOrFail($first)->state)->toBe('approved')
        ->and((int) $plan->fresh()->current_version)->toBe(2)->and(PlanVersion::query()->where('plan_id', $plan->id)->count())->toBe(2);

    // the handler checks it again, whoever dispatches: a stale base is a conflict, not an overwrite
    expect(fn () => app(CommandBus::class)->dispatch(new CatalogCommand('c4e-stale', ['op' => 'plan.publish', 'product_key' => 'vps', 'plan_key' => 'compute-4', 'base_version' => 1] + $mine), CommandContext::system('pest')))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('catalog_changed_since_request')->and($e->status)->toBe(409));

    // a rollback puts other prices on sale: a second person as well
    $rollback = (string) catalogFourEyesSend($this, $pm, 'POST', "{$uri}/1/activate", ['reason' => 'Verze 2 vydána omylem'])->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect((int) $plan->fresh()->current_version)->toBe(2);
    catalogFourEyesDecide($this, $finance, $rollback)->assertOk();
    catalogFourEyesSend($this, $pm, 'POST', "{$uri}/1/activate", ['reason' => 'Verze 2 vydána omylem'])->assertOk()->assertJsonPath('plan.current_version', 1);

    // the same for a whole setting: an approval of commitment discounts asked against one table is not spent on another
    $body = ['families' => ['web' => [12 => 5]], 'reason' => 'Roční závazek'];
    $asked = (string) catalogFourEyesSend($this, $pm, 'PUT', '/v1/staff/pricing/commit-discounts', $body)->assertForbidden()->json('approval_id');
    catalogFourEyesDecide($this, $finance, $asked)->assertOk();
    app(PricingRules::class)->setCommitDiscounts(['families' => ['vps' => [24 => 10]]], 'someone-else');
    expect((string) catalogFourEyesSend($this, $pm, 'PUT', '/v1/staff/pricing/commit-discounts', $body)->assertForbidden()->json('approval_id'))->not->toBe($asked)
        ->and(app(PricingRules::class)->commitDiscountPercent('web', 12))->toBe(0.0)->and(app(PricingRules::class)->commitDiscountPercent('vps', 24))->toBe(10.0);
});

it('lets one person withdraw a discount, delete or pause a promo code and take a product off sale with a step-up alone', function () {
    app(PricingRules::class)->setDomainDiscount('cz', ['register' => 15], 'seed');
    $pm = catalogFourEyesStaff('product_manager', stepUp: false);

    catalogFourEyesSend($this, $pm, 'DELETE', '/v1/staff/pricing/domain-discounts/cz')->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect(app(PricingRules::class)->domainDiscount('cz')['percent'])->toBe(15.0);
    app(StepUpService::class)->grant($pm, 'totp', null, '127.0.0.1');

    catalogFourEyesSend($this, $pm, 'DELETE', '/v1/staff/pricing/domain-discounts/cz')->assertOk()->assertJsonPath('deleted', true);
    catalogFourEyesSend($this, $pm, 'PUT', '/v1/staff/pricing/promo-codes', ['code' => 'ONHOST10', 'kind' => 'percent', 'value' => 10, 'state' => 'paused'])->assertOk()->assertJsonPath('promo.state', 'paused');
    catalogFourEyesSend($this, $pm, 'DELETE', '/v1/staff/pricing/promo-codes/ONHOST10')->assertOk()->assertJsonPath('deleted', true);
    catalogFourEyesSend($this, $pm, 'PUT', '/v1/staff/pricing/addon-products', ['product_key' => 'wordpress', 'addon_products' => ['cdn']])->assertOk();
    $draft = app(CommandBus::class)->dispatch(new CatalogCommand('c4e-draft', ['op' => 'product.state', 'state' => 'draft', 'products' => ['cdn']]), new CommandContext('user', $pm->id, sessionId: 'pest'));
    expect($draft['products'])->toBe(['cdn' => 'draft'])->and(Product::query()->where('key', 'cdn')->value('state'))->toBe('draft')
        ->and(app(PricingRules::class)->domainDiscount('cz')['percent'])->toBe(0.0)->and(PromoCode::query()->where('code', 'ONHOST10')->exists())->toBeFalse()
        ->and(Approval::query()->count())->toBe(0);

    // re-activating a paused code is offering it again: that takes the second person
    catalogFourEyesSend($this, $pm, 'PUT', '/v1/staff/pricing/promo-codes', ['code' => 'ONHOST10', 'kind' => 'percent', 'value' => 10, 'state' => 'active'])->assertForbidden()->assertJsonPath('error', 'approval_required');
});

it('refuses a broken change before anybody is asked to approve it', function () {
    $pm = catalogFourEyesStaff('product_manager');
    $uri = '/v1/staff/pricing/plans/vps/compute-4/versions';
    $refused = [
        'option_key_invalid' => ['PUT', '/v1/staff/pricing/options', ['product_key' => 'web-hosting', 'key' => 'bad key', 'kind' => 'addon', 'label' => ['cs' => 'x'], 'price_czk' => 1]],
        'percent_invalid' => ['PUT', '/v1/staff/pricing/promo-codes', ['code' => 'MOC-2026', 'kind' => 'percent', 'value' => 150]],
        'promo_code_invalid' => ['PUT', '/v1/staff/pricing/promo-codes', ['code' => 'ne platný', 'kind' => 'percent', 'value' => 10]],
        'family_invalid' => ['PUT', '/v1/staff/pricing/commit-discounts', ['families' => ['Web!' => [12 => 5]]]],
        'commitment_invalid' => ['PUT', '/v1/staff/pricing/commit-discounts', ['families' => ['web' => [6 => 5]]]],
        'region_key_invalid' => ['PUT', '/v1/staff/pricing/regions', ['regions' => [['key' => 'x', 'countries' => ['SK']]]]],
        'tld_invalid' => ['PUT', '/v1/staff/pricing/domain-discounts', ['tld' => 'c z!', 'register' => 10]],
        'plan_key_unknown' => ['POST', $uri, ['reason' => 'nový parametr', 'entitlements' => ['gpu' => 1]]],
        'plan_value_invalid' => ['POST', $uri, ['reason' => 'překlep v paměti', 'entitlements' => ['ram_mb' => 'hodně']]],
        'price_change_large' => ['POST', $uri, ['reason' => 'uklouzla desetinná čárka', 'prices' => [['currency' => 'CZK', 'period' => 'month', 'amount' => '4490']]]],
        'plan_version_unchanged' => ['POST', $uri, ['reason' => 'nic se nemění', 'entitlements' => ['ram_mb' => 8192]]],
    ];
    foreach ($refused as $error => [$method, $path, $body]) {
        catalogFourEyesSend($this, $pm, $method, $path, $body)->assertStatus(422)->assertJsonPath('error', $error);
    }
    catalogFourEyesSend($this, $pm, 'POST', "{$uri}/1/activate", ['reason' => 'už je v prodeji'])->assertStatus(422)->assertJsonPath('error', 'plan_version_unchanged');
    catalogFourEyesSend($this, $pm, 'POST', "{$uri}/9/activate", ['reason' => 'neexistuje'])->assertNotFound();
    catalogFourEyesSend($this, $pm, 'PUT', '/v1/staff/pricing/options', ['product_key' => 'nope', 'key' => 'x', 'kind' => 'addon', 'label' => ['cs' => 'x'], 'price_czk' => 1])->assertNotFound();
    expect(Approval::query()->count())->toBe(0);

    // a large change said out loud is a change like any other: it waits for the second person
    catalogFourEyesSend($this, $pm, 'POST', $uri, ['reason' => 'Nová generace hardwaru', 'confirm_large_change' => true, 'prices' => [['currency' => 'CZK', 'period' => 'month', 'amount' => '899']]])->assertForbidden()->assertJsonPath('error', 'approval_required');

    // nobody without the catalogue permission learns anything from the check either
    $support = catalogFourEyesStaff('support_l2');
    catalogFourEyesSend($this, $support, 'PUT', '/v1/staff/pricing/options', ['product_key' => 'web-hosting', 'key' => 'bad key', 'kind' => 'addon', 'label' => ['cs' => 'x'], 'price_czk' => 1])->assertForbidden();
    expect(Approval::query()->count())->toBe(1);
});

it('does not let the author, a product manager or an IAM admin without catalog.manage approve a price change', function () {
    $pm = catalogFourEyesStaff('product_manager');
    $otherPm = catalogFourEyesStaff('product_manager');
    $iam = catalogFourEyesStaff('iam_admin');
    $id = (string) catalogFourEyesSend($this, $pm, 'PUT', '/v1/staff/pricing/promo-codes', ['code' => 'LETO-2026', 'kind' => 'percent', 'value' => 20])->assertForbidden()->json('approval_id');

    catalogFourEyesDecide($this, $pm, $id)->assertForbidden()->assertJsonPath('error', 'access_not_approved');
    catalogFourEyesDecide($this, $otherPm, $id)->assertForbidden()->assertJsonPath('error', 'access_not_approved'); // may ask for a price change, may not approve one
    catalogFourEyesDecide($this, $iam, $id)->assertForbidden()->assertJsonPath('error', 'approver_lacks_permission'); // may approve, could not make the change
    expect(Approval::query()->findOrFail($id)->state)->toBe('pending')->and(PromoCode::query()->where('code', 'LETO-2026')->exists())->toBeFalse();

    // somebody who may approve price changes does not approve their own
    $finance = catalogFourEyesStaff('billing_finance_admin');
    $own = (string) catalogFourEyesSend($this, $finance, 'PUT', '/v1/staff/pricing/promo-codes', ['code' => 'PODZIM-2026', 'kind' => 'percent', 'value' => 20])->assertForbidden()->json('approval_id');
    catalogFourEyesDecide($this, $finance, $own)->assertForbidden()->assertJsonPath('error', 'approval_own_request');
    expect(Approval::query()->findOrFail($own)->state)->toBe('pending');
});

it('runs price changes with one operator when the server says so', function () {
    config(['onhost.identity.four_eyes' => false]);
    $solo = catalogFourEyesStaff('platform_owner', stepUp: false);

    catalogFourEyesSend($this, $solo, 'PUT', '/v1/staff/pricing/promo-codes', ['code' => 'SOLO-2026', 'kind' => 'percent', 'value' => 10])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');
    catalogFourEyesSend($this, $solo, 'PUT', '/v1/staff/pricing/promo-codes', ['code' => 'SOLO-2026', 'kind' => 'percent', 'value' => 10])->assertOk();
    catalogFourEyesSend($this, $solo, 'POST', '/v1/staff/pricing/plans/vps/compute-4/versions', ['reason' => 'Jediný provozovatel', 'entitlements' => ['ram_mb' => 12288]])->assertCreated();
    expect(Approval::query()->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'catalog.promo.upsert')->where('result', 'succeeded')->sole()->approval_ids)->toBe(['waived:single-operator'])
        ->and(AuditEvent::query()->where('action', 'catalog.plan.publish')->where('result', 'succeeded')->get()->pluck('approval_ids')->all())->toContain(['waived:single-operator']);
});

it('keeps the panel navigation an ordinary edit', function () {
    $pm = catalogFourEyesStaff('product_manager', stepUp: false);
    catalogFourEyesSend($this, $pm, 'PUT', '/v1/staff/settings/panel-nav', ['categories' => ['vps' => ['order' => 3]]])->assertOk();
    expect(Approval::query()->count())->toBe(0);
});

it('refuses an AI actor any price change and any withdrawal', function () {
    $pm = catalogFourEyesStaff('product_manager');
    foreach ([['op' => 'promo.upsert', 'promo' => ['code' => 'AI-2026', 'kind' => 'percent', 'value' => 90]], ['op' => 'promo.delete', 'code' => 'ONHOST10']] as $payload) {
        expect(fn () => app(CommandBus::class)->dispatch(new CatalogCommand('c4e-ai-'.$payload['op'], $payload), new CommandContext('ai', $pm->id, sessionId: 'pest')))
            ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('approval_required')->and($e->extra['requirement'] ?? null)->toBe('human'));
    }
    expect(PromoCode::query()->where('code', 'AI-2026')->exists())->toBeFalse()->and(PromoCode::query()->where('code', 'ONHOST10')->exists())->toBeTrue();
});

it('tells the operator whether a price change can get its second person', function () {
    $this->seed([LegalEntitySeeder::class]);
    $row = function (): array {
        Artisan::call('onhost:doctor', ['--json' => true]);

        return collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', 'price changes have a second person') ?? [];
    };

    catalogFourEyesStaff('product_manager');
    catalogFourEyesStaff('billing_finance_admin');
    expect($row())->toMatchArray(['area' => 'identity', 'status' => 'WARN'])->and($row()['detail'])->toContain('1'); // one approver cannot approve their own price change

    catalogFourEyesStaff('platform_owner');
    expect($row()['status'])->toBe('OK');

    PolicyBinding::query()->delete();
    config(['onhost.identity.four_eyes' => false]);
    expect($row()['status'])->toBe('OK')->and($row()['detail'])->toContain('ONHOST_FOUR_EYES=false');
});

it('pulls a brake again when the withdrawn code came back, instead of answering from an old reply', function () {
    config(['onhost.identity.four_eyes' => false]);
    $solo = catalogFourEyesStaff('platform_owner');
    $this->actingAs($solo, 'sanctum'); // no Idempotency-Key header in this test: the key is derived from the request

    $this->deleteJson('/v1/staff/pricing/promo-codes/ONHOST10')->assertOk()->assertJsonPath('deleted', true);
    $this->travel(2)->minutes();
    $this->putJson('/v1/staff/pricing/promo-codes', ['code' => 'ONHOST10', 'kind' => 'percent', 'value' => 10])->assertOk();
    expect(PromoCode::query()->where('code', 'ONHOST10')->exists())->toBeTrue();
    $this->travel(2)->minutes();
    $this->deleteJson('/v1/staff/pricing/promo-codes/ONHOST10')->assertOk()->assertJsonPath('deleted', true);
    expect(PromoCode::query()->where('code', 'ONHOST10')->exists())->toBeFalse();

    // the other way round: created, withdrawn, created again with the same body — before the first and the third request
    // there is no code at all, so only the time tells the third request from a retry of the first
    $code = ['code' => 'ZIMA-2026', 'kind' => 'percent', 'value' => 10];
    $this->putJson('/v1/staff/pricing/promo-codes', $code)->assertOk();
    $this->travel(2)->minutes();
    $this->deleteJson('/v1/staff/pricing/promo-codes/ZIMA-2026')->assertOk()->assertJsonPath('deleted', true);
    $this->travel(2)->minutes();
    $this->putJson('/v1/staff/pricing/promo-codes', $code)->assertOk();
    expect(PromoCode::query()->where('code', 'ZIMA-2026')->exists())->toBeTrue();
});
