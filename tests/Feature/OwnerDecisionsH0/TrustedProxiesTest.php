<?php

declare(strict_types=1);

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/*
 * H0, owner decision H-R2 (2026-10-06): no proxy and no CDN stand in front of the origin. Without TRUSTED_PROXIES the platform
 * trusts only the local aaPanel nginx (127.0.0.1, ::1) to name the client; a forwarded address from anywhere else is ignored.
 */

/** The address the application sees for a request from `$remote` that claims to be forwarded for 203.0.113.9. */
function h0ClientAddress(string $remote): string
{
    $request = Request::create('/v1/status', 'GET', server: ['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_FOR' => '203.0.113.9']);

    return (string) app(TrustProxies::class)->handle($request, fn (Request $r) => response((string) $r->ip()))->getContent();
}

it('trusts only the local nginx by default', function () {
    $this->get('/v1/status'); // boots the HTTP kernel, which hands bootstrap/app.php's list to TrustProxies
    $trusted = (new ReflectionProperty(TrustProxies::class, 'alwaysTrustProxies'))->getValue();

    expect($trusted)->toBe(['127.0.0.1', '::1'])
        ->and(h0ClientAddress('127.0.0.1'))->toBe('203.0.113.9')   // the local nginx names the client
        ->and(h0ClientAddress('198.51.100.7'))->toBe('198.51.100.7'); // anybody else cannot pick an address
    expect((string) file_get_contents(base_path('.env.example')))->toContain("\nTRUSTED_PROXIES=127.0.0.1,::1");
});
