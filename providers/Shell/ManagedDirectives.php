<?php

declare(strict_types=1);

namespace Onhost\Providers\Shell;

/**
 * The managed tools block of an ISPConfig site's web server directives: reverse proxies (a path forwarded to an
 * upstream URL) and the default documents (DirectoryIndex / index). ISPConfig has no API for either, so the platform
 * renders them into the site's Apache or nginx directives between markers, next to the customer's own directives and
 * the security block (SecurityRules). The state travels on the marker line as JSON, so reading it back is exact and
 * independent of the rendered syntax.
 */
final class ManagedDirectives
{
    public const BEGIN = '# ONHOST-TOOLS-BEGIN';

    public const END = '# ONHOST-TOOLS-END';

    /** @return array{proxies:list<array{name:string,path:string,target:string,cache:bool}>, index:list<string>} */
    public static function parse(string $directives): array
    {
        $state = ['proxies' => [], 'index' => []];
        if (! preg_match('/^'.preg_quote(self::BEGIN, '/').' (\{.*\})\s*$/m', $directives, $m)) {
            return $state;
        }
        $data = json_decode($m[1], true);
        if (! is_array($data)) {
            return $state;
        }
        foreach ((array) ($data['proxies'] ?? []) as $proxy) {
            if (is_array($proxy) && isset($proxy['name'], $proxy['target'])) {
                $state['proxies'][] = ['name' => (string) $proxy['name'], 'path' => self::path((string) ($proxy['path'] ?? '/')), 'target' => (string) $proxy['target'], 'cache' => (bool) ($proxy['cache'] ?? false)];
            }
        }
        $state['index'] = array_values(array_filter(array_map(fn ($n) => trim((string) $n), (array) ($data['index'] ?? [])), fn ($n) => $n !== ''));

        return $state;
    }

    /** A proxy path as one absolute prefix without a trailing slash ("/" for the root). */
    public static function path(string $path): string
    {
        $path = '/'.trim($path, '/');

        return $path === '//' ? '/' : $path;
    }

    /**
     * Renders the block for the node's web server; an empty state renders nothing (the block disappears).
     *
     * @param  array{proxies:list<array{name:string,path:string,target:string,cache:bool}>, index:list<string>}  $state
     */
    public static function render(array $state, string $server): string
    {
        $proxies = array_values($state['proxies'] ?? []);
        $index = array_values($state['index'] ?? []);
        if ($proxies === [] && $index === []) {
            return '';
        }
        $lines = [self::BEGIN.' '.json_encode(['proxies' => $proxies, 'index' => $index], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
        // ISPConfig's own vhost template ends its index list with standard_index.html (the "Welcome" page of an empty
        // site); a customer list replaces the template's line, so the fallback is kept at the end (live lesson: without it
        // an empty docroot answers 403)
        $rendered = in_array('standard_index.html', $index, true) ? $index : array_merge($index, ['standard_index.html']);
        if ($server === 'nginx') {
            if ($index !== []) {
                $lines[] = 'index '.implode(' ', $rendered).';';
            }
            foreach ($proxies as $proxy) {
                $prefix = self::path($proxy['path']) === '/' ? '/' : self::path($proxy['path']).'/';
                $lines[] = 'location '.$prefix.' {';
                $lines[] = '    proxy_pass '.rtrim($proxy['target'], '/').'/;';
                $lines[] = '    proxy_http_version 1.1;';
                $lines[] = '    proxy_set_header Host $host;';
                $lines[] = '    proxy_set_header X-Real-IP $remote_addr;';
                $lines[] = '    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;';
                $lines[] = '    proxy_set_header X-Forwarded-Proto $scheme;';
                $lines[] = '    proxy_set_header Upgrade $http_upgrade;';
                $lines[] = '    proxy_set_header Connection "upgrade";';
                $lines[] = '}';
            }
        } else {
            if ($index !== []) {
                $lines[] = 'DirectoryIndex '.implode(' ', $rendered);
            }
            if ($proxies !== []) {
                $lines[] = 'ProxyPreserveHost On';
                $lines[] = 'ProxyRequests Off';
            }
            foreach ($proxies as $proxy) {
                $prefix = self::path($proxy['path']) === '/' ? '/' : self::path($proxy['path']).'/';
                $target = rtrim($proxy['target'], '/').'/';
                $lines[] = 'ProxyPass '.$prefix.' '.$target;
                $lines[] = 'ProxyPassReverse '.$prefix.' '.$target;
            }
        }
        $lines[] = self::END;

        return implode("\n", $lines)."\n";
    }

    /** Replaces (or appends, or removes when the block is empty) the managed block inside the site's directives. */
    public static function splice(string $directives, string $block): string
    {
        $rest = self::strip($directives);
        if ($block === '') {
            return $rest;
        }

        return $rest === '' ? $block : $rest."\n".$block;
    }

    /** The directives without the managed block. */
    public static function strip(string $directives): string
    {
        return trim((string) preg_replace('/'.preg_quote(self::BEGIN, '/').'.*?'.preg_quote(self::END, '/').'\n?/s', '', $directives));
    }

    /** The managed block as it stands in the directives ('' when none). */
    public static function extract(string $directives): string
    {
        return preg_match('/'.preg_quote(self::BEGIN, '/').'.*?'.preg_quote(self::END, '/').'\n?/s', $directives, $m) ? $m[0] : '';
    }
}
