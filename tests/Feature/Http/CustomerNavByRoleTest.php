<?php

declare(strict_types=1);

use App\Http\Controllers\Web\SurfaceDataController;
use Database\Seeders\CatalogSeeder;
use Onhost\Domain\Catalog\PanelNavigation;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Settings\SettingsStore;
use Tests\TestCase;

/*
 * TASK-0053 / audit 2026-10 Phase B, B7: the customer sidebar ignored the role — a viewer was offered the order wizard and the
 * API keys, a support contact the billing, a guest everything. Every entry now names the permissions its view's endpoints ask
 * (SurfaceDataController::NAV_REQUIRES); `nav.links` keep the staff switch AND the permission, `nav.areas` are the fixed entries.
 */

beforeEach(fn () => $this->seed([CatalogSeeder::class]));

function cnrPayload(TestCase $test, User $user, Organization $org): array
{
    $test->actingAs($user);
    $seam = $test->get('/surfaces/onhost-panel.js?organization='.$org->id)->assertOk()->getContent();

    return json_decode(substr($seam, strlen('window.ONHOST_PANEL = '), -2), true, 512, JSON_THROW_ON_ERROR);
}

function cnrMember(TestCase $test, string $role): array
{
    [$owner, $org] = (fn () => $this->customerWithOrganization())->call($test);
    if ($role === 'owner') {
        return [$owner, $org];
    }
    $member = User::factory()->create(['email' => "cnr-{$role}@example.cz"]);
    app(OrganizationService::class)->attachMember($org, $member, $role, CommandContext::system('test'), joinedNow: true);

    return [$member, $org];
}

/** Every sidebar entry the member is NOT offered, per customer role (the rest of NAV_REQUIRES is offered). */
dataset('customer nav roles', [
    'owner' => ['owner', []],
    'org_admin' => ['org_admin', []],
    'billing_admin' => ['billing_admin', ['api']],
    'partner' => ['partner', ['api', 'topup']],
    'domain_manager' => ['domain_manager', ['api', 'order', 'topup']],
    'dns_manager' => ['dns_manager', ['api', 'order', 'topup']],
    'developer' => ['developer', ['api', 'order', 'topup']],
    'cloud_operator' => ['cloud_operator', ['api', 'order', 'topup']],
    'game_operator' => ['game_operator', ['api', 'order', 'topup']],
    'mail_manager' => ['mail_manager', ['api', 'order', 'topup']],
    'security_auditor' => ['security_auditor', ['api', 'order', 'topup']],
    'viewer' => ['viewer', ['api', 'order', 'topup']],
    'support_contact' => ['support_contact', ['api', 'registrars', 'audit', 'costs', 'backups', 'order', 'billing', 'topup', 'domains']],
    'guest' => ['guest', ['team', 'projects', 'windows', 'api', 'registrars', 'audit', 'costs', 'monitoring', 'backups', 'order', 'tickets', 'billing', 'topup', 'domains', 'calendar']],
]);

it('covers every customer role of the catalogue in the matrix', function () {
    $matrix = ['owner', 'org_admin', 'billing_admin', 'partner', 'domain_manager', 'dns_manager', 'developer', 'cloud_operator', 'game_operator', 'mail_manager', 'security_auditor', 'viewer', 'support_contact', 'guest'];
    expect(collect(RoleCatalog::customerRoleKeys())->sort()->values()->all())->toBe(collect($matrix)->sort()->values()->all())
        ->and(array_keys(SurfaceDataController::NAV_REQUIRES))->toContain(...array_keys(PanelNavigation::LINKS)); // no switchable link without a requirement
});

