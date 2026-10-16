<?php

declare(strict_types=1);

use App\Http\Support\PublicSeo;

/*
 * W7-3: the head of every public page (title, description, canonical, Open Graph, Twitter card, JSON-LD) through the head seam,
 * noindex for the Czech forms and for everything outside production, robots.txt with the sitemap and llms.txt. The prototype
 * stays byte-identical (the sha1 test of apps/surfaces).
 */

function pshConfig(array $extra = []): void
{
    config(array_merge(['onhost.portal_url' => 'https://onhost.example', 'onhost.ui.demo' => false, 'onhost.locale_sk' => false, 'onhost.seo.indexable' => true], $extra));
}

/** @return array<string, mixed> the decoded JSON-LD of a page */
function pshJsonLd(string $html): array
{
    expect(preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m))->toBe(1);
    $data = json_decode($m[1], true);
    expect($data)->toBeArray();

    return $data;
}

it('gives every sitemap page exactly one title, description and self canonical with a JSON-LD graph', function () {
    pshConfig();
    foreach (PublicSeo::paths() as $path) {
        $html = (string) $this->get($path)->assertOk()->getContent();
        expect(substr_count($html, '<title>'))->toBe(1, $path)
            ->and(substr_count($html, '<meta name="description"'))->toBe(1, $path)
            ->and(substr_count($html, '<link rel="canonical"'))->toBe(1, $path)
            ->and($html)->toContain('<link rel="canonical" href="https://onhost.example'.$path.'">')
            ->toContain('<meta property="og:locale" content="cs_CZ">')
            ->toContain('<meta name="twitter:card" content="summary">')
            ->not->toContain('og:image')->not->toContain('noindex');
        $types = array_column(pshJsonLd($html)['@graph'], '@type');
        expect($types)->toContain('Organization')->toContain('WebSite');
        expect(in_array('BreadcrumbList', $types, true))->toBe($path !== '/');
    }
});

it('keeps the JSON-LD and the attributes closed against a hostile seo text', function () {
    pshConfig();
    app('translator')->addLines(['seo.pages.ceny.title' => 'x</script><script>alert(1)</script>', 'seo.pages.ceny.crumb' => '</script><script>alert(2)'], 'cs');
    $html = (string) $this->get('/ceny')->assertOk()->getContent();
    expect($html)->not->toContain('<script>alert(');
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
    expect($m[1])->not->toContain('</script')->not->toContain('<');
    expect(pshJsonLd($html)['@graph'][2]['itemListElement'][1]['name'])->toBe('</script><script>alert(2)');
});

it('names the operator in the Organization node without a logo', function () {
    pshConfig();
    $org = pshJsonLd((string) $this->get('/')->getContent())['@graph'][0];
    expect($org['identifier']['value'])->toBe((string) config('onhost.legal_entity.ico'))
        ->and($org)->not->toHaveKey('logo')->toHaveKey('legalName');
});

it('has no Slovak element while locale_sk is off', function () {
    pshConfig();
    $html = (string) $this->get('/ceny')->getContent();
    expect($html)->not->toContain('hreflang="sk"')->not->toContain('sk_SK')->not->toContain('/sk/');
});

it('marks the Czech forms noindex in meta and header and keeps them out of the sitemap', function () {
    pshConfig();
    $sitemap = (string) $this->get('/sitemap.xml')->getContent();
    foreach (PublicSeo::NOT_INDEXED as $form) {
        $response = $this->get('/'.$form)->assertOk();
        expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
        expect((string) $response->getContent())->toContain('<meta name="robots" content="noindex, nofollow">')->not->toContain('rel="canonical"');
        expect($sitemap)->not->toContain('/'.$form.'<');
    }
    expect($sitemap)->not->toContain('<lastmod>');
});

it('sends noindex on every public page outside production', function () {
    pshConfig(['onhost.seo.indexable' => false]);
    foreach (['/', '/ceny', '/dokumenty'] as $path) {
        expect($this->get($path)->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
    }
    pshConfig();
    expect($this->get('/ceny')->headers->has('X-Robots-Tag'))->toBeFalse();
});

it('points robots.txt at the sitemap without hiding the forms', function () {
    $robots = (string) file_get_contents(public_path('robots.txt'));
    expect($robots)->toContain('Sitemap: https://onhost.cz/sitemap.xml')->toContain('Disallow: /sprava')->toContain('Disallow: /panel')
        ->not->toContain('kosik')->not->toContain('prihlaseni');
});

it('links llms.txt only to sitemap pages', function () {
    pshConfig(['onhost.portal_url' => 'https://onhost.cz']);
    preg_match_all('~\]\((https://onhost\.cz[^)]*)\)~', (string) file_get_contents(public_path('llms.txt')), $m);
    expect($m[1])->not->toBeEmpty();
    foreach ($m[1] as $url) {
        $path = substr($url, strlen('https://onhost.cz'));
        expect($path === '/sitemap.xml' || in_array($path, PublicSeo::paths(), true))->toBeTrue($url);
    }
});
