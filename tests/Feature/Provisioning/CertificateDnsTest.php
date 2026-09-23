<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\Website;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Dns\RecordResolver;

/*
 * A certificate is issued by an authority that first checks the name really is served here. The platform asked for one
 * whatever DNS said: the panel tried, the authority refused, the customer got a panel error nobody can read — and every
 * failed check counts against the authority's limit for that hostname (five an hour), so a domain that is simply not
 * pointed at us yet spends the day burning the allowance of the node it will live on. `CertificateAutoIssuer` waited
 * for DNS, but only for a site that was still marked `pending_dns`; the action itself — the customer's button, a
 * paired domain, a re-issue — never looked. It looks now, at the one place that asks the panel, and asks only for the
 * names that already answer with this node.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** Public DNS as the world answers it right now. */
function certDns(array $answers): void
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

/** aaPanel that remembers which names it was asked to certify. */
function certPanel(array &$asked): void
{
    Http::fake(function (Request $request) use (&$asked) {
        $query = (string) parse_url($request->url(), PHP_URL_QUERY);
        if (str_contains($query, 'apply_cert_api')) {
            $asked[] = $request->data();

            return Http::response(['status' => true, 'cert' => "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----", 'private' => "-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----"]);
        }

        return Http::response(['status' => true, 'msg' => 'ok', 'data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']], 'page' => '']);
    });
}

/** A web service on a node with a known address, and the site's further names. */
function certService(object $org, array $aliases = []): Service
{
    $service = featureWebService($org, 'aapanel');
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode(['public_ipv4' => '203.0.113.10'])]);
    Website::query()->create(['service_id' => $service->id, 'domain' => 'shop.cz', 'aliases' => $aliases, 'executor' => 'aapanel', 'php_version' => '8.3',
        'remote_site_id' => 41, 'remote_node' => 'aapanel-managed01', 'state' => 'active']);

    return $service->refresh();
}

it('does not ask for a certificate for a domain that does not point at us', function () {
    $asked = [];
    certPanel($asked);
    certDns(['shop.cz|A' => [['ip' => '198.51.100.7']]]); // the domain still points at the old host
    [$user, $org] = $this->customerWithOrganization();
    $service = certService($org);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'ssl.issue', $this->contextFor($user, $org), 'cert-1', []));

    expect($asked)->toBe([])                                    // the certificate authority is never asked
        ->and($operation->state)->not->toBe(Operation::SUCCEEDED)
        ->and((string) data_get($operation->error, 'message', ''))->toContain('shop.cz')
        ->and((string) data_get($operation->error, 'message', ''))->toContain('203.0.113.10')
        ->and((bool) data_get($operation->error, 'retryable'))->toBeTrue(); // it waits for the change to spread, it does not give up
});

it('asks only for the names that already point at us', function () {
    $asked = [];
    certPanel($asked);
    certDns(['shop.cz|A' => [['ip' => '203.0.113.10']], 'www.shop.cz|A' => [['ip' => '203.0.113.10']], 'shop.sk|A' => [['ip' => '198.51.100.7']]]);
    [$user, $org] = $this->customerWithOrganization();
    $service = certService($org, ['www.shop.cz', 'shop.sk']);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'ssl.issue', $this->contextFor($user, $org), 'cert-2', ['domains' => ['shop.cz', 'www.shop.cz', 'shop.sk']]));

    expect($operation->state)->toBe(Operation::SUCCEEDED, $operation->step_label.': '.(string) data_get($operation->error, 'message', ''));
    $names = $asked[0]['domains'] ?? null;
    $names = is_string($names) ? (json_decode($names, true) ?: []) : (array) $names;
    expect($names)->toBe(['shop.cz', 'www.shop.cz'])                       // the one that points elsewhere would fail the whole certificate
        ->and((array) data_get($operation->context, 'certificate_waiting'))->toBe(['shop.sk']);
});

it('asks for everything when every name points at us', function () {
    $asked = [];
    certPanel($asked);
    certDns(['shop.cz|A' => [['ip' => '203.0.113.10']], 'www.shop.cz|A' => [['ip' => '203.0.113.10']]]);
    [$user, $org] = $this->customerWithOrganization();
    $service = certService($org, ['www.shop.cz']);

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'ssl.issue', $this->contextFor($user, $org), 'cert-3', ['domains' => ['shop.cz', 'www.shop.cz']]));

    expect($operation->state)->toBe(Operation::SUCCEEDED, $operation->step_label.': '.(string) data_get($operation->error, 'message', ''))
        ->and($asked)->toHaveCount(1)
        ->and(data_get($service->refresh()->tags, 'access.certificate'))->toBe('issued');
});

it('asks anyway when the platform cannot tell where the node is', function () {
    $asked = [];
    certPanel($asked);
    certDns([]); // no answer at all, and no address to compare against
    [$user, $org] = $this->customerWithOrganization();
    $service = certService($org);
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode([])]); // the node's address is not recorded

    $operation = driveOperation(app(ServiceService::class)->requestAction($service, 'ssl.issue', $this->contextFor($user, $org), 'cert-4', []));

    // the platform does not invent a reason to refuse: with nothing to compare, the panel decides as it did before
    expect($operation->state)->toBe(Operation::SUCCEEDED, $operation->step_label.': '.(string) data_get($operation->error, 'message', ''))
        ->and($asked)->toHaveCount(1);
});
