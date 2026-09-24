<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Carbon\CarbonImmutable;
use Onhost\Domain\Dns\DomainPointing;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Tls\CertificateReader;
use Throwable;

/**
 * What the node really serves for a site, and what to do when it has run out.
 *
 * A certificate lasts ninety days and the panel renews it — until it does not. The domain is moved away, a redirect
 * the customer added swallows `/.well-known/acme-challenge`, the panel's own client breaks, the authority rate-limits
 * the name: the renewal fails, nothing says so, and one morning every visitor gets a browser security warning. The
 * platform sells HTTPS and was the last to know — worse, it said the opposite, because the health check answered from
 * a flag written once when the certificate was first issued (`tags.access.certificate`).
 *
 * So the platform stops believing anyone and looks: one handshake to the node's own address with the site's name for
 * SNI. That answer also catches what no panel can see — a certificate that does not cover the name at all, because
 * the request fell through to the node's default site.
 *
 * What it does with the answer is deliberately narrow. Between ninety and `renew_at` days the panel is renewing and
 * must be left alone: asking too would spend the authority's duplicate-certificate allowance for nothing. Below that
 * the panel has demonstrably not renewed, so the platform asks once — through the ordinary action, which already
 * refuses to burn the authority's limit on a name that no longer points here. An expired certificate, or one for
 * somebody else's name, is said out loud to the operators, once a day.
 */
final class CertificateWatch
{
    /** Below this many days left the panel has had its chance and the platform asks for the certificate itself. */
    public const RENEW_AT_DAYS = 10;

    public function __construct(
        private readonly CertificateReader $certificates,
        private readonly ServiceService $services,
        private readonly OutboxPublisher $outbox,
        private readonly AuditRecorder $audit,
    ) {}

    public static function renewAtDays(): int
    {
        return max(1, (int) config('onhost.acme.watch_renew_days', self::RENEW_AT_DAYS));
    }

    /** @return array{checked:int, renewed:int, told:int, errors:int} */
    public function run(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'renewed' => 0, 'told' => 0, 'errors' => 0];
        $services = Service::query()
            ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])
            ->whereIn('family', SiteNames::WEB)->orderBy('id')->limit(max(1, $limit))->get();
        foreach ($services as $service) {
            $name = mb_strtolower(rtrim(trim((string) ($service->hostname ?? '')), '.'));
            $addresses = DomainPointing::addressesOf($service);
            if ($name === '' || $addresses === []) {
                continue; // the platform does not know where to look; it does not guess a verdict
            }
            try {
                $served = $this->certificates->read($addresses[0], $name);
            } catch (Throwable) {
                $stats['errors']++;

                continue;
            }
            if ($served === null) {
                continue; // silence is not a verdict about a certificate
            }
            $stats['checked']++;
            $tags = (array) ($service->tags ?? []);
            $before = (array) ($tags['tls'] ?? []);
            $expires = CarbonImmutable::createFromTimestamp((int) $served['expires_at']);
            $left = (int) floor(now()->diffInDays($expires, false));
            $mismatch = ! self::covers((array) $served['names'], $name);
            $tags['tls'] = [
                'expires_at' => $expires->toIso8601String(), 'issuer' => (string) $served['issuer'], 'names' => array_values((array) $served['names']),
                'mismatch' => $mismatch, 'checked_at' => now()->toIso8601String(), 'told_on' => $before['told_on'] ?? null,
            ];
            if ($left < 0 || $mismatch) {
                if ((string) ($before['told_on'] ?? '') !== now()->toDateString()) {
                    $tags['tls']['told_on'] = now()->toDateString();
                    $this->tell($service, $name, $mismatch ? 'mismatch' : 'expired', $left, (array) $served['names']);
                    $stats['told']++;
                }
            } else {
                $tags['tls']['told_on'] = null; // it is healthy again; the next problem is news
            }
            $service->forceFill(['tags' => $tags])->save();
            if ($left <= self::renewAtDays() && ! $mismatch && $this->renew($service)) {
                $stats['renewed']++;
            }
        }

        return $stats;
    }

    /**
     * Whether the certificate covers the name, wildcards counted (`*.example.cz` covers `www.example.cz`).
     *
     * @param  array<int|string,mixed>  $names
     */
    public static function covers(array $names, string $name): bool
    {
        foreach ($names as $covered) {
            $covered = mb_strtolower(trim((string) $covered));
            if ($covered === $name) {
                return true;
            }
            if (str_starts_with($covered, '*.') && str_ends_with($name, mb_substr($covered, 1)) && substr_count($name, '.') === substr_count($covered, '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ask for a certificate the way a customer's button does — the action checks the plan, what the names point at
     * and the authority's limits by itself. One request a day at most: the key says so.
     */
    private function renew(Service $service): bool
    {
        try {
            $this->services->requestAction($service, 'ssl.issue', CommandContext::system('certificate watch'), 'cert-watch:'.$service->id.':'.now()->format('Ymd'), []);

            return true;
        } catch (DomainError) {
            return false; // another operation is running, the plan does not offer it, the names are not ours — tomorrow again
        }
    }

    /** @param  list<string>  $names */
    private function tell(Service $service, string $name, string $kind, int $left, array $names): void
    {
        $context = CommandContext::system('certificate watch')->withScope((string) $service->organization_id);
        $detail = ['domain' => $name, 'kind' => $kind, 'days_left' => $left, 'covers' => $names];
        $this->audit->record($context, 'service.certificate.problem', 'succeeded', $detail, 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('service.certificate.problem', 'service', $service->id, $detail + [
            'name' => $service->label ?: $service->name,
        ], (string) $service->organization_id));
    }
}
