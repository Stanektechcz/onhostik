<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Illuminate\Support\Facades\DB;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Orders\CreditOrderPolicy;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Tax\TaxEngine;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Money\Money;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * What a customer may still do with the archive of a service that is gone (audit §5ab):
 *
 *  • **restore it onto a new paid service — free of charge.** The archive is pushed back into the new service by the
 *    `archive.restore` operation; nothing is charged, whatever the archive's size.
 *  • **download it as one compressed file — for a fee** (500 Kč by default, "Nastavení systému → Životní cyklus
 *    služeb"). The fee is charged once per archive; ordering a new service afterwards does not refund it, and an
 *    archive already paid for stays downloadable for the rest of its retention.
 *
 * Archives live for `DeletionPolicy::retentionDays()` counted from the removal of the service and are pruned by
 * `onhost:backups:run` afterwards — after that neither path is possible any more.
 */
final class ServiceArchiveService
{
    public function __construct(
        private readonly FinalArchive $archives,
        private readonly DeletionPolicy $policy,
        private readonly WalletService $wallets,
        private readonly TaxEngine $tax,
        private readonly InvoiceService $invoices,
        private readonly ServiceService $services,
        private readonly AuditRecorder $audit,
        private readonly OutboxPublisher $outbox,
        private readonly Authorizer $authorizer,
    ) {}

    /**
     * The archives of one organization, with what may be done with each of them.
     *
     * @return list<array<string,mixed>>
     */
    public function forOrganization(string $organizationId, string $currency = 'CZK'): array
    {
        $rows = [];
        foreach (Backup::query()->where('organization_id', $organizationId)->where('kind', 'final')->whereIn('state', ['completed'])->orderByDesc('created_at')->get() as $backup) {
            $service = Service::query()->withTrashed()->find($backup->service_id);
            $rows[] = [
                'id' => $backup->id, 'service_id' => $backup->service_id, 'service' => $service === null ? '—' : $service->name, 'label' => $service === null ? null : ($service->label ?: $service->hostname),
                'family' => (string) data_get($backup->meta, 'family', $service === null ? '' : $service->family), 'created_at' => $backup->created_at?->toIso8601String(),
                'size_bytes' => (int) $backup->size_bytes, 'parts' => (array) data_get($backup->meta, 'parts', []), 'gaps' => (array) data_get($backup->meta, 'gaps', []),
                'retention_until' => $backup->retention_until?->toIso8601String(), 'days_left' => $backup->retention_until === null ? null : max(0, (int) now()->diffInDays($backup->retention_until, false)),
                'paid' => (bool) data_get($backup->meta, 'download.paid', false), 'fee_minor' => $this->policy->downloadFeeMinor($currency), 'currency' => $currency,
                'restorable' => in_array((string) data_get($backup->meta, 'family', ''), ['web', 'managed'], true),
            ];
        }

        return $rows;
    }

    /** The archive of this organization, or a 404 — never another organization's. */
    public function archive(string $backupId, string $organizationId): Backup
    {
        $backup = Backup::query()->where('id', $backupId)->where('organization_id', $organizationId)->where('kind', 'final')->first();
        if ($backup === null || $backup->state !== 'completed') {
            throw DomainError::notFound('backup');
        }
        if ($backup->retention_until !== null && $backup->retention_until->isPast()) {
            throw new DomainError('archive_expired', 'Doba uchování archivu už uplynula.', 410);
        }

        return $backup;
    }

    /**
     * The paid download. The fee is charged once; afterwards the archive stays downloadable for free until its
     * retention runs out. A restore onto a new paid service waives it (`waive()`), because that path is free.
     *
     * @return array{path:string, filename:string, bytes:int, sha256:string, fee_minor:int, charged:bool}
     */
    public function download(Backup $backup, CommandContext $context): array
    {
        $organization = Organization::query()->findOrFail($backup->organization_id);
        $currency = (string) ($organization->currency ?? 'CZK');
        $charged = false;
        if (! (bool) data_get($backup->meta, 'download.paid', false)) {
            $fee = $this->policy->downloadFeeMinor($currency);
            if ($fee > 0) {
                // owner decision 20 (TASK-0021): the fee is paid from credit — by the owner or the billing admin
                app(CreditOrderPolicy::class)->assertMaySpend($organization, $context, 'Požádejte vlastníka o stažení archivu.');
                $this->chargeFee($organization, $backup, Money::minor($fee, $currency), $context);
                $charged = true;
            }
            $backup->forceFill(['meta' => array_merge((array) $backup->meta, ['download' => array_merge((array) data_get($backup->meta, 'download', []), ['paid' => true, 'paid_at' => now()->toIso8601String(), 'fee_minor' => $fee, 'currency' => $currency])])])->save();
        }
        $package = $this->archives->package($backup->refresh());
        $this->audit->record($context->withScope($backup->organization_id), 'service.archive.download', 'succeeded', ['backup' => $backup->id, 'bytes' => $package['bytes'], 'charged' => $charged], 'backup', $backup->id);
        $this->outbox->publish(GenericEvent::of('service.archive.downloaded', 'backup', $backup->id, [
            'backup_id' => $backup->id, 'service_id' => $backup->service_id, 'bytes' => $package['bytes'], 'fee' => $charged ? data_get($backup->meta, 'download.fee_minor') : 0,
        ], $backup->organization_id));

        return $package + ['fee_minor' => (int) data_get($backup->meta, 'download.fee_minor', 0), 'charged' => $charged];
    }

    /** The fee is waived for this archive (it goes back onto a new paid service, or staff decided so). */
    public function waive(Backup $backup, CommandContext $context, string $reason): Backup
    {
        $backup->forceFill(['meta' => array_merge((array) $backup->meta, ['download' => array_merge((array) data_get($backup->meta, 'download', []), ['paid' => true, 'waived' => true, 'waived_reason' => mb_substr($reason, 0, 200), 'fee_minor' => 0])])])->save();
        $this->audit->record($context->withScope($backup->organization_id), 'service.archive.fee_waived', 'succeeded', ['backup' => $backup->id, 'reason' => $reason], 'backup', $backup->id);

        return $backup->refresh();
    }

    /**
     * Free restore onto a new, paid service of the same family: files and databases go back through the panel.
     * The target must be an active service of the same organization — never the archive's own (it no longer exists).
     *
     * Both doors lead here — `POST /services/archives/{id}/restore` and the generic action endpoint with `archive.restore`
     * (TASK-0035, IF-11 / audit SE-2, SE-14): the generic one checked only the TARGET, so a guest holding `svc_restore` on one
     * service could pull any archive of the organization into it and read a stranger's site. What is asked now is what the
     * restore really does: read the source (`backup.read` where the archived service lived — otherwise the archive does not exist
     * for the person, 404) and restore for the whole project or organization of the target (`backup.restore` there; a single
     * shared service is no such scope). The live target is copied first (the `pre_restore` step, fails closed), and the download
     * fee is waived only once the restore was accepted — a refused one leaves the archive costing what it cost.
     */
    public function restore(Backup $backup, Service $target, CommandContext $context, string $idempotencyKey): Operation
    {
        if ($target->organization_id !== $backup->organization_id) {
            throw DomainError::notFound('service');
        }
        $this->assertMayRestore($backup, $target, $context);
        $family = (string) data_get($backup->meta, 'family', '');
        if ($family !== '' && $target->family !== $family) {
            throw new DomainError('archive_family_mismatch', 'Archiv patří ke službě typu '.$family.'; obnovit ho lze jen do stejného typu služby.', 422);
        }
        if (! in_array($target->state, [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED], true)) {
            throw new DomainError('service_state_invalid', 'Obnovu lze spustit jen do aktivní služby.', 409, ['state' => $target->state]);
        }
        // No automated path for this family: support hands the archive over. This is a refusal (409), and a refusal inside the bus
        // transaction keeps nothing — the waive written here before was rolled back while the answer said `waived: true` and "free
        // now" (red-team round of the Phase-0 chain). The answer says what was kept: nothing; whether the fee goes is support's call.
        if (! in_array($family, ['web', 'managed'], true)) {
            throw new DomainError('archive_restore_manual', 'Archiv této služby vracíme ručně — napište podpoře, s obnovou vám pomůže a domluví s vámi i poplatek za stažení.', 409, ['waived' => false, 'backup_id' => $backup->id]);
        }
        // the customer's bus command was checked for backup.restore; a restore writes over a live service for minutes, so the run asks again before each step (H315)
        $operation = $this->services->requestAction($target, 'archive.restore', $context, $idempotencyKey, ['backup_id' => $backup->id], authorizedPermission: 'backup.restore');
        $this->waive($backup, $context, 'restore to a new paid service ('.$target->id.')'); // accepted: from now on the download is free (IF-11)

        return $operation;
    }

    /** IF-11: the source is read, the target is restored for a whole project or organization — see restore(). */
    private function assertMayRestore(Backup $backup, Service $target, CommandContext $context): void
    {
        if ($context->actorType === 'system') {
            return; // the platform itself (an operator command, a saga) acts on its own authority
        }
        // the person a staff member acts for is the one asked (red-team round; GrantPolicy, CreditOrderPolicy read it the same way)
        $person = $context->onBehalfOfUserId ?? $context->actorId;
        $user = $context->actorType === 'user' && $person !== null ? User::query()->find($person) : null;
        if ($user === null) {
            throw DomainError::forbidden('An archive is restored by a person.');
        }
        $source = Service::query()->withTrashed()->find((string) $backup->service_id);
        $sourceScope = $source === null ? CommandScope::organization($backup->organization_id) : CommandScope::resource($source->id, $source->organization_id, $source->project_id);
        if (! $this->authorizer->can($user, 'backup.read', $sourceScope)) {
            throw DomainError::notFound('backup');
        }
        $targetScope = $target->project_id === null ? CommandScope::organization($target->organization_id) : CommandScope::project($target->project_id, $target->organization_id);
        if (! $this->authorizer->can($user, 'backup.restore', $targetScope)) {
            throw new DomainError('forbidden', 'Archiv zrušené služby obnoví jen ten, kdo smí obnovovat zálohy v celém projektu nebo organizaci.', 403, ['permission' => 'backup.restore', 'scope' => $target->project_id === null ? 'organization' : 'project']);
        }
    }

    private function chargeFee(Organization $organization, Backup $backup, Money $net, CommandContext $context): void
    {
        $decision = $this->tax->calculate(
            VatStanding::taxCustomer($organization),
            [['key' => 'archive', 'net' => $net, 'product_class' => 'service']], $net->currency, $organization->id,
        );
        $line = $decision['lines'][0];
        $gross = Money::minor($net->minor + (int) $line['tax']->minor, $net->currency);
        DB::transaction(function () use ($organization, $backup, $net, $gross, $line, $decision, $context): void {
            $scoped = $context->withScope($organization->id);
            $this->wallets->charge($organization, $gross, 'services', "archive-download:{$backup->id}", $scoped, 'backup', $backup->id, Money::minor((int) $line['tax']->minor, $net->currency));
            $draft = $this->invoices->draft($organization, 'invoice', $net->currency->value, [[
                'sku' => 'archive-download', 'description' => 'Stažení archivu zrušené služby '.($backup->service_id ?? ''), 'qty' => 1, 'unit' => 'ks',
                'unit_net' => $net->minor, 'discount' => 0, 'net' => $net->minor, 'tax_rate' => (string) $line['rate'], 'tax_category' => (string) $line['category'], 'tax' => (int) $line['tax']->minor, 'total' => $gross->minor,
            ]], $scoped, null, ['payment_method' => 'wallet', 'backup_id' => $backup->id, 'tax_calculation_id' => $decision['calculation']->id]);
            $this->invoices->issue($draft, $scoped, dueDays: 0);
        }, 3);
    }
}
