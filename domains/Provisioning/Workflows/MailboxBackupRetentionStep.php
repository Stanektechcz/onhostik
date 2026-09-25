<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Services\Mail\MailboxBackupPolicy;
use Onhost\Domain\Services\Mail\MailDomains;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\MailboxBackupRetention;
use Onhost\Providers\Contracts\ProviderResult;
use Throwable;

/**
 * Sets the plan's mailbox backup retention on the service's own mailboxes (TASK-0024, owner decision 3) — the
 * `mailbox.backup_retention` action an operator asks for through `onhost:mail:backup-retention --apply`, and the last
 * step of a resize that a paid plan change started (`apply_mailbox_backup`).
 *
 * Nothing happens while the rule `mail.backup_retention` is off or the plan sells no `backup_days`. A mailbox that is not
 * provably the platform's is counted and never written. Fewer copies than a mailbox keeps now make the panel delete backups,
 * so a downgrade is held unless the operator said `allow_prune`. After a plan change the step never fails: the new plan is
 * paid and already applied, and failing here would roll the service back through the resize compensation — so every refusal
 * is recorded on the service (`tags.mail_backup.failed`) and in the audit, where the doctor and the command show it.
 */
final class MailboxBackupRetentionStep extends ServiceStep
{
    public function __construct(private readonly bool $afterPlanChange = false) {}

    /**
     * The step a resize ends with: only for a mail service whose resize a paid plan change asked for. Every other resize —
     * a drift repair, an add-on, any other family — keeps exactly the steps it had.
     *
     * @return list<self>
     */
    public static function afterResize(Operation $operation): array
    {
        if (data_get($operation->desired, 'apply_mailbox_backup') !== true) {
            return [];
        }

        return Service::query()->whereKey($operation->service_id)->value('family') === 'mail' ? [new self(afterPlanChange: true)] : [];
    }

    public function label(): string
    {
        return 'Zálohy schránek podle tarifu';
    }

    public function run(StepContext $context): StepResult
    {
        if ($this->afterPlanChange && $context->desired('apply_mailbox_backup') !== true) {
            return StepResult::done(['mail_backup' => 'not_a_plan_change']);
        }
        $service = $this->service($context)->fresh() ?? $this->service($context); // after a resize: the entitlements it just saved
        $copies = MailboxBackupPolicy::target($service);
        if ($service->family !== 'mail' || $copies === null || ! MailboxBackupPolicy::ruleOn()) {
            return StepResult::done(['mail_backup' => 'not_applicable']);
        }
        if (! $this->afterPlanChange) {
            return $this->apply($context, $service, $copies);
        }
        try {
            $this->apply($context, $service, $copies);
        } catch (Throwable $e) { // a paid plan change is not undone over its mailboxes' backups
            MailboxBackupPolicy::record($service, ['copies' => $copies, 'set' => 0, 'unchanged' => 0, 'held' => 0, 'foreign' => 0, 'failed' => [['remote_id' => null, 'code' => 'error', 'message' => mb_substr($e->getMessage(), 0, 160)]], 'operation_id' => $context->operation->id]);
        }

        return StepResult::done(['mail_backup' => 'recorded']); // the panel's queue is not awaited here: a late datalog error must not fail the plan change
    }

    private function apply(StepContext $context, Service $service, int $copies): StepResult
    {
        $allowPrune = ! $this->afterPlanChange && $context->desired('allow_prune') === true;
        $summary = ['copies' => $copies, 'set' => 0, 'unchanged' => 0, 'held' => 0, 'foreign' => 0, 'failed' => [], 'operation_id' => $context->operation->id];
        $last = null;
        foreach (MailDomains::bindingsOf($service) as $binding) {
            $adapter = $context->adapter($binding->provider_instance_id);
            if (! $adapter instanceof MailboxBackupRetention) {
                $summary['failed'][] = ['remote_id' => null, 'code' => 'unsupported', 'message' => 'this mail server keeps no per-mailbox backups the platform can set'];

                continue;
            }
            $domain = $binding->ref();
            try {
                $rows = $adapter->mailboxBackupRetention($domain);
            } catch (ProviderException $e) {
                if ($e->isRetryable() && ! $this->afterPlanChange) {
                    throw $e; // "not now" is asked again by the runner; it says nothing about the mailboxes
                }
                $summary['failed'][] = ['remote_id' => null, 'code' => $e->errorCode->value, 'message' => mb_substr($e->getMessage(), 0, 160)];

                continue;
            }
            foreach ($rows as $row) {
                $outcome = self::decide($row, $copies, $allowPrune);
                if ($outcome !== 'set') {
                    $summary[$outcome]++;

                    continue;
                }
                try {
                    $last = $adapter->setMailboxBackupRetention($domain, $row['remote_id'], $copies);
                    $summary['set']++;
                } catch (ProviderException $e) {
                    $summary['failed'][] = ['remote_id' => $row['remote_id'], 'code' => $e->errorCode->value, 'message' => mb_substr($e->getMessage(), 0, 160)];
                }
            }
        }
        MailboxBackupPolicy::record($service, $summary);
        $context->container->make(AuditRecorder::class)->record($context->actor->withScope($service->organization_id), 'service.mail_backup.retention', $summary['failed'] === [] ? 'succeeded' : 'partial',
            array_diff_key($summary, ['operation_id' => true]) + ['allow_prune' => $allowPrune, 'after_plan_change' => $this->afterPlanChange], 'service', $service->id);

        return $last instanceof ProviderResult && ! $this->afterPlanChange ? $this->settle($last, ['mail_backup' => $summary['set']]) : StepResult::done(['mail_backup' => $summary['set']]);
    }

    /**
     * What one mailbox gets: `foreign` (not provably ours), `unchanged` (already at the plan), `held` (it keeps more than
     * the plan and nobody allowed the panel to delete the difference) or `set`.
     *
     * @param  array{remote_id:string, address:string, interval:string, copies:int, owned:bool}  $row
     */
    public static function decide(array $row, int $copies, bool $allowPrune): string
    {
        return match (true) {
            ! $row['owned'] => 'foreign',
            $row['interval'] === MailboxBackupPolicy::INTERVAL && $row['copies'] === $copies => 'unchanged',
            $row['interval'] === MailboxBackupPolicy::INTERVAL && $row['copies'] > $copies && ! $allowPrune => 'held',
            default => 'set',
        };
    }
}
