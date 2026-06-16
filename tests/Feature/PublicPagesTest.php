<?php

declare(strict_types=1);

use Database\Seeders\ProductCatalogSeeder;

it('serves the full homepage with plans, features and FAQ', function (): void {
    $this->seed(ProductCatalogSeeder::class);

    $this->get('/')
        ->assertOk()
        ->assertSee(__('front.home.plans_title'))
        ->assertSee(__('front.home.features_title'))
        ->assertSee(__('front.home.faq_title'))
        ->assertSee('Business'); // seeded plan rendered via pricing card
});

it('runs the mock domain search from the public site', function (): void {
    $this->post(route('front.domains.check'), ['domain' => 'volna-domena.cz'])
        ->assertRedirect();

    expect(session('domain_check_result'))
        ->toBeArray()
        ->and(session('domain_check_result')['available'])->toBeTrue();

    $this->post(route('front.domains.check'), ['domain' => 'seznam.cz'])
        ->assertRedirect();

    expect(session('domain_check_result')['available'])->toBeFalse();
});

it('serves the new marketing pages', function (string $route): void {
    $this->seed(ProductCatalogSeeder::class);

    $this->get(route($route))->assertOk();
})->with([
    'front.wordpress',
    'front.managed',
    'front.features.ai',
    'front.features.monitoring',
    'front.features.backups',
    'front.builder',
    'front.support',
    'front.kb',
]);
