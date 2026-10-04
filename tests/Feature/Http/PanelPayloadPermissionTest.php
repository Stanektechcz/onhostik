<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Platform\Commands\CommandContext;
use Tests\TestCase;

/*
 * TASK-0053 / audit 2026-10 P0-1 (Phase B, B0): `/surfaces/onhost-panel.js` checked membership alone and handed every member —
 * a guest, a support contact, a developer — the organization's services, domains, credit, the open document and the bank details.
 * The payload now says what the member's own /v1 endpoints say, at the same scope: services by `service.read` (a guest: only the
 * services shared with them), domains by `domain.read`, credit by `billing.wallet.read`, documents by `billing.invoice.read`.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]));

/** An organization with two web services, a domain and an open proforma with a pending bank transfer. @return array{0:User,1:Organization,2:list<string>,3:string} */
function pppOrganization(TestCase $test): array
{
    [$owner, $org] = (fn () => $this->customerWithOrganization())->call($test);
    $first = featureWebService($org, 'ispconfig');
    $second = featureWebService($org, 'aapanel');
    $domain = graceDomain($org, 'tajna-domena.cz', 'ACTIVE', now()->addYear());
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'profi']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org);
    app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'bank'], 'ppp:q1', new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'test-session'));

    return [$owner, $org, [$first->id, $second->id], $domain->id];
}

function pppPayload(TestCase $test, User $user, Organization $org): array
{
    $test->actingAs($user);
    $seam = $test->get('/surfaces/onhost-panel.js?organization='.$org->id)->assertOk()->getContent();

    return json_decode(substr($seam, strlen('window.ONHOST_PANEL = '), -2), true, 512, JSON_THROW_ON_ERROR);
}

/** @return list<string> ids of the service rows (the state views repeat rows of the categories, the domain category holds domains) */
function pppServiceIds(array $payload): array
{
    $ids = [];
    foreach ($payload['services'] as $category => $rows) {
        if ($category !== 'domain' && ! str_starts_with((string) $category, 'state:')) {
            $ids = array_merge($ids, array_column($rows, 'id'));
        }
    }
    sort($ids);

    return $ids;
}

/** What the member's own API answers: list of ids, or null for a refusal. */
function pppApiIds(TestCase $test, User $user, Organization $org, string $path): ?array
{
    $test->actingAs($user, 'sanctum');
    $answer = $test->getJson($path, ['X-Organization' => $org->id]);
    if ($answer->status() === 403) {
        return null;
    }
    $answer->assertOk();
    $ids = array_column((array) $answer->json('data'), 'id');
    sort($ids);

    return $ids;
}

function pppApiAllows(TestCase $test, User $user, Organization $org, string $path): bool
{
    $test->actingAs($user, 'sanctum');
    $status = $test->getJson($path, ['X-Organization' => $org->id])->status();
    expect($status)->toBeIn([200, 403]);

    return $status === 200;
}

dataset('panel payload roles', function () {
    $rows = [];
    foreach (RoleCatalog::customerRoleKeys() as $role) {
        $rows[$role] = [$role, false];
    }
    $rows['guest with svc_view on one service'] = ['guest', true];

    return $rows;
});

it('gives every customer role exactly what its own API endpoints would give', function (string $role, bool $shared) {
    [$owner, $org, $serviceIds, $domainId] = pppOrganization($this);
    $member = $owner;
    if ($role !== 'owner') {
        $member = User::factory()->create(['email' => "ppp-{$role}".($shared ? '-shared' : '').'@example.cz']);
        app(OrganizationService::class)->attachMember($org, $member, $role, CommandContext::system('test'), joinedNow: true);
    }
    if ($shared) {
        PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $member->id, 'role_key' => 'svc_view', 'scope_type' => 'resource', 'scope_id' => $serviceIds[0], 'organization_id' => $org->id]);
    }

    $payload = pppPayload($this, $member, $org);
    $apiServices = pppApiIds($this, $member, $org, '/v1/services?limit=200') ?? [];
    $apiDomains = pppApiIds($this, $member, $org, '/v1/domains?limit=200') ?? [];
    $readsInvoices = pppApiAllows($this, $member, $org, '/v1/invoices');
    $readsWallet = pppApiAllows($this, $member, $org, '/v1/wallet');
    $readsPayments = pppApiAllows($this, $member, $org, '/v1/payments');
    $readsTickets = pppApiAllows($this, $member, $org, '/v1/tickets');

    $panelDomains = array_column($payload['services']['domain'] ?? [], 'id');
    sort($panelDomains);
    $billing = $payload['billing'] ?? null;
    expect(pppServiceIds($payload))->toBe($apiServices)
        ->and($panelDomains)->toBe($apiDomains)
        ->and(array_column($payload['servers'], 'id'))->each->toBeIn($apiServices)
        ->and(($payload['kpis']['credit'] ?? null) !== null)->toBe($readsWallet)
        ->and(($payload['kpis']['unpaid'] ?? null) !== null)->toBe($readsInvoices)
        ->and(($payload['kpis']['open_tickets'] ?? null) !== null)->toBe($readsTickets)
        ->and(($billing['document'] ?? null) !== null)->toBe($readsInvoices)
        ->and(($billing['bank']['iban'] ?? null) !== null)->toBe($readsInvoices)
        ->and(($billing['organization']['name'] ?? null) !== null)->toBe($readsInvoices)
        ->and(($billing['pending'] ?? []) !== [])->toBe($readsPayments)
        ->and(($billing['wallet'] ?? null) !== null)->toBe($readsWallet);

    // never vacuous: what the matrix compares is really there for the roles that read it, and really gone for the ones that do not
    $expectedServices = match (true) {
        $shared => [$serviceIds[0]],
        in_array($role, ['guest'], true) => [],
        default => $serviceIds,
    };
    sort($expectedServices);
    expect(pppServiceIds($payload))->toBe($expectedServices);
    $readsDomains = ! in_array($role, ['guest', 'support_contact'], true);
    expect($panelDomains)->toBe($readsDomains ? [$domainId] : []);
    if (in_array($role, ['guest', 'support_contact'], true)) {
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        expect($billing)->toBeNull()->and($payload['kpis']['credit'])->toBeNull()
            ->and($raw)->not->toContain('tajna-domena.cz')->not->toContain('"number":"PF-');
        if ($role === 'guest') {
            expect($raw)->not->toContain($serviceIds[1]);
        }
    }
})->with('panel payload roles');

it('counts in the sidebar only what the member sees, never the organization\'s other services', function () {
    [, $org, $serviceIds] = pppOrganization($this);
    $guest = User::factory()->create(['email' => 'ppp-nav-guest@example.cz']);
    app(OrganizationService::class)->attachMember($org, $guest, 'guest', CommandContext::system('test'), joinedNow: true);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $guest->id, 'role_key' => 'svc_view', 'scope_type' => 'resource', 'scope_id' => $serviceIds[0], 'organization_id' => $org->id]);

    $byKey = collect(pppPayload($this, $guest, $org)['nav']['categories'])->keyBy('key');
    expect($byKey['web']['owned'])->toBe(1)->and($byKey['web']['visible'])->toBeTrue()->and($byKey['web']['orderable'])->toBeFalse()
        ->and($byKey['domain']['owned'])->toBe(0)->and($byKey['domain']['visible'])->toBeFalse()
        ->and($byKey->where('visible', true)->keys()->all())->toBe(['web']); // a guest orders nothing: an empty category invites nothing
});
