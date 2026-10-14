<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Services\Mail\Reputation\Models\ReputationCheck;
use Onhost\Domain\Services\Mail\Reputation\Models\ReputationListing;
use Onhost\Domain\Services\Mail\Reputation\ReputationHealth;
use Onhost\Domain\Services\Mail\Reputation\ReputationMonitor;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Dns\Dnsbl\DnsblAnswer;
use Onhost\Platform\Dns\Dnsbl\DnsblLookup;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * SP1a (phase V, owner decision V-R1): every address the platform sends mail from and every customer mail domain is asked
 * about on the curated blocklists, through the operator's own resolver, at most once per interval per pair. A listing opens
 * when a list starts answering and is cleared — kept as history — when it stops. An error answer (Spamhaus refuses public
 * resolvers with 127.255.255.254) is never a listing: the old daily check counted it as one.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('onhost.antispam.dnsbl.disabled', []);
    config()->set('onhost.antispam.dnsbl.spamhaus_dqs_key', '');
    config()->set('onhost.antispam.dnsbl.min_interval_minutes', 60);
    config()->set('onhost.antispam.dnsbl.max_queries_per_run', 5000);
    config()->set('onhost.antispam.sending_addresses', []);
});

/** The blocklists as they answer right now; every name asked is remembered. `null` = no answer at all. */
function reputationDns(array $answers): object
{
    $fake = new class($answers) implements DnsblLookup
    {
        /** @var list<string> */
        public array $asked = [];

        public function __construct(public array $answers) {}

        public function a(string $name): DnsblAnswer
        {
            $this->asked[] = $name;
            $key = mb_strtolower($name);
            if (array_key_exists($key, $this->answers) && $this->answers[$key] === null) {
                return DnsblAnswer::failed('timeout');
            }

            return DnsblAnswer::records((array) ($this->answers[$key] ?? []));
        }

        public function describe(): string
        {
            return 'fake resolver';
        }
    };
    app()->instance(DnsblLookup::class, $fake);

    return $fake;
}

function sendingNode(array $tags): Node
{
    [, $org] = test()->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode($tags)]);

    return Node::query()->findOrFail($service->node_id);
}

it('opens a major listing for a sending address and tells the operators once a day', function () {
    reputationDns(['10.113.0.203.zen.spamhaus.org' => ['127.0.0.4']]); // 4 = XBL, a known source of spam
    $node = sendingNode(['public_ipv4' => '203.0.113.10']);

    $first = app(ReputationMonitor::class)->run();
    $this->travel(2)->hours();
    app(ReputationMonitor::class)->run();

    $listing = ReputationListing::query()->where('target', '203.0.113.10')->sole();
    expect($first)->toMatchArray(['listed' => 1, 'refused' => 0])
        ->and($listing->list_key)->toBe('spamhaus-zen')->and($listing->weight)->toBe('major')->and($listing->code)->toBe('127.0.0.4')
        ->and($listing->state)->toBe('open')->and($listing->node_id)->toBe($node->id)
        ->and(data_get($node->fresh()->tags, 'blocklist.listed.0.zone'))->toBe('zen.spamhaus.org')
        ->and(OutboxMessage::query()->where('name', 'mail.reputation.listed')->count())->toBe(1)
        ->and(OutboxMessage::query()->where('name', 'node.blocklisted')->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'mail.reputation.listed')->exists())->toBeTrue();
});

it('says nothing while the address is clean', function () {
    reputationDns([]);
    $node = sendingNode(['public_ipv4' => '203.0.113.10']);

    $result = app(ReputationMonitor::class)->run();

    expect($result['listed'])->toBe(0)->and($result['checked'])->toBeGreaterThan(0)
        ->and(ReputationListing::query()->count())->toBe(0)
        ->and(data_get($node->fresh()->tags, 'blocklist'))->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'node.blocklisted')->exists())->toBeFalse();
});

it('leaves a node whose address the platform does not know, or that is private, alone', function () {
    $dns = reputationDns([]);
    sendingNode(['public_ipv4' => '10.0.0.5', 'public_ipv6' => 'fe80::1']);

    expect(app(ReputationMonitor::class)->run())->toMatchArray(['checked' => 0, 'listed' => 0])->and($dns->asked)->toBe([]);
});

it('never reads an error answer as a listing: Spamhaus refusing a public resolver', function () {
    reputationDns(['10.113.0.203.zen.spamhaus.org' => ['127.255.255.254']]);
    sendingNode(['public_ipv4' => '203.0.113.10']);

    $result = app(ReputationMonitor::class)->run();

    expect($result['refused'])->toBe(1)->and($result['listed'])->toBe(0)
        ->and(ReputationListing::query()->count())->toBe(0)
        ->and(ReputationCheck::query()->where('list_key', 'spamhaus-zen')->value('outcome'))->toBe('refused');
    $row = collect(app(ReputationHealth::class)->checks())->firstWhere('check', ReputationHealth::RESOLVER);
    expect($row['ok'])->toBeFalse()->and($row['detail'])->toContain('Spamhaus ZEN')->and($row['remedy'])->toContain('ONHOST_DNSBL_RESOLVER');
});

it('clears a listing when the list stops answering and keeps it as history', function () {
    $dns = reputationDns(['10.113.0.203.bl.spamcop.net' => ['127.0.0.2']]);
    sendingNode(['public_ipv4' => '203.0.113.10']);
    app(ReputationMonitor::class)->run();

    $dns->answers = [];
    $this->travel(61)->minutes();
    $result = app(ReputationMonitor::class)->run();

    $listing = ReputationListing::query()->sole();
    expect($result['cleared'])->toBe(1)->and($listing->state)->toBe('cleared')->and($listing->cleared_at)->not->toBeNull()
        ->and(OutboxMessage::query()->where('name', 'mail.reputation.cleared')->count())->toBe(1);
});

