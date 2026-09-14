<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflow;

use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\InfrastructureProvider;

/** Default poll implementation via the adapter's awaitStatus + uniform provider error mapping. */
abstract class AbstractStep implements Step
{
    public function poll(StepContext $context, AsyncHandle $handle): StepResult
    {
        $adapter = $context->adapter();
        if (! method_exists($adapter, 'awaitStatus')) {
            return StepResult::done();
        }
        try {
            /** @var AsyncStatus $status */
            $status = $adapter->awaitStatus($handle);
        } catch (ProviderException $e) {
            return self::fromProviderException($e);
        }

        return match ($status->state) {
            AsyncStatus::SUCCEEDED => $this->afterAsyncSuccess($context, $status),
            AsyncStatus::FAILED => StepResult::fail($status->message ?? 'provider task failed', false, $status->detail),
            AsyncStatus::UNKNOWN => StepResult::fail($status->message ?? 'provider task state unknown', true, $status->detail),
            default => StepResult::wait($handle),
        };
    }

    /** Hook for steps that need to read back state after the async task completed. */
    protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
    {
        return StepResult::done(['last_task' => $status->detail]);
    }

    public static function fromProviderException(ProviderException $e): StepResult
    {
        return StepResult::fail($e->getMessage(), $e->isRetryable(), $e->toArray(), $e->retryAfterSeconds);
    }

    protected function infra(StepContext $context): InfrastructureProvider
    {
        $adapter = $context->adapter();
        if (! $adapter instanceof InfrastructureProvider) {
            throw new \RuntimeException(get_class($adapter).' is not an InfrastructureProvider');
        }

        return $adapter;
    }
}
