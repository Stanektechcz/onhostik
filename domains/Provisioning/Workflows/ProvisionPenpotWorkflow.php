<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Provisioning\Workflows\Steps\ScheduleNodeStep;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Penpot\PenpotInstances;
use Onhost\Domain\Services\Penpot\PenpotSecrets;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Penpot\PenpotDockerProvider;

/**
 * Penpot saga (TASK-0123): node → secrets in the vault → the stack (compose up + proxy site) → DNS in the platform zone → the
 * owner's Penpot profile → the address answers → ACTIVE. Suspend, resume, backup, terminate and purge are the ordinary service
 * actions (ServiceActionWorkflow through InfrastructureProvider / BackupCapable).
 */
final class ProvisionPenpotWorkflow implements Workflow
{
    /** How often the verification may find the address silent before the run fails (the first start pulls five images). */
    public const VERIFY_ATTEMPTS = 20;

    public static function kind(): string
    {
        return 'provision.penpot';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-penpot';
    }

    public function steps(Operation $operation): array
    {
        return [
            new ScheduleNodeStep('penpot', 'penpot'),
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Tajné klíče';
                }

                public function run(StepContext $context): StepResult
                {
                    $context->container->make(PenpotSecrets::class)->ensure($this->service($context)); // values stay in the vault

                    return StepResult::done(['secrets' => (string) PenpotSecrets::ref($this->service($context))]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Instance Penpotu';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $adapter = $this->capability($context, PenpotDockerProvider::class);
                    $stack = PenpotInstances::stackName($service);
                    $hostname = (string) ($context->desired('hostname') ?: $service->hostname);
                    // bound before the node is touched: a run that fails half-way leaves a binding the compensation takes back
                    $node = $context->get('node_name');
                    $context->bind($context->instance(), 'stack', $stack, is_string($node) ? $node : null, ['identifier' => $stack, 'name' => $stack, 'hostname' => $hostname], ['managed_by' => 'onhost']);
                    $secrets = $context->container->make(PenpotSecrets::class)->ensure($service);
                    try {
                        $result = $adapter->provision($context->spec('penpot_stack', ['stack' => $stack, 'hostname' => $hostname, 'entitlements' => (array) $service->entitlements])->with(['secrets' => $secrets]));
                    } catch (ProviderException $e) {
                        return self::fromProviderException($e);
                    }
                    $ref = $result->ref;
                    if ($ref !== null) {
                        $context->bind($context->instance(), 'stack', $ref->remoteId, $ref->node ?? (is_string($node) ? $node : null), $ref->meta, ['managed_by' => 'onhost']);
                    }

                    return StepResult::done(['stack' => $stack, 'port' => $result->data['port'] ?? null, 'hostname' => $hostname, 'limits' => $result->data['limits'] ?? [], 'already_existed' => $result->alreadyExisted]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'DNS záznamy';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $hostname = (string) ($context->get('hostname') ?: $service->hostname);
                    $dns = $context->container->make(DnsService::class);
                    $platform = $dns->platformZoneFor($hostname);
                    if ($platform === null) {
                        return StepResult::skip(); // no platform zone holds the name: the operator points it at the node (runbook)
                    }
                    $node = Node::query()->find($service->node_id);
                    $ipv4 = (string) ($node?->tags['public_ipv4'] ?? $context->instance()->option('public_ipv4', ''));
                    $ipv6 = (string) ($node?->tags['public_ipv6'] ?? $context->instance()->option('public_ipv6', ''));
                    if ($ipv4 === '' && $ipv6 === '') {
                        return StepResult::fail('The Penpot node has no public address (node tags public_ipv4/public_ipv6 or instance option)', false);
                    }
                    [$zone, $relative] = $platform;
                    $version = $dns->syncHostname($zone, $relative, $ipv4 ?: null, $ipv6 ?: null, $context->actor, "service:{$service->id}", "penpot {$service->id}");

                    return StepResult::done(['dns_version' => $version?->version, 'dns_zone' => $zone->name, 'public_ipv4' => $ipv4 ?: null, 'public_ipv6' => $ipv6 ?: null]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Účet vlastníka';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $organization = Organization::query()->findOrFail($service->organization_id);
                    $email = PenpotInstances::ownerEmail($organization);
                    if ($email === null) {
                        return StepResult::fail('The organization has no owner e-mail for the Penpot account', false);
                    }
                    try {
                        // a password nobody sees: the customer sets their own in the panel (step-up), so no password is ever stored or shown
                        $owner = $this->capability($context, PenpotDockerProvider::class)->ensureOwner($this->ref($context, 'stack'), $email, (string) ($organization->name ?: $email), PenpotSecrets::throwaway());
                    } catch (ProviderException $e) {
                        return self::fromProviderException($e);
                    }

                    return StepResult::done(['owner_email' => $email, 'owner_created' => $owner['created']]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Ověření a aktivace';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $ref = $this->ref($context, 'stack');
                    $adapter = $this->capability($context, PenpotDockerProvider::class);
                    try {
                        $state = $adapter->getActualState($ref);
                        $code = $state->status === 'running' ? $adapter->probe($ref) : 0;
                    } catch (ProviderException $e) {
                        return self::fromProviderException($e);
                    }
                    if ($code < 200 || $code >= 400) {
                        $tries = (int) $context->get('verify_tries', 0) + 1;
                        $context->operation->forceFill(['context' => array_replace((array) $context->operation->context, ['verify_tries' => $tries])])->save();

                        return StepResult::fail("Penpot does not answer yet (containers {$state->status}, HTTP {$code})", $tries < ProvisionPenpotWorkflow::VERIFY_ATTEMPTS, ['status' => $state->status, 'http' => $code], 30);
                    }
                    $url = 'https://'.(string) ($context->get('hostname') ?: $service->hostname);
                    $owner = (string) $context->get('owner_email', '');
                    $fresh = Service::query()->findOrFail($service->id);
                    $fresh->forceFill(['tags' => array_replace((array) $fresh->tags, ['penpot' => ['stack' => $ref->remoteId, 'url' => $url, 'owner_email' => $owner, 'owner_password_set' => false, 'version' => (string) $context->instance()->option('version', config('penpot.version'))]])])->save();
                    $context->container->make(ServiceService::class)->activate($fresh, $context->actor, $context->operation, ['url' => $url, 'hostname' => (string) $fresh->hostname]);
                    $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('penpot.instance.ready', 'service', $service->id, ['label' => (string) ($fresh->label ?: $fresh->hostname), 'url' => $url, 'owner_email' => $owner], $service->organization_id));

                    return StepResult::done(['activated' => true, 'url' => $url]);
                }
            },
        ];
    }

    public function compensate(StepContext $context): void
    {
        $service = $context->service ?? Service::query()->find($context->operation->service_id);
        if ($service === null) {
            return;
        }
        if ($context->get('already_existed') !== true) {
            $context->container->make(CompensationGuard::class)->takeBack($context, $service, 'stack');
        }
        $context->container->make(ServiceService::class)->fail($service, $context->actor, (string) ($context->operation->error['message'] ?? 'provisioning failed'), $context->operation);
    }
}