it('does not clear a listing on a failed answer', function () {
    $dns = reputationDns(['10.113.0.203.bl.spamcop.net' => ['127.0.0.2']]);
    sendingNode(['public_ipv4' => '203.0.113.10']);
    app(ReputationMonitor::class)->run();

    $dns->answers = ['10.113.0.203.bl.spamcop.net' => null];
    $this->travel(61)->minutes();
    $result = app(ReputationMonitor::class)->run();

    expect($result['failed'])->toBeGreaterThanOrEqual(1)->and(ReputationListing::query()->sole()->state)->toBe('open');
});

it('asks IPv6 lists in nibble form and IPv4-only lists never about an IPv6 address', function () {
    $dns = reputationDns([]);
    sendingNode(['public_ipv6' => '2001:db8::1']);

    app(ReputationMonitor::class)->run();

    $nibbles = '1.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2';
    expect($dns->asked)->toContain($nibbles.'.zen.spamhaus.org')->toContain($nibbles.'.dnsbl.dronebl.org')
        ->and(collect($dns->asked)->filter(fn ($n) => str_ends_with($n, '.bl.spamcop.net'))->all())->toBe([]);
});

it('checks every customer mail domain on the domain lists and names the services', function () {
    reputationDns(['shop.cz.dbl.spamhaus.org' => ['127.0.1.2']]); // 2 = spam domain
    [, $org] = $this->customerWithOrganization();
    $service = featureMailService($org, 'shop.cz');

    $result = app(ReputationMonitor::class)->run();

    $listing = ReputationListing::query()->where('target', 'shop.cz')->sole();
    expect($result['listed'])->toBe(1)->and($listing->target_kind)->toBe('domain')->and($listing->weight)->toBe('major')
        ->and($listing->service_ids)->toBe([$service->id])
        ->and(OutboxMessage::query()->where('name', 'mail.reputation.listed')->value('payload'))->toContain('shop.cz');
});

it('asks each pair at most once per interval and the oldest first within the budget', function () {
    $dns = reputationDns([]);
    sendingNode(['public_ipv4' => '203.0.113.10']);
    config()->set('onhost.antispam.dnsbl.max_queries_per_run', 3);

    $first = app(ReputationMonitor::class)->run();
    $again = app(ReputationMonitor::class)->run();

    expect($first['checked'])->toBe(3)->and($first['deferred'])->toBeGreaterThan(0)
        ->and(count($dns->asked))->toBe(6) // the second run asked the three that had never been asked
        ->and(count(array_unique($dns->asked)))->toBe(6)
        ->and($again['checked'])->toBe(3);
});

it('asks Spamhaus through DQS when a key is set and never stores or prints the key', function () {
    $key = 'testdqskey00000000000000';
    config()->set('onhost.antispam.dnsbl.spamhaus_dqs_key', $key);
    $dns = reputationDns(["10.113.0.203.{$key}.zen.dq.spamhaus.net" => ['127.0.0.2']]);
    sendingNode(['public_ipv4' => '203.0.113.10']);

    app(ReputationMonitor::class)->run();

    $listing = ReputationListing::query()->sole();
    expect($dns->asked)->toContain("10.113.0.203.{$key}.zen.dq.spamhaus.net")
        ->and($listing->zone)->toBe('zen.spamhaus.org')
        ->and(json_encode(ReputationListing::query()->get()->toArray()).json_encode(OutboxMessage::query()->get()->toArray()).json_encode(AuditEvent::query()->get()->toArray()))->not->toContain($key);
});

it('keeps a policy listing (PBL, UCEPROTECT L2) as info: no operator alarm, no doctor warning', function () {
    reputationDns(['10.113.0.203.zen.spamhaus.org' => ['127.0.0.10'], '10.113.0.203.dnsbl-2.uceprotect.net' => ['127.0.0.2']]);
    sendingNode(['public_ipv4' => '203.0.113.10']);

    app(ReputationMonitor::class)->run();

    expect(ReputationListing::query()->pluck('weight')->unique()->values()->all())->toBe(['info'])
        ->and(OutboxMessage::query()->where('name', 'node.blocklisted')->exists())->toBeFalse();
    $row = collect(app(ReputationHealth::class)->checks())->firstWhere('check', ReputationHealth::ADDRESSES);
    expect($row['ok'])->toBeTrue();
});

it('never asks a list the operator switched off', function () {
    config()->set('onhost.antispam.dnsbl.disabled', ['spamhaus-zen']);
    $dns = reputationDns([]);
    sendingNode(['public_ipv4' => '203.0.113.10']);

    app(ReputationMonitor::class)->run();

    expect(collect($dns->asked)->filter(fn ($n) => str_contains($n, 'spamhaus'))->all())->toBe([])->and($dns->asked)->not->toBe([]);
});

it('checks the extra sending addresses the operator names (the transactional relay)', function () {
    config()->set('onhost.antispam.sending_addresses', ['198.51.100.25']);
    $dns = reputationDns(['25.100.51.198.b.barracudacentral.org' => ['127.0.0.2']]);

    app(ReputationMonitor::class)->run();

    expect(ReputationListing::query()->where('target', '198.51.100.25')->value('list_key'))->toBe('barracuda')
        ->and(ReputationListing::query()->value('node_id'))->toBeNull();
});
