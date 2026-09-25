<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Mail;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflows\MailboxBackupRetentionStep;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Providers\Contracts\MailboxBackupRetention;
use Onhost\Providers\Contracts\MailToolsProvider;
use Throwable;

/**
 * What `onhost:mail:backup-retention` sees and does (TASK-0024, owner decision 3). `review()` only READS the panels: for
 * every live mail service, each mailbox of its own mail domains with the retention it has, the one its plan sells and what
 * would happen — `would_set`, `ok`, `not_ours` (never written), `would_prune` (the plan keeps fewer copies than the mailbox:
 * the panel would delete the difference, so it happens only with `--allow-prune`), `unreadable`. `apply()` asks for one
 * `mailbox.backup_retention` operation per service that is behind; the operation proves every mailbox again before it writes.
 */
final class MailboxBackupRetentionPlan
{
    /** A mailbox's status → the service counter it adds to (`ok` adds to none). */
    private const BUCKETS = ['would_set' => 'set', 'would_prune' => 'prune', 'not_ours' => 'foreign', 'unreadable' => 'unreadable'];

    public function __construct(private readonly ProviderRegistry $providers, private readonly ServiceService $services) {}

    /**
     * @param  list<string>  $serviceIds  only these, when given
     * @return array{rows: list<array{service:string, mailbox:string, now:string, target:string, status:string}>, services: list<array{service:Service, target:?int, apply:bool, set:int, prune:int, pruning:int, foreign:int, unreadable:int}>}
     */
    public function review(array $serviceIds, int $chunk, bool $allowPrune): array
    {
        $rows = [];
        $services = [];
        Service::query()->where('family', 'mail')->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->whereNotNull('provider_instance_id')
            ->when($serviceIds !== [], fn ($q) => $q->whereIn('id', $serviceIds))
            ->chunkById(max(1, $chunk), function ($chunked) use (&$rows, &$services, $allowPrune) {
                foreach ($chunked as $service) {
                    [$serviceRows, $summary] = $this->reviewService($service, $allowPrune);
                    array_push($rows, ...$serviceRows);
                    $services[] = $summary;
                }
            });

        return ['rows' => $rows, 'services' => $services];
    }

    /** Asks for the operation that brings one service's mailboxes to its plan. */
    public function apply(Service $service, int $copies, bool $allowPrune): Operation
    {
        return $this->services->requestAction($service, 'mailbox.backup_retention', CommandContext::system('cli:mail:backup-retention'),
            'mail-backup-retention:'.$service->id.':'.$copies.':'.(int) $allowPrune.':'.now()->format('YmdHis'), $allowPrune ? ['allow_prune' => true] : []);
    }

    /** @return array{0: list<array{service:string, mailbox:string, now:string, target:string, status:string}>, 1: array{service:Service, target:?int, apply:bool, set:int, prune:int, pruning:int, foreign:int, unreadable:int}} */
    private function reviewService(Service $service, bool $allowPrune): array
    {
        $name = (string) ($service->hostname ?: $service->name);
        $target = MailboxBackupPolicy::target($service);
        $summary = ['service' => $service, 'target' => $target, 'apply' => false, 'set' => 0, 'prune' => 0, 'pruning' => 0, 'foreign' => 0, 'unreadable' => 0];
        if ($target === null) {
            return [[['service' => $name, 'mailbox' => '—', 'now' => '—', 'target' => '—', 'status' => 'plan_sells_none']], $summary];
        }
        $rows = [];
        foreach (MailDomains::bindingsOf($service) as $binding) {
            foreach ($this->reviewDomain($binding, $target) as $row) {
                $bucket = self::BUCKETS[$row['status']] ?? null;
                if ($bucket !== null) {
                    $summary[$bucket]++;
                }
                $summary['pruning'] += $row['pruning'];
                $rows[] = ['service' => $name, 'mailbox' => $row['mailbox'], 'now' => $row['now'], 'target' => MailboxBackupPolicy::INTERVAL.'/'.$target, 'status' => $row['status'].($row['note'] !== '' ? ' — '.$row['note'] : '')];
            }
        }
        // held downgrades wait for --allow-prune; otherwise a service is applied when a mailbox needs it, or when all of
        // them are at the plan but the service does not know it yet (so the doctor stops counting it)
        $summary['apply'] = $summary['unreadable'] === 0 && ($summary['prune'] === 0 || $allowPrune)
            && ($summary['set'] > 0 || $summary['prune'] > 0 || MailboxBackupPolicy::behind($service));

        return [$rows, $summary];
    }

    /** @return list<array{mailbox:string, now:string, status:string, note:string, pruning:int}> */
    private function reviewDomain(ProviderBinding $binding, int $target): array
    {
        try {
            $adapter = $this->providers->forInstance(ProviderInstance::query()->findOrFail($binding->provider_instance_id));
            if (! $adapter instanceof MailboxBackupRetention) {
                return [['mailbox' => MailDomains::nameOf($binding), 'now' => '—', 'status' => 'unreadable', 'note' => 'this mail server keeps no per-mailbox backups the platform can set', 'pruning' => 0]];
            }
            $listed = $adapter->mailboxBackupRetention($binding->ref());
        } catch (Throwable $e) { // a panel that did not answer, or a domain not proven ours: nothing is said about its mailboxes
            return [['mailbox' => MailDomains::nameOf($binding), 'now' => '—', 'status' => 'unreadable', 'note' => mb_substr($e->getMessage(), 0, 80), 'pruning' => 0]];
        }
        $stored = null;
        $out = [];
        foreach ($listed as $row) {
            $decision = MailboxBackupRetentionStep::decide($row, $target, true);
            $downgrade = $row['owned'] && $row['interval'] === MailboxBackupPolicy::INTERVAL && $row['copies'] > $target;
            $status = match (true) {
                $decision === 'foreign' => 'not_ours',
                $decision === 'unchanged' => 'ok',
                $downgrade => 'would_prune',
                default => 'would_set',
            };
            $pruning = 0;
            $note = '';
            if ($status === 'would_prune') {
                $stored ??= $this->storedBackups($adapter, $binding);
                $pruning = max(0, ($stored[mb_strtolower($row['address'])] ?? 0) - $target);
                $note = "will prune {$pruning} existing copies (--allow-prune)";
            }
            $out[] = ['mailbox' => $row['address'], 'now' => $row['interval'].'/'.$row['copies'], 'status' => $status, 'note' => $note, 'pruning' => $pruning];
        }

        return $out;
    }

    /** @return array<string,int> address → backups the panel holds now */
    private function storedBackups(object $adapter, ProviderBinding $binding): array
    {
        if (! $adapter instanceof MailToolsProvider) {
            return [];
        }
        $counts = [];
        foreach ($adapter->listMailboxBackups($binding->ref()) as $backup) {
            $address = mb_strtolower((string) ($backup['mailbox'] ?? ''));
            $counts[$address] = ($counts[$address] ?? 0) + 1;
        }

        return $counts;
    }
}
