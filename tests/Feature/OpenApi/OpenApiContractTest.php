<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Onhost\Platform\Http\Middleware\IdempotencyKey;
use Symfony\Component\Yaml\Yaml;

/*
 * TASK-0074 (D1): the OpenAPI contract describes the routes that exist and nothing else. Every /v1 route and method has exactly
 * one operation with its own id; a bearer token is offered only where TokenRouteScope lets one in (and the operation names the scope
 * it needs); the Idempotency-Key header only where the idempotency middleware runs; the numbers come from config, not literals.
 */

/** @return array<string, mixed> the contract as the generator writes it now */
function openApiFreshDocument(): array
{
    static $document = null;
    if ($document === null) {
        $relative = 'storage/framework/testing/openapi-'.uniqid().'.yaml';
        Artisan::call('onhost:openapi', ['--out' => $relative]);
        $document = Yaml::parseFile(base_path($relative));
        @unlink(base_path($relative));
    }

    return $document;
}

/** @return array<string, array{route: Route, path: string, method: string}> "METHOD /path" => route, for every v1 route */
function openApiRouteSet(): array
{
    $set = [];
    foreach (app(Router::class)->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'v1')) {
            continue;
        }
        $path = '/'.ltrim(preg_replace('/\{([a-zA-Z_]+)\??\}/', '{$1}', substr($route->uri(), 3)) ?? '', '/');
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            $set["{$method} {$path}"] = ['route' => $route, 'path' => $path, 'method' => $method];
        }
    }

    return $set;
}

/** @return array<string, array<string, mixed>> "METHOD /path" => operation */
function openApiOperations(): array
{
    $operations = [];
    foreach (openApiFreshDocument()['paths'] as $path => $item) {
        foreach ($item as $method => $operation) {
            $operations[strtoupper($method).' '.$path] = $operation;
        }
    }

    return $operations;
}

function openApiHasBearer(array $operation): bool
{
    return collect($operation['security'] ?? [])->contains(fn ($requirement) => array_key_exists('bearer', $requirement));
}

it('has exactly one operation for every v1 route and method, and no other', function () {
    $routes = array_keys(openApiRouteSet());
    $operations = array_keys(openApiOperations());

    expect(array_values(array_diff($routes, $operations)))->toBe([])
        ->and(array_values(array_diff($operations, $routes)))->toBe([])
        ->and(count($operations))->toBe(count($routes));
});

it('gives every operation its own operationId', function () {
    $ids = array_map(fn (array $op) => $op['operationId'], array_values(openApiOperations()));
    $duplicates = array_keys(array_filter(array_count_values($ids), fn (int $n) => $n > 1));

    expect($duplicates)->toBe([])
        ->and(array_filter($ids, fn ($id) => ! preg_match('/^[a-z][A-Za-z0-9]*$/', $id)))->toBe([]);
});

it('derives the operationId from method and path', function () {
    $operations = openApiOperations();

    expect($operations['POST /domains/{domain}/holder']['operationId'])->toBe('postDomainsByDomainHolder')
        ->and($operations['GET /services']['operationId'])->toBe('getServices');
});

it('offers the Idempotency-Key header exactly where the idempotency middleware runs', function () {
    $router = app(Router::class);
    $operations = openApiOperations();
    $wrong = [];
    foreach (openApiRouteSet() as $key => ['route' => $route, 'method' => $method]) {
        $runs = in_array($method, ['POST', 'PUT', 'PATCH'], true) && in_array(IdempotencyKey::class, $router->gatherRouteMiddleware($route), true);
        $refs = array_column($operations[$key]['parameters'] ?? [], '$ref');
        if (in_array('#/components/parameters/IdempotencyKey', $refs, true) !== $runs) {
            $wrong[] = $key;
        }
    }

    expect($wrong)->toBe([])
        ->and(array_column($operations['POST /staff/services/{service}/panel-login']['parameters'], '$ref'))->not->toContain('#/components/parameters/IdempotencyKey');
});

