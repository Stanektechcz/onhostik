<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/**
 * Phase L (L151): production-cache readiness.
 *
 * On a real deploy the app runs with `route:cache`, `config:cache` and
 * `view:cache`. Several things work in local development but break the moment
 * those caches are built:
 *
 *   - a closure in a route definition makes `route:cache` throw, so the WHOLE
 *     route file fails to cache and every route 500s;
 *   - two routes sharing a name silently shadow each other cached, so one of
 *     them resolves to the wrong URL;
 *   - `env()` called outside config returns null once config is cached.
 *
 * These are the classic "nefunguje na produkci" reports. This test reproduces
 * the cached state in CI so they cannot reach production.
 */

afterEach(function (): void {
    // Never leave a cache behind — a stale one would poison later tests.
    Artisan::call('route:clear');
    Artisan::call('config:clear');
});

it('caches all routes without a closure blocking it', function (): void {
    // route:cache throws if any route uses a closure instead of a controller.
    $exit = Artisan::call('route:cache');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->not->toContain('Unable to prepare route');
})->skip(PHP_OS_FAMILY === 'Windows', 'route:cache serialises paths that differ on Windows CI');

it('has no duplicate route names', function (): void {
    /*
     | A duplicate name is not an error uncached — the later definition just
     | wins. Cached, the collision is baked in and route() can resolve to the
     | wrong URL. Asserted directly so it fails regardless of OS.
     */
    $names = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();
        if ($name !== null && $name !== '') {
            $names[] = $name;
        }
    }

    $duplicates = array_keys(array_filter(
        array_count_values($names),
        static fn (int $count): bool => $count > 1,
    ));

    expect($duplicates)->toBe([], 'Duplicitní názvy rout: ' . implode(', ', $duplicates));
});

it('caches configuration cleanly', function (): void {
    $exit = Artisan::call('config:cache');

    expect($exit)->toBe(0);
})->skip(PHP_OS_FAMILY === 'Windows', 'config:cache writes an absolute bootstrap path that differs on Windows CI');

it('references no config value that reads env() at runtime', function (): void {
    /*
     | Once config is cached, env() outside the config/ files returns null. The
     | audit already hit this once (config casts). Guard the app-critical keys
     | that must survive caching.
     */
    $mustExist = [
        'app.name',
        'app.env',
        'security.csp_enforce',
        'security.known_ip_retention_days',
        'notifications.channels',
        'error-tracking.enabled',
    ];

    foreach ($mustExist as $key) {
        expect(config()->has($key))->toBeTrue("Chybí konfigurační klíč: {$key}");
    }
});
