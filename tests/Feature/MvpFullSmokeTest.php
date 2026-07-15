<?php

declare(strict_types=1);

use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Route;

/**
 * MVP smoke audit: every parameterless GET page in the system must render
 * without a server error (5xx). Redirects (auth, feature gates) and 4xx
 * (403 for role-gated pages) are acceptable — a 500 never is.
 */

/** @return list<string> */
function smokeUris(string $prefix): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true)
            && str_starts_with($route->uri(), $prefix)
            && !str_contains($route->uri(), '{'))
        ->map(fn ($route) => '/' . $route->uri())
        ->unique()
        ->values()
        ->all();
}

it('every parameterless admin GET page renders without server error', function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
    $admin = adminUser();

    $failures = [];

    foreach (smokeUris('admin') as $uri) {
        try {
            $status = $this->actingAs($admin)->get($uri)->baseResponse->getStatusCode();
        } catch (\Throwable $e) {
            $failures[$uri] = get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 120);
            continue;
        }

        if ($status >= 500) {
            $failures[$uri] = "HTTP {$status}";
        }
    }

    expect($failures)->toBe([]);
});

it('every parameterless panel GET page renders without server error for a customer', function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
    $user = customerUser();

    $failures = [];

    foreach (smokeUris('panel') as $uri) {
        try {
            $status = $this->actingAs($user)->get($uri)->baseResponse->getStatusCode();
        } catch (\Throwable $e) {
            $failures[$uri] = get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 120);
            continue;
        }

        if ($status >= 500) {
            $failures[$uri] = "HTTP {$status}";
        }
    }

    expect($failures)->toBe([]);
});

it('every parameterless public GET page renders without server error for a guest', function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);

    $skipPrefixes = ['/admin', '/panel', '/api', '/reseller', '/partner', '/_debugbar', '/sanctum', '/livewire', '/storage', '/up'];

    $uris = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true) && !str_contains($route->uri(), '{'))
        ->map(fn ($route) => '/' . ltrim($route->uri(), '/'))
        ->unique()
        ->reject(function (string $uri) use ($skipPrefixes): bool {
            foreach ($skipPrefixes as $prefix) {
                if ($uri === $prefix || str_starts_with($uri, $prefix . '/') || str_starts_with($uri, $prefix)) {
                    return true;
                }
            }

            return false;
        })
        ->values();

    $failures = [];

    foreach ($uris as $uri) {
        try {
            $status = $this->get($uri)->baseResponse->getStatusCode();
        } catch (\Throwable $e) {
            $failures[$uri] = get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 120);
            continue;
        }

        if ($status >= 500) {
            $failures[$uri] = "HTTP {$status}";
        }
    }

    expect($failures)->toBe([]);
});

it('every parameterless reseller and partner GET page responds without server error', function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
    $user = customerUser();

    $failures = [];

    foreach (array_merge(smokeUris('reseller'), smokeUris('partner')) as $uri) {
        try {
            $status = $this->actingAs($user)->get($uri)->baseResponse->getStatusCode();
        } catch (\Throwable $e) {
            $failures[$uri] = get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 120);
            continue;
        }

        if ($status >= 500) {
            $failures[$uri] = "HTTP {$status}";
        }
    }

    expect($failures)->toBe([]);
});
