<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows\Steps;

use Illuminate\Support\Carbon;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
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
                $this->forgetUnconfirmed($domain);

                return StepResult::done(['registered' => true]);
            }
            if ($info === 'pending') {
                $domain->forceFill(['state' => DomainStateMachine::PENDING_REGISTRY])->save();

                return StepResult::wait(new AsyncHandle('wapi_async', $domain->fqdn_ascii, null, ['command' => 'domain-create'], 900, 5 * 86400));
            }
            // "Not found" right after a create whose answer was lost proves nothing: a registry that works through a queue shows
            // the name minutes later. One such answer used to be enough to send `domain-create` again — two registrations, two
            // charges at the registrar, one payment. The name has to stay unknown for a while before the create is repeated.
            $wait = (int) config('onhost.domains.recreate_after_seconds', 600);
            $firstSeenFree = data_get($domain->meta, 'create_unconfirmed.free_since');
            if ($earlier && $wait > 0 && ($firstSeenFree === null || now()->diffInSeconds(Carbon::parse((string) $firstSeenFree), true) < $wait)) {
                $domain->forceFill(['meta' => array_replace_recursive((array) $domain->meta, ['create_unconfirmed' => ['free_since' => $firstSeenFree ?? now()->toIso8601String()]])])->save();

                return StepResult::fail('the registry does not know the domain yet and the earlier create was not confirmed; asking again before sending another one', true, ['create_unconfirmed' => true], min(300, max(60, intdiv($wait, 2))));
            }
        }
        $this->forgetUnconfirmed($domain);
        // A premium name costs what the registry says, and the registrar takes it from OUR credit the moment the create is accepted.
        // The order was priced from the TLD's list, so the name is asked about once more right before the create — whatever the
        // search said, whoever placed the order (the API quotes a domain without searching for it).
        try {
            $answer = $adapter->checkAvailability([$domain->fqdn_ascii])[$domain->fqdn_ascii] ?? [];
        } catch (ProviderException $e) {
            return StepResult::fail('the registrar did not say whether the name is premium; the create waits for the answer', true, $e->toArray(), $e->retryAfterSeconds ?? 120);
        }
        if (! array_key_exists('available', $answer) || $answer['available'] === null) { // no answer is not "ordinary": the create waits until the registrar says what the name is
            return StepResult::fail('the registrar did not say whether the name is free and ordinary ('.(string) ($answer['reason'] ?? 'no answer').'); the create waits for the answer', true, [], 120);
        }
        if (DomainService::isPremium($answer)) {
            return StepResult::fail("{$domain->fqdn_ascii} is a premium name: the registry sets its own price for it, so it is not registered at the list price of .{$domain->tld}", false, ['premium' => true, 'registry_price' => data_get($answer, 'price.amount'), 'currency' => data_get($answer, 'price.currency')]);
        }
        if (($answer['available'] ?? null) === false && ! $earlier) {
            return StepResult::fail("{$domain->fqdn_ascii} is no longer available (".($answer['reason'] ?? 'registered').')', false, ['reason' => $answer['reason'] ?? 'registered']);
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

    private function forgetUnconfirmed(Domain $domain): void
    {
        if (data_get($domain->meta, 'create_unconfirmed') !== null) {
            $domain->forceFill(['meta' => array_diff_key((array) $domain->meta, ['create_unconfirmed' => true])])->save();
        }
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
