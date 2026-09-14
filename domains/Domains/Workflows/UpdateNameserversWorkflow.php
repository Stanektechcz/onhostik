<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows;

use Onhost\Domain\Domains\Models\RegistrarOperation;
use Onhost\Domain\Domains\Workflows\Steps\EnsureNssetStep;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\RegistrarProvider;

/** Nameserver change: (NSSET for .cz) → domain-update-ns → verify with domain-info. */
final class UpdateNameserversWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'domain.update_ns';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-registrar';
    }

    public function steps(Operation $operation): array
    {
        return [new EnsureNssetStep, new class extends DomainStep
        {
            public function label(): string
            {
                return 'Změna nameserverů';
            }

            public function run(StepContext $context): StepResult
            {
                $domain = $this->domain($context);
                $nameservers = array_values((array) $context->desired('nameservers', []));
                $nsset = $context->get('nsset_handle');
                $result = $this->registrar($context)->mutate('domain-update-ns', $domain, ['name' => $domain->fqdn_ascii, 'dns' => $nameservers, 'nsset' => $nsset], fn (RegistrarProvider $a, string $clTrid) => $a->updateNameservers($domain->fqdn_ascii, $nameservers, $nsset, $clTrid), $context->operation->id, $domain->organization_id);

                return $result->isAsync() && $result->async !== null ? StepResult::wait($result->async) : StepResult::done(['ns_updated' => true]);
            }

            protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
            {
                RegistrarOperation::query()->where('operation_id', $context->operation->id)->where('command', 'domain-update-ns')->where('state', RegistrarOperation::PENDING_REGISTRY)->update(['state' => RegistrarOperation::SUCCEEDED, 'completed_at' => now()]);

                return StepResult::done(['ns_updated' => true]);
            }
        }, new class extends DomainStep
        {
            public function label(): string
            {
                return 'Ověření delegace';
            }

            public function run(StepContext $context): StepResult
            {
                $domain = $this->domain($context);
                $info = $this->adapter($context)->domainInfo($domain->fqdn_ascii);
                $nameservers = array_values((array) $context->desired('nameservers', []));
                $domain->forceFill(['nameservers' => $nameservers, 'dns_provider' => $context->desired('dns_provider', 'external'), 'registry_status' => $info, 'last_reconciled_at' => now()])->save();
                $context->container->make(OutboxPublisher::class)->publish(GenericEvent::of('domain.nameservers_changed', 'domain', $domain->id, ['fqdn' => $domain->fqdn_ascii, 'nameservers' => $nameservers], $domain->organization_id));

                return StepResult::done(['registry_nameservers' => $info['nameservers'] ?? []]);
            }
        }];
    }

    public function compensate(StepContext $context): void
    {
        // Nothing to undo: a failed update leaves the previous delegation in place at the registry.
    }
}
