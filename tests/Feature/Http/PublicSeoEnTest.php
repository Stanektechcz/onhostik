<?php

declare(strict_types=1);

use App\Http\Support\PublicSeo;
use App\Http\Support\SurfaceRenderer;
use Onhost\Domain\Content\Models\Post;

/*
 * W7-3-EN: the English public site at /en/… on the same domain, behind config('onhost.locale_en_urls') (default off, W7-K4).
 * Off, /en/… answers 404 and the sitemap and hreflang are byte for byte the W7-3 ones; on, /en/… renders the public surface
 * in English with hreflang cs/en/x-default (+sk while locale_sk is on), the sitemap carries xhtml:link alternates and the
 * forms under /en/ are noindex and never listed.
 */

/** The Czech-only sitemap (both switches off). */
function publicSeoEnLegacySitemap(): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach (PublicSeo::paths() as $path) {
        $xml .= '  <url><loc>'.htmlspecialchars(PublicSeo::base().$path, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'."\n";
    }

    return $xml.'</urlset>'."\n";
}

it('answers 404 under /en/ and keeps the sitemap and hreflang unchanged while the switch is off', function () {
    config(['onhost.portal_url' => 'https://onhost.example', 'onhost.ui.demo' => false, 'onhost.locale_sk' => false, 'onhost.locale_en_urls' => false]);
    Post::query()->delete(); // the migration publishes starter posts (W7-4); the pre-W7-3-EN shape is asserted without them
    $this->get('/en/')->assertNotFound();
    $this->get('/en/ceny')->assertNotFound();
    $this->get('/en')->assertNotFound();

    expect($this->get('/sitemap.xml')->assertOk()->getContent())->toBe(publicSeoEnLegacySitemap());
    expect(PublicSeo::hreflang('/ceny'))->toBe('<link rel="alternate" hreflang="cs" href="https://onhost.example/ceny">'."\n".'<link rel="alternate" hreflang="x-default" href="https://onhost.example/ceny">');
    expect(PublicSeo::hreflang('/en/ceny'))->toBe('');

    config(['onhost.locale_sk' => true]); // the Slovak output does not change either
    expect((string) $this->get('/sitemap.xml')->getContent())->not->toContain('/en/')->not->toContain('hreflang="en"');
    expect(PublicSeo::hreflang('/ceny'))->not->toContain('hreflang="en"');
});

it('renders /en/ceny in English with hreflang cs/en/x-default and a self canonical', function () {
    config(['onhost.portal_url' => 'https://onhost.example', 'onhost.ui.demo' => false, 'onhost.locale_sk' => false, 'onhost.locale_en_urls' => true]);
    $html = (string) $this->get('/en/ceny')->assertOk()->getContent();
    expect(app()->getLocale())->toBe('en');
    expect($html)->toContain('<link rel="alternate" hreflang="cs" href="https://onhost.example/ceny">')
        ->toContain('<link rel="alternate" hreflang="en" href="https://onhost.example/en/ceny">')
        ->toContain('<link rel="alternate" hreflang="x-default" href="https://onhost.example/ceny">')
        ->toContain('<link rel="canonical" href="https://onhost.example/en/ceny">')
        ->not->toContain('hreflang="sk"')
        ->not->toContain('noindex');

    config(['onhost.locale_sk' => true]);
    expect(PublicSeo::hreflang('/en/ceny'))->toContain('hreflang="sk" href="https://onhost.example/sk/ceny"')
        ->toContain('hreflang="en" href="https://onhost.example/en/ceny"');
    expect((string) $this->get('/en/')->assertOk()->getContent())->toContain('<link rel="canonical" href="https://onhost.example/en/">');
});

it('lists the English pages in the sitemap with xhtml:link alternates, never the forms', function () {
    config(['onhost.portal_url' => 'https://onhost.example', 'onhost.locale_sk' => false, 'onhost.locale_en_urls' => true]);
    $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();
    expect(simplexml_load_string($xml))->not->toBeFalse();
    expect($xml)->toContain('xmlns:xhtml="http://www.w3.org/1999/xhtml"')
        ->toContain('<url><loc>https://onhost.example/en/ceny</loc>')
        ->toContain('<url><loc>https://onhost.example/en/</loc>')
        ->toContain('<xhtml:link rel="alternate" hreflang="en" href="https://onhost.example/en/ceny"/>')
        ->toContain('<xhtml:link rel="alternate" hreflang="x-default" href="https://onhost.example/ceny"/>')
        ->not->toContain('/kosik')->not->toContain('/prihlaseni')->not->toContain('/en/dokumenty')->not->toContain('/sk/');
    preg_match_all('~<loc>([^<]+)</loc>~', $xml, $m);
    expect(count($m[1]))->toBe(count(array_unique($m[1])));
});

it('serves the forms under /en/ with noindex and keeps the panel and the console out of /en/', function () {
    config(['onhost.portal_url' => 'https://onhost.example', 'onhost.ui.demo' => false, 'onhost.locale_en_urls' => true]);
    $file = app(SurfaceRenderer::class)->root().'/'.SurfaceRenderer::SURFACES['public'];
    $before = hash_file('sha256', $file);

    $response = $this->get('/en/kosik')->assertOk();
    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
    expect((string) $response->getContent())->toContain('<meta name="robots" content="noindex, nofollow">')->not->toContain('hreflang=');
    expect(PublicSeo::hreflang('/en/kosik'))->toBe('');

    $this->get('/en/panel')->assertNotFound();
    $this->get('/en/sprava')->assertNotFound();
    expect(hash_file('sha256', $file))->toBe($before);
});
