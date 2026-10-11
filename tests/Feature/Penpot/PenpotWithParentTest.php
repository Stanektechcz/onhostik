<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Onhost\Domain\Catalog\Models\PlanVersion;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\PenpotLine;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Penpot\PenpotInstances;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Money\Money;
use Onhost\Providers\AaPanel\AaPanelWebProvider;
use Onhost\Providers\Penpot\PenpotDockerProvider;
use Onhost\Providers\Shell\ScriptedShell;

require_once __DIR__.'/../../Support/Penpot/PenpotDoubles.php';

/*
 * TASK-0130 (owner decision H-R7, follow-up): a Penpot follows the service it was ordered for — suspended and resumed with it,
 * cancelled with it, and back when the cancellation is taken back (or the service is reinstated after payment). One Penpot per
 * service holds under concurrency: the checkout locks the parent, and a partial unique index refuses a second live row.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

afterEach(function () {
    PenpotDockerProvider::$shellFactory = null;
    PenpotDockerProvider::$transportFactory = null;
    AaPanelWebProvider::$shellFactory = null;
});

/** The aaPanel of the parent web hosting: its one site, file archive pulls, every other call accepted. */
function penpotParentPanel(): void
{
    $blob = gzencode(str_repeat('site files', 50));
    AaPanelWebProvider::$shellFactory = fn () => new ScriptedShell(['/exec 3</' => [0, 'SIZE '.strlen((string) $blob)]]);
    Http::fake(function ($request) use ($blob) {
        if (! str_contains($request->url(), 'managed01.mgmt.test')) {
            return null;
        }
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);

        return match (true) {
            str_contains($q, 'table=sites') => Http::response(['data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'status' => '1', 'ps' => 'onhost']], 'page' => '']),
            str_contains($q, 'GetFileBody') => Http::response(['status' => true, 'data' => base64_encode((string) $blob)]),
            str_contains($q, 'GetDir') => Http::response(['PATH' => (string) ($request->data()['path'] ?? ''), 'DIR' => [], 'FILES' => ['index.php;120;1700000000;0644;www']]),
            default => Http::response(['status' => true, 'msg' => 'ok', 'data' => [], 'page' => '']),
        };
    });
}

/** A running Penpot (provisioned on the node double) that belongs to `$parent`. */
function penpotOfParent(Organization $org, Service $parent, CommandContext $ctx): Service
{
    $penpot = penpotProvisioned($org, $ctx);
    $penpot->forceFill(['tags' => array_merge((array) $penpot->tags, ['parent_service_id' => $parent->id]), 'penpot_parent_id' => $parent->id])->save();

    return $penpot->refresh();
}

/** Drives every operation of the service that asks for `$action`. */
function penpotDriveChild(Service $penpot, string $action): void
{
    foreach (Operation::query()->where('service_id', $penpot->id)->where('desired->action', $action)->whereNotIn('state', [Operation::SUCCEEDED])->get() as $operation) {
        driveOperation($operation, 25);
    }
}

it('suspends and resumes the Penpot with the service it belongs to, and leaves alone one the customer stopped', function () {
    $node = penpotLab();
    penpotParentPanel();
    [$user, $org] = $this->customerWithOrganization();
    $parent = featureWebService($org, 'aapanel');
    $penpot = penpotOfParent($org, $parent, $this->contextFor($user, $org));
    $stack = PenpotInstances::stackName($penpot);
    $services = app(ServiceService::class);

    driveOperation($services->requestAction($parent, 'suspend', CommandContext::system('dunning'), 'pwp-susp-1', ['reason' => 'nezaplaceno']), 25);
    penpotDriveChild($penpot, 'suspend');
    expect($penpot->refresh()->state)->toBe(ServiceStateMachine::SUSPENDED)->and($node->stacks[$stack])->toBe('stopped')
        ->and(data_get($penpot->tags, 'included.held_by'))->toBe($parent->id);

    driveOperation($services->requestAction($parent->refresh(), 'resume', CommandContext::system('paid'), 'pwp-res-1', ['reason' => 'zaplaceno', 'lift' => 'review']), 25);
    penpotDriveChild($penpot, 'resume');
    expect($penpot->refresh()->state)->toBe(ServiceStateMachine::ACTIVE)->and($node->stacks[$stack])->toBe('running')
        ->and(data_get($penpot->tags, 'included.held_by'))->toBeNull();
});

it('cancels the Penpot with its service, and brings it back when the cancellation is taken back', function () {
    $node = penpotLab();
    penpotParentPanel();
    [$user, $org] = $this->customerWithOrganization();
    $parent = featureWebService($org, 'aapanel');
    $penpot = penpotOfParent($org, $parent, $this->contextFor($user, $org));
    $services = app(ServiceService::class);

    $cancel = driveOperation($services->requestAction($parent, 'terminate', $this->contextFor($user, $org, 'webauthn'), 'pwp-term-1', ['reason' => 'customer request']), 25);
    expect($cancel->state)->toBe(Operation::SUCCEEDED, (string) data_get($cancel->error, 'message', ''));
    penpotDriveChild($penpot, 'terminate');
    expect($penpot->refresh()->terminate_at)->not->toBeNull()->and($penpot->state)->toBe(ServiceStateMachine::SUSPENDED)
        ->and(data_get($penpot->tags, 'included.ended_by'))->toBe($parent->id);

    // the customer takes the cancellation back (a plain resume, as the reinstatement after payment does): the Penpot comes back too
    driveOperation($services->requestAction($parent->refresh(), 'resume', $this->contextFor($user, $org), 'pwp-undo-1', ['reason' => 'přeci jen pokračujeme']), 25);
    penpotDriveChild($penpot, 'resume');
    expect($parent->refresh()->terminate_at)->toBeNull()
        ->and($penpot->refresh()->terminate_at)->toBeNull()->and($penpot->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and($node->stacks[PenpotInstances::stackName($penpot)])->toBe('running');
});

it('keeps one live Penpot per service in the database, and a new one is possible once the old one is gone', function () {
    $this->seed(CatalogSeeder::class);
    [, $org] = $this->customerWithOrganization();
    $parentId = 'srv_'.Str::lower((string) Str::ulid());
    $row = fn (string $state) => ['organization_id' => $org->id, 'product_key' => 'penpot', 'family' => 'penpot', 'name' => 'Penpot', 'state' => $state, 'region_code' => 'cz1',
        'entitlements' => [], 'sla_class' => 'standard', 'tags' => ['parent_service_id' => $parentId], 'desired_spec' => [], 'penpot_parent_id' => $parentId];
    $first = Service::query()->create($row(ServiceStateMachine::ACTIVE));
    expect(fn () => DB::transaction(fn () => Service::query()->create($row(ServiceStateMachine::PAID))))->toThrow(UniqueConstraintViolationException::class);
    $first->forceFill(['state' => ServiceStateMachine::TERMINATED])->save();
    expect(Service::query()->create($row(ServiceStateMachine::PAID))->exists)->toBeTrue();
});

it('lets only one of two racing deliveries create the Penpot, and only one of two racing checkouts take the order', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    penpotLab();
    Queue::fake();
    [$owner, $org] = $this->customerWithOrganization();
    $ctx = CommandContext::system('pwp race')->withScope($org->id);
    $parent = featureWebService($org, 'aapanel');
    $product = Product::query()->where('key', 'penpot')->firstOrFail();
    $version = PlanVersion::query()->whereHas('plan', fn ($q) => $q->where('product_id', $product->id)->where('key', 'penpot-team'))->firstOrFail();

    // two deliveries that both passed every check in code (each read "no Penpot yet"): the database lets one through
    app(ServiceService::class)->create($org, $product, $version, ['parent_service_id' => $parent->id], $ctx);
    expect(fn () => app(ServiceService::class)->create($org, $product, $version, ['parent_service_id' => $parent->id], $ctx))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('penpot_exists')->and($e->status)->toBe(409));
    expect(Service::query()->where('family', 'penpot')->where('penpot_parent_id', $parent->id)->count())->toBe(1);

    // two checkouts of two quotes for another service, made before either was placed: the second is refused, under the lock
    $other = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'plan_version_id' => $parent->plan_version_id, 'family' => 'web', 'name' => 'Web 2', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'sla_class' => 'standard', 'tags' => [], 'desired_spec' => [], 'hostname' => 'jiny.example.cz', 'activated_at' => now()]);
    app(WalletService::class)->topup($org, Money::decimal('500', 'CZK'), 'bank', 'pwp-seed-'.Str::random(6), $ctx);
    $quote = fn () => app(QuoteService::class)->quote([['product_key' => 'penpot', 'plan_key' => 'penpot-team', 'config' => ['parent_service_id' => $other->id]]], 'CZK', [], 1, null, $org);
    [$a, $b] = [$quote(), $quote()];
    $consents = [];
    foreach (app(CheckoutService::class)->requiredDocuments($a, $org) as $doc) {
        $consents[$doc] = ['person' => 'test'];
    }
    app(CheckoutService::class)->placeOrder($a, $org, $owner, $consents, ['mode' => 'wallet'], 'pwp-a', $ctx, 'panel');
    expect(fn () => DB::transaction(fn () => PenpotLine::claimParents($b, $org)))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('penpot_exists'));
    expect(fn () => app(CheckoutService::class)->placeOrder($b, $org, $owner, $consents, ['mode' => 'wallet'], 'pwp-b', $ctx, 'panel'))
        ->toThrow(fn (DomainError $e) => expect($e->error)->toBe('penpot_exists'));
});

it('offers Penpot on the service detail of the panel through the cart API, prototypes untouched', function () {
    [$user] = $this->customerWithOrganization();
    $workbench = (string) file_get_contents((string) $this->actingAs($user)->get('/surfaces/api/onhost-panel-workbench.api.js')->assertOk()->baseResponse->getFile());
    expect($workbench)->toContain("'/penpot-offer'")->toContain('window.OnhostPanelOrder.penpot(cmp, sel.id, pp)')->toContain('penpot_unavailable')->toContain("_('v ceně tarifu', 'included in the plan')");
    $order = (string) file_get_contents((string) $this->get('/surfaces/api/onhost-panel-order.api.js')->assertOk()->baseResponse->getFile());
    expect($order)->toContain("product_key: 'penpot', plan_key: 'penpot-team', qty: 1, config: { parent_service_id: serviceId }")->toContain("A.put('/cart'")->toContain("A.post('/orders'")
        ->toContain('penpot: penpot };');
});
