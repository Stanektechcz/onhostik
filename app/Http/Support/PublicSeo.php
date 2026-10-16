<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Controllers\Web\SurfaceController;

/**
 * N15 (variant B, ROZHODNUTI-vlna2 2026-10-15): the public site's sitemap and its hreflang links, both through seams only.
 * Only public, indexable URLs (no cart, sign-in, registration, password reset, panel, admin, status or signed links). hreflang
 * lists the languages that have their own approved text. W6-6 (owner decision 2026-10-16): the SK texts are approved and Slovak
 * lives at /sk/… on the same domain, but only while config('onhost.locale_sk') is on; off, every output is the Czech-only one.
 * W7-3-EN: English lives at /en/… the same way, behind config('onhost.locale_en_urls') (off until the owner approves lang/en/seo.php, W7-K4).
 * JSON-LD Offer is deliberately left for later (one price source).
 */
final class PublicSeo
{
    /** Public surface paths that must never be indexed (forms and personal flows). */
    public const NOT_INDEXED = ['kosik', 'prihlaseni', 'registrace', 'obnova-hesla'];

    /** The path prefixes of the translated public sites (W6-6 sk, W7-3-EN en). */
    public const PREFIXES = ['sk' => '/sk', 'en' => '/en'];

    /** @return list<string> languages with an approved text of the public site, as hreflang codes ('sk' while locale_sk, 'en' while locale_en_urls is on) */
    public static function languages(): array
    {
        $languages = ['cs'];
        if ((bool) config('onhost.locale_sk', false)) {
            $languages[] = 'sk';
        }
        if ((bool) config('onhost.locale_en_urls', false)) {
            $languages[] = 'en';
        }

        return $languages;
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

    /** A Czech page with a twin under /sk/ or /en/ (the public surface; the legal documents at /dokumenty stay Czech only). */
    public static function hasTwin(string $lang, string $path): bool
    {
        return isset(self::PREFIXES[$lang]) && in_array($lang, self::languages(), true) && ($path === '/' || in_array(explode('/', trim($path, '/'))[0], SurfaceController::PUBLIC_PATHS, true));
    }

    /** The path of a Czech path in another language ('/' → '/sk/', '/ceny' → '/en/ceny'). */
    public static function langPath(string $lang, string $path): string
    {
        $prefix = self::PREFIXES[$lang];

        return $path === '/' ? $prefix.'/' : $prefix.$path;
    }

    /**
     * Splits a request path into its language and Czech path; '/sk/…' and '/en/…' count only while their switch is on.
     *
     * @return array{0: string, 1: string}
     */
    private static function split(string $path): array
    {
        $path = '/'.trim($path, '/');
        foreach (self::PREFIXES as $lang => $prefix) {
            if (in_array($lang, self::languages(), true) && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
                return [$lang, '/'.trim(substr($path, strlen($prefix)), '/')];
            }
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
            if ($lang !== 'cs' && ! self::hasTwin($lang, $path)) {
                continue;
            }
            $href = $lang !== 'cs' ? htmlspecialchars(self::base().self::langPath($lang, $path), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $url;
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
        if ($lang !== 'cs') { // W6-6, W7-3-EN: a translated page is canonical to itself; its forms are never indexed
            if ($links !== '') {
                $links .= "\n".'<link rel="canonical" href="'.htmlspecialchars(self::base().self::langPath($lang, $cs), ENT_QUOTES | ENT_HTML5, 'UTF-8').'">';
            } elseif (self::notIndexed($path)) {
                $links = '<meta name="robots" content="noindex, nofollow">';
            }
        }

        return $links === '' ? $html : (string) preg_replace('~</head>~', $links."\n</head>", $html, 1);
    }

    public static function sitemap(): string
    {
        $twins = array_values(array_diff(self::languages(), ['cs']));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'.($twins !== [] ? ' xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '').'>'."\n";
        foreach (self::paths() as $path) {
            $langs = array_values(array_filter($twins, fn (string $lang) => self::hasTwin($lang, $path)));
            if ($langs === []) {
                $xml .= '  <url><loc>'.self::xml(self::base().$path).'</loc></url>'."\n";

                continue;
            }
            $locs = [$path];
            $alternates = '<xhtml:link rel="alternate" hreflang="cs" href="'.self::xml(self::base().$path).'"/>';
            foreach ($langs as $lang) {
                $locs[] = self::langPath($lang, $path);
                $alternates .= '<xhtml:link rel="alternate" hreflang="'.$lang.'" href="'.self::xml(self::base().self::langPath($lang, $path)).'"/>';
            }
            $alternates .= '<xhtml:link rel="alternate" hreflang="x-default" href="'.self::xml(self::base().$path).'"/>';
            foreach ($locs as $loc) {
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
