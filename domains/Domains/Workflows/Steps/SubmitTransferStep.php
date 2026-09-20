<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows\Steps;

use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\DomainTransferSecret;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Domains\Models\RegistrarOperation;
use Onhost\Domain\Domains\Workflows\DomainStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\RegistrarProvider;

/** domain-transfer with the AUTH-ID consumed from the encrypted secret store; the secret is wiped on use (§46.5). */
final class SubmitTransferStep extends DomainStep
{
    public function label(): string
    {
        return 'Transfer u registru';
    }

    public function run(StepContext $context): StepResult
    {
        $domain = $this->domain($context);
        $adapter = $this->adapter($context);
        $earlier = RegistrarOperation::query()->where('operation_id', $context->operation->id)->where('command', 'domain-transfer')->whereIn('state', [RegistrarOperation::PENDING_REGISTRY, RegistrarOperation::UNKNOWN, RegistrarOperation::SENT])->exists();
        if ($earlier) {
            return StepResult::wait(new AsyncHandle('wapi_async', $domain->fqdn_ascii, null, ['command' => 'domain-transfer'], 1800, 7 * 86400));
        }
        $check = $adapter->transferCheck($domain->fqdn_ascii);
        if (($check['transferable'] ?? false) !== true) {
            return StepResult::fail('Registry reports the domain is not transferable (locked, pending or wrong registrar)', false, $check);
        }
        try { // a transfer renews the name — a premium one at the registry's price, from our credit
            $answer = $adapter->checkAvailability([$domain->fqdn_ascii])[$domain->fqdn_ascii] ?? [];
        } catch (ProviderException $e) {
            return StepResult::fail('the registrar did not say whether the name is premium; the transfer waits for the answer', true, $e->toArray(), $e->retryAfterSeconds ?? 300);
        }
        if (DomainService::isPremium($answer)) {
            return StepResult::fail("{$domain->fqdn_ascii} is a premium name: its transfer is priced by the registry, so it is not transferred at the list price — finance handle it by hand", false, ['premium' => true]);
        }
        $secret = DomainTransferSecret::query()->where('domain_id', $domain->id)->where('direction', 'in')->whereNull('used_at')->latest()->first();
        if ($secret === null || ! $secret->isUsable()) {
            return StepResult::fail('AUTH-ID is missing or expired; ask the customer to submit it again', false);
        }
        $registrant = RegistrarContact::query()->find($domain->registrant_contact_id);
        $request = array_filter(['registrant' => $registrant?->remote_id, 'admin' => $registrant?->remote_id, 'nsset' => $context->get('nsset_handle'), 'period' => (int) $context->desired('period', 1)], fn ($v) => $v !== null);
        $authInfo = $secret->consume();
        try {
            $result = $this->registrar($context)->mutate('domain-transfer', $domain, ['name' => $domain->fqdn_ascii, 'auth_info' => '[redacted]'] + $request, fn (RegistrarProvider $a, string $clTrid) => $a->transferIn($domain->fqdn_ascii, $authInfo, $request, $clTrid), $context->operation->id, $domain->organization_id);
        } catch (ProviderException $e) {
            if (in_array($e->errorCode, [ProviderErrorCode::TRANSIENT, ProviderErrorCode::UNKNOWN], true)) {
                return StepResult::fail($e->getMessage(), true, $e->toArray(), $e->retryAfterSeconds ?? 300);
            }

            return self::fromProviderException($e);
        } finally {
            $authInfo = null;
        }
        $domain->forceFill(['state' => DomainStateMachine::TRANSFER_IN_PENDING])->save();

        return $result->isAsync() && $result->async !== null ? StepResult::wait($result->async) : StepResult::done(['transferred' => true]);
    }

    protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
    {
        RegistrarOperation::query()->where('operation_id', $context->operation->id)->where('command', 'domain-transfer')->where('state', RegistrarOperation::PENDING_REGISTRY)->update(['state' => RegistrarOperation::SUCCEEDED, 'completed_at' => now()]);

        return StepResult::done(['transferred' => true]);
    }
}
