<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * What one fetch of an advisory feed returned. Each advisory is a normalized array:
 *
 *     key            CVE id when the advisory has one, else `<source>:<id>` (the merge key across feeds)
 *     source         kev | nvd | wordfence | osv
 *     external_id    the feed's own id
 *     aliases        other ids (CVE, GHSA, vendor)
 *     title, summary plain text, cut to 300 / 4 000 characters
 *     severity       critical | high | medium | low | unknown;  cvss  ?float
 *     kev            exploited in the wild (CISA KEV);  kev_added_on  ?Y-m-d
 *     affected       list of {ecosystem, product, introduced?, fixed?, last_affected?, versions?}; ecosystems are the inventory's:
 *                    wordpress-core | wordpress-plugin | wordpress-theme | cpe (`vendor:product`) | Packagist | npm | Debian:12 …
 *     references     https URLs
 *     published_at, modified_at   ISO 8601
 *
 * `complete` is false when the feed had more than one run may take (a page limit); the cursor then resumes inside the window.
 */
final class AdvisoryBatch
{
    /** @param list<array<string,mixed>> $advisories */
    public function __construct(public readonly array $advisories, public readonly ?string $cursor, public readonly bool $complete = true) {}

    /** Plain text of bounded length: a feed's HTML and control characters never reach the platform's pages or mails. */
    public static function text(mixed $value, int $max): string
    {
        // only tag-like runs go (<b>, </p>, <!-- -->): strip_tags() would also eat "<= 5.9.7", the way Wordfence titles name a range
        $text = (string) preg_replace('~<[a-zA-Z/!?][^>]*>~u', '', (string) (is_scalar($value) ? $value : ''));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $text);
        $text = trim((string) preg_replace('/[ \t]+/u', ' ', $text));

        return mb_substr($text, 0, $max);
    }

    public static function severity(?float $cvss, ?string $label = null): string
    {
        $label = strtolower(trim((string) $label));
        $label = $label === 'moderate' ? 'medium' : $label;
        if (in_array($label, ['critical', 'high', 'medium', 'low'], true)) {
            return $label;
        }
        if ($cvss === null) {
            return 'unknown';
        }

        return match (true) {
            $cvss >= 9.0 => 'critical',
            $cvss >= 7.0 => 'high',
            $cvss >= 4.0 => 'medium',
            $cvss > 0.0 => 'low',
            default => 'unknown',
        };
    }

    /** @param  list<mixed>  $urls @return list<string> */
    public static function references(array $urls, int $max = 12): array
    {
        $out = [];
        foreach ($urls as $url) {
            $url = is_array($url) ? ($url['url'] ?? null) : $url;
            if (is_string($url) && preg_match('#^https://[^\s<>"]{3,500}$#', $url) === 1 && ! in_array($url, $out, true)) {
                $out[] = $url;
            }
        }

        return array_slice($out, 0, $max);
    }

    /** A version string as feeds and panels write it, kept when it looks like one (`6.4.3`, `v1.33.5+rke2r1`, `1:2.4.57-2`). */
    public static function version(mixed $value): ?string
    {
        $v = trim((string) (is_scalar($value) ? $value : ''));

        return $v !== '' && $v !== '*' && $v !== '-' && preg_match('/^[0-9A-Za-z][0-9A-Za-z.+~:_-]{0,59}$/', $v) === 1 ? $v : null;
    }
}
