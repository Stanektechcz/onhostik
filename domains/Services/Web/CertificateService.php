<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\ManagedCertificate;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/**
 * Certificates the platform issues itself (wildcard, DNS-01 through our authoritative DNS) and installs on the
 * panel: order records, key material in the secret store, renewal thirty days before expiry.
 */
final class CertificateService
{
    public function __construct(private readonly AcmeClient $acme, private readonly DnsService $dns, private readonly SecretStore $secrets, private readonly OutboxPublisher $outbox, private readonly ServiceFeatures $features, private readonly ServiceService $services) {}

    /** @return array<string,mixed> */
    public function status(Service $service): array
    {
        $domain = strtolower((string) $service->spec('domain', $service->hostname));
        $zone = $this->zoneFor($service->organization_id, $domain);
        $features = $this->features->features($service);

        return [
            'available' => ! empty($features['ssl_wildcard']['enabled']),
            'domain' => $domain,
            'dns_managed' => $zone !== null,
            'dns_zone' => $zone?->name,
            'directory' => str_contains($this->acme->directoryUrl(), 'staging') ? 'staging' : 'production',
            'certificates' => ManagedCertificate::query()->where('service_id', $service->id)->orderByDesc('created_at')->get()->map(fn (ManagedCertificate $c) => $this->present($c))->all(),
        ];
    }

    public function zoneFor(string $organizationId, string $domain): ?DnsZone
    {
        $domain = strtolower(trim($domain, '.'));

        return DnsZone::query()->where('organization_id', $organizationId)->get()->filter(fn (DnsZone $z) => $domain === $z->name || str_ends_with($domain, '.'.$z->name))->sortByDesc(fn (DnsZone $z) => strlen($z->name))->first();
    }

    public function open(Service $service, string $domain, Operation $operation): ManagedCertificate
    {
        $domain = strtolower($domain);
        $cert = ManagedCertificate::query()->where('service_id', $service->id)->where('domains->0', $domain)->first() ?? new ManagedCertificate(['service_id' => $service->id, 'organization_id' => $service->organization_id]);
        $cert->forceFill(['domains' => [$domain, '*.'.$domain], 'wildcard' => true, 'state' => 'pending', 'last_error' => null, 'order_url' => null])->save();
        $cert->forceFill(['secret_ref' => "db://services/{$service->id}/certificates/{$cert->id}"])->save();
        $this->outbox->publish(GenericEvent::of('certificate.requested', 'service', $service->id, ['certificate_id' => $cert->id, 'domains' => $cert->domains, 'operation_id' => $operation->id], $service->organization_id));

        return $cert;
    }

    public function store(ManagedCertificate $cert, string $fullchain, string $key): void
    {
        [$leaf, $chain] = self::split($fullchain);
        $info = AcmeClient::inspect($leaf);
        $this->secrets->write(SecretRef::parse((string) $cert->secret_ref), ['cert' => $leaf, 'chain' => $chain, 'key' => $key, 'fullchain' => $fullchain]);
        $expires = $info['not_after'] ? now()->setTimestamp($info['not_after']) : now()->addDays(90);
        $cert->forceFill(['state' => 'issued', 'issuer' => $info['issuer'] ?: 'Let\'s Encrypt', 'issued_at' => $info['not_before'] ? now()->setTimestamp($info['not_before']) : now(), 'expires_at' => $expires, 'renew_after' => $expires->copy()->subDays((int) config('onhost.acme.renew_days_before', 30))->addMinutes(self::spread($cert)), 'rate_limited_until' => null, 'last_error' => null])->save();
        $this->outbox->publish(GenericEvent::of('certificate.issued', 'service', $cert->service_id, ['certificate_id' => $cert->id, 'domains' => $cert->domains, 'expires_at' => $expires->toIso8601String()], $cert->organization_id));
    }

    public function fail(ManagedCertificate $cert, string $error, ?int $rateLimitedFor = null): void
    {
        $cert->forceFill(['state' => $cert->issued_at ? 'issued' : 'failed', 'last_error' => mb_substr($error, 0, 400)]
            + ($rateLimitedFor !== null ? ['rate_limited_until' => now()->addSeconds($rateLimitedFor)] : []))->save();
        $this->outbox->publish(GenericEvent::of('certificate.failed', 'service', $cert->service_id, ['certificate_id' => $cert->id, 'domains' => $cert->domains, 'error' => mb_substr($error, 0, 400), 'renewal' => $cert->issued_at !== null], $cert->organization_id));
    }

