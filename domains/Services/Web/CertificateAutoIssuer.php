<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Closure;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;

/**
 * Sites provisioned before their DNS pointed at the node keep `access.certificate = pending_dns`. Every quarter of an
 * hour this checks whether the site's name now resolves to the node and, once it does, requests the certificate as the
 * customer would (`ssl.issue`) — no ticket, no manual click. The resolver is injectable for tests.
 */
final class CertificateAutoIssuer
{
    /** @var Closure(string): list<string> */
    private Closure $resolver;

    public function __construct(private readonly ServiceService $services)
    {
        $this->resolver = static function (string $hostname): array {
            $records = @dns_get_record($hostname, DNS_A) ?: [];

            return array_values(array_filter(array_map(fn ($r) => (string) ($r['ip'] ?? ''), $records)));
        };
    }

    /** @param Closure(string): list<string> $resolver */
    public function resolveWith(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    /** @return array{checked:int, resolved:int, requested:int} */
    public function run(int $limit = 20): array
    {
        $stats = ['checked' => 0, 'resolved' => 0, 'requested' => 0];
        $query = Service::query()->whereIn('family', ['web', 'managed'])->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->orderBy('activated_at');
        foreach ($query->cursor() as $service) {
            if (($service->tags['access']['certificate'] ?? null) !== 'pending_dns') {
                continue;
            }
            $stats['checked']++;
            if ($stats['checked'] > $limit) {
                break;
            }
            $domain = strtolower((string) $service->spec('domain', $service->hostname));
            $nodeIps = $this->nodeAddresses($service);
            // the site's own name plus the domains paired with it; a name counts once it answers with the node, its www variant is added when it does too
            $names = [];
            foreach (array_values(array_unique(array_filter(array_merge([$domain], array_map('strval', (array) $service->spec('extra_domains', [])))))) as $candidate) {
                if ($nodeIps !== [] && array_intersect(($this->resolver)($candidate), $nodeIps) !== []) {
                    $names[] = $candidate;
                    if (array_intersect(($this->resolver)('www.'.$candidate), $nodeIps) !== []) {
                        $names[] = 'www.'.$candidate;
                    }
                }
            }
            if ($names === []) {
                continue; // still parked or pointed elsewhere; the panel keeps telling the customer what to set
            }
            $stats['resolved']++;
            // marked before the request: when the queue runs inline the workflow already writes `issued` and must not be overwritten
            $service->forceFill(['tags' => array_replace_recursive((array) $service->tags, ['access' => ['certificate' => 'requested']])])->save();
            try {
                $this->services->requestAction($service, 'ssl.issue', CommandContext::system('certificate auto-issue'), 'cert-auto:'.$service->id.':'.now()->format('Ymd'), ['domains' => $names]);
                $stats['requested']++;
            } catch (\Throwable $e) {
                $service->forceFill(['tags' => array_replace_recursive((array) $service->tags, ['access' => ['certificate' => 'pending_dns']])])->save();
                report($e); // a busy site (another operation running) is retried next round
            }
        }

        return $stats;
    }

    /** @return list<string> */
    private function nodeAddresses(Service $service): array
    {
        return NodeAddresses::ipv4($service);
    }
}
