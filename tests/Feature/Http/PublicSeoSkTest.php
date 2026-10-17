<?php

declare(strict_types=1);

use App\Http\Support\PublicSeo;
use App\Http\Support\SurfaceRenderer;
use Onhost\Domain\Content\Models\Post;

/*
 * W6-6 (owner decision 2026-10-16): the Slovak public site at /sk/… on the same domain, behind config('onhost.locale_sk').
 * Off, /sk/… answers 404 and the sitemap and hreflang are byte for byte the Czech-only ones; on, /sk/… renders the public
 * surface in Slovak with hreflang cs/sk/x-default, the sitemap carries xhtml:link alternates, forms under /sk/ are noindex and
 * never listed. The .sk domain only redirects (301) at the edge to the fixed host onhost.cz, path and query kept.
 */

/** The Czech-only sitemap as N15 built it before W6-6. */
function publicSeoSkLegacySitemap(): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach (PublicSeo::paths() as $path) {
        $xml .= '  <url><loc>'.htmlspecialchars(PublicSeo::base().$path, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'."\n";
    }

    return $xml.'</urlset>'."\n";
}

it('answers 404 under /sk/ and keeps the sitemap and hreflang Czech only while the switch is off', function () {
    config(['onhost.portal_url' => 'https://onhost.example', 'onhost.ui.demo' => false, 'onhost.locale_sk' => false]);
    Post::query()->delete(); // the migration publishes starter posts (W7-4); the pre-W6-6 shape is asserted without them
    $this->get('/sk/')->assertNotFound();
    $this->get('/sk/ceny')->assertNotFound();
    $this->get('/sk')->assertNotFound();

    expect($this->get('/sitemap.xml')->assertOk()->getContent())->toBe(publicSeoSkLegacySitemap());
    expect(PublicSeo::hreflang('/ceny'))->toBe('<link rel="alternate" hreflang="cs" href="https://onhost.example/ceny">'."\n".'<link rel="alternate" hreflang="x-default" href="https://onhost.example/ceny">');
    expect(PublicSeo::hreflang('/sk/ceny'))->toBe(''); // not a Slovak page while off
});

it('renders /sk/ceny in Slovak with the hreflang triple and a self canonical', function () {
    config(['onhost.portal_url' => 'https://onhost.example', 'onhost.ui.demo' => false, 'onhost.locale_sk' => true]);
    $html = (string) $this->get('/sk/ceny')->assertOk()->getContent();
    expect(app()->getLocale())->toBe('sk');
    expect($html)->toContain('<link rel="alternate" hreflang="cs" href="https://onhost.example/ceny">')
        ->toContain('<link rel="alternate" hreflang="sk" href="https://onhost.example/sk/ceny">')
        ->toContain('<link rel="alternate" hreflang="x-default" href="https://onhost.example/ceny">')
        ->toContain('<link rel="canonical" href="https://onhost.example/sk/ceny">')
        ->not->toContain('noindex');

    $cs = (string) $this->get('/ceny')->assertOk()->getContent();
    expect($cs)->toContain('<link rel="alternate" hreflang="sk" href="https://onhost.example/sk/ceny">')
        ->toContain('<link rel="alternate" hreflang="cs" href="https://onhost.example/ceny">');

    expect(PublicSeo::hreflang('/'))->toContain('hreflang="sk" href="https://onhost.example/sk/"');
    expect((string) $this->get('/sk/')->assertOk()->getContent())->toContain('<link rel="canonical" href="https://onhost.example/sk/">');
});

it('lists the Slovak pages in the sitemap with xhtml:link alternates, never the forms', function () {
    config(['onhost.portal_url' => 'https://onhost.example', 'onhost.locale_sk' => true]);
    $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();
    expect(simplexml_load_string($xml))->not->toBeFalse();
    expect($xml)->toContain('xmlns:xhtml="http://www.w3.org/1999/xhtml"')
        ->toContain('<url><loc>https://onhost.example/sk/ceny</loc>')
        ->toContain('<url><loc>https://onhost.example/sk/</loc>')
        ->toContain('<xhtml:link rel="alternate" hreflang="sk" href="https://onhost.example/sk/ceny"/>')
        ->toContain('<xhtml:link rel="alternate" hreflang="x-default" href="https://onhost.example/ceny"/>')
        ->not->toContain('/kosik')->not->toContain('/prihlaseni')->not->toContain('/sk/dokumenty');
    preg_match_all('~<loc>([^<]+)</loc>~', $xml, $m);
    expect(count($m[1]))->toBe(count(array_unique($m[1])));
});

it('serves the forms under /sk/ with noindex and keeps the panel and the console out of /sk/', function () {
    config(['onhost.portal_url' => 'https://onhost.example', 'onhost.ui.demo' => false, 'onhost.locale_sk' => true]);
    $file = app(SurfaceRenderer::class)->root().'/'.SurfaceRenderer::SURFACES['public'];
    $before = hash_file('sha256', $file);

    $response = $this->get('/sk/kosik')->assertOk();
    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
    expect((string) $response->getContent())->toContain('<meta name="robots" content="noindex, nofollow">')->not->toContain('hreflang=');
    expect(PublicSeo::hreflang('/sk/kosik'))->toBe('');

    $this->get('/sk/panel')->assertNotFound();
    $this->get('/sk/sprava')->assertNotFound();
    expect(hash_file('sha256', $file))->toBe($before);
});

it('redirects the .sk domain at the edge to the fixed host with 301, keeping path and query', function () {
    $caddy = (string) file_get_contents(base_path('infra/edge/Caddyfile.sk-redirect'));
    expect($caddy)->toMatch('~^onhost\.sk, www\.onhost\.sk \{$~m')
        ->toContain('redir https://onhost.cz/sk{uri} 301')
        ->not->toContain('{host}');

    $nginx = (string) file_get_contents(base_path('infra/edge/nginx-sk-redirect.conf'));
    expect(substr_count($nginx, 'server_name onhost.sk www.onhost.sk;'))->toBe(2)
        ->and(substr_count($nginx, 'return 301 https://onhost.cz/sk$request_uri;'))->toBe(2);
    expect($nginx)->not->toContain('$host')->not->toContain('$uri;');
});
