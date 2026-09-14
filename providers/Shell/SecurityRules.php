<?php

declare(strict_types=1);

namespace Onhost\Providers\Shell;

/**
 * The managed security block of a site, rendered for nginx (aaPanel include, ISPConfig nginx directives) and Apache
 * (ISPConfig apache directives): IP deny/allow lists, bad-bot blocking, hotlink protection, HSTS and the standard
 * security headers. Rate limits live in aaPanel's per-site connection limits. Parsing reads the rules back from the
 * marker line so the customer's own directives next to the block stay untouched.
 */
final class SecurityRules
{
    public const BEGIN = '# ONHOST-SECURITY-BEGIN';

    public const END = '# ONHOST-SECURITY-END';

    public const PHP_EDITABLE = ['memory_limit', 'upload_max_filesize', 'post_max_size', 'max_execution_time', 'max_input_time', 'max_input_vars', 'display_errors', 'date.timezone', 'default_charset', 'opcache.enable'];

    public const BAD_BOTS = 'AhrefsBot|SemrushBot|MJ12bot|DotBot|PetalBot|BLEXBot|DataForSeoBot|serpstatbot|python-requests|masscan|nikto|sqlmap|zgrab|libwww-perl';

    /** @return array{deny:list<string>, allow:list<string>, bots:bool, hotlink:bool, hotlink_allow:list<string>, hsts:bool, headers:bool, rate:?array{perip:int,perserver:int,limit_rate:int}} */
    public static function defaults(): array
    {
        return ['deny' => [], 'allow' => [], 'bots' => false, 'hotlink' => false, 'hotlink_allow' => [], 'hsts' => false, 'headers' => false, 'rate' => null];
    }

    /** @param array<string,mixed> $rules @return array{deny:list<string>, allow:list<string>, bots:bool, hotlink:bool, hotlink_allow:list<string>, hsts:bool, headers:bool, rate:?array{perip:int,perserver:int,limit_rate:int}} */
    public static function normalize(array $rules): array
    {
        $ips = fn (mixed $list) => array_values(array_unique(array_filter(array_map(fn ($ip) => trim((string) $ip), (array) $list), fn ($ip) => $ip !== '' && (filter_var(explode('/', $ip)[0], FILTER_VALIDATE_IP) !== false))));
        $hosts = fn (mixed $list) => array_values(array_unique(array_filter(array_map(fn ($h) => strtolower(trim((string) $h)), (array) $list), fn ($h) => $h !== '' && preg_match('/^\*?\.?[a-z0-9.-]+$/', $h))));
        $rate = null;
        if (isset($rules['rate']) && is_array($rules['rate'])) {
            $rate = ['perip' => max(0, min(1000, (int) ($rules['rate']['perip'] ?? 0))), 'perserver' => max(0, min(10000, (int) ($rules['rate']['perserver'] ?? 0))), 'limit_rate' => max(0, min(1000000, (int) ($rules['rate']['limit_rate'] ?? 0)))];
        }

        return ['deny' => $ips($rules['deny'] ?? []), 'allow' => $ips($rules['allow'] ?? []), 'bots' => (bool) ($rules['bots'] ?? false), 'hotlink' => (bool) ($rules['hotlink'] ?? false), 'hotlink_allow' => $hosts($rules['hotlink_allow'] ?? []), 'hsts' => (bool) ($rules['hsts'] ?? false), 'headers' => (bool) ($rules['headers'] ?? false), 'rate' => $rate];
    }

    /** The block for nginx (server context). */
    public static function nginx(array $rules, string $domain): string
    {
        $rules = self::normalize($rules);
        $lines = [self::BEGIN, '# managed by ONhost '.self::encode($rules)];
        foreach ($rules['deny'] as $ip) {
            $lines[] = "deny {$ip};";
        }
        if ($rules['allow'] !== []) {
            foreach ($rules['allow'] as $ip) {
                $lines[] = "allow {$ip};";
            }
            $lines[] = 'deny all;';
        }
        if ($rules['bots']) {
            $lines[] = 'if ($http_user_agent ~* "('.self::BAD_BOTS.')") { return 403; }';
        }
        if ($rules['hotlink']) {
            $extra = $rules['hotlink_allow'] !== [] ? ' '.implode(' ', $rules['hotlink_allow']) : '';
            $lines[] = 'valid_referers none blocked server_names '.($domain !== '' ? "*.{$domain} " : '').ltrim($extra).';';
            $lines[] = 'set $onhost_hl 0;';
            $lines[] = 'if ($request_uri ~* "\.(jpe?g|png|gif|webp|avif|svg|mp4|webm|zip|pdf)$") { set $onhost_hl 1; }';
            $lines[] = 'if ($invalid_referer) { set $onhost_hl "${onhost_hl}1"; }';
            $lines[] = 'if ($onhost_hl = "11") { return 403; }';
        }
        if ($rules['hsts']) {
            $lines[] = 'add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;';
        }
        if ($rules['headers']) {
            $lines[] = 'add_header X-Content-Type-Options nosniff always;';
            $lines[] = 'add_header X-Frame-Options SAMEORIGIN always;';
            $lines[] = 'add_header Referrer-Policy strict-origin-when-cross-origin always;';
            $lines[] = 'add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;';
        }
        $lines[] = self::END;

        return implode("\n", $lines)."\n";
    }

