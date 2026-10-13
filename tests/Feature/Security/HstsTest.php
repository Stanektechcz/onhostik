<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Platform\HstsHealth;

/*
 * P11 (phase Q, P-R74 / HS-1): Strict-Transport-Security was sent twice on staging — by the application
 * (`max-age=31536000; includeSubDomains; preload`, hard-coded) and by the aaPanel vhost. Decision: the application alone sends it,
 * from configuration, without `preload`, and `includeSubDomains` only after every subdomain of the apex has valid TLS. The doctor
 * row "HSTS is sent once and as configured" asks the application's own APP_URL/up once, on staging and in production only.
 */

function hstsHeaderFor(array $config, bool $secure = true): ?string
{
    config(['onhost.security.hsts' => $config + ['enabled' => true, 'max_age' => 31536000, 'include_subdomains' => false, 'preload' => false]]);
    $request = Request::create(($secure ? 'https' : 'http').'://portal.test/up');
    $response = (new SecurityHeaders)->handle($request, fn () => response('ok'));

    return $response->headers->get('Strict-Transport-Security');
}

it('sends HSTS from configuration in every combination, preload only where the preload list accepts it', function () {
    expect(hstsHeaderFor([]))->toBe('max-age=31536000') // the default of P-R74: no includeSubDomains, no preload
        ->and(hstsHeaderFor(['include_subdomains' => true]))->toBe('max-age=31536000; includeSubDomains')
        ->and(hstsHeaderFor(['preload' => true]))->toBe('max-age=31536000') // preload without includeSubDomains is refused by the list
        ->and(hstsHeaderFor(['include_subdomains' => true, 'preload' => true]))->toBe('max-age=31536000; includeSubDomains; preload')
        ->and(hstsHeaderFor(['include_subdomains' => true, 'preload' => true, 'max_age' => 86400]))->toBe('max-age=86400; includeSubDomains') // under a year: no preload
        ->and(hstsHeaderFor(['max_age' => 600]))->toBe('max-age=600')
        ->and(hstsHeaderFor(['enabled' => false]))->toBeNull()
        ->and(hstsHeaderFor(['max_age' => 0]))->toBeNull()
        ->and(hstsHeaderFor([], false))->toBeNull(); // plain http outside production: never
    expect(SecurityHeaders::hstsValue())->toBe('max-age=31536000'); // what the doctor expects is what the middleware sends
});

it('sends exactly one HSTS header on a real response', function () {
    config(['onhost.security.hsts' => ['enabled' => true, 'max_age' => 31536000, 'include_subdomains' => false, 'preload' => false]]);
    $response = $this->get('https://localhost/up');
    expect($response->headers->all('strict-transport-security'))->toBe(['max-age=31536000']);
});

/** The doctor row as the doctor prints it. @return array<string, mixed> */
function hstsDoctorRow(): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);

    return (array) collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', 'HSTS is sent once and as configured');
}

it('checks the header once against APP_URL/up on staging and in production: once, missing, twice or different', function () {
    config(['app.url' => 'https://staging.onhost.test', 'app.env' => 'staging', 'onhost.security.hsts' => ['enabled' => true, 'max_age' => 31536000, 'include_subdomains' => false, 'preload' => false]]);
    app()->detectEnvironment(fn () => 'staging');
    $answers = [
        'once' => ['Strict-Transport-Security' => 'max-age=31536000'],
        'missing' => [],
        'twice' => ['Strict-Transport-Security' => ['max-age=31536000', 'max-age=31536000; includeSubDomains; preload']],
        'different' => ['Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload'],
    ];
    $expect = ['once' => true, 'missing' => false, 'twice' => false, 'different' => false];
    foreach ($answers as $case => $headers) {
        Http::fake(['https://staging.onhost.test/up' => Http::response('ok', 200, $headers)]);
        Http::preventStrayRequests();
        $row = collect(app(HstsHealth::class)->checks())->firstWhere('check', 'HSTS is sent once and as configured');
        expect($row['ok'])->toBe($expect[$case], $case.': '.$row['detail']);
        if ($case === 'twice') {
            expect($row['detail'])->toContain('2×')->and($row['remedy'])->toContain('aaPanel');
        }
        Http::assertSentCount(1); // one request, to the application's own address only
        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://staging.onhost.test/up');
        app()->forgetInstance(Factory::class);
        Http::clearResolvedInstances();
    }
});

it('reports an unreachable APP_URL as a warning with the reason and never asks another host', function () {
    config(['app.url' => 'https://staging.onhost.test']);
    app()->detectEnvironment(fn () => 'staging');
    Http::fake(['https://staging.onhost.test/up' => fn () => throw new ConnectionException('Connection refused')]);
    Http::preventStrayRequests();
    $row = collect(app(HstsHealth::class)->checks())->firstWhere('check', 'HSTS is sent once and as configured');
    expect($row['ok'])->toBeFalse()->and($row['blocking'])->toBeFalse()->and($row['detail'])->toContain('could not reach');
});

it('does not ask anything outside staging and production, and the doctor prints the row', function () {
    Http::fake();
    Http::preventStrayRequests();
    $row = collect(app(HstsHealth::class)->checks())->firstWhere('check', 'HSTS is sent once and as configured');
    expect($row['ok'])->toBeTrue()->and($row['detail'])->toContain('only on staging and production');
    Http::assertNothingSent();
    expect(hstsDoctorRow()['status'])->toBe('OK');
});
