<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns;

use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Dns\RecordResolver;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Throwable;

/**
 * Whether the address our customers send mail from is on a blocklist.
 *
 * On a shared node every customer sends from the same address. One spammer — a hacked WordPress, a mailbox whose
 * password leaked — and that address is listed: from then on the mail of **every other customer on the node**
 * bounces, and the platform used to learn about it from the customers, days later, one ticket at a time.
 *
 * The lists answer over DNS (`<the address, backwards>.<zone>` resolves to `127.0.0.x` when listed, and the last
 * number says why), and the platform already has a resolver of its own, so it asks them itself. It tells the
 * operators, once a day per node, with the list and the code — never the customer: the address is ours, and getting
 * it off the list is ours too. It only looks; delisting is a person's job with the list's own form.
 */
final class BlocklistCheck
{
    public function __construct(
        private readonly RecordResolver $dns,
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    /** The lists to ask, as `zone => name`. @return array<string,string> */
    public static function zones(): array
    {
        return array_map('strval', (array) config('onhost.mail.blocklists', []));
    }

    /** @return array{checked:int, listed:int, told:int, errors:int} */
    public function run(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'listed' => 0, 'told' => 0, 'errors' => 0];
        $zones = self::zones();
        if ($zones === []) {
            return $stats;
        }
        $nodes = Node::query()->whereIn('state', ['active', 'draining', 'maintenance'])->orderBy('id')->limit(max(1, $limit))->get();
        foreach ($nodes as $node) {
            $address = trim((string) ($node->tags['public_ipv4'] ?? ''));
            if ($address === '' || ! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue; // the platform does not know where this node sends from, and it does not guess
            }
            try {
                $listed = $this->listings($address, $zones);
            } catch (Throwable) {
                $stats['errors']++;

                continue;
            }
            $stats['checked']++;
            $tags = (array) ($node->tags ?? []);
            $before = (array) ($tags['blocklist'] ?? []);
            $tags['blocklist'] = ['listed' => $listed, 'checked_at' => now()->toIso8601String(), 'told_on' => $before['told_on'] ?? null];
            if ($listed !== []) {
                $stats['listed']++;
                if ((string) ($before['told_on'] ?? '') !== now()->toDateString()) {
                    $tags['blocklist']['told_on'] = now()->toDateString();
                    $this->tell($node, $address, $listed);
                    $stats['told']++;
                }
            }
            $node->forceFill(['tags' => $tags])->save();
        }

        return $stats;
    }

    /**
     * The lists this address is on, with the code each of them answered.
     *
     * @param  array<string,string>  $zones
     * @return list<array{zone:string, name:string, code:string}>
     */
    public function listings(string $address, array $zones): array
    {
        $reversed = implode('.', array_reverse(explode('.', $address)));
        $out = [];
        foreach ($zones as $zone => $name) {
            $answers = array_map(fn (array $record) => (string) ($record['ip'] ?? ''), $this->dns->records($reversed.'.'.(string) $zone, 'A'));
            // a list answers with 127.0.0.x and nothing else; anything outside that range is not an answer about us
            $codes = array_values(array_filter($answers, fn (string $ip) => str_starts_with($ip, '127.')));
            if ($codes !== []) {
                $out[] = ['zone' => (string) $zone, 'name' => $name, 'code' => $codes[0]];
            }
        }

        return $out;
    }

    /** @param  list<array{zone:string, name:string, code:string}>  $listed */
    private function tell(Node $node, string $address, array $listed): void
    {
        $context = CommandContext::system('mail.blocklist');
        $this->audit->record($context, 'node.blocklisted', 'succeeded', ['node' => $node->name, 'address' => $address, 'listed' => $listed], 'node', $node->id);
        $this->outbox->publish(GenericEvent::of('node.blocklisted', 'node', $node->id, [
            'name' => $node->name, 'role' => $node->role, 'address' => $address, 'lists' => implode(', ', array_column($listed, 'name')),
            'listed' => $listed, 'count' => count($listed),
        ], null));
    }
}
