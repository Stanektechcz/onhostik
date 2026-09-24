<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\Website;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Dns\RecordResolver;
use Onhost\Platform\Errors\DomainError;

/*
 * Whose name is it?
 *
 * A shared web node serves dozens of customers from one address, and a web server decides which site answers a
 * request purely by the name in it. The platform therefore has exactly one thing to protect: **a name belongs to one
 * service**. It did protect it where a site is added to an existing hosting (`ServiceSites::assertRoomFor` refuses a
 * domain another service holds) — and nowhere else:
 *
 *  - `ssl.issue` took any host name the customer sent. `DomainPointing` (audit row 82) then checks the name really
 *    points at the node — and a neighbour's domain **does** point at the node, because they live on the same one. So
 *    the platform asked a certificate authority for a certificate for another customer's domain: at best it spends
 *    that domain's hourly allowance (five checks) and can keep its owner from ever getting one, at worst the name
 *    lands on somebody else's certificate.
 *  - `subdomain.add` took any host name too, so a customer could write a neighbour's domain into their own vhost's
 *    server names — the textbook shared-hosting hijack — and spend their own plan's allowance on it.
 *  - The order itself, the way every hosting is actually created, took `domain` and `aliases` straight out of the
 *    cart with no check at all: two services could claim one name, and the alias list was not even checked for being
 *    host names.
 *
 * One rule, one place: a name may be claimed by one service, a name under another organization's name belongs to
 * that organization, and everything else — a name the world holds but nobody here does — is left to the layers that
 * already answer for it (the certificate authority, the panel, DNS).
 */

beforeEach(fn () => Http::preventStrayRequests());

/** Public DNS as the world answers it right now. */
function claimDns(array $answers): void
{
    app()->bind(RecordResolver::class, fn () => new class($answers) implements RecordResolver
    {
        public function __construct(private readonly array $answers) {}

        public function records(string $name, string $type): array
        {
            return (array) ($this->answers[mb_strtolower($name).'|'.$type] ?? []);
        }
    });
}

/** aaPanel that remembers what it was asked to certify and which names it was told to serve. */
function claimPanel(array &$asked): void
{
    Http::fake(function (Request $request) use (&$asked) {
        $query = (string) parse_url($request->url(), PHP_URL_QUERY);
        foreach (['apply_cert_api' => 'certificate', 'AddDomain' => 'domain'] as $needle => $what) {
            if (str_contains($query, $needle)) {
                $asked[] = ['what' => $what] + $request->data();

                return Http::response($what === 'certificate'
                    ? ['status' => true, 'cert' => "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----", 'private' => "-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----"]
                    : ['status' => true, 'msg' => 'ok']);
            }
        }

        return Http::response(['status' => true, 'msg' => 'ok', 'data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']], 'page' => '']);
    });
}

/** A web hosting on the shared node, with the names its site really serves. */
function claimWeb(object $org, string $domain = 'shop.cz', array $aliases = [], int $remoteId = 41): Service
{
    $service = featureWebService($org, 'aapanel');
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode(['public_ipv4' => '203.0.113.10'])]);
    $service->forceFill(['hostname' => $domain, 'desired_spec' => array_merge((array) $service->desired_spec, ['domain' => $domain])])->save();
    ProviderBinding::query()->where('service_id', $service->id)->update(['remote_id' => (string) $remoteId, 'meta' => json_encode(['name' => $domain, 'path' => "/www/wwwroot/{$domain}"])]);
    Website::query()->create(['service_id' => $service->id, 'domain' => $domain, 'aliases' => $aliases, 'executor' => 'aapanel', 'php_version' => '8.3',
        'remote_site_id' => $remoteId, 'remote_node' => 'aapanel-managed01', 'state' => 'active']);

    return $service->refresh();
}

