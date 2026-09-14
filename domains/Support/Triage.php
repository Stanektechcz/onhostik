<?php

declare(strict_types=1);

namespace Onhost\Domain\Support;

/**
 * Deterministic triage (blueprint §68.4, §68.6): topic from keywords (the prototype's
 * topic list, diacritics-insensitive), required skills and queue per topic, priority
 * policy — customers cannot flood P1; staff override anytime.
 */
final class Triage
{
    /** @var array<string, array{label:string, kw:list<string>, skills:list<string>, queue:string}> */
    public const TOPICS = [
        'dostupnost' => ['label' => 'Výpadky a dostupnost', 'kw' => ['vypadek', 'nedostupn', '502', '503', 'timeout', 'pada', 'spadl', 'restart', 'nejede', 'nefunguje', 'down', 'outage'], 'skills' => ['GENERAL', 'PROXMOX', 'ISPCONFIG'], 'queue' => 'l2'],
        'latence' => ['label' => 'Latence a síť', 'kw' => ['latence', 'ping', 'traceroute', 'retransmis', 'uplink', 'paket', 'route', 'ztrat', 'latency', 'packet loss'], 'skills' => ['NETWORK'], 'queue' => 'l3'],
        'vykon' => ['label' => 'Výkon a kapacita', 'kw' => ['cpu', 'ram', 'pomal', 'vytiz', 'iops', 'disk', 'tick', 'kapacit', 'load', 'zpomal', 'slow'], 'skills' => ['PROXMOX', 'ISPCONFIG'], 'queue' => 'l2'],
        'fakturace' => ['label' => 'Fakturace a doklady', 'kw' => ['faktur', 'ico', 'doklad', 'dph', 'platb', 'upominka', 'storno', 'cena', 'uhrad', 'zaplat', 'neuhraz', 'nedoplat', 'dluz', 'invoice', 'vat', 'payment', 'kredit'], 'skills' => ['BILLING'], 'queue' => 'billing'],
        'zalohy' => ['label' => 'Zálohy a obnova', 'kw' => ['zaloh', 'obnov', 'snapshot', 'restore', 'smazal', 'ztratil', 'backup'], 'skills' => ['BACKUP'], 'queue' => 'l2'],
        'pristup' => ['label' => 'Přístupy a bezpečnost', 'kw' => ['heslo', 'pristup', 'ssh', '2fa', 'klic', 'firewall', 'port', 'certifik', 'ssl', 'tls', 'prihlas', 'password', 'login', 'totp'], 'skills' => ['GENERAL', 'SECURITY'], 'queue' => 'l1'],
        'dns' => ['label' => 'DNS a domény', 'kw' => ['dns', 'domen', 'zaznam', 'mx', 'nameserver', 'ttl', 'zona', 'presmerov', 'domain', 'dnssec', 'registr'], 'skills' => ['DNS', 'DOMAIN_WAPI'], 'queue' => 'domains'],
        'mail' => ['label' => 'E-mail a doručitelnost', 'kw' => ['mail', 'spf', 'dkim', 'dmarc', 'spam', 'schrank', 'dorucit', 'posta', 'smtp', 'imap'], 'skills' => ['MAIL_DELIVERABILITY'], 'queue' => 'l2'],
        'migrace' => ['label' => 'Migrace a přenos', 'kw' => ['migrac', 'prenos', 'presun', 'postgres', 'databaz', 'import', 'okno', 'stehov', 'migration'], 'skills' => ['ISPCONFIG', 'PROXMOX'], 'queue' => 'l2'],
        'objednavka' => ['label' => 'Objednávky a služby', 'kw' => ['objedn', 'provisioning', 'aktivac', 'navys', 'upgrade', 'zrus', 'tarif', 'sluzb', 'sluzeb', 'stav mych', 'moje sluzb', 'my services', 'order'], 'skills' => ['ORDERS'], 'queue' => 'l1'],
        'hry' => ['label' => 'Herní servery', 'kw' => ['minecraft', 'cs2', 'rust', 'ark', 'valheim', 'palworld', 'herni', 'server hry', 'mod', 'plugin', 'wings', 'pterodactyl'], 'skills' => ['GAME_MINECRAFT', 'PTERODACTYL'], 'queue' => 'games'],
        'sprava' => ['label' => 'Správa služeb', 'kw' => ['restartuj', 'zalohuj', 'nasad', 'deploy', 'staging', 'aktualizuj', 'wordpress', 'redis', 'object cache', 'vyprazdni', 'purge', 'vynut https', 'prepni', 'terminal', 'cron', 'wp-cli', 'git'], 'skills' => ['ISPCONFIG', 'AAPANEL'], 'queue' => 'l1'],
        'bezpecnost' => ['label' => 'Bezpečnostní incident', 'kw' => ['hack', 'napaden', 'unik', 'breach', 'phishing', 'malware', 'ransom', 'ddos', 'zneuzit', 'abuse'], 'skills' => ['SECURITY', 'ABUSE'], 'queue' => 'security'],
        // the haystack is padded with spaces, so ' api ' matches the word and not "napište"
        'api' => ['label' => 'API a integrace', 'kw' => [' api ', ' api,', ' api.', 'api kli', 'api key', 'token', 'webhook', 'openapi', 'bearer', 'integrac', 'endpoint', 'swagger'], 'skills' => ['GENERAL'], 'queue' => 'l1'],
        'cenik' => ['label' => 'Ceník a tarify', 'kw' => ['cenik', 'kolik stoji', 'kolik to stoji', 'pricing', 'price', 'nabidk', 'nabizite', 'levn', 'draz', 'zdarma', 'za mesic', 'rocne', 'jak drah'], 'skills' => ['GENERAL'], 'queue' => 'l1'],
    ];

