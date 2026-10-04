<?php

declare(strict_types=1);

use App\Http\Navigation\NavItem;
use App\Http\Navigation\StaffNavigation;
use Illuminate\Support\Facades\Route;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RoleCatalog;

/* Audit 2026-10 P1-1 / P1-4, packages B2 + B4: the staff console's navigation follows permissions, and so do the settings pages. */

/** @return array<string, list<string>> */
function staffNavSnapshot(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/staff-nav-snapshot.json'), true, flags: JSON_THROW_ON_ERROR);
}

/** The boot object of a rendered surface (`window.ONHOST = {...};`). */
function staffNavBoot(string $html): array
{
    preg_match('~<script>window\.ONHOST = (\{.*?\});</script>~s', $html, $m);

    return json_decode($m[1] ?? 'null', true, flags: JSON_THROW_ON_ERROR) ?? [];
}

it('shows every staff role of the role catalogue exactly the items of the snapshot', function () {
    $snapshot = staffNavSnapshot();
    $staffRoles = array_keys(array_filter(RoleCatalog::all(), fn (array $r) => $r['staff']));
    expect(array_keys($snapshot))->toEqualCanonicalizing($staffRoles);

    foreach ($staffRoles as $role) {
        $nav = app(StaffNavigation::class)->for($this->staff($role));
        expect(array_column($nav, 'key'))->toBe($snapshot[$role], "navigation of {$role}");
    }
});

it('gives a member of staff without permissions only the overview and the approvals page, and the console role none (R10)', function () {
    $nobody = $this->staff('auditor_read_only');
    PolicyBinding::query()->where('principal_id', $nobody->id)->delete();
    app(Authorizer::class)->flush();

    $nav = app(StaffNavigation::class)->for($nobody);
    expect(array_column($nav, 'key'))->toBe(['dash', 'approvals'])
        ->and($nav[0]['api'])->toBe([]); // the overview reads nothing the person may not call

    $this->actingAs($nobody);
    $boot = staffNavBoot($this->get('/sprava')->assertOk()->getContent());
    expect($boot['user']['role'])->toBe('none')->and(array_column($boot['user']['nav'], 'key'))->toBe(['dash', 'approvals']);
});

it('narrows every visible item to the reads the person may call', function () {
    $nav = collect(app(StaffNavigation::class)->for($this->staff('support_l1')))->keyBy('key');

    // support L1 reads the queue but manages no queue (clusters) — the overview still offers its tickets
    expect(array_column($nav['queue']['api'], 'path'))->toContain('staff/tickets')->toContain('staff/tickets/macros')->not->toContain('staff/tickets/clusters')
        ->and(array_column($nav['dash']['api'], 'path'))->toContain('staff/tickets')->not->toContain('staff/orders')->not->toContain('staff/outbox')
        ->and($nav['queue'])->toHaveKeys(['key', 'section', 'order', 'icon', 'label', 'screen', 'api', 'required_permissions'])
        ->and($nav['queue']['label'])->toBe(['cs' => 'Fronta tiketů', 'en' => 'Ticket queue'])
        ->and($nav['queue']['screen'])->toBe(['type' => 'view', 'target' => 'queue'])
        ->and($nav['queue']['required_permissions'])->toBe(['any' => [], 'all' => ['staff.support.ticket.read']]);
});

it('delivers the navigation in the boot object of the staff console, and none to a customer', function () {
    $this->actingAs($this->staff('billing_operator'));
    $html = $this->get('/sprava')->assertOk()->getContent();
    $boot = staffNavBoot($html);
    expect(array_column($boot['user']['nav'], 'key'))->toBe(staffNavSnapshot()['billing_operator'])
        ->and($boot['user']['role'])->toBe('fakturace')
        // the console reads labels, page headers and the "Doklady" quick action from the module, not from fixed lists
        ->and($html)->toContain('window.OnhostAdmin.label(this, x[0])')->toContain('window.OnhostAdmin.head(this, s.view)')
        ->toContain('window.OnhostAdmin.documents(this, _)')->not->toContain("this.setState({ view: 'invoices' }); return; }");

    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer);
    expect(staffNavBoot($this->get('/panel')->assertOk()->getContent())['user']['nav'])->toBe([]);
});

it('keeps the prototype surfaces byte-identical', function () {
    $expected = [
        'Onhost-admin.dc.html' => '7b32e8e796af6abe8bb4e58d2c671735cb429d50181114c117ef226ce06ec30b',
        'Onhost-app.dc.html' => 'aaf3fe7570d2c408303cd655173f7a0949882ccc866f994b6a9166f3460127ca',
        'Onhost-mobil.dc.html' => '57c4b440b335a759bc1d23a9beae7b4c1b80604802ecb1a17d0b18335a536c29',
        'Onhost-partner.dc.html' => '777ff7d4c2bb59d23323c1b7b83cf0363e58e22a614eb1b5aa621d61e233bdc1',
        'Onhost-widgets.dc.html' => '769e26f061923bd034338b78c79cc0bec705b6d4b63fbb2f7034827a60636e7f',
        'Onhost.dc.html' => '8e69a7fb360443ef808dee4acacd15d158e7a953d326439a2047940d14aff1a1',
    ];
    foreach ($expected as $file => $sha) {
        expect(hash_file('sha256', base_path('apps/surfaces/'.$file)))->toBe($sha, $file);
    }
});

