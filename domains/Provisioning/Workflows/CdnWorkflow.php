<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Web\CdnService;

/** CDN actions on a site: enable (zone, records, settings, nameservers), disable, purge. */
final class CdnWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'service.cdn';
    }

    public function queue(Operation $operation): string
    {
        return 'default';
    }

    public function steps(Operation $operation): array
    {
        return [match ((string) data_get($operation->desired, 'action')) {
            'cdn.disable' => $this->disableStep(),
            'cdn.purge' => $this->purgeStep(),
            default => $this->enableStep(),
        }];
    }

    public function compensate(StepContext $context): void {}

    private function enableStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Zapnutí CDN';
            }

            public function run(StepContext $context): StepResult
            {
                $row = $context->container->make(CdnService::class)->enable($this->service($context), (array) $context->desired('settings', []), $context->actor);

                return StepResult::done(['zone_id' => $row->zone_id, 'state' => $row->state, 'nameservers' => $row->nameservers]);
            }
        };
    }

    private function disableStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Vypnutí CDN';
            }

            public function run(StepContext $context): StepResult
            {
                $context->container->make(CdnService::class)->disable($this->service($context), $context->actor);

                return StepResult::done(['disabled' => true]);
            }
        };
    }

    private function purgeStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Vyprázdnění cache CDN';
            }

            public function run(StepContext $context): StepResult
            {
                $count = $context->container->make(CdnService::class)->purge($this->service($context), (array) data_get((array) $context->desired('settings', []), 'urls', []), $context->actor);

                return StepResult::done(['purged' => $count === -1 ? 'everything' : $count]);
            }
        };
    }
}
