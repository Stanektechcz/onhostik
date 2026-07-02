<?php

declare(strict_types=1);

use App\Models\Subscriber;
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

it('serves the status page with overall status', function (): void {
    $this->get(route('front.status'))
        ->assertOk()
        ->assertSee('Stav');
});

it('newsletter subscribe creates a new subscriber', function (): void {
    $this->post(route('front.newsletter.subscribe'), ['email' => 'test@example.com'])
        ->assertRedirect();

    expect(Subscriber::where('email', 'test@example.com')->where('is_active', true)->exists())->toBeTrue();
});

it('newsletter subscribe reactivates an unsubscribed email', function (): void {
    Subscriber::create([
        'email'            => 'old@example.com',
        'locale'           => 'cs',
        'source'           => 'website',
        'is_active'        => false,
        'unsubscribed_at'  => now()->subDay(),
    ]);

    $this->post(route('front.newsletter.subscribe'), ['email' => 'old@example.com'])
        ->assertRedirect();

    $sub = Subscriber::where('email', 'old@example.com')->firstOrFail();
    expect($sub->is_active)->toBeTrue()
        ->and($sub->unsubscribed_at)->toBeNull();
});

it('newsletter subscribe ignores already-active email silently', function (): void {
    Subscriber::create([
        'email'        => 'active@example.com',
        'locale'       => 'cs',
        'source'       => 'website',
        'is_active'    => true,
        'confirmed_at' => now(),
    ]);

    $this->post(route('front.newsletter.subscribe'), ['email' => 'active@example.com'])
        ->assertRedirect();

    expect(Subscriber::where('email', 'active@example.com')->count())->toBe(1);
});