    /** @return array{cert:string, chain:string, key:string} */
    public function material(ManagedCertificate $cert): array
    {
        $data = $this->secrets->read(SecretRef::parse((string) $cert->secret_ref));

        return ['cert' => (string) ($data['cert'] ?? ''), 'chain' => (string) ($data['chain'] ?? ''), 'key' => (string) ($data['key'] ?? '')];
    }

    /** The zone-relative name of the DNS-01 record for a domain. */
    public function challengeName(DnsZone $zone, string $domain): string
    {
        $domain = strtolower(trim($domain, '.'));

        return $domain === $zone->name ? '_acme-challenge' : '_acme-challenge.'.substr($domain, 0, -strlen('.'.$zone->name));
    }

    /** @param  list<string>  $values */
    public function publishChallenge(DnsZone $zone, string $name, array $values, CommandContext $actor): void
    {
        $this->removeChallenge($zone, $name, $actor, false);
        foreach (array_unique($values) as $value) {
            $this->dns->stageAdd($zone, ['name' => $name, 'type' => 'TXT', 'content' => $value, 'ttl' => 60], $actor, 'ACME DNS-01 validation');
        }
        $this->dns->commit($zone, $actor, 'ACME DNS-01 validation');
    }

    public function removeChallenge(DnsZone $zone, string $name, CommandContext $actor, bool $commit = true): void
    {
        $existing = $zone->records()->where('type', 'TXT')->where('name', $name)->get();
        if ($existing->isEmpty()) {
            return;
        }
        foreach ($existing as $record) {
            /** @var DnsRecord $record */
            $this->dns->stageDelete($zone, $record, $actor, 'ACME DNS-01 cleanup', true);
        }
        if ($commit) {
            $this->dns->commit($zone, $actor, 'ACME DNS-01 cleanup');
        }
    }

    /** Scheduler: start renewals for certificates past their renew date. */
    /**
     * How long after the earliest renewal day this certificate waits, in minutes. A hundred sites set up in one
     * afternoon get certificates that expire within minutes of each other, so they would all come due on the same
     * day for ever — and a day's worth of renewals against one authority is how an account meets its rate limit.
     * The offset is derived from the certificate's own id, so it is the same at every run (nothing wanders) and
     * different for every certificate: up to six days, spread over the hours of the day.
     */
    public static function spread(ManagedCertificate $cert): int
    {
        return (int) (hexdec(substr(hash('sha256', (string) $cert->id), 0, 8)) % (6 * 24 * 60));
    }

    public function renewDue(int $limit = 20): int
    {
        $started = 0;
        $context = CommandContext::system('certificate renewal');
        $due = ManagedCertificate::query()->where('state', 'issued')->whereNotNull('renew_after')->where('renew_after', '<=', now())
            ->where(fn ($q) => $q->whereNull('rate_limited_until')->orWhere('rate_limited_until', '<=', now())) // the authority named a time; it is kept
            ->orderBy('renew_after')->limit($limit)->get();
        foreach ($due as $cert) {
            $service = Service::query()->find($cert->service_id);
            if ($service === null || ! $service->isActive()) {
                continue;
            }
            try {
                $this->services->requestAction($service, 'ssl.wildcard', $context, 'cert:renew:'.$cert->id.':'.now()->format('Ymd'), ['domain' => (string) ($cert->domains[0] ?? '')]);
                $started++;
            } catch (DomainError) {
                // another operation is running or the feature was removed from the plan; try again tomorrow
            }
        }

        return $started;
    }

    /** @return array{0:string,1:string} leaf certificate and the rest of the chain */
    public static function split(string $fullchain): array
    {
        preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $fullchain, $m);
        $certs = $m[0] ?? [];

        return [(string) ($certs[0] ?? trim($fullchain)), implode("\n", array_slice($certs, 1))];
    }

    /** @return array<string,mixed> */
    private function present(ManagedCertificate $c): array
    {
        return ['id' => $c->id, 'domains' => (array) $c->domains, 'wildcard' => (bool) $c->wildcard, 'state' => $c->state, 'issuer' => $c->issuer, 'issued_at' => $c->issued_at?->toIso8601String(), 'expires_at' => $c->expires_at?->toIso8601String(), 'renew_after' => $c->renew_after?->toIso8601String(), 'days_left' => $c->expires_at ? (int) now()->diffInDays($c->expires_at, false) : null, 'last_error' => $c->last_error];
    }
}
