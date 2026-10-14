<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail\Reputation;

use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Mail\MailDomains;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/**
 * What the blocklists are asked about: every address the platform sends mail from — each node's public IPv4/IPv6 (a site's
 * `mail()` leaves from there too) and the extra addresses the operator names (ONHOST_ANTISPAM_SENDING_ADDRESSES, e.g. the
 * transactional relay) — and every mail domain of a live customer service. Private and reserved addresses are never asked:
 * the platform does not guess where a node sends from.
 */
final class ReputationTargets
{
    /** Services whose mail domain still sends or receives. */
    private const LIVE = [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::RESIZING, ServiceStateMachine::SUSPENDING, ServiceStateMachine::SUSPENDED, ServiceStateMachine::RESUMING];

    /** @return list<array{kind:string, target:string, node_id:?string, service_ids:list<string>}> */
    public function all(): array
    {
        $targets = [];
        foreach (Node::query()->whereIn('state', ['active', 'draining', 'maintenance'])->orderBy('id')->get() as $node) {
            foreach (['public_ipv4' => FILTER_FLAG_IPV4, 'public_ipv6' => FILTER_FLAG_IPV6] as $tag => $family) {
                $address = $this->address((string) ($node->tags[$tag] ?? ''), $family);
                if ($address !== null) {
                    $targets[$address] ??= ['kind' => $family === FILTER_FLAG_IPV4 ? 'ipv4' : 'ipv6', 'target' => $address, 'node_id' => (string) $node->id, 'service_ids' => []];
                }
            }
        }
        foreach ((array) config('onhost.antispam.sending_addresses', []) as $extra) {
            foreach ([FILTER_FLAG_IPV4 => 'ipv4', FILTER_FLAG_IPV6 => 'ipv6'] as $family => $kind) {
                $address = $this->address((string) $extra, $family);
                if ($address !== null) {
                    $targets[$address] ??= ['kind' => $kind, 'target' => $address, 'node_id' => null, 'service_ids' => []];
                }
            }
        }
        $bindings = ProviderBinding::query()->where('remote_type', 'mail_domain')
            ->whereIn('service_id', Service::query()->select('id')->whereIn('state', self::LIVE))
            ->orderBy('id')->get(['id', 'service_id', 'meta']);
        foreach ($bindings as $binding) {
            $domain = MailDomains::nameOf($binding);
            if ($domain === '' || ! str_contains($domain, '.') || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
                continue;
            }
            $key = 'domain:'.$domain;
            $targets[$key] ??= ['kind' => 'domain', 'target' => $domain, 'node_id' => null, 'service_ids' => []];
            if (! in_array((string) $binding->service_id, $targets[$key]['service_ids'], true)) {
                $targets[$key]['service_ids'][] = (string) $binding->service_id;
            }
        }

        return array_values($targets);
    }

    private function address(string $value, int $family): ?string
    {
        $value = trim($value);
        // private ranges never; loopback, unspecified and link-local by hand (FILTER_FLAG_NO_RES_RANGE differs between PHP versions)
        if ($value === '' || filter_var($value, FILTER_VALIDATE_IP, $family | FILTER_FLAG_NO_PRIV_RANGE) === false
            || preg_match('/^(127\.|0\.|169\.254\.|::1$|::$|fe[89ab][0-9a-f]:)/i', $value) === 1) {
            return null;
        }

        return $family === FILTER_FLAG_IPV6 ? (string) inet_ntop((string) inet_pton($value)) : $value;
    }
}
