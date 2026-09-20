<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

/**
 * What a customer may write into their own vhost. The text is pasted into the web server's configuration on a shared
 * node and the server is reloaded, so a directive that reaches outside the site is a way into the node — or, through
 * `proxy_pass`, into the management network behind it.
 *
 * The old check was a deny-list anchored at the start of a LINE. nginx is not line-oriented: several directives fit on
 * one line and inside a block, so `location /x { proxy_pass http://10.0.0.5:8888/; } location /y { root /etc; }` —
 * one line, none of it at a line start — passed, and gave a reverse proxy to the panel API and the node's /etc.
 *
 * Now the text is cut into STATEMENTS (at newlines, `;`, `{`, `}`) and the first word of each is judged:
 *  · nginx (`nginx`, aaPanel's `rewrite` file): an allow-list — rewriting, headers, caching, access rules, error pages.
 *    Anything that names a file, an upstream, a log, a module or a script engine is simply not on it.
 *  · Apache: line-oriented, so a deny-list holds — files, modules, handlers, proxies, logs, script and CGI aliases.
 */
final class CustomDirectives
{
    private const NGINX_ALLOWED = [
        'location', 'if', 'rewrite', 'return', 'set', 'break', 'try_files', 'error_page', 'index', 'autoindex', 'charset', 'etag', 'expires', 'add_header', 'more_set_headers', 'more_clear_headers',
        'allow', 'deny', 'satisfy', 'limit_except', 'client_max_body_size', 'client_body_timeout', 'gzip', 'gzip_types', 'gzip_min_length', 'gzip_comp_level', 'gzip_vary', 'gzip_static', 'brotli', 'brotli_types',
        'types', 'default_type', 'valid_referers', 'absolute_redirect', 'port_in_redirect', 'server_name_in_redirect', 'if_modified_since', 'open_file_cache', 'open_file_cache_valid', 'log_not_found',
        'keepalive_timeout', 'send_timeout', 'sendfile', 'tcp_nodelay', 'tcp_nopush', 'internal', 'limit_rate', 'limit_rate_after', 'max_ranges', 'merge_slashes', 'msie_padding', 'recursive_error_pages', 'underscores_in_headers',
    ];

    private const APACHE_DENIED = [
        'include', 'includeoptional', 'loadmodule', 'loadfile', 'sethandler', 'addhandler', 'removehandler', 'action', 'script', 'scriptalias', 'scriptaliasmatch', 'alias', 'aliasmatch', 'documentroot',
        'php_admin_value', 'php_admin_flag', 'proxypass', 'proxypassmatch', 'proxypassreverse', 'proxyremote', 'proxyrequests', 'sslproxyengine', 'rewritemap', 'customlog', 'errorlog', 'transferlog', 'forensiclog',
        'define', 'undefine', 'use', 'user', 'group', 'suexecusergroup', 'listen', 'servername', 'serveralias', 'virtualhost', '<virtualhost', 'sslcertificatefile', 'sslcertificatekeyfile', 'sslcacertificatefile',
        'authuserfile', 'authgroupfile', 'cgipassauth', 'fcgidwrapper', 'fcgidinitialenv', 'extfilterdefine', 'luahook', 'luahooktranslatename', 'perlmodule', 'wsgiscriptalias', 'dav', 'davlockdb',
    ];

    /** @return string|null the first statement that is not allowed, or null when the text may be written */
    public static function firstRefused(string $kind, string $content): ?string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $content) === 1) {
            return 'control characters';
        }
        $apache = $kind === 'apache';
        if ($apache) {
            $content = (string) preg_replace('/\\\\\n/', '', $content); // Apache joins a line ending in `\` with the next one: `Inclu\` + `de /etc/x` is `Include /etc/x`
        }
        // Only a whole-line comment is dropped from the check. What follows a `#` later in a line is NOT reliably a comment —
        // nginx keeps a `#` inside quotes, Apache has no inline comments at all — so text hidden there would be judged by us as
        // a comment and by the server as a directive. nginx text with a `#` anywhere else is refused outright.
        $lines = [];
        foreach (explode("\n", $content) as $line) {
            if (str_starts_with(ltrim($line), '#')) {
                continue;
            }
            if (! $apache && str_contains($line, '#')) {
                return 'a # inside a line (write comments on lines of their own)';
            }
            $lines[] = $line;
        }
        $statements = $apache ? $lines : (preg_split('/[;{}\n]/', implode("\n", $lines)) ?: []);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $word = strtolower((string) (preg_split('/[\s>]+/', ltrim($statement, '<'))[0] ?? ''));
            if ($apache) {
                $closing = str_starts_with($statement, '</');
                if (! $closing && (in_array($word, self::APACHE_DENIED, true) || in_array('<'.$word, self::APACHE_DENIED, true) || preg_match('~\bprg:|\bdbd:|\bproxy:|\[[^\]]*\bP\b[^\]]*\]\s*$~i', $statement) === 1)) {
                    return $statement;
                }

                continue;
            }
            if (! in_array($word, self::NGINX_ALLOWED, true)) {
                return $statement;
            }
        }

        return null;
    }
}
