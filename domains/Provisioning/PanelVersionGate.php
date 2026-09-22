<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Illuminate\Support\Carbon;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceIdentityCheck;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * A panel on a version nobody verified takes no new orders (Brain cards H511–H530).
 *
 * A panel gets upgraded — by the vendor's updater, by an operator, by an unattended upgrade — and the platform went on
 * calling it as before. Every adapter declares the versions it was verified against (`supportedVendorVersions()`), and the
 * health probe wrote the version each panel reports onto the instance every minute; nothing compared the two, so a panel
 * on a version nobody had checked kept getting new orders.
 *
 * The version the health probe reads is compared with the last one the gate knows. A change (an upgrade, a rollback) or a
 * new instance is verified: the version must be one the adapter declares, and the checks the platform relies on must pass
 * on the panel as it is now (`NodePrerequisites::check()` — the calls the adapters use, the API user's rights, the cron
 * and client APIs). Until both hold the instance is **held**: no new orders go there (`ProviderInstance::isUsable()`),
 * the services already on it are still managed, operators are told. A declared version whose checks failed is looked at
 * again every few minutes and released by itself once they pass; a version the adapter does not declare waits for an
 * operator who checked it (`onhost:integrations:versions --accept`), and even then only on passing checks.
 *
 * The first look at a panel that already carries customers takes the version it runs as the baseline — nothing stops at
 * deploy — and the doctor names a baseline the adapter was never verified against.
 */
final class PanelVersionGate
{
    /** The software the platform runs and upgrades itself. A vendor's hosted API (the registrars) changes on the vendor's schedule, not by a version it reports. */
    public const SELF_HOSTED = ['proxmox', 'pbs', 'aapanel', 'ispconfig', 'pterodactyl', 'powerdns', 'kubernetes'];

    /** A declared version whose checks failed is looked at again after this long. */
    public const RECHECK_MINUTES = 15;

    /** How many of the instance's services must still prove to be themselves after a change (H517). */
    public const IDENTITY_SAMPLE = 3;

    /** The points of an identity check that say whether the panel's resource is still the service's — not whether it may be deleted today. */
    private const IDENTITY_POINTS = ['organization', 'binding', 'binding_type', 'remote_id', 'provider_instance', 'sole_owner', 'remote_exists', 'owner', 'name', 'node'];

    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly NodePrerequisites $prerequisites,
        private readonly ServiceIdentityCheck $identity,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /** Every version the health probe reads comes here; nothing is asked of the panel unless it changed or a held one is due. */
    public function observe(ProviderInstance $instance, ?string $reported): void
    {
        $version = trim((string) $reported);
        if ($version === '' || $instance->organization_id !== null || ! in_array($instance->provider, self::SELF_HOSTED, true)) {
            return;
        }
        $gate = (array) ($instance->version_gate ?? []);
        $known = (string) ($gate['version'] ?? '');
        if ($known === '') {
            $this->firstLook($instance, $version);

            return;
        }
        if ($known !== $version) {
            $this->evaluate($instance, $version, $known, $gate);

            return;
        }
        $checked = isset($gate['checked_at']) && is_string($gate['checked_at']) ? Carbon::parse($gate['checked_at']) : null;
        if (($gate['state'] ?? null) === 'held' && ($gate['declared'] ?? false) === true && ($checked === null || $checked->lt(now()->subMinutes(self::RECHECK_MINUTES)))) {
            $this->evaluate($instance, $version, is_string($gate['previous'] ?? null) ? $gate['previous'] : null, $gate);
        }
    }

