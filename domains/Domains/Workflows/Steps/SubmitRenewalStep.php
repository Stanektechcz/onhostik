<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows\Steps;

use Onhost\Domain\Domains\Models\RegistrarOperation;
use Onhost\Domain\Domains\Workflows\DomainStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\RegistrarProvider;

/** domain-renew guarded by an expiry comparison: a renewal that already landed is never sent twice. */
final class SubmitRenewalStep extends DomainStep
{
    public function label(): string
    {
        return 'Prodloužení u registru';
    }

    public function run(StepContext $context): StepResult
    {
        $domain = $this->domain($context);
        $years = (int) $context->desired('period', $domain->renewal_period ?: 1);
        $adapter = $this->adapter($context);
        $before = $domain->expires_at;
        $expected = $before?->copy()->addYears($years);
        $info = $adapter->domainInfo($domain->fqdn_ascii);
        $current = isset($info['expires_at']) ? new \DateTimeImmutable((string) $info['expires_at']) : null;
        if ($expected !== null && $current !== null && $current >= $expected->startOfDay()) {
            return StepResult::done(['renewed' => true, 'expires_at' => $current->format(DATE_ATOM), 'already_renewed' => true]);
        }
        try {
            $result = $this->registrar($context)->mutate('domain-renew', $domain, ['name' => $domain->fqdn_ascii, 'period' => $years], fn (RegistrarProvider $a, string $clTrid) => $a->renew($domain->fqdn_ascii, $years, $clTrid, (bool) $context->desired('test_mode', false)), $context->operation->id, $domain->organization_id, (bool) $context->desired('test_mode', false));
        } catch (ProviderException $e) {
            if (in_array($e->errorCode, [ProviderErrorCode::TRANSIENT, ProviderErrorCode::UNKNOWN], true)) {
                return StepResult::fail($e->getMessage(), true, $e->toArray(), $e->retryAfterSeconds ?? 120); // the guard above resolves it on retry
            }

            return self::fromProviderException($e);
        }
        if ($result->isAsync() && $result->async !== null) {
            return StepResult::wait($result->async, ['expected_expiry' => $expected?->toIso8601String()]);
        }

        return StepResult::done(['renewed' => true, 'expected_expiry' => $expected?->toIso8601String()]);
    }

    protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
    {
        RegistrarOperation::query()->where('operation_id', $context->operation->id)->where('command', 'domain-renew')->where('state', RegistrarOperation::PENDING_REGISTRY)->update(['state' => RegistrarOperation::SUCCEEDED, 'completed_at' => now()]);

        return StepResult::done(['renewed' => true]);
    }
}
