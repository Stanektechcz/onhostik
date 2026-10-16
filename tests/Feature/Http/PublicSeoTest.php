<?php

declare(strict_types=1);

use App\Http\Support\PublicSeo;
use App\Http\Support\SurfaceRenderer;

/*
 * N15 (variant B): /sitemap.xml lists only the public, indexable URLs; the public pages get hreflang links through the head
 * seam (Czech and x-default only until the owner approves the SK texts); the prototype files stay byte-identical.
 */

function publicSeoSitemapLocs(string $xml): array
{
    preg_match_all('~<loc>([^<]+)</loc>~', $xml, $m);

    return $m[1];
}

it('serves a sitemap with the public indexable pages only', function () {
    config(['onhost.portal_url' => 'https://onhost.example']);
    $response = $this->get('/sitemap.xml')->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/xml');
    $xml = $response->getContent();
    expect(simplexml_load_string($xml))->not->toBeFalse();
    $locs = publicSeoSitemapLocs($xml);
    expect($locs)->toContain('https://onhost.example/')->toContain('https://onhost.example/ceny')->toContain('https://onhost.example/dokumenty')
        ->and(count($locs))->toBe(count(array_unique($locs)));
    foreach ($locs as $loc) {
        expect($loc)->toStartWith('https://onhost.example/')
            ->not->toContain('/kosik')->not->toContain('/prihlaseni')->not->toContain('/registrace')->not->toContain('/obnova-hesla')
            ->not->toContain('/panel')->not->toContain('/sprava')->not->toContain('/partner')->not->toContain('/stav/')->not->toContain('?');
    }
});

it('adds hreflang cs and x-default to a public page, never sk before the texts are approved', function () {
    config(['onhost.portal_url' => 'https://onhost.example', 'onhost.ui.demo' => false]);
    $html = $this->get('/ceny')->assertOk()->getContent();
    expect($html)->toContain('<link rel="alternate" hreflang="cs" href="https://onhost.example/ceny">')
        ->toContain('<link rel="alternate" hreflang="x-default" href="https://onhost.example/ceny">')
        ->not->toContain('hreflang="sk"');
    expect(PublicSeo::languages())->not->toContain('sk'); // locale_sk is off by default (W6-6 adds sk only when it is on)
});

it('adds no hreflang to pages outside the sitemap and keeps the prototype untouched', function () {
    config(['onhost.ui.demo' => false]);
    $file = app(SurfaceRenderer::class)->root().'/'.SurfaceRenderer::SURFACES['public'];
    $before = hash_file('sha256', $file);
    expect($this->get('/kosik')->assertOk()->getContent())->not->toContain('hreflang=');
    expect(PublicSeo::hreflang('/prihlaseni'))->toBe('')->and(PublicSeo::hreflang('/panel'))->toBe('');
    expect(hash_file('sha256', $file))->toBe($before);
});
