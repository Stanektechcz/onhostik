<?php

declare(strict_types=1);

use App\Http\Support\PublicSeo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Onhost\Domain\Support\Models\KnowledgeArticle;

/*
 * W7-4b: the knowledge base article page /znalostni-baze/{slug} — a published article gets its own head (title, perex as
 * description, canonical, JSON-LD TechArticle without an author), anything else is a 404 with noindex; the sitemap lists the
 * published articles with lastmod = published_at in one query, and staff publishing an article refreshes it.
 */

function pkbConfig(): void
{
    config(['onhost.portal_url' => 'https://onhost.example', 'onhost.ui.demo' => false, 'onhost.locale_sk' => false, 'onhost.seo.indexable' => true]);
}

function pkbArticle(string $slug, string $state = 'published', string $publishedAt = '2026-10-01 08:00:00'): KnowledgeArticle
{
    return KnowledgeArticle::query()->forceCreate([
        'slug' => $slug, 'category' => 'DNS', 'title' => ['cs' => 'Nastavení DNS '.$slug], 'excerpt' => ['cs' => 'Jak nastavit záznamy '.$slug.'.'],
        'body' => ['cs' => [['Postup', 'Text článku.']]], 'tags' => [], 'state' => $state, 'author' => 'Interní autor', 'read_minutes' => 4, 'published_at' => $publishedAt,
    ]);
}

it('serves a published article with its own title, description, canonical and TechArticle', function () {
    pkbConfig();
    pkbArticle('pkb-dns');
    $response = $this->get('/znalostni-baze/pkb-dns')->assertOk();
    $html = (string) $response->getContent();
    expect(substr_count($html, '<title>'))->toBe(1)
        ->and($html)->toContain('<title>Nastavení DNS pkb-dns | ')
        ->toContain('<meta name="description" content="Jak nastavit záznamy pkb-dns.">')
        ->toContain('<link rel="canonical" href="https://onhost.example/znalostni-baze/pkb-dns">')
        ->not->toContain('noindex')
        ->not->toContain('Interní autor');
    expect(preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m))->toBe(1);
    $node = json_decode($m[1], true);
    expect($node['@type'])->toBe('TechArticle')
        ->and($node['headline'])->toBe('Nastavení DNS pkb-dns')
        ->and($node['dateModified'])->toBe('2026-10-01')
        ->and($node['inLanguage'])->toBe('cs')
        ->and($node['publisher']['@type'])->toBe('Organization')
        ->and($node)->not->toHaveKey('author');
    expect($response->headers->get('X-Robots-Tag'))->toBeNull();
});

it('answers 404 with noindex for an unknown or unpublished article', function () {
    pkbConfig();
    pkbArticle('pkb-draft', 'draft');
    foreach (['/znalostni-baze/pkb-draft', '/znalostni-baze/pkb-missing'] as $path) {
        $response = $this->get($path)->assertNotFound();
        expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
    }
    $this->get('/znalostni-baze')->assertOk(); // the list page is untouched
});

it('lists the published articles in the sitemap with lastmod (cached under seo:sitemap)', function () {
    pkbConfig();
    pkbArticle('pkb-live');
    pkbArticle('pkb-hidden', 'draft');
    $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('<url><loc>https://onhost.example/znalostni-baze/pkb-live</loc><lastmod>2026-10-01</lastmod></url>')
        ->not->toContain('pkb-hidden');
    expect(Cache::has(PublicSeo::SITEMAP_CACHE))->toBeTrue();
});

it('reads the sitemap articles in the same number of queries for n and 2n articles', function () {
    pkbConfig();
    $count = function (): int {
        Cache::forget(PublicSeo::SITEMAP_CACHE);
        DB::flushQueryLog();
        DB::enableQueryLog();
        PublicSeo::sitemap();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };
    foreach (range(1, 3) as $i) {
        pkbArticle('pkb-n-'.$i);
    }
    $small = $count();
    foreach (range(4, 6) as $i) {
        pkbArticle('pkb-n-'.$i);
    }
    expect($count())->toBe($small)->and($small)->toBeLessThanOrEqual(1);
});