it('offers each customer role only the sidebar entries its permissions open', function (string $role, array $hidden) {
    [$member, $org] = cnrMember($this, $role);
    $nav = cnrPayload($this, $member, $org)['nav'];

    $offered = array_merge($nav['links'], $nav['areas']);
    expect(array_keys($nav['links']))->toBe(array_keys(PanelNavigation::LINKS))
        ->and($nav['requires'])->toBe(SurfaceDataController::NAV_REQUIRES)
        ->and(array_keys($offered))->toEqualCanonicalizing(array_keys(SurfaceDataController::NAV_REQUIRES))
        ->and(array_keys(array_filter($offered, fn (bool $on) => ! $on)))->toEqualCanonicalizing($hidden);

    // the entries agree with the permissions the role really carries (role_permissions, what the API asks)
    $carries = RoleCatalog::all()[$role]['permissions'];
    foreach (SurfaceDataController::NAV_REQUIRES as $key => $needs) {
        expect($offered[$key])->toBe(array_diff($needs, $carries) === [], "{$role}: {$key}");
    }
})->with('customer nav roles');

it('lists an empty category only to somebody who may order into it, and a guest only the categories of what was shared', function () {
    [$owner, $org] = cnrMember($this, 'owner');
    $site = featureWebService($org, 'ispconfig');
    $viewer = User::factory()->create(['email' => 'cnr-cat-viewer@example.cz']);
    app(OrganizationService::class)->attachMember($org, $viewer, 'viewer', CommandContext::system('test'), joinedNow: true);
    $guest = User::factory()->create(['email' => 'cnr-cat-guest@example.cz']);
    app(OrganizationService::class)->attachMember($org, $guest, 'guest', CommandContext::system('test'), joinedNow: true);

    $visible = fn (User $user) => collect(cnrPayload($this, $user, $org)['nav']['categories'])->where('visible', true)->pluck('key')->all();
    expect($visible($owner))->toContain('web', 'domain', 'game') // the owner orders: what the catalogue sells stays listed
        ->and($visible($viewer))->toBe(['web']) // a viewer reads what runs, orders nothing
        ->and($visible($guest))->toBe([]); // nothing shared yet

    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $guest->id, 'role_key' => 'svc_view', 'scope_type' => 'resource', 'scope_id' => $site->id, 'organization_id' => $org->id]);
    expect($visible($guest))->toBe(['web']);
});

it('keeps the staff switch: a link switched off stays off for the owner, and a permission never switches it back on', function () {
    app(SettingsStore::class)->set(PanelNavigation::SETTING, ['links' => ['api' => false, 'audit' => false]]);
    [$owner, $org] = cnrMember($this, 'owner');
    $nav = cnrPayload($this, $owner, $org)['nav'];
    expect($nav['links']['api'])->toBeFalse()->and($nav['links']['audit'])->toBeFalse()->and($nav['links']['team'])->toBeTrue()
        ->and($nav['areas']['billing'])->toBeTrue();
});

it('makes the sidebar seam follow nav.areas for the fixed entries and their deep links', function () {
    $nav = (string) file_get_contents(base_path('apps/surfaces/api/onhost-panel-nav.api.js'));
    expect($nav)->toContain('function areas() { var n = nav(); return (n && n.areas) || {}; }')
        ->toContain("var support = ar.tickets !== false ? [[_('Tikety', 'Tickets'), 'tickets', {}]] : [];")
        ->toContain("var account = ar.billing !== false ? [['billing', _('Fakturace', 'Billing'), due ? String(due) : '']] : [];")
        ->toContain("if (ar.order !== false) out.push(['market', _('Objednat', 'Order'), [")
        ->toContain("var open = ['overview', 'svcdesk', 'settings'];\n    ['order', 'tickets', 'billing'].forEach(function (k) { if (ar[k] !== false) open.push(k); });")
        ->toContain("when(ar.topup !== false, [cs ? 'Dobít kredit' : 'Top up credit',")
        ->toContain("when(ar.domains !== false, [cs ? 'Domény a DNS' : 'Domains and DNS',")
        ->not->toContain("var open = ['overview', 'svcdesk', 'order', 'tickets', 'billing', 'settings'];");
    $this->get('/surfaces/api/onhost-panel-nav.api.js')->assertOk();
});