it('never asks a certificate authority for a name the neighbour on the same node holds', function () {
    $asked = [];
    claimPanel($asked);
    // both live on 203.0.113.10, so the neighbour's domain passes every "does it point at us" test there is
    claimDns(['shop.cz|A' => [['ip' => '203.0.113.10']], 'soused.cz|A' => [['ip' => '203.0.113.10']]]);
    [, $neighbour] = $this->customerWithOrganization();
    claimWeb($neighbour, 'soused.cz', [], 77);
    [$user, $org] = $this->customerWithOrganization();
    $service = claimWeb($org, 'shop.cz');

    expect(fn () => app(ServiceService::class)->requestAction($service, 'ssl.issue', $this->contextFor($user, $org), 'claim-cert-1', ['domains' => ['shop.cz', 'soused.cz']]))
        ->toThrow(DomainError::class, 'soused.cz');
    expect($asked)->toBe([]); // nothing was asked of the panel, so nothing was asked of the authority
});

it('still issues a certificate for every name the service itself serves', function () {
    $asked = [];
    claimPanel($asked);
    claimDns(['shop.cz|A' => [['ip' => '203.0.113.10']], 'www.shop.cz|A' => [['ip' => '203.0.113.10']], 'eshop.shop.cz|A' => [['ip' => '203.0.113.10']], 'shop.sk|A' => [['ip' => '203.0.113.10']]]);
    [$user, $org] = $this->customerWithOrganization();
    $service = claimWeb($org, 'shop.cz', ['shop.sk']); // a second domain the customer added to the same site

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'ssl.issue', $this->contextFor($user, $org), 'claim-cert-2', ['domains' => ['shop.cz', 'www.shop.cz', 'eshop.shop.cz', 'shop.sk']]));

    expect($operation->state)->toBe(Operation::SUCCEEDED, $operation->step_label.': '.(string) data_get($operation->error, 'message', ''));
    $names = $asked[0]['domains'] ?? null;
    $names = is_string($names) ? (json_decode($names, true) ?: []) : (array) $names;
    expect($names)->toBe(['shop.cz', 'www.shop.cz', 'eshop.shop.cz', 'shop.sk']);
});

it('refuses to write a neighbour name into this site server names', function () {
    $asked = [];
    claimPanel($asked);
    [, $neighbour] = $this->customerWithOrganization();
    claimWeb($neighbour, 'soused.cz', [], 77);
    [$user, $org] = $this->customerWithOrganization();
    $service = claimWeb($org, 'shop.cz');

    expect(fn () => app(ServiceService::class)->requestAction($service, 'subdomain.add', $this->contextFor($user, $org), 'claim-sub-1', ['domain' => 'soused.cz']))
        ->toThrow(DomainError::class, 'soused.cz');
    // a name under the neighbour's domain is the neighbour's too: their wildcard would send it to whoever serves it here
    expect(fn () => app(ServiceService::class)->requestAction($service, 'subdomain.add', $this->contextFor($user, $org), 'claim-sub-2', ['domain' => 'blog.soused.cz']))
        ->toThrow(DomainError::class, 'soused.cz');
    expect($asked)->toBe([]);
});

it('lets a customer add their own subdomain and a domain nobody here holds', function () {
    $asked = [];
    claimPanel($asked);
    [$user, $org] = $this->customerWithOrganization();
    $service = claimWeb($org, 'shop.cz');

    $own = driveOperation(app(ServiceService::class)->requestAction($service, 'subdomain.add', $this->contextFor($user, $org), 'claim-sub-3', ['domain' => 'blog.shop.cz']));
    $second = driveOperation(app(ServiceService::class)->requestAction($service, 'subdomain.add', $this->contextFor($user, $org), 'claim-sub-4', ['domain' => 'shop.sk']));

    expect($own->state)->toBe(Operation::SUCCEEDED, $own->step_label.': '.(string) data_get($own->error, 'message', ''))
        ->and($second->state)->toBe(Operation::SUCCEEDED, $second->step_label.': '.(string) data_get($second->error, 'message', ''))
        ->and(array_column($asked, 'domain'))->toBe(['blog.shop.cz', 'shop.sk']);
});

