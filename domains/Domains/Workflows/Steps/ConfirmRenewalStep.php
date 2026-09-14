<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows\Steps;

use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\Workflows\DomainStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;

/** Reads the new expiry back, captures the wallet hold, issues the statement and advances the subscription. */
final class ConfirmRenewalStep extends DomainStep
{
    public function label(): string
    {
        return 'Potvrzení a vyúčtování';
    }

    public function run(StepContext $context): StepResult
    {
        $domain = $this->domain($context);
        $info = $this->adapter($context)->domainInfo($domain->fqdn_ascii);
        $service = $context->container->make(DomainService::class);
        $domain = $service->applyRegistryInfo($domain, $info);
        $expected = $context->get('expected_expiry');
        if ($expected !== null && $domain->expires_at !== null && $domain->expires_at->copy()->startOfDay() < (new \DateTimeImmutable((string) $expected))->setTime(0, 0)) {
            return StepResult::fail("Registry expiry {$domain->expires_at->toDateString()} did not advance to the expected {$expected}", true, ['status' => $info['status']], 300);
        }
        $service->settleRenewal($domain, $context->operation, $context->actor);

        return StepResult::done(['expires_at' => $domain->expires_at?->toIso8601String()]);
    }
}
