<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\Http\Controllers\Web\SurfaceController;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Content\ContentEditor;
use Onhost\Domain\Content\ContentService;

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
    /** W7-4b: the cache key of the sitemap's knowledge base rows; staff publishing an article forgets it. */
    public const SITEMAP_CACHE = ContentEditor::SITEMAP_CACHE_KEY;

    /** W7-4b: the path prefix of a knowledge base article ('/znalostni-baze/{slug}'). */
    public const KB_PREFIX = '/znalostni-baze/';

    /** The path prefix of the Slovak public site (W6-6). */
    public const SK_PREFIX = '/sk';

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
        $url = self::base().($lang === 'sk' ? self::langPath('sk', $cs) : $cs);
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
        $home = self::base().($lang === 'sk' ? self::langPath('sk', '/') : '/');
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
    /** @param  array<string, mixed>|null  $article  W7-4: the published post of a /blog/{slug} page */
    public static function withHead(string $html, string $path, ?array $article = null): string
    {
        $head = $article === null ? self::head($path) : self::articleHead($path, $article);

        return $head === '' ? $html : (string) preg_replace('~</head>~', addcslashes($head, '\\$')."\n</head>", $html, 1);
    }

    /** W7-4: a page that is not there (an unknown, draft or withheld post) carries noindex in its head as well as in its header. */
    public static function withNoindex(string $html): string
    {
        return (string) preg_replace('~</head>~', '<meta name="robots" content="noindex, nofollow">'."\n</head>", $html, 1);
    }

    /**
     * W7-4: the head of a published blog post — its title and perex, a self canonical (a Slovak page without a Slovak title of its own
     * is canonical to the Czech one), og:type article and JSON-LD BlogPosting. The author (a member of staff) is deliberately left out
     * until their consent is on record.
     *
     * @param  array<string, mixed>  $article
     */
    private static function articleHead(string $path, array $article): string
    {
        [$lang] = self::split($path);
        $slug = (string) ($article['slug'] ?? '');
        $cs = '/blog/'.$slug;
        $own = $lang === 'cs' || ($article['translated'] ?? false) === true;
        $url = self::base().($lang === 'sk' && $own ? self::langPath('sk', $cs) : $cs);
        $title = (string) ($article['title'] ?? '');
        $description = (string) ($article['excerpt'] ?? '');
        $siteName = self::text($lang, 'home', 'site_name') ?: 'ONhost';
        $tags = [];
        if ($title !== '') {
            $tags[] = '<title>'.self::attr($title.' | '.$siteName).'</title>';
        }
        if ($description !== '') {
            $tags[] = '<meta name="description" content="'.self::attr($description).'">';
        }
        $links = ['<link rel="alternate" hreflang="cs" href="'.self::attr(self::base().$cs).'">'];
        if (in_array('sk', self::languages(), true) && $lang === 'sk' && $own) { // a Czech page cannot tell yet; the sitemap carries the pair
            $links[] = '<link rel="alternate" hreflang="sk" href="'.self::attr(self::base().self::langPath('sk', $cs)).'">';
        }
        $links[] = '<link rel="alternate" hreflang="x-default" href="'.self::attr(self::base().$cs).'">';
        $tags[] = implode("\n", $links);
        $tags[] = '<link rel="canonical" href="'.self::attr($url).'">';
        $og = ['og:type' => 'article', 'og:title' => $title, 'og:description' => $description, 'og:url' => $url, 'og:locale' => $lang === 'sk' && $own ? 'sk_SK' : 'cs_CZ', 'og:site_name' => $siteName];
        foreach ($og as $property => $content) {
            if ($content !== '') {
                $tags[] = '<meta property="'.$property.'" content="'.self::attr($content).'">';
            }
        }
        $tags[] = '<meta name="twitter:card" content="summary">';
        $posting = array_filter([
            '@type' => 'BlogPosting', 'headline' => $title, 'description' => $description,
            'datePublished' => is_string($article['date'] ?? null) ? $article['date'] : null,
            'dateModified' => is_string($article['updated_at'] ?? null) ? $article['updated_at'] : null,
            'inLanguage' => $lang === 'sk' && $own ? 'sk' : 'cs', 'mainEntityOfPage' => $url, 'publisher' => ['@id' => self::base().'/#organization'],
        ], fn (mixed $v): bool => $v !== null && $v !== '');
        $graph = self::graph($lang, $cs, $url, $siteName);
        $graph[] = $posting;
        $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($json)) {
            $tags[] = '<script type="application/ld+json">'.$json.'</script>';
        }

        return implode("\n", $tags);
    }

    /** W7-4b: the slug of a knowledge base article path ('/znalostni-baze/{slug}', also under /sk/), null for any other path. */
    public static function kbSlug(string $path): ?string
    {
        [, $cs] = self::split($path);

        return preg_match('~^/znalostni-baze/([a-z0-9-]+)$~', $cs, $m) === 1 ? $m[1] : null;
    }

    /**
     * W7-4b: the head of a published knowledge base article — title, description (the perex), self canonical and JSON-LD
     * TechArticle {headline, dateModified = published_at, inLanguage, publisher}; no author.
     *
     * @param  array<string, mixed>  $article  ContentService::kbArticle()
     */
    public static function kbHead(string $path, array $article): string
    {
        [$lang, $cs] = self::split($path);
        $title = (string) ($article['title'] ?? '');
        $description = (string) ($article['excerpt'] ?? '');
        $siteName = self::text($lang, 'home', 'site_name') ?: 'ONhost';
        $url = self::base().($lang === 'sk' ? self::langPath('sk', $cs) : $cs);
        $tags = ['<title>'.self::attr($title.' | '.$siteName).'</title>'];
        if ($description !== '') {
            $tags[] = '<meta name="description" content="'.self::attr($description).'">';
        }
        $tags[] = '<link rel="canonical" href="'.self::attr($url).'">';
        $node = ['@context' => 'https://schema.org', '@type' => 'TechArticle', 'headline' => $title, 'inLanguage' => $lang, 'url' => $url,
            'publisher' => ['@type' => 'Organization', '@id' => self::base().'/#organization', 'name' => $siteName, 'url' => self::base().'/']];
        if (is_string($article['updated'] ?? null) && $article['updated'] !== '') {
            $node['dateModified'] = $article['updated'];
        }
        if ($description !== '') {
            $node['description'] = $description;
        }
        $json = json_encode($node, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($json)) {
            $tags[] = '<script type="application/ld+json">'.$json.'</script>';
        }

        return implode("\n", $tags);
    }

    /**
     * W7-4b head seam for an article page, as withHead().
     *
     * @param  array<string, mixed>  $article
     */
    public static function withKbHead(string $html, string $path, array $article): string
    {
        return (string) preg_replace('~</head>~', addcslashes(self::kbHead($path, $article), '\\$')."\n</head>", $html, 1);
    }

    /**
     * W7-4b: the published knowledge base articles as sitemap rows (one query, cached until staff publish an article).
     *
     * @return list<array{slug: string, lastmod: string|null}>
     */
    public static function kbRows(): array
    {
        /** @var list<array{slug: string, lastmod: string|null}> $rows */
        $rows = app(ContentService::class)->kbSitemap(); // the whole sitemap is cached under SITEMAP_CACHE

        return $rows;
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

    /** The sitemap, cached for an hour (W7-4); ContentEditor forgets it with every write of a post or reference. */
    public static function sitemap(): string
    {
        return (string) Cache::remember(ContentEditor::SITEMAP_CACHE_KEY, 3600, fn (): string => self::buildSitemap());
    }

    private static function buildSitemap(): string
    {
        $twins = array_values(array_diff(self::languages(), ['cs']));
        $sk = in_array('sk', $twins, true);
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
        // W7-4: the published blog posts (one query); the Slovak twin only with the switch on and a Slovak title of its own
        foreach (app(ContentService::class)->sitemapPosts() as $post) {
            $path = '/blog/'.$post['slug'];
            $lastmod = $post['updated_at'] === null ? '' : '<lastmod>'.Carbon::parse($post['updated_at'])->toAtomString().'</lastmod>';
            if (! ($sk && $post['sk'])) {
                $xml .= '  <url><loc>'.self::xml(self::base().$path).'</loc>'.$lastmod.'</url>'."\n";

                continue;
            }
            $alternates = '<xhtml:link rel="alternate" hreflang="cs" href="'.self::xml(self::base().$path).'"/>'
                .'<xhtml:link rel="alternate" hreflang="sk" href="'.self::xml(self::base().self::langPath('sk', $path)).'"/>'
                .'<xhtml:link rel="alternate" hreflang="x-default" href="'.self::xml(self::base().$path).'"/>';
            foreach ([$path, self::langPath('sk', $path)] as $loc) {
                $xml .= '  <url><loc>'.self::xml(self::base().$loc).'</loc>'.$lastmod.$alternates.'</url>'."\n";
            }
        }
        foreach (self::kbRows() as $row) { // W7-4b: the Czech article pages, lastmod = published_at
            $xml .= '  <url><loc>'.self::xml(self::base().self::KB_PREFIX.$row['slug']).'</loc>'.($row['lastmod'] === null ? '' : '<lastmod>'.self::xml($row['lastmod']).'</lastmod>').'</url>'."\n";
        }

        return $xml.'</urlset>'."\n";
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
