<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\Website;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Domain\Services\Web\CertificateWatch;
use Onhost\Platform\Dns\RecordResolver;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Tls\CertificateReader;

/*
 * A certificate that quietly expired.
 *
 * A web hosting's certificate lasts ninety days and the panel renews it — until it does not. The domain is moved
 * away, the customer adds a redirect that swallows `/.well-known/acme-challenge`, the panel's own client breaks, the
 * authority rate-limits the name: the renewal fails, nothing anywhere says so, and one morning every visitor gets a
 * browser security warning. The platform sells HTTPS and was the last to know.
 *
 * Worse, it said the opposite. `ServiceHealthCheck` answered from `tags.access.certificate`, a flag written **once,
 * when the certificate was first issued** — so the panel, the assistant and support all kept saying "the HTTPS
 * certificate is issued" on the day after it expired. A managed wildcard certificate had real expiry logic; the
 * ordinary per-site certificate that nearly every web hosting has had none.
 *
 * The truth is what the node actually serves, so that is what the platform reads: a TLS handshake to the node's own
 * address with the site's name for SNI — no panel involved, nothing believed, and it catches the two failures a
 * panel cannot see either (a certificate that does not cover the name, and one the panel thinks it renewed).
 */

beforeEach(fn () => Http::preventStrayRequests());

/** What the node serves right now, keyed `<address>|<name>`. */
function servedCertificate(array $answers): void
{
    app()->bind(CertificateReader::class, fn () => new class($answers) implements CertificateReader
    {
        public function __construct(private readonly array $answers) {}

        public function read(string $address, string $serverName): ?array
        {
            return $this->answers[$address.'|'.mb_strtolower($serverName)] ?? null;
        }
    });
}

/** A certificate as the reader answers it. */
function certificateOf(string $expires, array $names, string $issuer = "Let's Encrypt"): array
{
    return ['expires_at' => strtotime($expires), 'issued_at' => strtotime($expires) - 90 * 86400, 'issuer' => $issuer, 'names' => $names];
}

/** Public DNS, so the certificate step knows the name points at the node. */
function certWatchDns(array $answers): void
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

/** aaPanel that remembers what it was asked to certify. */
function certWatchPanel(array &$asked): void
{
    Http::fake(function (Request $request) use (&$asked) {
        if (str_contains((string) parse_url($request->url(), PHP_URL_QUERY), 'apply_cert_api')) {
            $asked[] = $request->data();

            return Http::response(['status' => true, 'cert' => "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----", 'private' => "-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----"]);
        }

        return Http::response(['status' => true, 'msg' => 'ok', 'data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz']], 'page' => '']);
    });
}

/** A live web hosting whose certificate the platform believes is issued. */
function certWatchWeb(object $org, string $domain = 'shop.cz'): Service
{
    $service = featureWebService($org, 'aapanel');
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode(['public_ipv4' => '203.0.113.10'])]);
    $service->forceFill(['hostname' => $domain, 'desired_spec' => array_merge((array) $service->desired_spec, ['domain' => $domain]), 'tags' => array_merge((array) $service->tags, ['access' => ['certificate' => 'issued']])])->save();
    ProviderBinding::query()->where('service_id', $service->id)->update(['meta' => json_encode(['name' => $domain, 'path' => "/www/wwwroot/{$domain}"])]);
    Website::query()->create(['service_id' => $service->id, 'domain' => $domain, 'aliases' => [], 'executor' => 'aapanel', 'php_version' => '8.3',
        'remote_site_id' => 41, 'remote_node' => 'aapanel-managed01', 'state' => 'active', 'ssl_state' => 'issued']);

    return $service->refresh();
}