it('offers a bearer token only where TokenRouteScope lets one in, and names the scope', function () {
    $operations = openApiOperations();

    foreach ($operations as $key => $op) {
        expect(array_key_exists('x-token-scope', $op))->toBeTrue($key);
        if (! empty($op['security'])) {
            expect(openApiHasBearer($op))->toBe($op['x-token-scope'] !== null, $key);
        }
    }

    // staff routes and the account's own writes are the portal's: no bearer there
    expect(openApiHasBearer($operations['GET /staff/customers']))->toBeFalse()
        ->and($operations['GET /staff/customers']['x-token-scope'])->toBeNull()
        ->and(openApiHasBearer($operations['PATCH /me']))->toBeFalse()
        ->and($operations['PATCH /me']['x-token-scope'])->toBeNull()
        ->and(openApiHasBearer($operations['GET /services']))->toBeTrue()
        ->and($operations['GET /services']['x-token-scope'])->toBe(['services:read'])
        ->and($operations['GET /invoices']['x-token-scope'])->toBe(['invoices:read'])
        ->and($operations['POST /services/{service}/console-token']['x-token-scope'])->toBe(['services:console'])
        ->and($operations['POST /services/{service}/actions']['x-token-scope'])->toContain('services:power', 'services:console')
        ->and($operations['GET /me']['x-token-scope'])->toBe([])
        ->and(openApiHasBearer($operations['GET /me']))->toBeTrue();
});

it('reads the numbers from config', function () {
    config(['onhost.api.page_size' => 17, 'onhost.api.max_page_size' => 77, 'onhost.api.idempotency_key_max_length' => 99]);
    $relative = 'storage/framework/testing/openapi-cfg-'.uniqid().'.yaml';
    Artisan::call('onhost:openapi', ['--out' => $relative]);
    $document = Yaml::parseFile(base_path($relative));
    @unlink(base_path($relative));

    expect($document['components']['parameters']['IdempotencyKey']['schema']['maxLength'])->toBe(99)
        ->and($document['components']['parameters']['Limit']['schema']['default'])->toBe(17)
        ->and($document['components']['parameters']['Limit']['schema']['maximum'])->toBe(77);
});

it('keeps the idempotency key length of the contract equal to what the middleware refuses', function () {
    $max = (int) config('onhost.api.idempotency_key_max_length');
    $call = fn (int $length) => app(IdempotencyKey::class)->handle(
        Request::create('/v1/x', 'POST', server: ['HTTP_IDEMPOTENCY_KEY' => str_repeat('k', $length)]),
        fn () => response()->json(['ok' => true]),
    );

    expect($max)->toBe(200)
        ->and($call($max)->getStatusCode())->toBe(200)
        ->and($call($max + 1)->getStatusCode())->toBe(422);
});

it('puts limit and offset only on lists that paginate', function () {
    $operations = openApiOperations();
    $has = fn (string $key) => array_column($operations[$key]['parameters'] ?? [], '$ref');

    expect($has('GET /services'))->toContain('#/components/parameters/Limit', '#/components/parameters/Offset')
        ->and($has('GET /staff/customers'))->toContain('#/components/parameters/Limit')
        ->and($has('GET /me'))->not->toContain('#/components/parameters/Limit')
        ->and($has('GET /catalog'))->not->toContain('#/components/parameters/Limit');
});

it('checks the committed contract and fails on drift', function () {
    $relative = 'storage/framework/testing/openapi-check-'.uniqid().'.yaml';
    Artisan::call('onhost:openapi', ['--out' => $relative]);
    expect(Artisan::call('onhost:openapi', ['--out' => $relative, '--check' => true]))->toBe(0);

    file_put_contents(base_path($relative), "# drift\n".file_get_contents(base_path($relative)));
    $before = file_get_contents(base_path($relative));
    expect(Artisan::call('onhost:openapi', ['--out' => $relative, '--check' => true]))->toBe(1)
        ->and(file_get_contents(base_path($relative)))->toBe($before); // --check never writes
    expect(Artisan::call('onhost:openapi', ['--out' => 'storage/framework/testing/missing-'.uniqid().'.yaml', '--check' => true]))->toBe(1);
    @unlink(base_path($relative));
});

it('is the committed contract', function () {
    expect(Artisan::call('onhost:openapi', ['--check' => true]))->toBe(0);
});
