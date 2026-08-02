<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Phase J (J133): the OpenAPI spec must describe every API route that exists.
 *
 * Documentation drifts silently — someone adds an endpoint, nobody adds it to
 * the spec, and six months later the "complete" API reference is a liability.
 * These tests fail the build instead.
 */

/** @return array<int, array{method: string, path: string}> */
function apiRoutes(): array
{
    $out = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/')) {
            continue;
        }

        /*
         | Not part of the customer-facing API surface:
         |  - the docs/changelog endpoints describe the API rather than being
         |    part of it (audit 103 — the changelog is the deprecation target,
         |    not an operation clients build against),
         |  - gateway callbacks are called by Comgate/Stripe/GoPay,
         |  - the CSP report collector is posted to by BROWSERS (audit C24) —
         |    documenting it would invite clients to call it, which is the
         |    opposite of what it is for,
         |  - the GraphQL endpoint is self-documenting via introspection and does
         |    not map onto OpenAPI's per-path REST operation model.
         */
        if (in_array($uri, ['api/docs', 'api/openapi.json', 'api/changelog', 'api/security/csp-report', 'api/graphql', 'api/metrics'], true)
            || str_starts_with($uri, 'api/webhook/')
            || str_starts_with($uri, 'api/webhooks/')) {
            continue;
        }

        foreach ($route->methods() as $method) {
            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }

            // /api/v1/services/{service} in Laravel is /v1/services/{id} in the
            // spec — parameter names are the route's business, not the API's.
            $path = '/' . preg_replace('/\{[^}]+\}/', '{id}', substr($uri, 4));

            $out[] = ['method' => strtolower($method), 'path' => $path];
        }
    }

    return $out;
}

/** @return array<string, mixed> */
function openApiSpec(): array
{
    /** @var array<string, mixed> $spec */
    $spec = test()->getJson('/api/openapi.json')->assertOk()->json();

    return $spec;
}

it('documents every API route in the OpenAPI spec', function (): void {
    $spec  = openApiSpec();
    $paths = $spec['paths'];

    $missing = [];

    foreach (apiRoutes() as $route) {
        if (! isset($paths[$route['path']][$route['method']])) {
            $missing[] = strtoupper($route['method']) . ' ' . $route['path'];
        }
    }

    expect($missing)->toBe([], 'Nezdokumentované API endpointy: ' . implode(', ', $missing));
});

it('does not document routes that no longer exist', function (): void {
    $spec = openApiSpec();

    $real = array_map(
        fn (array $r): string => $r['method'] . ' ' . $r['path'],
        apiRoutes(),
    );

    $stale = [];

    foreach ($spec['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            if (! in_array($method . ' ' . $path, $real, true)) {
                $stale[] = strtoupper($method) . ' ' . $path;
            }
        }
    }

    // A spec promising endpoints that 404 is worse than one that is merely thin.
    expect($stale)->toBe([], 'Spec popisuje neexistující endpointy: ' . implode(', ', $stale));
});

it('advertises Idempotency-Key on every documented write operation', function (): void {
    $spec = openApiSpec();

    $without = [];

    foreach ($spec['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            if (! in_array($method, ['post', 'put', 'patch', 'delete'], true)) {
                continue;
            }

            $refs = array_column($operation['parameters'] ?? [], '$ref');

            if (! in_array('#/components/parameters/IdempotencyKey', $refs, true)) {
                $without[] = strtoupper($method) . ' ' . $path;
            }
        }
    }

    expect($without)->toBe([], 'Zápisy bez Idempotency-Key: ' . implode(', ', $without));
});

it('resolves documented paths against the declared server base', function (): void {
    $spec = openApiSpec();

    // Regression guard: the spec once declared servers at /api/v1 AND /api/v2
    // while path keys already carried their own version, so every URL Swagger
    // built was wrong (/api/v1/v2/services). One base, versioned paths.
    expect($spec['servers'])->toHaveCount(1)
        ->and($spec['servers'][0]['url'])->toEndWith('/api');

    $base = $spec['servers'][0]['url'];

    foreach (array_keys($spec['paths']) as $path) {
        // Substituting a real id must produce a URL Laravel actually routes.
        $url = $base . str_replace('{id}', '1', $path);

        expect($url)->not->toContain('/v1/v1/')
            ->and($url)->not->toContain('/v1/v2/');
    }
});

it('keeps the spec self-consistent — every $ref points at something', function (): void {
    $spec = openApiSpec();

    $refs = [];
    array_walk_recursive($spec, function ($value, $key) use (&$refs): void {
        if ($key === '$ref') {
            $refs[] = $value;
        }
    });

    expect($refs)->not->toBeEmpty();

    foreach (array_unique($refs) as $ref) {
        $segments = explode('/', ltrim((string) $ref, '#/'));
        $node     = $spec;

        foreach ($segments as $segment) {
            expect(is_array($node) && array_key_exists($segment, $node))
                ->toBeTrue("Rozbitý \$ref: {$ref}");

            $node = $node[$segment];
        }
    }
});