    /**
     * An operator vouches for a version the adapter does not declare, having checked it (a lab cluster, the vendor's notes).
     * Only a held version, only with a reason, and only while the checks pass on the panel as it is now.
     *
     * @return array<string,mixed> the gate record
     */
    public function accept(ProviderInstance $instance, string $reason, CommandContext $context, ?string $by = null): array
    {
        $gate = (array) ($instance->version_gate ?? []);
        if (($gate['state'] ?? null) !== 'held') {
            throw new DomainError('version_not_held', "{$instance->key} holds no version waiting to be accepted.", 409);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new DomainError('version_accept_reason', 'Say what was checked and where (at least ten characters); it stays on the record.', 422);
        }
        $evidence = $this->evidence($instance);
        if ($evidence['api'] !== 'up' || $evidence['failed'] !== []) {
            $instance->forceFill(['version_gate' => array_replace($gate, ['checked_at' => now()->toIso8601String(), 'evidence' => $evidence])])->save();

            throw new DomainError('version_checks_failing', 'The checks of the panel fail ('.self::failures($evidence).'); a version is accepted only while they pass.', 409, ['failed' => $evidence['failed']]);
        }
        $gate = array_replace($gate, ['state' => 'accepted', 'checked_at' => now()->toIso8601String(), 'evidence' => $evidence, 'why' => null,
            'accepted_by' => $by ?? $context->actorId, 'accepted_at' => now()->toIso8601String(), 'reason' => mb_substr($reason, 0, 300)]);
        $instance->forceFill(['version_gate' => $gate])->save();
        $this->audit->record($context, 'provider.instance.version.accepted', 'succeeded', ['key' => $instance->key, 'version' => $gate['version'] ?? null, 'reason' => $gate['reason'], 'by' => $gate['accepted_by']], 'provider_instance', $instance->id);
        $this->outbox->publish(GenericEvent::of('integration.version.accepted', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'version' => $gate['version'] ?? null, 'reason' => $gate['reason'], 'by' => $gate['accepted_by']]));