    public const SECURITY_TOPICS = ['bezpecnost'];

    public static function normalize(string $text): string
    {
        $lower = mb_strtolower($text);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        // libiconv (Windows, musl) transliterates "stojí" to "stoj'i" and "klíč" to "kl'ic": drop those accent marks so keywords match everywhere
        $ascii = $ascii === false ? $lower : preg_replace("/['`^~\"]/", '', $ascii);

        return preg_replace('/\s+/', ' ', (string) $ascii) ?? $lower;
    }

    /** @return array{topic:string, label:string, confident:bool, hits:int} */
    public static function classify(string ...$texts): array
    {
        $haystack = ' '.self::normalize(implode(' ', $texts)).' ';
        $best = ['topic' => 'ostatni', 'label' => 'Ostatní', 'confident' => false, 'hits' => 0];
        foreach (self::TOPICS as $topic => $def) {
            $hits = 0;
            foreach ($def['kw'] as $kw) {
                $hits += substr_count($haystack, self::normalize($kw));
            }
            if ($hits > $best['hits']) {
                $best = ['topic' => $topic, 'label' => $def['label'], 'confident' => $hits >= 2, 'hits' => $hits];
            }
        }

        return $best;
    }

    /** @return list<string> */
    public static function skillsFor(string $topic): array
    {
        return self::TOPICS[$topic]['skills'] ?? ['GENERAL'];
    }

    public static function queueFor(string $topic): string
    {
        return self::TOPICS[$topic]['queue'] ?? 'l1';
    }

    /**
     * Priority policy: customers may ask for `vysoka` (→ P2); P1 is reserved for staff and for
     * availability tickets of services sold with a contractual SLA. Security topics are at least P2.
     */
    public static function priority(?string $requested, string $topic, bool $staff, bool $contractualSla, ?string $subjectAndBody = null): string
    {
        $requested = match ($requested) {
            'vysoka', 'high', 'p1', 'p2' => $requested === 'p1' ? 'p1' : 'p2', 'nizka', 'low', 'p4' => 'p4', 'p3', 'stredni', 'normal', null, '' => 'p3', default => 'p3'
        };
        if ($requested === 'p1' && ! $staff) {
            $requested = 'p2';
        }
        if (! $staff && $contractualSla && $topic === 'dostupnost' && $subjectAndBody !== null && preg_match('/nedostupn|vypadek|down|outage|502|503/', self::normalize($subjectAndBody))) {
            return 'p1';
        }
        if (in_array($topic, self::SECURITY_TOPICS, true) && $requested !== 'p1') {
            return 'p2';
        }

        return $requested;
    }
}
