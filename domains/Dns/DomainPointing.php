<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns;

use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Web\NodeAddresses;
use Onhost\Platform\Dns\RecordResolver;

/**
 * Whether a name already answers with the node that serves it.
 *
 * Two things depend on the answer and each used to work it out on its own: the daily look at a customer's DNS
 * (`PublicDnsCheck`) and the certificate, which an authority only issues after checking the name really is served
 * here. Asking for a certificate for a name that points elsewhere costs a refusal the customer cannot read and one
 * of the five checks an hour the authority allows that hostname — and a single name that is not ready fails the
 * whole certificate, taking the names that were ready with it.
 *
 * When the platform does not know where the node is, it says nothing: a check with nothing to compare against is not
 * a reason to hold a customer's request back.
 */
final class DomainPointing
{
    public function __construct(private readonly RecordResolver $dns) {}

    /** Every address public DNS answers with for a name. @return list<string> */
    public function answers(string $name): array
    {
        $name = rtrim(mb_strtolower(trim($name)), '.');

        return array_values(array_filter(array_merge(
            array_map(fn (array $record) => (string) ($record['ip'] ?? ''), $this->dns->records($name, 'A')),
            array_map(fn (array $record) => (string) ($record['ipv6'] ?? ''), $this->dns->records($name, 'AAAA')),
        )));
    }

    /** The addresses a name of this service should answer with. @return list<string> */
    public static function addressesOf(Service $service): array
    {
        return array_values(array_unique(array_merge(NodeAddresses::ipv4($service), NodeAddresses::ipv6($service))));
    }

    /**
     * Whether the name answers with one of the addresses.
     *
     * @param  list<string>  $addresses
     */
    public function pointsAt(string $name, array $addresses): bool
    {
        return $addresses !== [] && array_intersect($this->answers($name), $addresses) !== [];
    }

    /**
     * The names that already answer with this service's node, and the ones still to come. With no address to compare
     * against, every name counts as ready — the platform does not invent a reason to refuse.
     *
     * @param  list<string>  $names
     * @return array{ready:list<string>, waiting:list<string>, addresses:list<string>}
     */
    public function split(Service $service, array $names): array
    {
        $names = array_values(array_unique(array_filter(array_map(fn ($name) => rtrim(mb_strtolower(trim((string) $name)), '.'), $names))));
        $addresses = self::addressesOf($service);
        if ($addresses === []) {
            return ['ready' => $names, 'waiting' => [], 'addresses' => []];
        }
        $ready = $waiting = [];
        foreach ($names as $name) {
            if ($this->pointsAt($name, $addresses)) {
                $ready[] = $name;
            } else {
                $waiting[] = $name;
            }
        }

        return ['ready' => $ready, 'waiting' => $waiting, 'addresses' => $addresses];
    }
}