        return $gate;
    }

    /** @return list<string> */
    public function declaredFor(ProviderInstance $instance): array
    {
        $class = $this->providers->adapterClass($instance->provider);

        return $class !== null && method_exists($class, 'supportedVendorVersions') ? array_values(array_map('strval', (array) $class::supportedVendorVersions())) : [];
    }

    private function firstLook(ProviderInstance $instance, string $version): void
    {
        $live = Service::query()->where('provider_instance_id', $instance->id)->whereNotIn('state', [ServiceStateMachine::TERMINATED, ServiceStateMachine::FAILED])->exists();
        if (! $live) {
            $this->evaluate($instance, $version, null, []); // a panel that serves nobody yet enters the offer on a verified version only

            return;
        }
        // it has been serving customers on this version: the baseline the gate starts from, not a reason to stop anything
        $declared = VendorVersion::declared($version, $this->declaredFor($instance));
        $instance->forceFill(['version_gate' => ['state' => $declared ? 'verified' : 'baseline', 'version' => $version, 'declared' => $declared, 'previous' => null,
            'since' => now()->toIso8601String(), 'checked_at' => null, 'baseline' => true, 'why' => $declared ? null : 'running before the version gate existed; the adapter was not verified against it']])->save();
    }

    /** @param array<string,mixed> $gate */
    private function evaluate(ProviderInstance $instance, string $version, ?string $previous, array $gate): void
    {
        $declaredList = $this->declaredFor($instance);
        $declared = VendorVersion::declared($version, $declaredList);
        $evidence = $this->evidence($instance);
        $passing = $evidence['api'] === 'up' && $evidence['failed'] === [];
        $state = $declared && $passing ? 'verified' : 'held';
        $why = match (true) {
            ! $declared => "the adapter was not verified against {$version} (it declares ".(implode(', ', $declaredList) ?: 'nothing').')',
            ! $passing => 'checks on the panel fail after the change: '.self::failures($evidence),
            default => null,
        };
        $sameVersion = ($gate['version'] ?? null) === $version;
        $wasState = $gate['state'] ?? null;
        $instance->forceFill(['version_gate' => [
            'state' => $state, 'version' => $version, 'declared' => $declared, 'previous' => $previous,
            'since' => $sameVersion && is_string($gate['since'] ?? null) ? $gate['since'] : now()->toIso8601String(),
            'checked_at' => now()->toIso8601String(), 'evidence' => $evidence, 'why' => $why,
        ]])->save();
        if ($sameVersion && $wasState === $state) {
            return; // looked at again, nothing changed: the record says when
        }
        $context = CommandContext::system('version gate');
        $this->audit->record($context, 'provider.instance.version', $state === 'held' ? 'failed' : 'succeeded', ['key' => $instance->key, 'version' => $version, 'previous' => $previous, 'declared' => $declared, 'why' => $why], 'provider_instance', $instance->id);
        if ($state === 'held') {
            $this->outbox->publish(GenericEvent::of('integration.version.held', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'version' => $version, 'previous' => $previous, 'declared' => $declared, 'why' => $why]));
        } elseif ($wasState === 'held' || ($previous !== null && ! $sameVersion)) {
            $this->outbox->publish(GenericEvent::of('integration.version.verified', 'provider_instance', $instance->id, ['key' => $instance->key, 'provider' => $instance->provider, 'version' => $version, 'previous' => $previous]));
        }
    }

    /**
     * The checks the platform relies on, on the panel as it is now: the calls the adapters use (`SelfProbing`), the cron and
     * the client APIs — and that a few of the services already there are still themselves (H517): an upgrade that renumbered
     * or renamed what it holds must not be taken as the same resources by their numbers alone. Nothing is changed on the
     * panel; `datalog_api` is knowledge for the adapter, not a condition.
     *
     * @return array{api:string, failed:list<string>, checked_at:string}
     */
    private function evidence(ProviderInstance $instance): array
    {
        $prereqs = $this->prerequisites->check($instance, CommandContext::system('version gate'));
        $failed = [];
        foreach ((array) ($prereqs['probes'] ?? []) as $probe => $answer) {
            if (is_string($answer) && $probe !== 'datalog_api' && ! str_starts_with($answer, 'ok') && ! str_starts_with($answer, 'skipped')) {
                $failed[] = "{$probe}: ".mb_substr($answer, 0, 160);
            }
        }
        if (($prereqs['cron_api'] ?? null) === 'broken') {
            $failed[] = 'cron_api: broken';
        }
        $client = $prereqs['client_api'] ?? null;
        if (is_string($client) && ! in_array($client, ['ok', 'unknown'], true)) {
            $failed[] = "client_api: {$client}";
        }
        if (($prereqs['api'] ?? 'down') === 'up') {
            $failed = array_merge($failed, $this->identities($instance));
        }

        return ['api' => (string) ($prereqs['api'] ?? 'down'), 'failed' => $failed, 'checked_at' => now()->toIso8601String()];
    }

    /** @return list<string> the services of a sample whose resource the panel no longer shows as theirs */
    private function identities(ProviderInstance $instance): array
    {
        $services = Service::query()->where('provider_instance_id', $instance->id)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED])
            ->whereHas('bindings')->inRandomOrder()->limit(self::IDENTITY_SAMPLE)->get();
        if ($services->isEmpty()) {
            return [];
        }
        $adapter = $this->providers->forInstance($instance);
        $failed = [];
        foreach ($services as $service) {
            $report = $this->identity->verify($service, $adapter);
            $points = array_values(array_intersect($report['failed'], self::IDENTITY_POINTS));
            $unanswered = collect($report['checks'])->contains(fn (array $check) => $check['key'] === 'remote_exists' && $check['ok'] === null);
            if ($points !== [] || $unanswered || ! $report['identifier_matched']) {
                $failed[] = "identity of {$service->id}: ".($points !== [] ? implode(', ', $points) : ($unanswered ? 'the panel could not be asked' : 'nothing the panel reports matches'));
            }
        }

        return $failed;
    }

    /** @param array{api:string, failed:list<string>} $evidence */
    private static function failures(array $evidence): string
    {
        return implode('; ', $evidence['failed'] !== [] ? $evidence['failed'] : ['the API does not answer']);
    }
}
