<?php

declare(strict_types=1);

use App\Models\KbArticle;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
    Cache::flush();
});

// ── Helper ────────────────────────────────────────────────────────────────────

function makeKbArticle(array $attrs = []): KbArticle
{
    return KbArticle::create(array_merge([
        'title'        => 'Test KB Article',
        'slug'         => 'test-kb-article-' . uniqid(),
        'category'     => 'general',
        'excerpt'      => 'Test excerpt',
        'body'         => 'Test body content.',
        'is_published' => true,
        'sort_order'   => 1,
        'locale'       => 'cs',
        'views_count'  => 0,
    ], $attrs));
}

// ── View counter increments ───────────────────────────────────────────────────

it('viewing a KB article increments views_count', function (): void {
    $user    = customerUser();
    $article = makeKbArticle();

    expect($article->views_count)->toBe(0);

    $this->actingAs($user)
         ->get(route('panel.kb.show', $article->slug))
         ->assertOk();

    expect($article->fresh()->views_count)->toBe(1);
});

it('viewing a KB article twice from same IP does not double-count', function (): void {
    $user    = customerUser();
    $article = makeKbArticle();

    $this->actingAs($user)->get(route('panel.kb.show', $article->slug));
    $this->actingAs($user)->get(route('panel.kb.show', $article->slug));

    expect($article->fresh()->views_count)->toBe(1);
});

it('articles start with zero views_count', function (): void {
    $article = makeKbArticle(['views_count' => 0]);
    expect($article->views_count)->toBe(0);
});

// ── Admin KB analytics ────────────────────────────────────────────────────────

it('admin can view KB analytics page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.kb.analytics'))
         ->assertOk()
         ->assertSee('Analytika znalostní báze');
});

it('admin KB analytics shows most viewed articles', function (): void {
    $admin = adminUser();
    makeKbArticle(['title' => 'Most Popular Article', 'slug' => 'most-popular', 'views_count' => 150]);
    makeKbArticle(['title' => 'Less Popular Article', 'slug' => 'less-popular', 'views_count' => 50]);

    $this->actingAs($admin)
         ->get(route('admin.kb.analytics'))
         ->assertOk()
         ->assertSee('Most Popular Article')
         ->assertSee('150');
});

it('admin KB analytics shows total views count', function (): void {
    $admin = adminUser();
    makeKbArticle(['views_count' => 100]);
    makeKbArticle(['views_count' => 200]);

    $this->actingAs($admin)
         ->get(route('admin.kb.analytics'))
         ->assertOk()
         ->assertSee('300');
});

it('customer cannot access KB analytics', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.kb.analytics'))
         ->assertForbidden();
});

it('admin KB analytics orders articles by views descending', function (): void {
    $admin = adminUser();
    makeKbArticle(['title' => 'Low Views',  'slug' => 'low-views',  'views_count' => 10]);
    makeKbArticle(['title' => 'High Views', 'slug' => 'high-views', 'views_count' => 999]);

    $response = $this->actingAs($admin)
                     ->get(route('admin.kb.analytics'))
                     ->assertOk();

    $content = $response->getContent();
    $posHigh = strpos($content, 'High Views');
    $posLow  = strpos($content, 'Low Views');

    expect($posHigh)->toBeLessThan($posLow);
});
