<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Controllers\Web\SurfaceController;

/**
 * N15 (variant B, ROZHODNUTI-vlna2 2026-10-15): the public site's sitemap and its hreflang links, both through seams only.
 * Only public, indexable URLs (no cart, sign-in, registration, password reset, panel, admin, status or signed links). hreflang
 * lists the languages that have their own approved text: Czech today; Slovak is added to LANGUAGES only after the owner approves
 * the SK texts (a sk→cs fallback would publish duplicate content). JSON-LD Offer is deliberately left for later (one price source).
 */
final class PublicSeo
{
    /** Public surface paths that must never be indexed (forms and personal flows). */
    public const NOT_INDEXED = ['kosik', 'prihlaseni', 'registrace', 'obnova-hesla'];

    /** Languages with an approved text of the public site, as hreflang codes ('sk' only after the owner approves the SK texts). */
    public const LANGUAGES = ['cs'];

    public static function base(): string
    {
        return rtrim((string) config('onhost.portal_url', config('app.url')), '/');
    }

    /** @return list<string> the public, indexable paths ('/' first) */
    public static function paths(): array
    {
        $paths = ['/'];
        foreach (SurfaceController::PUBLIC_PATHS as $path) {
            if (! in_array($path, self::NOT_INDEXED, true)) {
                $paths[] = '/'.$path;
            }
        }
        $paths[] = '/dokumenty';

        return array_values(array_unique($paths));
    }

    /** @return list<string> absolute URLs of every public, indexable page */
    public static function urls(): array
    {
        return array_map(fn (string $p) => self::base().$p, self::paths());
    }

    /** The hreflang links for one request path (each approved language plus x-default), empty for a page outside the sitemap. */
    public static function hreflang(string $path): string
    {
        $path = '/'.trim($path, '/');
        if (! in_array($path, self::paths(), true)) {
            return '';
        }
        $url = htmlspecialchars(self::base().$path, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $links = [];
        foreach (self::LANGUAGES as $lang) {
            $links[] = '<link rel="alternate" hreflang="'.$lang.'" href="'.$url.'">';
        }
        $links[] = '<link rel="alternate" hreflang="x-default" href="'.$url.'">';

        return implode("\n", $links);
    }

    /** Head seam: the links go right before </head> of the rendered page; the prototype file itself stays untouched. */
    public static function withHreflang(string $html, string $path): string
    {
        $links = self::hreflang($path);

        return $links === '' ? $html : (string) preg_replace('~</head>~', $links."\n</head>", $html, 1);
    }

    public static function sitemap(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach (self::urls() as $url) {
            $xml .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'."\n";
        }

        return $xml.'</urlset>'."\n";
    }
}
