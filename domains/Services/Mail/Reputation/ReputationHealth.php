<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail\Reputation;

use Onhost\Domain\Services\Mail\Reputation\Models\ReputationCheck;
use Onhost\Domain\Services\Mail\Reputation\Models\ReputationListing;
use Onhost\Platform\Dns\Dnsbl\DnsblLookup;

/**
 * SP1a: the doctor rows and the /metrics numbers of the sending reputation. No answer yet is never a warning — a fresh
 * deploy's gate runs the doctor before the first check — and `info` listings (network policy lists) are never counted.
 */
final class ReputationHealth
{
    public const ADDRESSES = 'sending addresses are on no major blocklist';

    public const DOMAINS = 'customer mail domains are on no major domain blocklist';

    public const RESOLVER = 'mail blocklist resolver is accepted';

    private const SHOWN = 5;

    public function __construct(private readonly DnsblLookup $dns) {}

    /** @return list<array{area:string, check:string, ok:bool, detail:string, blocking:bool, remedy:string}> */
    public function checks(): array
    {
        $asked = ReputationCheck::query()->exists() || ReputationListing::query()->exists();
        $addresses = $this->open(['ipv4', 'ipv6']);
        $domains = $this->open(['domain']);
        $minor = ReputationListing::query()->where('state', ReputationListing::OPEN)->where('weight', BlocklistCatalog::MINOR)->count();
        $refused = ReputationCheck::query()->where('outcome', 'refused')->where('checked_at', '>', now()->subDay())->distinct()->pluck('list_key')
            ->map(fn (string $key) => (string) (BlocklistCatalog::list($key)['name'] ?? $key))->sort()->values()->all();

        return [
            [
                'area' => 'mail', 'check' => self::ADDRESSES, 'ok' => $addresses === [], 'blocking' => false,
                'detail' => ! $asked ? 'not checked yet — onhost:mail:blocklist runs every hour' : ($addresses === [] ? 'none listed' : $this->describe($addresses)).($minor > 0 ? " · {$minor} minor listing(s) shown on the staff page" : ''),
                'remedy' => $addresses === [] ? '' : 'find the sender (outbound volume per mailbox, a hacked site), stop it, then follow the list\'s removal page (delist_url in the mail.reputation.listed event)',
            ],
            [
                'area' => 'mail', 'check' => self::DOMAINS, 'ok' => $domains === [], 'blocking' => false,
                'detail' => ! $asked ? 'not checked yet' : ($domains === [] ? 'none listed' : $this->describe($domains)),
                'remedy' => $domains === [] ? '' : 'check the listed domain\'s site and mailboxes for abuse, then ask the list for removal',
            ],
            [
                'area' => 'mail', 'check' => self::RESOLVER, 'ok' => $refused === [], 'blocking' => false,
                'detail' => $refused === [] ? 'asked through the '.$this->dns->describe() : 'refused by '.implode(', ', $refused).' through the '.$this->dns->describe().' (public or over-quota resolver) — those lists say nothing about us now',
                'remedy' => $refused === [] ? '' : 'set ONHOST_DNSBL_RESOLVER to an own recursive resolver with an attributable reverse name, or ONHOST_SPAMHAUS_DQS_KEY for Spamhaus, then php artisan config:cache',
            ],
        ];
    }

    /** @return array{listings: array<string, array<string,int>>, refused:int, last_check:int} */
    public function metrics(): array
    {
        $listings = [];
        foreach ([BlocklistCatalog::MAJOR, BlocklistCatalog::MINOR, BlocklistCatalog::INFO] as $weight) {
            foreach (['address' => ['ipv4', 'ipv6'], 'domain' => ['domain']] as $kind => $kinds) {
                $listings[$weight][$kind] = ReputationListing::query()->where('state', ReputationListing::OPEN)->where('weight', $weight)->whereIn('target_kind', $kinds)->count();
            }
        }
        $last = ReputationCheck::query()->max('checked_at');

        return [
            'listings' => $listings,
            'refused' => ReputationCheck::query()->where('outcome', 'refused')->where('checked_at', '>', now()->subDay())->distinct()->count('list_key'),
            'last_check' => $last === null ? 0 : (int) strtotime((string) $last.' UTC'),
        ];
    }

    /** @param  list<array{target:string, list:string, code:string}>  $open */
    private function describe(array $open): string
    {
        $shown = array_map(fn (array $l) => "{$l['target']} on {$l['list']} ({$l['code']})", array_slice($open, 0, self::SHOWN));

        return count($open).' major listing(s): '.implode('; ', $shown).(count($open) > self::SHOWN ? '; …' : '');
    }

    /** @param  list<string>  $kinds @return list<array{target:string, list:string, code:string}> */
    private function open(array $kinds): array
    {
        return ReputationListing::query()->where('state', ReputationListing::OPEN)->where('weight', BlocklistCatalog::MAJOR)->whereIn('target_kind', $kinds)
            ->orderBy('first_seen_at')->get()->map(fn (ReputationListing $l) => ['target' => $l->target, 'list' => $l->list_name, 'code' => $l->code])->values()->all();
    }
}
