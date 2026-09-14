<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Blueprint §5.4 provider adapter contract. Every method is idempotent with respect
 * to the ResourceSpec idempotency key and reports either a completed result or an
 * awaitable handle; it never returns "probably ok".
 */
interface InfrastructureProvider extends ProviderAdapter
{
    public function provision(ResourceSpec $spec): ProviderResult;

    public function getActualState(ResourceRef $ref): ActualState;

    public function reconcile(ResourceSpec $spec, ActualState $actual): ActionPlan;

    public function resize(ResourceRef $ref, ResourceSpec $spec): ProviderResult;

    public function suspend(ResourceRef $ref): ProviderResult;

    public function resume(ResourceRef $ref): ProviderResult;

    public function terminate(ResourceRef $ref): ProviderResult;

    public function usage(ResourceRef $ref, ?string $periodStart = null, ?string $periodEnd = null): Usage;

    /** Poll an asynchronous handle previously returned by this adapter. */
    public function awaitStatus(AsyncHandle $handle): AsyncStatus;
}