it('stops saying the certificate is issued once it has expired', function () {
    $asked = [];
    certWatchPanel($asked);
    certWatchDns(['shop.cz|A' => [['ip' => '203.0.113.10']]]);
    servedCertificate(['203.0.113.10|shop.cz' => certificateOf('-2 days', ['shop.cz', 'www.shop.cz'])]);
    [, $org] = $this->customerWithOrganization();
    $service = certWatchWeb($org);

    app(CertificateWatch::class)->run();

    $finding = collect(app(ServiceHealthCheck::class)->run($service->refresh())['findings'])->firstWhere('key', 'certificate');
    expect($finding['level'])->toBe('bad')
        ->and($finding['cs'])->toContain('vypršel')
        ->and(OutboxMessage::query()->where('name', 'service.certificate.problem')->exists())->toBeTrue();
});

it('asks for a new certificate itself when the panel has not renewed in time', function () {
    $asked = [];
    certWatchPanel($asked);
    certWatchDns(['shop.cz|A' => [['ip' => '203.0.113.10']]]);
    servedCertificate(['203.0.113.10|shop.cz' => certificateOf('+5 days', ['shop.cz'])]);
    [, $org] = $this->customerWithOrganization();
    certWatchWeb($org);

    $result = app(CertificateWatch::class)->run();

    expect($result['renewed'])->toBe(1)
        ->and($asked)->toHaveCount(1); // the authority is asked once, by us, because the panel did not
});

it('leaves a certificate with time alone', function () {
    $asked = [];
    certWatchPanel($asked);
    certWatchDns(['shop.cz|A' => [['ip' => '203.0.113.10']]]);
    servedCertificate(['203.0.113.10|shop.cz' => certificateOf('+60 days', ['shop.cz', 'www.shop.cz'])]);
    [, $org] = $this->customerWithOrganization();
    $service = certWatchWeb($org);

    $result = app(CertificateWatch::class)->run();

    $finding = collect(app(ServiceHealthCheck::class)->run($service->refresh())['findings'])->firstWhere('key', 'certificate');
    expect($result)->toMatchArray(['checked' => 1, 'renewed' => 0, 'told' => 0])
        ->and($asked)->toBe([])
        ->and($finding['level'])->toBe('ok')
        ->and($finding['cs'])->toContain('platí do');
});

it('notices a certificate that does not cover the name the site answers to', function () {
    $asked = [];
    certWatchPanel($asked);
    certWatchDns(['shop.cz|A' => [['ip' => '203.0.113.10']]]);
    // the vhost fell back to the node's default site: a valid certificate, for somebody else's name
    servedCertificate(['203.0.113.10|shop.cz' => certificateOf('+60 days', ['jiny-web.cz'])]);
    [, $org] = $this->customerWithOrganization();
    $service = certWatchWeb($org);

    app(CertificateWatch::class)->run();

    $finding = collect(app(ServiceHealthCheck::class)->run($service->refresh())['findings'])->firstWhere('key', 'certificate');
    expect($finding['level'])->toBe('bad')
        ->and($finding['cs'])->toContain('neplatí pro')
        ->and((string) data_get(OutboxMessage::query()->where('name', 'service.certificate.problem')->first()?->payload, 'kind'))->toBe('mismatch');
});

it('tells the operators once a day, however often it looks', function () {
    $asked = [];
    certWatchPanel($asked);
    certWatchDns(['shop.cz|A' => [['ip' => '203.0.113.10']]]);
    servedCertificate(['203.0.113.10|shop.cz' => certificateOf('-2 days', ['shop.cz'])]);
    [, $org] = $this->customerWithOrganization();
    certWatchWeb($org);

    app(CertificateWatch::class)->run();
    app(CertificateWatch::class)->run();

    expect(OutboxMessage::query()->where('name', 'service.certificate.problem')->count())->toBe(1);
});

it('says nothing about a site whose node answers with no certificate at all', function () {
    $asked = [];
    certWatchPanel($asked);
    certWatchDns([]);
    servedCertificate([]); // the handshake did not happen — the platform does not invent a verdict from silence
    [, $org] = $this->customerWithOrganization();
    $service = certWatchWeb($org);

    $result = app(CertificateWatch::class)->run();

    expect($result)->toMatchArray(['renewed' => 0, 'told' => 0])
        ->and(data_get($service->refresh()->tags, 'tls'))->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'service.certificate.problem')->exists())->toBeFalse();
});
