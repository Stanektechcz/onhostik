<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail\Reputation;

/**
 * The blocklists the platform asks (V-R1, docs/proposals/antispam-reputation.md §2). Curated in code, never scraped: of the
 * ~900 lists ever published about 600 are dead, and a dead zone can be re-registered to answer every question "listed".
 * SORBS (shut down 2024-06-05) is deliberately absent.
 *
 * - `kinds`: what a list is asked about — `ipv4`, `ipv6` (nibble-reversed), `domain`.
 * - `weight`: `major` = the big receivers act on it (a listing opens an incident), `minor` = recorded and shown,
 *   `info` = lists networks by policy, not by behaviour (UCEPROTECT L2/L3, Spamhaus PBL): shown, never an incident or a doctor row.
 * - `codes`: the weight of single answer codes where they differ from the list's (ZEN's PBL codes are `info`).
 * - `errors`: answers that are NOT listings — the querier was refused (public resolver, volume) or asked wrongly.
 * - `dqs`: the Spamhaus Data Query Service zone used instead when ONHOST_SPAMHAUS_DQS_KEY is set.
 * - `delist`: `expiry` (the list drops it after the cause stops), `form` (a person submits the list's removal form — the
 *   platform prepares the request, it never fills a captcha), `api` (none today).
 */
final class BlocklistCatalog
{
    public const MAJOR = 'major';

    public const MINOR = 'minor';

    public const INFO = 'info';

    private const SPAMHAUS_ERRORS = ['127.255.255.252', '127.255.255.254', '127.255.255.255'];

