<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows;

use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\RegistrarClient;
use Onhost\Domain\Provisioning\Workflow\AbstractStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\RegistrarProvider;

/** Base for registrar steps: resolves the Domain aggregate and the registrar adapter for the operation. */
abstract class DomainStep extends AbstractStep
{
    protected function domain(StepContext $context): Domain
    {
        $id = $context->operation->domain_id ?? $context->desired('domain_id');
        $domain = $id === null ? null : Domain::query()->find($id);
        if ($domain === null) {
            throw new \RuntimeException('Operation has no domain assigned');
        }

        return $domain;
    }

    protected function registrar(StepContext $context): RegistrarClient
    {
        return $context->container->make(RegistrarClient::class);
    }

    protected function adapter(StepContext $context): RegistrarProvider
    {
        return $this->registrar($context)->forDomain($this->domain($context));
    }

    /** Registrar async handles are polled against domain-info instead of the operation's infra adapter. */
    public function poll(StepContext $context, AsyncHandle $handle): StepResult
    {
        try {
            $status = $this->adapter($context)->awaitStatus($handle);
        } catch (ProviderException $e) {
            return self::fromProviderException($e);
        }

        return match ($status->state) {
            AsyncStatus::SUCCEEDED => $this->afterAsyncSuccess($context, $status),
            AsyncStatus::FAILED => StepResult::fail($status->message ?? 'registry rejected the request', false, $status->detail),
            AsyncStatus::UNKNOWN => StepResult::fail($status->message ?? 'registry state unknown', true, $status->detail),
            default => StepResult::wait($handle),
        };
    }
}
