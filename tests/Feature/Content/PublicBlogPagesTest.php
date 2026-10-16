<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Content\Models\Post;

/*
 * W7-4: /blog/{slug} is an indexable article page. A published post gets its own title, perex, self canonical, og:type article and
 * JSON-LD BlogPosting (no author until consent is on record); a draft, a withheld or an unknown post is a real 404 with noindex
 * (no soft 404). The sitemap lists the published posts with <lastmod>, is cached and forgotten with every editor write.
 */

function pbpConfig(array $extra = []): void
{
    config(array_merge(['onhost.portal_url' => 'https://onhost.example', 'onhost.ui.demo' => false, 'onhost.locale_sk' => false, 'onhost.seo.indexable' => true], $extra));
}

function pbpPost(string $slug, string $state = 'published', array $title = ['cs' => 'Jak jsme dostali PUE na 1,18']): Post
{
    return Post::query()->create([
        'slug' => $slug, 'kind' => 'blog', 'category' => ['cs' => 'Provoz'], 'title' => $title, 'excerpt' => ['cs' => 'Chlazení, měření a čísla.'],
        'body' => ['cs' => [['', 'PUE 1,18']]], 'author' => 'Jan Zaměstnanec', 'state' => $state, 'published_on' => '2026-10-01',
    ]);
}

/** @return array<string, mixed> the BlogPosting node of a page */
function pbpPosting(string $html): array
{
    expect(preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m))->toBe(1);
    $graph = (array) (json_decode($m[1], true)['@graph'] ?? []);

    return (array) collect($graph)->firstWhere('@type', 'BlogPosting');
}

it('serves a published post with its own title, description, canonical and a BlogPosting without author', function () {
    pbpConfig();
    pbpPost('pue-118');
    $response = $this->get('/blog/pue-118')->assertOk();
    $html = (string) $response->getContent();
    expect($response->headers->get('X-Robots-Tag'))->toBeNull()
        ->and(substr_count($html, '<title>'))->toBe(1)
        ->and($html)->toContain('<title>Jak jsme dostali PUE na 1,18 | ONhost</title>')
        ->toContain('<meta name="description" content="Chlazení, měření a čísla.">')
        ->toContain('<link rel="canonical" href="https://onhost.example/blog/pue-118">')
        ->toContain('<meta property="og:type" content="article">')
        ->not->toContain('noindex')->not->toContain('Jan Zaměstnanec');
    $posting = pbpPosting($html);
    expect($posting)->toMatchArray([
        'headline' => 'Jak jsme dostali PUE na 1,18', 'description' => 'Chlazení, měření a čísla.', 'datePublished' => '2026-10-01',
        'inLanguage' => 'cs', 'mainEntityOfPage' => 'https://onhost.example/blog/pue-118', 'publisher' => ['@id' => 'https://onhost.example/#organization'],
    ])->and($posting)->toHaveKey('dateModified')->not->toHaveKey('author');
});

it('answers 404 with noindex for a draft, a withheld and an unknown post', function () {
    pbpConfig();
    pbpPost('koncept', 'draft');
    pbpPost('migrace-z-cloudu');
    foreach (['/blog/koncept', '/blog/migrace-z-cloudu', '/blog/neexistuje', '/blog/a/b'] as $path) {
        $response = $this->get($path)->assertNotFound();
        expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow', $path)
            ->and((string) $response->getContent())->toContain('<meta name="robots" content="noindex, nofollow">')->not->toContain('BlogPosting')->not->toContain('rel="canonical"');
    }
    $this->get('/blog')->assertOk();
});

it('lists published posts in the sitemap with lastmod, never a draft or a withheld post, Czech only while locale_sk is off', function () {
    pbpConfig();
    pbpPost('pue-118', 'published', ['cs' => 'PUE', 'sk' => 'PUE po slovensky']);
    pbpPost('koncept', 'draft');
    pbpPost('migrace-z-cloudu');
    $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toMatch('~<url><loc>https://onhost\.example/blog/pue-118</loc><lastmod>\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d</lastmod></url>~')
        ->not->toContain('/blog/koncept')->not->toContain('migrace-z-cloudu')->not->toContain('/sk/blog/')->not->toContain('xhtml:link');
});

it('lists the Slovak twin only with the switch on and a Slovak title of its own', function () {
    pbpConfig(['onhost.locale_sk' => true]);
    pbpPost('pue-118', 'published', ['cs' => 'PUE', 'sk' => 'PUE po slovensky']);
    pbpPost('jen-cesky');
    $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('<loc>https://onhost.example/sk/blog/pue-118</loc>')->not->toContain('/sk/blog/jen-cesky');
    $html = (string) $this->get('/sk/blog/jen-cesky')->assertOk()->getContent();
    expect($html)->toContain('<link rel="canonical" href="https://onhost.example/blog/jen-cesky">');
});

it('puts a post into the sitemap once it is published through the editor', function () {
    pbpConfig();
    $this->actingAs($this->staff('marketing_content'), 'sanctum');
    $draft = $this->postJson('/v1/staff/content/posts', ['title' => ['cs' => 'Nový článek'], 'category' => ['cs' => 'Provoz'], 'excerpt' => ['cs' => 'Perex.'], 'body' => ['cs' => [['', 'Text.']]]])
        ->assertCreated()->json('data');
    expect((string) $this->get('/sitemap.xml')->getContent())->not->toContain('/blog/'.$draft['slug'].'<');
    $this->postJson("/v1/staff/content/posts/{$draft['id']}/state", ['state' => 'published'])->assertOk();
    expect((string) $this->get('/sitemap.xml')->getContent())->toContain('/blog/'.$draft['slug'].'</loc>');
});

it('builds the sitemap with the same number of queries for n and 2n posts', function () {
    pbpConfig();
    $count = function (int $n): int {
        Post::query()->delete();
        cache()->forget('seo:sitemap');
        for ($i = 0; $i < $n; $i++) {
            pbpPost('clanek-'.$i);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/sitemap.xml')->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };
    expect($count(3))->toBe($count(6));
});
