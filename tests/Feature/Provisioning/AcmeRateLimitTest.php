<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\Models\ManagedCertificate;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Web\AcmeClient;
use Onhost\Domain\Services\Web\CertificateService;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;

/*
 * A certificate authority rate-limits: Let's Encrypt refuses a duplicate certificate after five of the same set in a
 * week and an account after three hundred new orders in three hours, and says so with HTTP 429 and a `Retry-After`.
 * The platform read that as an ordinary failure — so the certificate failed outright and the nightly renewal sweep
 * asked again the next night, and the night after, which is exactly what the limit is there to stop. Now it is the
 * authority's own answer: the run waits the time it named, and the sweep steps over that certificate until then.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** A certificate of a service, as `CertificateService::open` leaves it once issued. */
function issuedCertificate(string $serviceId, string $organizationId, string $domain, ?string $id = null): ManagedCertificate
{
    $cert = new ManagedCertificate(['service_id' => $serviceId, 'organization_id' => $organizationId]);
    if ($id !== null) {
        $cert->forceFill(['id' => $id]);
    }
    $cert->forceFill(['domains' => [$domain, '*.'.$domain], 'wildcard' => true, 'state' => 'issued', 'issued_at' => now()->subDays(60), 'expires_at' => now()->addDays(30), 'renew_after' => now()->subHour()])->save();

    return $cert->refresh();
}

it('takes a rate limit for what it is: the authority\'s own answer, with the authority\'s own time', function () {
    Http::fake([
        '*/directory' => Http::response(['newNonce' => 'https://acme.test/nonce', 'newAccount' => 'https://acme.test/acct', 'newOrder' => 'https://acme.test/order']),
        'https://acme.test/nonce' => Http::response('', 200, ['Replay-Nonce' => 'nonce-1']),
        'https://acme.test/acct' => Http::response(['status' => 'valid'], 201, ['Location' => 'https://acme.test/acct/1', 'Replay-Nonce' => 'nonce-2']),
        'https://acme.test/order' => Http::response(['type' => 'urn:ietf:params:acme:error:rateLimited', 'detail' => 'too many certificates already issued for exact set of domains'], 429, ['Retry-After' => '7200', 'Replay-Nonce' => 'nonce-3']),
    ]);
    config(['onhost.acme.directory' => 'https://acme.test/directory']);

    // this run also creates the ACME account, which nothing had ever done in a test: the account key was generated
    // without an OpenSSL configuration of its own (no key at all on a host without an openssl.cnf) and the account
    // call passed `null` where a `string` was declared — a TypeError before the first request ever left the platform
    try {
        app(AcmeClient::class)->newOrder(['shop.cz', '*.shop.cz']);
        $this->fail('the rate limit was not reported');
    } catch (ProviderException $e) {
        expect($e->errorCode)->toBe(ProviderErrorCode::RATE_LIMIT)
            ->and($e->isRetryable())->toBeTrue()          // the run waits instead of failing the customer's certificate
            ->and($e->retryAfterSeconds)->toBe(7200)      // and waits exactly as long as the authority asked
            ->and($e->getMessage())->toContain('rate limiting us');
    }
});

it('reads the authority\'s Retry-After in every shape it comes in, and never takes its word for a silly number', function () {
    $case = 0;
    $of = function (array $headers) use (&$case) {
        $url = 'https://acme.test/retry-'.(++$case); // a fake answers the first stub registered for a URL: one URL per case
        Http::fake([$url => Http::response('', 429, $headers)]);

        return AcmeClient::retryAfter(Http::get($url));
    };

    expect($of(['Retry-After' => '900']))->toBe(900)
        ->and($of(['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 1800)]))->toBeGreaterThan(1700)->toBeLessThanOrEqual(1800)
        ->and($of([]))->toBe(3600)                    // said nothing: an hour
        ->and($of(['Retry-After' => '0']))->toBe(3600) // said "now": not an invitation to hammer
        ->and($of(['Retry-After' => '999999']))->toBe(86400); // said "next month": a day is enough to try again
});

it('steps over a certificate the authority does not want to hear about yet', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['state' => ServiceStateMachine::ACTIVE])->save();
    $held = issuedCertificate($service->id, $org->id, 'drzeny.cz');
    $held->forceFill(['rate_limited_until' => now()->addHours(2)])->save();

    expect(app(CertificateService::class)->renewDue())->toBe(0); // nothing asked for

    $held->forceFill(['rate_limited_until' => now()->subMinute()])->save();
    expect(app(CertificateService::class)->renewDue())->toBe(1); // the time the authority named has passed
});

it('does not let a batch of certificates come due on the same day for ever', function () {
    $offsets = collect(range(1, 40))->map(fn (int $i) => CertificateService::spread(new ManagedCertificate(['id' => 'crt_'.str_pad((string) $i, 10, '0', STR_PAD_LEFT)])));

    expect($offsets->unique()->count())->toBeGreaterThan(35)          // every certificate gets its own moment
        ->and($offsets->max())->toBeLessThan(6 * 24 * 60)             // …within six days of the earliest renewal day
        ->and($offsets->map(fn (int $m) => intdiv($m, 24 * 60))->unique()->count())->toBeGreaterThan(3) // spread over days, not bunched in one
        ->and(CertificateService::spread(new ManagedCertificate(['id' => 'crt_0000000001'])))->toBe($offsets->first()); // the same certificate always lands on the same moment
});