it('will not sell a second hosting for a domain this platform already serves', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    [, $neighbour] = $this->customerWithOrganization();
    claimWeb($neighbour, 'soused.cz', [], 77);
    [$user, $org] = $this->customerWithOrganization();

    $this->actingAs($user, 'sanctum')->withHeader('X-Organization', $org->id)
        ->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => ['domain' => 'soused.cz']]], 'currency' => 'CZK'])->assertOk();

    $quote = $this->postJson('/v1/cart/quote');

    expect($quote->status())->toBe(409)
        ->and((string) $quote->json('error'))->toBe('site_name_taken')
        ->and((string) json_encode($quote->json()))->toContain('soused.cz');
});

it('will not sell a hosting whose further names are not names at all', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    [$user, $org] = $this->customerWithOrganization();

    $this->actingAs($user, 'sanctum')->withHeader('X-Organization', $org->id)
        ->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => ['domain' => 'muj-web.cz', 'aliases' => ['www.muj-web.cz', 'not a domain']]]], 'currency' => 'CZK'])->assertOk();

    $quote = $this->postJson('/v1/cart/quote');

    expect($quote->status())->toBe(422)
        ->and((string) $quote->json('error'))->toBe('action_param_invalid');
});

it('names the customer own service when the domain is already theirs', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    [$user, $org] = $this->customerWithOrganization();
    claimWeb($org, 'muj-web.cz');

    $this->actingAs($user, 'sanctum')->withHeader('X-Organization', $org->id)
        ->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => ['domain' => 'muj-web.cz']]], 'currency' => 'CZK'])->assertOk();

    $quote = $this->postJson('/v1/cart/quote');

    expect($quote->status())->toBe(409)
        ->and((string) $quote->json('message'))->toContain('vaše služba')   // their own, so they can act on it
        ->and((string) $quote->json('message'))->toContain('Webhosting Standard');
});

it('will not order one name twice in one cart', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    [$user, $org] = $this->customerWithOrganization();

    $this->actingAs($user, 'sanctum')->withHeader('X-Organization', $org->id)->putJson('/v1/cart', ['items' => [
        ['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => ['domain' => 'muj-web.cz']],
        ['line_id' => 'l2', 'product_key' => 'web-hosting', 'plan_key' => 'start', 'qty' => 1, 'config' => ['domain' => 'muj-web.cz']],
    ], 'currency' => 'CZK'])->assertOk();

    expect($this->postJson('/v1/cart/quote')->status())->toBe(409);
});

it('refuses a wildcard certificate for a neighbour domain', function () {
    $asked = [];
    claimPanel($asked);
    [, $neighbour] = $this->customerWithOrganization();
    claimWeb($neighbour, 'soused.cz', [], 77);
    [$user, $org] = $this->customerWithOrganization();
    $service = claimWeb($org, 'shop.cz');

    expect(fn () => app(ServiceService::class)->requestAction($service, 'ssl.wildcard', $this->contextFor($user, $org), 'claim-wild-1', ['domain' => 'soused.cz']))
        ->toThrow(DomainError::class, 'soused.cz');
});

it('sells web hosting for a domain that already has mail here, and the other way round', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    [$user, $org] = $this->customerWithOrganization();
    featureMailService($org, 'muj-web.cz'); // a mailbox domain of the same name is the ordinary case, not a clash

    $this->actingAs($user, 'sanctum')->withHeader('X-Organization', $org->id)
        ->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => ['domain' => 'muj-web.cz']]], 'currency' => 'CZK'])->assertOk();

    expect($this->postJson('/v1/cart/quote')->assertOk()->json('data.lines.0.config.domain'))->toBe('muj-web.cz');
});

it('sells a hosting for a free domain as it always did', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class]);
    [$user, $org] = $this->customerWithOrganization();

    $this->actingAs($user, 'sanctum')->withHeader('X-Organization', $org->id)
        ->putJson('/v1/cart', ['items' => [['line_id' => 'l1', 'product_key' => 'web-hosting', 'plan_key' => 'standard', 'qty' => 1, 'config' => ['domain' => 'muj-web.cz', 'aliases' => ['www.muj-web.cz']]]], 'currency' => 'CZK'])->assertOk();

    expect($this->postJson('/v1/cart/quote')->assertOk()->json('data.lines.0.config.domain'))->toBe('muj-web.cz');
});