    /** The block for Apache (vhost context). */
    public static function apache(array $rules, string $domain): string
    {
        $rules = self::normalize($rules);
        $lines = [self::BEGIN, '# managed by ONhost '.self::encode($rules)];
        if ($rules['deny'] !== [] || $rules['allow'] !== []) {
            $lines[] = '<RequireAll>';
            $lines[] = $rules['allow'] !== [] ? '  Require ip '.implode(' ', $rules['allow']) : '  Require all granted';
            foreach ($rules['deny'] as $ip) {
                $lines[] = "  Require not ip {$ip}";
            }
            $lines[] = '</RequireAll>';
        }
        if ($rules['bots']) {
            $lines[] = 'SetEnvIfNoCase User-Agent "('.self::BAD_BOTS.')" onhost_bad_bot';
            $lines[] = '<RequireAll>';
            $lines[] = '  Require all granted';
            $lines[] = '  Require not env onhost_bad_bot';
            $lines[] = '</RequireAll>';
        }
        if ($rules['hotlink']) {
            $allowed = array_merge($domain !== '' ? [preg_quote($domain, '/')] : [], array_map(fn ($h) => str_replace('\\*', '.*', preg_quote(ltrim($h, '*.'), '/')), $rules['hotlink_allow']));
            $lines[] = 'RewriteEngine On';
            $lines[] = 'RewriteCond %{HTTP_REFERER} !^$';
            foreach ($allowed as $host) {
                $lines[] = 'RewriteCond %{HTTP_REFERER} !^https?://([^/]+\.)?'.$host.'(/|$) [NC]';
            }
            $lines[] = 'RewriteRule \.(jpe?g|png|gif|webp|avif|svg|mp4|webm|zip|pdf)$ - [F,NC]';
        }
        if ($rules['hsts']) {
            $lines[] = 'Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"';
        }
        if ($rules['headers']) {
            $lines[] = 'Header always set X-Content-Type-Options nosniff';
            $lines[] = 'Header always set X-Frame-Options SAMEORIGIN';
            $lines[] = 'Header always set Referrer-Policy strict-origin-when-cross-origin';
            $lines[] = 'Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"';
        }
        $lines[] = self::END;

        return implode("\n", $lines)."\n";
    }

    /** Replace (or append) the managed block inside a directives text, keeping everything the customer wrote. */
    public static function splice(string $directives, string $block): string
    {
        $pattern = '/'.preg_quote(self::BEGIN, '/').'.*?'.preg_quote(self::END, '/').'\n?/s';
        if (preg_match($pattern, $directives)) {
            return (string) preg_replace($pattern, str_replace('$', '\$', $block), $directives, 1);
        }

        return rtrim($directives) === '' ? $block : rtrim($directives)."\n".$block;
    }

    /** The customer's own directives without the managed block. */
    public static function strip(string $directives): string
    {
        return trim((string) preg_replace('/'.preg_quote(self::BEGIN, '/').'.*?'.preg_quote(self::END, '/').'\n?/s', '', $directives));
    }

    /** The managed block as it stands in the directives ('' when none). */
    public static function extract(string $directives): string
    {
        return preg_match('/'.preg_quote(self::BEGIN, '/').'.*?'.preg_quote(self::END, '/').'\n?/s', $directives, $m) ? $m[0] : '';
    }

    /** Rules encoded on the marker line of a rendered block. @return array<string,mixed> */
    public static function parse(string $rendered): array
    {
        if (preg_match('/# managed by ONhost (\{.*\})$/m', $rendered, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return self::normalize($decoded);
            }
        }

        return self::defaults();
    }

    public static function iniValue(string $key, string $value): string
    {
        $value = trim($value);
        if (in_array($key, ['display_errors', 'opcache.enable'], true)) {
            return in_array(strtolower($value), ['1', 'on', 'true', 'yes'], true) ? 'On' : 'Off';
        }
        if (in_array($key, ['memory_limit', 'upload_max_filesize', 'post_max_size'], true)) {
            return preg_match('/^\d+[KMG]?$/i', $value) ? strtoupper($value) : '128M';
        }
        if (in_array($key, ['max_execution_time', 'max_input_time', 'max_input_vars'], true)) {
            return (string) max(0, (int) $value);
        }
        if ($key === 'date.timezone') {
            return in_array($value, timezone_identifiers_list(), true) ? $value : 'Europe/Prague';
        }

        return preg_replace('/[^\w.\/-]/', '', $value) ?? '';
    }

    private static function encode(array $rules): string
    {
        return (string) json_encode(array_diff_key($rules, ['rate' => 1]), JSON_UNESCAPED_SLASHES);
    }
}
