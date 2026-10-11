<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\CollectorHeartbeat;
use Onhost\Domain\Services\Freshness;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceHealthCheck;
use Onhost\Domain\Services\Web\SecurityHeadersWatch;
use Tests\FakeHostResolver;

/*
 * The security headers a site sends, read once a night with one HTTPS GET (SecurityHeadersWatch). It advises and never
 * changes anything; a probe that could not be made is a collection error, never "fine"; and the request goes only where
 * EgressGuard allows (a public address, pinned, no redirect to a foreign host).
 */

beforeEach(fn () => Http::preventStrayRequests());

const FULL_HEADERS = [
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    'Content-Security-Policy' => "default-src 'self'; frame-ancestors 'self'",
    'X-Content-Type-Options' => 'nosniff',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
];

function headersWatchWeb(object $org, string $domain = 'shop.cz'): Service
{
    $service = featureWebService($org, 'aapanel');
    $service->forceFill(['hostname' => $domain])->save();

    return $service->refresh();
}

function headersFinding(Service $service): ?array
{
    return collect(app(ServiceHealthCheck::class)->run($service->refresh())['findings'])->firstWhere('key', 'security_headers');
}

it('warns with concrete advice when the site sends no HSTS', function () {
    Http::fake(['https://shop.cz/*' => Http::response('', 200, array_diff_key(FULL_HEADERS, ['Strict-Transport-Security' => 1]))]);
    [, $org] = $this->customerWithOrganization();
    $service = headersWatchWeb($org);

    $result = app(SecurityHeadersWatch::class)->run();

    $finding = headersFinding($service);
    expect($result)->toMatchArray(['checked' => 1, 'missing' => 1, 'errors' => 0])
        ->and($finding['level'])->toBe('warn')
        ->and($finding['state'])->toBe('current')
        ->and($finding['cs'])->toContain('Strict-Transport-Security')->and($finding['en'])->toContain('Strict-Transport-Security')
        ->and($finding['en'])->not->toContain('Content-Security-Policy header');
    Http::assertSentCount(1);
});

it('says ok when every monitored header and cookie flag is present', function () {
    Http::fake(['https://shop.cz/*' => Http::response('', 200, FULL_HEADERS + ['Set-Cookie' => ['sid=1; Secure; HttpOnly; SameSite=Lax']])]);
    [, $org] = $this->customerWithOrganization();
    $service = headersWatchWeb($org);

    app(SecurityHeadersWatch::class)->run();

    expect(headersFinding($service)['level'])->toBe('ok');
});

it('names the cookie flags a Set-Cookie lacks and accepts frame-ancestors for framing protection', function () {
    Http::fake(['https://shop.cz/*' => Http::response('', 200, FULL_HEADERS + ['Set-Cookie' => ['sid=1; Secure; HttpOnly', 'pref=dark']])]);
    [, $org] = $this->customerWithOrganization();
    $service = headersWatchWeb($org);

    app(SecurityHeadersWatch::class)->run();

    $finding = headersFinding($service);
    expect($finding['level'])->toBe('warn')
        ->and($finding['en'])->toContain('Secure flag')->toContain('HttpOnly flag')->toContain('SameSite')
        ->and(SecurityHeadersWatch::missing(array_merge(FULL_HEADERS, ['Set-Cookie' => ['a=1; Secure; HttpOnly; SameSite=Strict']])))->toBe([])
        ->and(SecurityHeadersWatch::missing(['Content-Security-Policy' => "frame-ancestors 'none'"]))->not->toContain('frame');
});

it('refuses a private or metadata address and sends nothing', function () {
    FakeHostResolver::$hosts['shop.cz'] = ['169.254.169.254'];
    Http::fake();
    [, $org] = $this->customerWithOrganization();
    $service = headersWatchWeb($org);

    $result = app(SecurityHeadersWatch::class)->run();

    $finding = headersFinding($service);
    Http::assertNothingSent();
    expect($result)->toMatchArray(['checked' => 0, 'errors' => 1])
        ->and($finding['level'])->toBe('warn')->and($finding['level'])->not->toBe('ok')
        ->and($finding['state'])->toBe('collection_error');
});

it('treats an unreachable site as a collection error, not as ok, and keeps the last good reading', function () {
    Http::fake(['https://shop.cz/*' => Http::response('', 200, FULL_HEADERS)]);
    [, $org] = $this->customerWithOrganization();
    $service = headersWatchWeb($org);
    app(SecurityHeadersWatch::class)->run();
    expect(headersFinding($service)['level'])->toBe('ok');

    Http::fake(['https://shop.cz/*' => fn () => throw new ConnectionException('timed out')]);
    $result = app(SecurityHeadersWatch::class)->run();

    $finding = headersFinding($service);
    $tags = $service->refresh()->tags['security_headers'];
    expect($result['errors'])->toBe(1)
        ->and($finding['level'])->toBe('warn')->and($finding['state'])->toBe('collection_error')
        ->and($finding['cs'])->toContain('nepodařilo ověřit')
        ->and($tags['missing'])->toBe([]); // the last good reading is still there beside the error
});

it('does not follow a redirect to a foreign host and judges the answer of our own site', function () {
    $urls = [];
    Http::fake(function (Request $request) use (&$urls) {
        $urls[] = $request->url();

        return Http::response('', 301, ['Location' => 'https://evil.example/', 'Strict-Transport-Security' => 'max-age=1']);
    });
    [, $org] = $this->customerWithOrganization();
    $service = headersWatchWeb($org);

    app(SecurityHeadersWatch::class)->run();

    expect($urls)->toBe(['https://shop.cz/'])
        ->and($service->refresh()->tags['security_headers']['missing'])->not->toContain('hsts');
});

it('follows a redirect to the same site only, over https', function () {
    $urls = [];
    Http::fake(function (Request $request) use (&$urls) {
        $urls[] = $request->url();

        return count($urls) === 1 ? Http::response('', 302, ['Location' => 'https://www.shop.cz/home']) : Http::response('', 200, FULL_HEADERS);
    });
    [, $org] = $this->customerWithOrganization();
    $service = headersWatchWeb($org);

    app(SecurityHeadersWatch::class)->run();

    expect($urls)->toBe(['https://shop.cz/', 'https://www.shop.cz/home'])->and(headersFinding($service)['level'])->toBe('ok');
});

it('is registered as a collector so a silent run is noticed', function () {
    expect(CollectorHeartbeat::EXPECTED)->toHaveKey('sites.headers')
        ->and(Freshness::limitMinutes('security_headers'))->toBe(2880);

    app(AutomationLedger::class)->record('sites.headers', [], 'boom');
    $service = headersWatchWeb($this->customerWithOrganization()[1]);
    $service->forceFill(['tags' => ['security_headers' => ['missing' => [], 'checked_at' => now()->toIso8601String(), 'error' => null]]])->save();

    $finding = headersFinding($service);
    expect($finding['state'])->toBe('collection_error')->and($finding['level'])->not->toBe('ok');
});
