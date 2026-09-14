<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Web\CertificateAutoIssuer;

/*
 * Automation: a site provisioned before its DNS pointed at the node waits with `certificate: pending_dns`. The quarter-hour
 * pass resolves the name; once it answers with the node's address the certificate is requested through the same action the
 * customer has (`ssl.issue`), the tag moves to `requested` and then `issued` when the panel returns the certificate.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('requests the certificate of a pending site once its name resolves to the node', function () {
    [$user, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['tags' => array_replace_recursive((array) $service->tags, ['access' => ['certificate' => 'pending_dns']])])->save();
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode(['public_ipv4' => '192.0.2.11'])]);
    $issued = false;
    Http::fake(function ($request) use (&$issued) {
        $q = (string) parse_url($request->url(), PHP_URL_QUERY);
        if (str_contains($q, 'apply_cert_api')) {
            $issued = true;

            return Http::response(['cert' => "-----BEGIN CERTIFICATE-----\nMIIB\n-----END CERTIFICATE-----", 'private' => "-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----"]);
        }

        return match (true) {
            str_contains($q, 'table=sites') => Http::response(['data' => [['id' => 41, 'name' => 'shop.cz', 'path' => '/www/wwwroot/shop.cz', 'status' => '1']], 'page' => '']),
            default => Http::response(['status' => true, 'msg' => 'ok']),
        };
    });
    $issuer = app(CertificateAutoIssuer::class);
    $lookups = [];
    $issuer->resolveWith(function (string $hostname) use (&$lookups) {
        $lookups[] = $hostname;

        return $hostname === 'shop.cz' ? ['198.51.100.7'] : [];
    });

    // still pointed elsewhere: nothing happens
    expect($issuer->run())->toBe(['checked' => 1, 'resolved' => 0, 'requested' => 0])->and($lookups)->toBe(['shop.cz'])->and($issued)->toBeFalse();

    // the customer changed the A record: the certificate is requested as an ordinary service action
    $issuer->resolveWith(fn (string $hostname) => ['192.0.2.11']);
    expect($issuer->run())->toBe(['checked' => 1, 'resolved' => 1, 'requested' => 1]);
    $service->refresh();
    expect($service->tags['access']['certificate'])->toBeIn(['requested', 'issued']); // `issued` already when the test queue runs the operation inline
    $operation = Operation::query()->where('service_id', $service->id)->latest('created_at')->firstOrFail();
    expect(data_get($operation->desired, 'action'))->toBe('ssl.issue')->and(data_get($operation->desired, 'domains'))->toBe(['shop.cz', 'www.shop.cz']);
    $operation = driveOperation($operation);
    expect($operation->state)->toBe(Operation::SUCCEEDED, json_encode($operation->error))->and($issued)->toBeTrue()
        ->and(Service::query()->findOrFail($service->id)->tags['access']['certificate'])->toBe('issued');

    // done: the next pass leaves the site alone
    expect($issuer->run())->toBe(['checked' => 0, 'resolved' => 0, 'requested' => 0]);
});
