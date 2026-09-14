<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows\Steps;

use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\DomainConsent;
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

/**
 * domain-create with the S34 guard: if an earlier attempt of THIS operation is
 * PENDING_REGISTRY/UNKNOWN, we read `domain-info` first and never resend create.
 */
final class SubmitRegistrationStep extends DomainStep
{
    public function label(): string
    {
        return 'Registrace u registru';
    }

    public function run(StepContext $context): StepResult
    {
        $domain = $this->domain($context);
        $adapter = $this->adapter($context);
        $earlier = RegistrarOperation::query()->where('operation_id', $context->operation->id)->where('command', 'domain-create')->whereIn('state', [RegistrarOperation::PENDING_REGISTRY, RegistrarOperation::UNKNOWN, RegistrarOperation::SENT])->exists();
        if ($earlier || $domain->state === DomainStateMachine::PENDING_REGISTRY) {
            $info = $this->probe($adapter, $domain->fqdn_ascii);
            if ($info === 'registered') {
                return StepResult::done(['registered' => true]);
            }
            if ($info === 'pending') {
                $domain->forceFill(['state' => DomainStateMachine::PENDING_REGISTRY])->save();

                return StepResult::wait(new AsyncHandle('wapi_async', $domain->fqdn_ascii, null, ['command' => 'domain-create'], 900, 5 * 86400));
            }
        }
        $registrant = RegistrarContact::query()->find($domain->registrant_contact_id);
        $admin = $domain->admin_contact_id ? RegistrarContact::query()->find($domain->admin_contact_id) : $registrant;
        if ($registrant === null || ! $registrant->isSynced()) {
            return StepResult::fail('Registrant contact is not synced', false);
        }
        $consent = DomainConsent::query()->where('domain_id', $domain->id)->latest('accepted_at')->first();
        if ($consent === null) {
            return StepResult::fail('Missing registry/registrar terms consent evidence (blueprint §46.2)', false);
        }
        $request = array_filter([
            'period' => (int) $domain->renewal_period, 'registrant' => $registrant->remote_id, 'admin' => $admin?->remote_id ?? $registrant->remote_id,
            'nsset' => $context->get('nsset_handle'), 'nameservers' => $context->get('nsset_handle') ? null : ($domain->nameservers ?: null), 'rules' => $consent->toRegistryRules(),
        ], fn ($v) => $v !== null);
        try {
            $result = $this->registrar($context)->mutate('domain-create', $domain, ['name' => $domain->fqdn_ascii] + $request, fn (RegistrarProvider $a, string $clTrid) => $a->register($domain->fqdn_ascii, $request, $clTrid, (bool) $context->desired('test_mode', false)), $context->operation->id, $domain->organization_id, (bool) $context->desired('test_mode', false));
        } catch (ProviderException $e) {
            if (in_array($e->errorCode, [ProviderErrorCode::TRANSIENT, ProviderErrorCode::UNKNOWN], true)) {
                $domain->forceFill(['state' => DomainStateMachine::PENDING_REGISTRY])->save(); // resolve via domain-info on the retry, never resend

                return StepResult::fail($e->getMessage(), true, $e->toArray(), $e->retryAfterSeconds ?? 120);
            }

            return self::fromProviderException($e);
        }
        if ($result->isAsync() && $result->async !== null) {
            $domain->forceFill(['state' => DomainStateMachine::PENDING_REGISTRY])->save();

            return StepResult::wait($result->async, ['registered' => false]);
        }

        return StepResult::done(['registered' => true, 'registration_detail' => $result->data]);
    }

    protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
    {
        RegistrarOperation::query()->where('operation_id', $context->operation->id)->where('command', 'domain-create')->where('state', RegistrarOperation::PENDING_REGISTRY)->update(['state' => RegistrarOperation::SUCCEEDED, 'completed_at' => now()]);

        return StepResult::done(['registered' => true, 'registration_detail' => $status->detail]);
    }

    /** @return 'registered'|'pending'|'free' */
    private function probe(RegistrarProvider $adapter, string $fqdn): string
    {
        try {
            $info = $adapter->domainInfo($fqdn);
        } catch (ProviderException $e) {
            if ($e->errorCode === ProviderErrorCode::NOT_FOUND || $e->errorCode === ProviderErrorCode::VALIDATION) {
                return 'free';
            }
            throw $e;
        }
        $status = strtolower((string) $info['status']);

        return str_contains($status, 'pending') ? 'pending' : (($status === 'free' || $status === 'available') ? 'free' : 'registered');
    }
}
