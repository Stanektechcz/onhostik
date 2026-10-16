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

    /** Search engines may index the public site (config onhost.seo.indexable; off outside production, so staging never is). */
    public static function indexable(): bool
    {
        return (bool) config('onhost.seo.indexable', false);
    }

    /** The key of a path in lang/{cs,sk}/seo.php ('/' → 'home', '/ceny/x' → 'ceny'). */
    private static function key(string $cs): string
    {
        $first = explode('/', trim($cs, '/'))[0];

        return $first === '' ? 'home' : $first;
    }

    /** One text of the seo table in the page's language, falling back to Czech. */
    private static function text(string $lang, string $key, string $field): string
    {
        $line = 'seo.pages.'.$key.'.'.$field;
        $value = trans($line, [], $lang);
        if ($lang !== 'cs' && (! is_string($value) || $value === $line)) {
            $value = trans($line, [], 'cs');
        }

        return is_string($value) && $value !== $line ? $value : '';
    }

    private static function attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * W7-3: the whole head of a public page — title, description, canonical, Open Graph, Twitter card and JSON-LD
     * (Organization, WebSite, BreadcrumbList) next to the hreflang links; forms and personal flows carry noindex in both languages.
     */
    public static function head(string $path): string
    {
        [$lang, $cs] = self::split($path);
        $key = self::key($cs);
        $title = self::text($lang, $key, 'title');
        $description = self::text($lang, $key, 'description');
        $tags = [];
        if ($title !== '') {
            $tags[] = '<title>'.self::attr($title).'</title>';
        }
        if ($description !== '') {
            $tags[] = '<meta name="description" content="'.self::attr($description).'">';
        }
        if (self::notIndexed($path)) {
            $tags[] = '<meta name="robots" content="noindex, nofollow">';

            return implode("\n", $tags);
        }
        $hreflang = self::hreflang($path);
        if ($hreflang === '') { // a page outside the sitemap (a blog post, a hash route): no canonical of its own here
            return implode("\n", $tags);
        }
        $tags[] = $hreflang;
        $url = self::base().($lang === 'sk' ? self::skPath($cs) : $cs);
        $siteName = self::text($lang, 'home', 'site_name') ?: 'ONhost';
        $tags[] = '<link rel="canonical" href="'.self::attr($url).'">';
        $og = ['og:type' => 'website', 'og:title' => $title, 'og:description' => $description, 'og:url' => $url, 'og:locale' => $lang === 'sk' ? 'sk_SK' : 'cs_CZ', 'og:site_name' => $siteName];
        foreach ($og as $property => $content) {
            if ($content !== '') {
                $tags[] = '<meta property="'.$property.'" content="'.self::attr($content).'">';
            }
        }
        $tags[] = '<meta name="twitter:card" content="summary">'; // no og:image until W7-K4 approves one
        $json = json_encode(['@context' => 'https://schema.org', '@graph' => self::graph($lang, $cs, $url, $siteName)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($json)) {
            $tags[] = '<script type="application/ld+json">'.$json.'</script>';
        }

        return implode("\n", $tags);
    }

    /** @return list<array<string, mixed>> the JSON-LD nodes of one page (no logo, no offers: W7-K4 and one price source) */
    private static function graph(string $lang, string $cs, string $url, string $siteName): array
    {
        $home = self::base().($lang === 'sk' ? self::skPath('/') : '/');
        $id = LegalIdentitySeam::identity();
        $organization = ['@type' => 'Organization', '@id' => self::base().'/#organization', 'name' => $siteName, 'url' => self::base().'/'];
        if ($id['name'] !== '') {
            $organization['legalName'] = $id['name'];
        }
        if ($id['ico'] !== '') {
            $organization['identifier'] = ['@type' => 'PropertyValue', 'propertyID' => 'IČO', 'value' => $id['ico']];
        }
        $contact = array_filter(['telephone' => $id['phone'], 'email' => $id['email']], fn (string $v): bool => $v !== '');
        if ($contact !== []) {
            $organization['contactPoint'] = ['@type' => 'ContactPoint', 'contactType' => 'customer service'] + $contact;
        }
        $graph = [$organization, ['@type' => 'WebSite', '@id' => $home.'#website', 'url' => $home, 'name' => $siteName, 'inLanguage' => $lang, 'publisher' => ['@id' => self::base().'/#organization']]];
        if ($cs !== '/') {
            $key = self::key($cs);
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => self::text($lang, 'home', 'crumb') ?: $siteName, 'item' => $home],
                ['@type' => 'ListItem', 'position' => 2, 'name' => self::text($lang, $key, 'crumb') ?: $key, 'item' => $url],
            ]];
        }

        return $graph;
    }

    /** W7-3 head seam: everything of head() goes right before </head> in one replacement; the prototype file stays untouched. */
    public static function withHead(string $html, string $path): string
    {
        $head = self::head($path);

        return $head === '' ? $html : (string) preg_replace('~</head>~', addcslashes($head, '\\$')."\n</head>", $html, 1);
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
