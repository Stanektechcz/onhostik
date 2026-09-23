<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Dns\BlocklistCheck;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Platform\Dns\RecordResolver;
use Onhost\Platform\Outbox\OutboxMessage;

/*
 * On a shared node every customer sends mail from the same address. One spammer — a hacked WordPress, a mailbox
 * whose password leaked — and that address lands on a blocklist: from then on the mail of every other customer on
 * the node bounces, and the platform learns about it from the customers. The lists answer over DNS and the platform
 * already has a resolver, so it asks them itself, every day, and tells the operators with the list and its reason
 * code. It never tells the customer: the address is ours, and getting it off the list is ours too.
 */

beforeEach(fn () => Http::preventStrayRequests());

/** The blocklists as they answer right now: a listed address gets 127.0.0.x, everything else nothing. */
function blocklistDns(array $answers): void
{
    app()->bind(RecordResolver::class, fn () => new class($answers) implements RecordResolver
    {
        public function __construct(private readonly array $answers) {}

        public function records(string $name, string $type): array
        {
            return $type !== 'A' ? [] : array_map(fn (string $ip) => ['ip' => $ip], (array) ($this->answers[mb_strtolower($name)] ?? []));
        }
    });
}

it('notices that the node the customers send from is on a blocklist', function () {
    blocklistDns(['10.113.0.203.zen.spamhaus.org' => ['127.0.0.4']]); // 4 = a known source of spam
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode(['public_ipv4' => '203.0.113.10'])]);

    $result = app(BlocklistCheck::class)->run();

    $node = Node::query()->findOrFail($service->node_id);
    expect($result['checked'])->toBe(1)->and($result['listed'])->toBe(1)
        ->and(data_get($node->tags, 'blocklist.listed.0.zone'))->toBe('zen.spamhaus.org')
        ->and(data_get($node->tags, 'blocklist.listed.0.code'))->toBe('127.0.0.4')
        ->and(OutboxMessage::query()->where('name', 'node.blocklisted')->exists())->toBeTrue();
});

it('says nothing while the address is clean', function () {
    blocklistDns([]);
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode(['public_ipv4' => '203.0.113.10'])]);

    $result = app(BlocklistCheck::class)->run();

    expect($result['listed'])->toBe(0)
        ->and(data_get(Node::query()->findOrFail($service->node_id)->tags, 'blocklist.listed'))->toBe([])
        ->and(OutboxMessage::query()->where('name', 'node.blocklisted')->exists())->toBeFalse();
});

it('tells the operators once a day, however often it looks', function () {
    blocklistDns(['10.113.0.203.zen.spamhaus.org' => ['127.0.0.4']]);
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode(['public_ipv4' => '203.0.113.10'])]);

    app(BlocklistCheck::class)->run();
    app(BlocklistCheck::class)->run();

    expect(OutboxMessage::query()->where('name', 'node.blocklisted')->count())->toBe(1);
});

it('leaves a node whose address the platform does not know alone', function () {
    blocklistDns(['10.113.0.203.zen.spamhaus.org' => ['127.0.0.4']]);
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    Node::query()->where('id', $service->node_id)->update(['tags' => json_encode([])]);

    expect(app(BlocklistCheck::class)->run())->toMatchArray(['checked' => 0, 'listed' => 0]);
});