    /**
     * @var array<string, array{zone:string, name:string, kinds:list<string>, weight:string, codes?:array<string,string>, errors:list<string>, dqs?:string, delist:string, url:string}>
     */
    public const LISTS = [
        // ── major: addresses ────────────────────────────────────────────────────────────────────────────────────────────
        'spamhaus-zen' => ['zone' => 'zen.spamhaus.org', 'name' => 'Spamhaus ZEN', 'kinds' => ['ipv4', 'ipv6'], 'weight' => self::MAJOR,
            'codes' => ['127.0.0.10' => self::INFO, '127.0.0.11' => self::INFO], // PBL: the range should not send directly (policy)
            'errors' => self::SPAMHAUS_ERRORS, 'dqs' => 'zen', 'delist' => 'form', 'url' => 'https://check.spamhaus.org/'],
        'barracuda' => ['zone' => 'b.barracudacentral.org', 'name' => 'Barracuda BRBL', 'kinds' => ['ipv4'], 'weight' => self::MAJOR,
            'errors' => [], 'delist' => 'form', 'url' => 'https://www.barracudacentral.org/rbl/removal-request'],
        'spamcop' => ['zone' => 'bl.spamcop.net', 'name' => 'SpamCop', 'kinds' => ['ipv4'], 'weight' => self::MAJOR,
            'errors' => [], 'delist' => 'expiry', 'url' => 'https://www.spamcop.net/bl.shtml'],
        'mailspike' => ['zone' => 'bl.mailspike.net', 'name' => 'Mailspike BL', 'kinds' => ['ipv4'], 'weight' => self::MAJOR,
            'errors' => [], 'delist' => 'form', 'url' => 'https://mailspike.org/iplookup.html'],
        'psbl' => ['zone' => 'psbl.surriel.com', 'name' => 'PSBL', 'kinds' => ['ipv4'], 'weight' => self::MAJOR,
            'errors' => [], 'delist' => 'form', 'url' => 'https://psbl.org/remove'],
        // ── major: domains ──────────────────────────────────────────────────────────────────────────────────────────────
        'spamhaus-dbl' => ['zone' => 'dbl.spamhaus.org', 'name' => 'Spamhaus DBL', 'kinds' => ['domain'], 'weight' => self::MAJOR,
            'errors' => [...self::SPAMHAUS_ERRORS, '127.0.1.255'], 'dqs' => 'dbl', 'delist' => 'form', 'url' => 'https://check.spamhaus.org/'],
        'surbl' => ['zone' => 'multi.surbl.org', 'name' => 'SURBL multi', 'kinds' => ['domain'], 'weight' => self::MAJOR,
            'errors' => ['127.0.0.1'], 'delist' => 'form', 'url' => 'https://www.surbl.org/surbl-analysis'],
        'uribl' => ['zone' => 'multi.uribl.com', 'name' => 'URIBL multi', 'kinds' => ['domain'], 'weight' => self::MAJOR,
            'codes' => ['127.0.0.4' => self::INFO], // grey: bulk sender, not a spam source
            'errors' => ['127.0.0.1'], 'delist' => 'form', 'url' => 'https://admin.uribl.com/'],
        // ── minor ─────────────────────────────────────────────────────────────────────────────────────────────────────
        'nixspam' => ['zone' => 'ix.dnsbl.manitu.net', 'name' => 'NiX Spam', 'kinds' => ['ipv4'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'expiry', 'url' => 'https://www.nixspam.net/'],
        'uceprotect-1' => ['zone' => 'dnsbl-1.uceprotect.net', 'name' => 'UCEPROTECT L1', 'kinds' => ['ipv4'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'expiry', 'url' => 'https://www.uceprotect.net/en/rblcheck.php'],
        'spamrats' => ['zone' => 'all.spamrats.com', 'name' => 'SpamRATS', 'kinds' => ['ipv4'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'form', 'url' => 'https://www.spamrats.com/removal.php'],
        'dronebl' => ['zone' => 'dnsbl.dronebl.org', 'name' => 'DroneBL', 'kinds' => ['ipv4', 'ipv6'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'form', 'url' => 'https://dronebl.org/lookup'],
        'backscatterer' => ['zone' => 'ips.backscatterer.org', 'name' => 'Backscatterer', 'kinds' => ['ipv4'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'expiry', 'url' => 'https://www.backscatterer.org/'],
        '0spam' => ['zone' => 'bl.0spam.org', 'name' => '0spam', 'kinds' => ['ipv4'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'form', 'url' => 'https://0spam.org/'],
        'justspam' => ['zone' => 'dnsbl.justspam.org', 'name' => 'JustSpam', 'kinds' => ['ipv4'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'expiry', 'url' => 'http://www.justspam.org/'],
        's5h' => ['zone' => 'all.s5h.net', 'name' => 's5h.net', 'kinds' => ['ipv4'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'form', 'url' => 'https://www.usenix.org.uk/content/rbl.html'],
        'nordspam' => ['zone' => 'bl.nordspam.com', 'name' => 'NordSpam', 'kinds' => ['ipv4', 'ipv6'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'form', 'url' => 'https://www.nordspam.com/'],
        'nordspam-dbl' => ['zone' => 'dbl.nordspam.com', 'name' => 'NordSpam DBL', 'kinds' => ['domain'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'form', 'url' => 'https://www.nordspam.com/'],
        'gbudb' => ['zone' => 'truncate.gbudb.net', 'name' => 'GBUdb Truncate', 'kinds' => ['ipv4'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'expiry', 'url' => 'https://www.gbudb.com/'],
        'spfbl' => ['zone' => 'dnsbl.spfbl.net', 'name' => 'SPFBL', 'kinds' => ['ipv4', 'ipv6', 'domain'], 'weight' => self::MINOR, 'errors' => [], 'delist' => 'form', 'url' => 'https://spfbl.net/en/delist/'],
        // ── info: whole networks by policy ──────────────────────────────────────────────────────────────────────────────
        'uceprotect-2' => ['zone' => 'dnsbl-2.uceprotect.net', 'name' => 'UCEPROTECT L2', 'kinds' => ['ipv4'], 'weight' => self::INFO, 'errors' => [], 'delist' => 'expiry', 'url' => 'https://www.uceprotect.net/en/rblcheck.php'],
        'uceprotect-3' => ['zone' => 'dnsbl-3.uceprotect.net', 'name' => 'UCEPROTECT L3', 'kinds' => ['ipv4'], 'weight' => self::INFO, 'errors' => [], 'delist' => 'expiry', 'url' => 'https://www.uceprotect.net/en/rblcheck.php'],
    ];

    /** The lists in use: every list but the ones the operator switched off (ONHOST_DNSBL_DISABLED). @return array<string, array<string,mixed>> */
    public static function enabled(): array
    {
        $off = array_map('strval', (array) config('onhost.antispam.dnsbl.disabled', []));

        return array_diff_key(self::LISTS, array_flip($off));
    }

    /** @return array<string,mixed>|null */
    public static function list(string $key): ?array
    {
        return self::LISTS[$key] ?? null;
    }

    /** Does the list take questions about this kind of target? */
    public static function asks(string $key, string $kind): bool
    {
        return in_array($kind, (array) (self::LISTS[$key]['kinds'] ?? []), true);
    }

    /**
     * The name to ask: the target in the list's form, under its zone — or its DQS zone when a key is set. The key is part of
     * the question only; nothing stores or prints this name (the listing keeps the public zone).
     */
    public static function questionFor(string $key, string $kind, string $target): ?string
    {
        $list = self::LISTS[$key] ?? null;
        if ($list === null || ! self::asks($key, $kind)) {
            return null;
        }
        $subject = match ($kind) {
            'ipv4' => implode('.', array_reverse(explode('.', $target))),
            'ipv6' => self::nibbles($target),
            default => rtrim(mb_strtolower($target), '.'),
        };
        if ($subject === null || $subject === '') {
            return null;
        }
        $dqsKey = trim((string) config('onhost.antispam.dnsbl.spamhaus_dqs_key', ''));
        $zone = isset($list['dqs']) && $dqsKey !== '' && preg_match('/^[a-z0-9]{20,40}$/', $dqsKey) === 1
            ? $dqsKey.'.'.$list['dqs'].'.dq.spamhaus.net'
            : $list['zone'];

        return $subject.'.'.$zone;
    }

    /**
     * What the answer means for this list: `listed` with the code and its weight, `clean`, or `refused` when the list only
     * answered with an error code (the querier was refused — never a listing, never "clean").
     *
     * @param  list<string>  $addresses
     * @return array{outcome:string, code:?string, weight:?string}
     */
    public static function classify(string $key, array $addresses): array
    {
        $list = self::LISTS[$key];
        $errors = (array) $list['errors'];
        $listed = array_values(array_filter($addresses, fn (string $a) => str_starts_with($a, '127.') && ! in_array($a, $errors, true)));
        if ($listed === []) {
            $refused = array_values(array_filter($addresses, fn (string $a) => in_array($a, $errors, true)));

            return $refused !== [] ? ['outcome' => 'refused', 'code' => $refused[0], 'weight' => null] : ['outcome' => 'clean', 'code' => null, 'weight' => null];
        }
        // the strongest code decides: one major code among info codes is a major listing
        $rank = [self::MAJOR => 3, self::MINOR => 2, self::INFO => 1];
        usort($listed, fn (string $a, string $b) => $rank[self::weightOf($key, $b)] <=> $rank[self::weightOf($key, $a)] ?: strcmp($a, $b));

        return ['outcome' => 'listed', 'code' => $listed[0], 'weight' => self::weightOf($key, $listed[0])];
    }

    public static function weightOf(string $key, string $code): string
    {
        return (string) (self::LISTS[$key]['codes'][$code] ?? self::LISTS[$key]['weight']);
    }

    /** An IPv6 address as 32 reversed nibbles (`::1` → `1.0.0.0…`), null when it is not one. */
    private static function nibbles(string $address): ?string
    {
        $packed = @inet_pton($address);
        if ($packed === false || strlen($packed) !== 16) {
            return null;
        }

        return implode('.', array_reverse(str_split(bin2hex($packed))));
    }
}