it('maps every console view of the navigation to a view the prototype console knows, once', function () {
    $html = (string) file_get_contents(base_path('apps/surfaces/Onhost-admin.dc.html'));
    preg_match("~^      \\['lead', [^\\n]*?, \\[('dash'[^\\n]*)\\]\\],\$~m", $html, $lead); // the shift lead's view list: the console persona
    $views = array_filter(StaffNavigation::items(), fn (NavItem $i) => $i->screenType === NavItem::SCREEN_VIEW);
    $targets = array_values(array_map(fn (NavItem $i) => $i->target, $views));
    expect($targets)->toBe(array_values(array_unique($targets)));
    foreach ($views as $item) {
        expect(str_contains($lead[1] ?? '', "'{$item->target}'"))->toBeTrue("{$item->key} → {$item->target} is in the lead persona");
        expect(str_contains($html, "        ['{$item->target}', _("))->toBeTrue("{$item->key} → {$item->target} has a sidebar entry");
    }
});

it('covers every staff GET route with a navigation read or a reasoned allow-list entry', function () {
    $covered = [];
    foreach (StaffNavigation::items() as $item) {
        foreach ($item->api as $api) {
            $covered[$api['method'].' '.$api['path']] = true;
        }
    }
    $missing = [];
    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();
        if (! str_starts_with($uri, 'v1/staff/') || ! in_array('GET', $route->methods(), true)) {
            continue;
        }
        $path = substr($uri, 3);
        if (! isset($covered['GET '.$path]) && ! isset(StaffNavigation::API_ALLOW_LIST[$path])) {
            $missing[] = $path;
        }
    }
    expect($missing)->toBe([]);

    // and every read the navigation names exists as a route
    $routes = collect(Route::getRoutes()->getRoutes())->flatMap(fn ($r) => array_map(fn ($m) => $m.' '.substr($r->uri(), 3), $r->methods()))->flip();
    foreach (array_keys($covered) as $read) {
        expect($routes->has($read))->toBeTrue("{$read} is a route");
    }
});

it('leaves no staff permission without an item unless it is a reasoned write-only or endpoint-less key', function () {
    $inItems = [];
    foreach (StaffNavigation::items() as $item) {
        foreach ($item->api as $api) {
            foreach (NavItem::accepts($api) as $p) {
                $inItems[$p] = true;
            }
        }
        foreach ([...$item->any, ...$item->all] as $p) {
            $inItems[$p] = true;
            expect(PermissionCatalog::all()[$p]['audience'] ?? null)->toBe('staff', "{$item->key} requires the staff key {$p}");
        }
    }
    $staff = array_keys(array_filter(PermissionCatalog::all(), fn (array $p) => $p['audience'] === 'staff'));
    $without = array_values(array_diff($staff, array_keys($inItems)));
    expect($without)->toEqualCanonicalizing(array_keys(StaffNavigation::PERMISSIONS_WITHOUT_ITEM))
        ->and(array_intersect(array_keys(StaffNavigation::PERMISSIONS_WITHOUT_ITEM), array_keys($inItems)))->toBe([]);
});

it('authorizes each system settings page like its navigation item (P1-4)', function () {
    // product manager: plans, pricing and lifecycle yes; operations, bulk actions no; integrations sends them to their first page
    $this->actingAs($this->staff('product_manager'));
    $this->get('/sprava/nastaveni/tarify')->assertOk();
    $this->get('/sprava/nastaveni/zivotni-cyklus')->assertOk();
    $this->get('/sprava/nastaveni/integrace')->assertOk(); // pricing and the panel navigation live on that page
    $this->get('/sprava/nastaveni/provoz')->assertForbidden();
    $this->get('/sprava/nastaveni/hromadne-akce')->assertForbidden();
    $this->get('/sprava/nastaveni/schvalovani')->assertOk(); // every member of staff watches the requests they opened

    $this->actingAs($this->staff('marketing_content'));
    $this->get('/sprava/nastaveni/integrace')->assertRedirect('/sprava/nastaveni/schvalovani');
    $this->get('/sprava/nastaveni/tarify')->assertForbidden();
    $this->get('/sprava/nastaveni/zivotni-cyklus')->assertForbidden();

    $this->actingAs($this->staff('support_l1'));
    $this->get('/sprava/nastaveni/provoz')->assertOk();
    $this->get('/sprava/nastaveni/hromadne-akce')->assertOk();
    $this->get('/sprava/nastaveni/tarify')->assertForbidden();
    $this->get('/sprava/nastaveni/integrace')->assertRedirect('/sprava/nastaveni/provoz');

    [$customer] = $this->customerWithOrganization();
    $this->actingAs($customer);
    $this->get('/sprava/nastaveni/tarify')->assertRedirect('/panel');
});
