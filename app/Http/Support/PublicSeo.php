<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Controllers\Web\SurfaceController;

/**
 * N15 (variant B, ROZHODNUTI-vlna2 2026-10-15): the public site's sitemap and its hreflang links, both through seams only.
 * Only public, indexable URLs (no cart, sign-in, registration, password reset, panel, admin, status or signed links). hreflang
 * lists the languages that have their own approved text. W6-6 (owner decision 2026-10-16): the SK texts are approved and Slovak
 * lives at /sk/… on the same domain, but only while config('onhost.locale_sk') is on; off, every output is the Czech-only one.
 * JSON-LD Offer is deliberately left for later (one price source).
 */
final class PublicSeo
{
    /** Public surface paths that must never be indexed (forms and personal flows). */
    public const NOT_INDEXED = ['kosik', 'prihlaseni', 'registrace', 'obnova-hesla'];

    /** The path prefix of the Slovak public site (W6-6). */
    public const SK_PREFIX = '/sk';

    /** @return list<string> languages with an approved text of the public site, as hreflang codes ('sk' only while locale_sk is on) */
    public static function languages(): array
    {
        return (bool) config('onhost.locale_sk', false) ? ['cs', 'sk'] : ['cs'];
    }

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

    /** A Czech page with a Slovak twin under /sk/ (the public surface; the legal documents at /dokumenty stay Czech only). */
    public static function hasSk(string $path): bool
    {
        return in_array('sk', self::languages(), true) && ($path === '/' || in_array(explode('/', trim($path, '/'))[0], SurfaceController::PUBLIC_PATHS, true));
    }

    /** The Slovak path of a Czech path ('/' → '/sk/', '/ceny' → '/sk/ceny'). */
    public static function skPath(string $path): string
    {
        return $path === '/' ? self::SK_PREFIX.'/' : self::SK_PREFIX.$path;
    }

    /**
     * Splits a request path into its language and Czech path; '/sk/…' is Slovak only while locale_sk is on.
     *
     * @return array{0: string, 1: string}
     */
    private static function split(string $path): array
    {
        $path = '/'.trim($path, '/');
        if (in_array('sk', self::languages(), true) && ($path === self::SK_PREFIX || str_starts_with($path, self::SK_PREFIX.'/'))) {
            return ['sk', '/'.trim(substr($path, strlen(self::SK_PREFIX)), '/')];
        }

        return ['cs', $path];
    }

    /** A form or personal flow (cart, sign-in, registration, password reset), in either language. */
    public static function notIndexed(string $path): bool
    {
        [, $cs] = self::split($path);

        return in_array(explode('/', trim($cs, '/'))[0], self::NOT_INDEXED, true);
    }

    /** The hreflang links for one request path (each approved language plus x-default), empty for a page outside the sitemap. */
    public static function hreflang(string $path): string
    {
        [, $path] = self::split($path);
        if (! in_array($path, self::paths(), true)) {
            return '';
        }
        $url = htmlspecialchars(self::base().$path, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $links = [];
        foreach (self::languages() as $lang) {
            if ($lang === 'sk' && ! self::hasSk($path)) {
                continue;
            }
            $href = $lang === 'sk' ? htmlspecialchars(self::base().self::skPath($path), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $url;
            $links[] = '<link rel="alternate" hreflang="'.$lang.'" href="'.$href.'">';
        }
        $links[] = '<link rel="alternate" hreflang="x-default" href="'.$url.'">';

        return implode("\n", $links);
    }

    /** Head seam: the links go right before </head> of the rendered page; the prototype file itself stays untouched. */
    public static function withHreflang(string $html, string $path): string
    {
        $links = self::hreflang($path);
        [$lang, $cs] = self::split($path);
        if ($lang === 'sk') { // W6-6: a Slovak page is canonical to itself; its forms are never indexed
            if ($links !== '') {
                $links .= "\n".'<link rel="canonical" href="'.htmlspecialchars(self::base().self::skPath($cs), ENT_QUOTES | ENT_HTML5, 'UTF-8').'">';
            } elseif (self::notIndexed($path)) {
                $links = '<meta name="robots" content="noindex, nofollow">';
            }
        }

        return $links === '' ? $html : (string) preg_replace('~</head>~', $links."\n</head>", $html, 1);
    }

    public static function sitemap(): string
    {
        $sk = in_array('sk', self::languages(), true);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'.($sk ? ' xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '').'>'."\n";
        foreach (self::paths() as $path) {
            if (! self::hasSk($path)) {
                $xml .= '  <url><loc>'.self::xml(self::base().$path).'</loc></url>'."\n";

                continue;
            }
            $alternates = '<xhtml:link rel="alternate" hreflang="cs" href="'.self::xml(self::base().$path).'"/>'
                .'<xhtml:link rel="alternate" hreflang="sk" href="'.self::xml(self::base().self::skPath($path)).'"/>'
                .'<xhtml:link rel="alternate" hreflang="x-default" href="'.self::xml(self::base().$path).'"/>';
            foreach ([$path, self::skPath($path)] as $loc) {
                $xml .= '  <url><loc>'.self::xml(self::base().$loc).'</loc>'.$alternates.'</url>'."\n";
            }
        }

        return $xml.'</urlset>'."\n";
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
