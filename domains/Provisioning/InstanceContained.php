<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Platform\Errors\DomainError;

/**
 * The registry refused an adapter because the instance is `contained` or `disabled` (TASK-0045, staging pre-mortem
 * 2026-09-27). Thrown before any adapter is built, so no credential is read and no request is sent: the refusal is the
 * same for a customer, a member of staff and the system. It is a pause, not a failure — the operation runner parks
 * the run and it goes on once staff lift the state through the instance state action.
 */
final class InstanceContained extends DomainError
{
    public function __construct(public readonly string $instanceKey, public readonly string $instanceState)
    {
        parent::__construct(
            'provider_instance_contained',
            "This control panel is {$instanceState} by the operator: nothing is sent to it until staff lift that state.",
            423,
            // no instance key in what a caller may be shown: it names the vendor (VendorNeutralityTest); staff read it on the parked run
            ['contained' => true, 'instance_state' => $instanceState],
        );
    }

    /**
     * The refusal a step handed back as its own failure detail (`StepResult::fail($e->getMessage(), false, $e->extra)`), or null.
     *
     * @param  array<string, mixed>  $detail
     */
    public static function fromDetail(array $detail): ?self
    {
        $state = $detail['instance_state'] ?? null;
        if (($detail['contained'] ?? null) !== true || ! is_string($state) || ! in_array($state, ProviderInstance::REFUSED_STATES, true)) {
            return null;
        }

        return new self('', $state); // which instance: the step did not say
    }

    public static function of(ProviderInstance $instance, string $state): self
    {
        return new self((string) $instance->key, $state);
    }
}
