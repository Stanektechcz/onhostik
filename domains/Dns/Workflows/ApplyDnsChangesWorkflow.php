<?php

declare(strict_types=1);

namespace Onhost\Domain\Dns\Workflows;

use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\AbstractStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Platform\Errors\DomainError;

/** Background commit of a staged DNS batch (used by automation such as hosting IP moves); interactive commits call DnsService directly. */
final class ApplyDnsChangesWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'dns.commit';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-powerdns';
    }

    public function steps(Operation $operation): array
    {
        return [new class extends AbstractStep
        {
            public function label(): string
            {
                return 'Publikace DNS změn';
            }

            public function run(StepContext $context): StepResult
            {
                $zone = DnsZone::query()->find((string) $context->desired('zone_id'));
                if ($zone === null) {
                    return StepResult::fail('zone not found', false);
                }
                try {
                    $version = $context->container->make(DnsService::class)->commit($zone, $context->actor, (string) $context->desired('reason', 'automated commit'));
                } catch (DomainError $e) {
                    return $e->error === 'dns_nothing_to_commit' ? StepResult::skip() : StepResult::fail($e->getMessage(), false, $e->extra);
                }

                return StepResult::done(['version' => $version->version, 'serial' => $version->serial]);
            }
        }];
    }

    public function compensate(StepContext $context): void
    {
        // Commit is atomic on the provider; a failed commit leaves the changes in `failed` state for the operator.
    }
}
