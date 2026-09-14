<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows\Steps;

use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Domains\Models\RegistrarObject;
use Onhost\Domain\Domains\Models\RegistrarOperation;
use Onhost\Domain\Domains\Workflows\DomainStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\RegistrarProvider;

/**
 * .CZ (and other NSSET registries) need a nameserver set object. ONhost DNS uses
 * the shared ONhost NSSET; custom nameservers get a per-domain NSSET.
 */
final class EnsureNssetStep extends DomainStep
{
    public function label(): string
    {
        return 'NSSET';
    }

    public function run(StepContext $context): StepResult
    {
        $domain = $this->domain($context);
        if (! in_array($domain->tld, (array) config('onhost.domains.nsset_tlds', ['cz']), true)) {
            return StepResult::skip();
        }
        $custom = (array) ($context->desired('nameservers') ?? []);
        $registrar = $this->registrar($context);
        $tech = $this->techContactHandle($domain, $context);

        if ($custom === []) {
            $shared = RegistrarObject::sharedNsset($domain->registrar_provider);
            if ($shared !== null) {
                $domain->forceFill(['nsset_id' => $shared->id])->save();

                return StepResult::done(['nsset_handle' => $shared->handle]);
            }
            $handle = (string) config('onhost.domains.shared_nsset_handle', 'NSSET-ONHOST');
            $nameservers = $context->container->make(DnsService::class)->nameservers('powerdns');
            $object = RegistrarObject::query()->firstOrCreate(['handle' => $handle], ['kind' => 'nsset', 'registrar_provider' => $domain->registrar_provider, 'nameservers' => $nameservers, 'shared' => true, 'state' => 'draft', 'tech_contact_id' => $domain->admin_contact_id]);
        } else {
            $handle = 'NSSET-ONH-'.strtoupper(substr(sha1(implode(',', $custom)), 0, 10));
            $nameservers = array_values($custom);
            $object = RegistrarObject::query()->firstOrCreate(['handle' => $handle], ['kind' => 'nsset', 'organization_id' => $domain->organization_id, 'registrar_provider' => $domain->registrar_provider, 'nameservers' => $nameservers, 'shared' => false, 'state' => 'draft', 'tech_contact_id' => $domain->admin_contact_id]);
        }
        if ($object->state !== 'synced') {
            try {
                $result = $registrar->mutate('nsset-create', $domain, ['nsset' => $handle, 'dns' => $nameservers, 'tech_c' => $tech], fn (RegistrarProvider $adapter, string $clTrid) => $adapter->createNsset($handle, $nameservers, $tech, $clTrid), $context->operation->id, $domain->organization_id, (bool) $context->desired('test_mode', false));
                if ($result->isAsync() && $result->async !== null) {
                    $domain->forceFill(['nsset_id' => $object->id, 'nameservers' => $nameservers])->save();

                    return StepResult::wait($result->async, ['nsset_handle' => $handle]);
                }
            } catch (ProviderException $e) {
                if ($e->errorCode !== ProviderErrorCode::CONFLICT) {
                    return self::fromProviderException($e);
                }
            }
            $object->forceFill(['state' => 'synced'])->save();
        }
        $domain->forceFill(['nsset_id' => $object->id, 'nameservers' => $nameservers])->save();

        return StepResult::done(['nsset_handle' => $handle]);
    }

    protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
    {
        $handle = (string) $context->get('nsset_handle');
        RegistrarObject::query()->where('handle', $handle)->update(['state' => 'synced']);
        RegistrarOperation::query()->where('operation_id', $context->operation->id)->where('command', 'nsset-create')->where('state', RegistrarOperation::PENDING_REGISTRY)->update(['state' => RegistrarOperation::SUCCEEDED, 'completed_at' => now()]);

        return StepResult::done(['nsset_handle' => $handle]);
    }

    private function techContactHandle(Domain $domain, StepContext $context): string
    {
        $handle = (string) config('onhost.domains.tech_contact_handle', '');
        if ($handle !== '') {
            return $handle;
        }
        $admin = $domain->admin_contact_id ? RegistrarContact::query()->find($domain->admin_contact_id) : null;

        return (string) ($admin?->remote_id ?? $context->get('registrant_handle') ?? '');
    }
}
