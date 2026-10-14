<?php

declare(strict_types=1);

use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Services\Mail\Reputation\BlocklistCatalog;
use Onhost\Domain\Services\Mail\Reputation\Models\ReputationCheck;
use Onhost\Domain\Services\Mail\Reputation\Models\ReputationListing;
use Onhost\Domain\Services\Mail\Reputation\ReputationHealth;
use Onhost\Platform\Dns\Dnsbl\DnsblAnswer;
use Onhost\Platform\Dns\Dnsbl\DnsblLookup;

/*
 * SP1a: what the doctor and /metrics say about the sending reputation. A fresh deploy has no answers yet — that is never a
 * warning (the gate of a deploy must not stop on a check that has not run). A major listing of one of our addresses, or a list
 * that refuses our resolver, is.
 */

function listing(array $attributes): ReputationListing
{
    return ReputationListing::query()->create($attributes + [
        'list_key' => 'spamhaus-zen', 'zone' => 'zen.spamhaus.org', 'list_name' => 'Spamhaus ZEN', 'target_kind' => 'ipv4', 'target' => '203.0.113.10',
        'weight' => 'major', 'code' => '127.0.0.2', 'state' => 'open', 'first_seen_at' => now(), 'last_seen_at' => now(),
    ]);
}

it('is all OK before anything was asked', function () {
    $rows = collect(app(ReputationHealth::class)->checks());

    expect($rows->pluck('check')->all())->toBe([ReputationHealth::ADDRESSES, ReputationHealth::DOMAINS, ReputationHealth::RESOLVER])
        ->and($rows->every(fn ($r) => $r['ok'] === true && $r['blocking'] === false))->toBeTrue()
        ->and($rows->firstWhere('check', ReputationHealth::ADDRESSES)['detail'])->toContain('not checked yet');
});

it('warns on an open major listing of a sending address and names the list and the way out', function () {
    listing([]);
    listing(['list_key' => 'nixspam', 'zone' => 'ix.dnsbl.manitu.net', 'list_name' => 'NiX Spam', 'weight' => 'minor']);
    listing(['target' => '203.0.113.11', 'state' => 'cleared', 'cleared_at' => now()]);

    $row = collect(app(ReputationHealth::class)->checks())->firstWhere('check', ReputationHealth::ADDRESSES);

    expect($row['ok'])->toBeFalse()->and($row['detail'])->toContain('203.0.113.10 on Spamhaus ZEN (127.0.0.2)')->not->toContain('203.0.113.11')
        ->and($row['detail'])->toContain('1 minor')->and($row['remedy'])->not->toBe('');
});

it('warns on a customer domain on a major domain list', function () {
    listing(['list_key' => 'spamhaus-dbl', 'zone' => 'dbl.spamhaus.org', 'list_name' => 'Spamhaus DBL', 'target_kind' => 'domain', 'target' => 'shop.cz', 'code' => '127.0.1.2']);

    $row = collect(app(ReputationHealth::class)->checks())->firstWhere('check', ReputationHealth::DOMAINS);

    expect($row['ok'])->toBeFalse()->and($row['detail'])->toContain('shop.cz on Spamhaus DBL');
});

it('forgets a refusal that is older than a day', function () {
    ReputationCheck::query()->create(['list_key' => 'spamhaus-zen', 'target_kind' => 'ipv4', 'target' => '203.0.113.10', 'outcome' => 'refused', 'code' => '127.255.255.254', 'checked_at' => now()->subHours(30)]);

    expect(collect(app(ReputationHealth::class)->checks())->firstWhere('check', ReputationHealth::RESOLVER)['ok'])->toBeTrue();
});

it('exports the listings, refusals and the last check time to /metrics', function () {
    $this->freezeSecond(); // the timestamp is compared to the second
    listing([]);
    ReputationCheck::query()->create(['list_key' => 'spamhaus-zen', 'target_kind' => 'ipv4', 'target' => '203.0.113.10', 'outcome' => 'listed', 'code' => '127.0.0.2', 'checked_at' => now()]);

    $body = $this->get('/metrics')->assertOk()->getContent();

    expect($body)->toContain('onhost_mail_blocklist_listings{weight="major",kind="address"} 1')
        ->toContain('onhost_mail_blocklist_listings{weight="major",kind="domain"} 0')
        ->toContain('onhost_mail_blocklist_refused 0')
        ->toContain('onhost_mail_reputation_last_check_timestamp '.now()->getTimestamp());
});

it('the scheduled command runs the monitor, records the run and obeys the staff switch', function () {
    app()->instance(DnsblLookup::class, new class implements DnsblLookup
    {
        public function a(string $name): DnsblAnswer
        {
            return DnsblAnswer::none();
        }

        public function describe(): string
        {
            return 'fake';
        }

        public function isOwnResolver(): bool
        {
            return true;
        }
    });

    $this->artisan('onhost:mail:blocklist')->assertSuccessful();
    expect(app(AutomationLedger::class)->last('mail.blocklist')['stats'])->toHaveKeys(['checked', 'listed', 'cleared', 'refused', 'failed', 'deferred']);

    app(AutomationLedger::class)->setEnabled('mail.blocklist', false);
    $this->artisan('onhost:mail:blocklist')->expectsOutputToContain('switched off')->assertSuccessful();
});

it('lists the curated catalogue without SORBS and with every Spamhaus zone knowing its error answers', function () {
    $lists = BlocklistCatalog::LISTS;

    expect(collect($lists)->pluck('zone')->filter(fn ($z) => str_contains($z, 'sorbs'))->all())->toBe([])
        ->and($lists['spamhaus-zen']['errors'])->toContain('127.255.255.254')->toContain('127.255.255.255')
        ->and($lists['spamhaus-dbl']['errors'])->toContain('127.255.255.254');
});

it('warns when the resolver answered none of the questions of the last day', function () {
    ReputationCheck::query()->create(['list_key' => 'spamcop', 'target_kind' => 'ipv4', 'target' => '203.0.113.10', 'outcome' => 'failed', 'checked_at' => now()]);
    ReputationCheck::query()->create(['list_key' => 'psbl', 'target_kind' => 'ipv4', 'target' => '203.0.113.10', 'outcome' => 'failed', 'checked_at' => now()]);

    $row = collect(app(ReputationHealth::class)->checks())->firstWhere('check', ReputationHealth::RESOLVER);

    expect($row['ok'])->toBeFalse()->and($row['detail'])->toContain('no answer')->and($row['remedy'])->toContain('ONHOST_DNSBL_RESOLVER');
});

it('stays OK while some answers fail and the rest arrive', function () {
    ReputationCheck::query()->create(['list_key' => 'spamcop', 'target_kind' => 'ipv4', 'target' => '203.0.113.10', 'outcome' => 'failed', 'checked_at' => now()]);
    ReputationCheck::query()->create(['list_key' => 'psbl', 'target_kind' => 'ipv4', 'target' => '203.0.113.10', 'outcome' => 'clean', 'checked_at' => now()]);

    expect(collect(app(ReputationHealth::class)->checks())->firstWhere('check', ReputationHealth::RESOLVER)['ok'])->toBeTrue();
});
