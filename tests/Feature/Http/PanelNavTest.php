<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\PanelNavigation;
use Onhost\Platform\Settings\SettingsStore;
use Tests\TestCase;

/* The customer panel's sidebar follows the offer (domains/Catalog/PanelNavigation.php): staff switches, catalogue state and what the organization already runs. */

beforeEach(fn () => $this->seed([CatalogSeeder::class]));

function panelNavPayload(TestCase $test, $user): array
{
    $test->actingAs($user);
    $seam = $test->get('/surfaces/onhost-panel.js')->assertOk()->getContent();

    return json_decode(substr($seam, strlen('window.ONHOST_PANEL = '), -2), true, 512, JSON_THROW_ON_ERROR);
}

it('lists the categories the catalogue sells, hides sold-out ones and keeps a switched-off category for an organization that owns a service in it', function () {
    Http::fake();
    [$user, $org] = $this->customerWithOrganization();
    $payload = panelNavPayload($this, $user);
    $byKey = collect($payload['nav']['categories'])->keyBy('key');
    expect(array_keys($byKey->all()))->toBe(['domain', 'web', 'game', 'vps', 'mail', 'bucket', 'housing'])
        ->and($byKey['web']['visible'])->toBeTrue()->and($byKey['domain']['visible'])->toBeTrue()->and($byKey['game']['offered'])->toBeTrue()
        ->and($byKey['bucket']['visible'])->toBeFalse()->and($byKey['housing']['visible'])->toBeFalse() // nothing active in the catalogue
        ->and($payload['nav']['links'])->toBe(['kb' => true, 'status' => true, 'team' => true, 'api' => true, 'projects' => true, 'registrars' => true, 'audit' => true, 'windows' => true, 'costs' => true, 'privacy' => true, 'monitoring' => true, 'backups' => true])
        ->and(collect($payload['catalog'])->firstWhere('key', 'web-hosting')['category'])->toBe('web')
        ->and(collect($payload['catalog'])->firstWhere('key', 'apps'))->toBeNull(); // Kubernetes is not an operated executor: the product is a draft

    // staff switch web hosting off: it leaves the sidebar and the wizard of an organization without a web service …
    app(SettingsStore::class)->set(PanelNavigation::SETTING, ['categories' => ['web' => ['enabled' => false]]]);
    $payload = panelNavPayload($this, $user);
    expect(collect($payload['nav']['categories'])->firstWhere('key', 'web')['visible'])->toBeFalse()
        ->and(collect($payload['catalog'])->firstWhere('key', 'web-hosting')['orderable'])->toBeFalse();

    // … but never of one that runs a site there (it can still manage it, only not order more)
    featureWebService($org, 'ispconfig');
    $payload = panelNavPayload($this, $user);
    $web = collect($payload['nav']['categories'])->firstWhere('key', 'web');
    expect($web['visible'])->toBeTrue()->and($web['owned'])->toBe(1)->and($web['orderable'])->toBeFalse()
        ->and($payload['services']['web'])->toHaveCount(1);

    // a product taken off the market hides its category for everybody without it
    Product::query()->where('family', 'game')->update(['state' => 'draft']);
    $payload = panelNavPayload($this, $user);
    expect(collect($payload['nav']['categories'])->firstWhere('key', 'game')['visible'])->toBeFalse();
});

it('lets staff read and edit the sidebar configuration and refuses it to customers and overlong labels', function () {
    $this->actingAs($this->staff('platform_owner'), 'sanctum');
    $shown = $this->getJson('/v1/staff/settings/panel-nav')->assertOk();
    expect($shown->json('data.categories.0.key'))->toBe('domain')->and($shown->json('data.categories.1.offered'))->toBeTrue()->and($shown->json('data.links'))->toHaveCount(12);

    $this->putJson('/v1/staff/settings/panel-nav', [
        'categories' => ['vps' => ['enabled' => true, 'order' => 0, 'label' => ['cs' => 'Virtuální servery', 'en' => 'Virtual servers']], 'domain' => ['order' => 5], 'housing' => ['enabled' => false]],
        'links' => ['kb' => false],
    ], ['Idempotency-Key' => 'nav-1'])->assertOk()->assertJsonPath('panel_nav.links.kb', false);
    $shown = $this->getJson('/v1/staff/settings/panel-nav')->assertOk();
    expect($shown->json('data.categories.0.key'))->toBe('vps')->and($shown->json('data.categories.0.label.cs'))->toBe('Virtuální servery')
        ->and(collect($shown->json('data.categories'))->firstWhere('key', 'housing')['enabled'])->toBeFalse()
        ->and(collect($shown->json('data.links'))->firstWhere('key', 'kb')['enabled'])->toBeFalse();
    $this->putJson('/v1/staff/settings/panel-nav', ['categories' => ['vps' => ['label' => ['cs' => str_repeat('x', 41)]]]], ['Idempotency-Key' => 'nav-2'])->assertUnprocessable();

    // the panel follows: VPS first, renamed, knowledge base link gone
    [$user] = $this->customerWithOrganization();
    $payload = panelNavPayload($this, $user);
    expect($payload['nav']['categories'][0]['key'])->toBe('vps')->and($payload['nav']['categories'][0]['label']['cs'])->toBe('Virtuální servery')->and($payload['nav']['links']['kb'])->toBeFalse();
    $this->actingAs($user, 'sanctum');
    $this->getJson('/v1/staff/settings/panel-nav')->assertForbidden();
});
