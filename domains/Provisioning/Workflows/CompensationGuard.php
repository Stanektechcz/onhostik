<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceIdentityCheck;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\InfrastructureProvider;
use Throwable;

/**
 * What a failed operation takes back: the resource IT created, once the panel confirms that this is what stands under the
 * number — the same proof a cancellation needs (`ServiceIdentityCheck`), because deleting has no undo at the provider.
 *
 * The compensations called `terminate()` on whatever binding the service had. The VPS clone step binds the service to the
 * vmid it RESERVED the moment the clone is accepted; when the clone then failed because somebody else had taken that number
 * in the meantime, the compensation stopped and destroyed that somebody's running machine (proven by a test). A panel that
 * cannot be asked, a name that does not match, a binding this operation did not write: the resource is kept, the fact is on
 * record, and operations hear about it — an orphan costs a look, a wrong delete costs a customer's server.
 */
final class CompensationGuard
{
    public function __construct(
        private readonly ServiceIdentityCheck $identity,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * @param  object|null  $adapter  the panel the resource lives on (default: the operation's)
     * @return 'deleted'|'missing'|'kept'|'nothing'
     */
    public function takeBack(StepContext $context, Service $service, ?string $remoteType = null, ?object $adapter = null, ?ProviderBinding $binding = null): string
    {
        $binding ??= $this->createdHere($context, $service, $remoteType);
        if ($binding === null) {
            return 'nothing'; // this operation bound nothing: there is nothing of its making to take back
        }
        $adapter ??= $context->adapter();
        $ref = $binding->ref();
        try {
            $report = $this->identity->verify($service, $adapter, $ref, $context->operation);
        } catch (Throwable $e) {
            $report = ['ok' => false, 'missing' => false, 'failed' => ['identity_check'], 'matched' => 0, 'required' => 0, 'checks' => [], 'error' => mb_substr($e->getMessage(), 0, 200)];
        }
        if ($report['missing'] ?? false) {
            return 'missing';
        }
        if (($report['ok'] ?? false) && $adapter instanceof InfrastructureProvider) {
            try {
                $adapter->terminate($ref);

                return 'deleted';
            } catch (Throwable $e) {
                $report['failed'][] = 'terminate';
                $report['error'] = mb_substr($e->getMessage(), 0, 200);
            }
        }
        $detail = ['operation_id' => $context->operation->id, 'kind' => $context->operation->kind, 'remote_type' => $binding->remote_type, 'remote_id' => (string) $binding->remote_id, 'node' => $binding->remote_node,
            'failed' => array_values((array) ($report['failed'] ?? [])), 'matched' => $report['matched'] ?? 0, 'required' => $report['required'] ?? 0, 'error' => $report['error'] ?? null];
        $this->audit->record($context->actor->withScope($service->organization_id, $service->project_id), 'provisioning.compensation.kept', 'succeeded', $detail, 'service', $service->id);
        $this->outbox->publish(GenericEvent::of('provisioning.compensation.kept', 'service', $service->id, $detail + ['label' => $service->label ?: ($service->hostname ?: $service->name)], $service->organization_id));

        return 'kept';
    }

    /** The binding THIS operation wrote (`StepContext::bind` keys it by the operation), never simply the service's first one. */
    private function createdHere(StepContext $context, Service $service, ?string $remoteType): ?ProviderBinding
    {
        $prefix = $context->operation->idempotency_key.':';

        return ProviderBinding::query()->where('service_id', $service->id)->when($remoteType !== null, fn ($q) => $q->where('remote_type', $remoteType))->orderBy('created_at')->get()
            ->first(fn (ProviderBinding $b) => str_starts_with((string) $b->idempotency_key, $prefix));
    }
}
