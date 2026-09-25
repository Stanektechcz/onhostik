<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\URL;
use Onhost\Domain\Billing\ChargebackAnalyst;
use Onhost\Domain\Billing\DunningService;
use Onhost\Domain\Billing\MeteringService;
use Onhost\Domain\Billing\RatingService;
use Onhost\Domain\Billing\SubscriptionService;
use Onhost\Domain\Compliance\ComplianceService;
use Onhost\Domain\Dns\BlocklistCheck;
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Dns\PublicDnsCheck;
use Onhost\Domain\Domains\DomainRenewalScheduler;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Domains\RegistrarConnectionService;
use Onhost\Domain\Domains\RegistrarCreditMonitor;
use Onhost\Domain\Domains\RegistrarPollWorker;
use Onhost\Domain\Domains\RegistrarPriceScraper;
use Onhost\Domain\Domains\RegistrarPricing;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Incidents\MaintenanceService;
use Onhost\Domain\Incidents\OnCallRota;
use Onhost\Domain\Incidents\OnCallService;
use Onhost\Domain\Incidents\OrganizationStatusService;
use Onhost\Domain\Incidents\SlaService;
use Onhost\Domain\Integrations\DiscordService;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Loyalty\MissionService;
use Onhost\Domain\Marketplace\MarketplaceService;
use Onhost\Domain\Notifications\DigestService;
use Onhost\Domain\Notifications\MailHealth;
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Domain\Orders\CommerceHousekeeping;
use Onhost\Domain\Orders\OrderSettlement;
use Onhost\Domain\Organizations\AccessExpiry;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Partners\PartnerService;
use Onhost\Domain\Payments\BankStatementImporter;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\CapacityForecast;
use Onhost\Domain\Provisioning\CapacityPlanner;
use Onhost\Domain\Provisioning\FreezeSwitch;
use Onhost\Domain\Provisioning\GameTemplates;
use Onhost\Domain\Provisioning\IntegrationHealthProbe;
use Onhost\Domain\Provisioning\Ipam\IpamService;
use Onhost\Domain\Provisioning\Jobs\QueueHeartbeat;
use Onhost\Domain\Provisioning\LoadShedding;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\NodePrerequisites;
use Onhost\Domain\Provisioning\NodeQualification;
use Onhost\Domain\Provisioning\NodeSampler;
use Onhost\Domain\Provisioning\NodeUsageSync;
use Onhost\Domain\Provisioning\OperationLatency;
use Onhost\Domain\Provisioning\OperationsBoard;
use Onhost\Domain\Provisioning\OperationSecrets;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\PanelVersionGate;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\QueueScaler;
use Onhost\Domain\Provisioning\Reconciler;
use Onhost\Domain\Provisioning\Scheduling\NodeRebalancer;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\DelegatedAccessReview;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\RescueMode;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceIdentityCheck;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\SshKeyLedger;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Domain\Services\Web\CdnService;
use Onhost\Domain\Services\Web\CertificateAutoIssuer;
use Onhost\Domain\Services\Web\CertificateService;
use Onhost\Domain\Services\Web\CertificateWatch;
use Onhost\Domain\Services\Web\RestoreTest;
use Onhost\Domain\Services\Web\SiteIntegrityCheck;
use Onhost\Domain\Services\Web\UptimeMonitor;
use Onhost\Domain\Services\Web\WebFileStore;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Support\WorkOfferService;
use Onhost\Domain\Tax\CnbRates;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\WalletForecast;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Files\FileStore;
use Onhost\Platform\Ops\PlatformBackup;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Platform\Redaction\Redactor;
use Onhost\Platform\Secrets\SecretStore;
use Onhost\Providers\Contracts\DnsProvider;

/*
|--------------------------------------------------------------------------
| Operational commands (blueprint §5.2, §46.3, §45.3). Every command is idempotent
| and safe to re-run; the scheduler below wires them with overlap protection.
|--------------------------------------------------------------------------
*/

Artisan::command('onhost:outbox:relay {--limit=500}', function (OutboxPublisher $outbox) {
    $this->info('delivered: '.$outbox->relayPending((int) $this->option('limit')));
})->purpose('Deliver pending outbox events (webhooks, notifications, fulfilment)');

/*
 * What an operation must forget (OperationSecrets). A finished operation drops the passwords it carried at once; this
 * pass takes what is left: the generated password whose half hour of being shown is over, the input of a failed run
 * past its retry days — and every row written before this rule, in batches until none is left. Nothing is sent
 * anywhere; it cannot be switched off, because a switch that keeps passwords is not a feature.
 */
Artisan::command('onhost:operations:forget-secrets {--limit=500}', function () {
    $stats = OperationSecrets::sweep((int) $this->option('limit'));
    $this->table(['scrubbed'], [$stats]);
})->purpose('Remove passwords and keys from finished operations');

Artisan::command('onhost:provisioning:tick {--limit=200}', function (OperationService $operations, AutomationLedger $ledger) {
    $recovered = $operations->recoverStuck();
    $dispatched = $operations->dispatchDue((int) $this->option('limit'));
    $ledger->record('provisioning.tick', ['recovered' => $recovered, 'dispatched' => $dispatched]);
    $this->info('recovered stuck: '.$recovered.' dispatched: '.$dispatched);
})->purpose('Dispatch due operations and recover stuck ones');

Artisan::command('onhost:provisioning:reconcile {tier=normal} {--limit=200}', function (Reconciler $reconciler) {
    $this->table(['checked', 'drifted', 'missing', 'repaired', 'errors'], [$reconciler->run((string) $this->argument('tier'), (int) $this->option('limit'))]);
})->purpose('Reconcile desired vs actual state for services (tier: normal|critical)');

Artisan::command('onhost:provisioning:freeze {reason}', function (FreezeSwitch $freeze) {
    $freeze->freeze((string) $this->argument('reason'), 'cli:'.(get_current_user() ?: 'operator'));
    $this->warn('Provisioning frozen: '.$this->argument('reason'));
})->purpose('Freeze new provisioning during an incident');

Artisan::command('onhost:provisioning:thaw', function (FreezeSwitch $freeze) {
    $freeze->thaw();
    $this->info('Provisioning resumed');
})->purpose('Lift the provisioning freeze');

Artisan::command('onhost:integrations:health', function (IntegrationHealthProbe $probe, AutomationLedger $ledger, OutboxPublisher $outbox) {
    $result = $probe->run();
    // the queue worker is an integration too (audit §5g-6): a stale heartbeat is one internal alert per half hour, not silence
    $worker = $ledger->liveness()['worker'];
    if (! $worker['alive'] && Cache::add('onhost:queue:stalled-alert', 1, 1800)) {
        $outbox->publish(GenericEvent::of('platform.queue.stalled', 'platform', 'queue', ['driver' => $worker['driver'], 'last_seen_at' => $worker['at']]));
    }
    $result['worker'] = $worker['alive'] ? 'alive' : 'stalled';
    // operations due for longer than the age limit pile up (audit §5h-5): one internal alert per half hour, a gauge for autoscaling
    $backlog = $ledger->backlog();
    if ($backlog['alert'] && Cache::add('onhost:queue:backlog-alert', 1, 1800)) {
        $outbox->publish(GenericEvent::of('platform.queue.backlog', 'platform', 'queue', ['stale' => $backlog['stale'], 'threshold' => $backlog['threshold'], 'age_minutes' => $backlog['age_minutes'], 'by_queue' => $backlog['by_queue']]));
    }
    $result['backlog'] = $backlog['stale'];
    // overviews give way while operations pile up (H139): requests only read this verdict, they never measure
    $shedding = app(LoadShedding::class);
    $turn = $shedding->observe($backlog);
    if ($turn !== null && $shedding->mode() === 'auto') {
        $outbox->publish(GenericEvent::of('platform.load_shedding.'.$turn, 'platform', 'load', ['stale' => $backlog['stale'], 'threshold' => $backlog['threshold'], 'age_minutes' => $backlog['age_minutes']]));
    }
    $ledger->record('integrations.health', $result);
    $this->table(['checked', 'up', 'down', 'worker', 'backlog'], [$result]);
})->purpose('Probe every provider instance and the queue worker heartbeat; record integration health');

Artisan::command('onhost:queue:scale {--apply : Start the missing helper workers} {--max= : Cap on helper workers}', function (QueueScaler $scaler) {
    $max = $this->option('max') !== null ? (int) $this->option('max') : null;
    $advice = $scaler->advise($max);
    $started = $this->option('apply') ? $scaler->apply($max) : 0;
    $this->table(['stale', 'threshold', 'desired', 'running', 'max', 'started'], [$advice + ['started' => $started]]);
})->purpose('Advise (or start, with --apply) helper queue workers from the operation backlog gauge');

Artisan::command('onhost:ipam:release-quarantine', function (IpamService $ipam) {
    $this->info('released: '.$ipam->releaseQuarantine());
})->purpose('Return quarantined IP addresses to the free pool after the cooling period');

Artisan::command('onhost:domains:renewals', function (DomainRenewalScheduler $scheduler) {
    $this->table(['scheduled', 'notices', 'started', 'retried', 'failed'], [$scheduler->tick()]);
})->purpose('Schedule domain renewals, send expiry notices and execute due renewals');

Artisan::command('onhost:registrar:poll', function (RegistrarPollWorker $worker) {
    try {
        $this->table(['received', 'processed', 'acked', 'dead'], [$worker->drain()]);
    } catch (Throwable $e) {
        // WAPI unreachable / credentials missing: the health probe raises integration.down; the poll simply retries next run.
        $this->warn('registrar unreachable: '.$e->getMessage());
        Log::warning('registrar:poll skipped', ['error' => $e instanceof ProviderException ? $e->errorCode->value : get_class($e), 'message' => $e->getMessage()]);
    }
})->purpose('Consume the registrar notification queue (poll-req/poll-ack)');

Artisan::command('onhost:registrar:reconcile', function (DomainService $domains) {
    $report = $domains->reconcile(CommandContext::system('registrar reconcile'));
    $this->table(['checked', 'updated', 'missing_remote', 'unknown_remote', 'expired'], [[$report['checked'], $report['updated'], count($report['missing_remote']), count($report['unknown_remote']), $report['expired']]]);
})->purpose('Compare local domains with the registrar listing and sweep expiries');

Artisan::command('onhost:registrar:credit', function (RegistrarCreditMonitor $monitor) {
    foreach ($monitor->sampleAll() as $snapshot) {
        $this->info(sprintf('%s: balance %s %s, runway %d days%s', $snapshot->registrar_provider, number_format($snapshot->balance_minor / 100, 2), $snapshot->currency, $snapshot->runway_days, $snapshot->below_minimum ? ' — BELOW MINIMUM' : ''));
    }
})->purpose('Sample the credit of every registrar and alert on a short runway');

Artisan::command('onhost:registrar:costs', function (RegistrarPricing $pricing) {
    $report = $pricing->refresh(null, null, CommandContext::system('registrar cost refresh'));
    foreach ($report['registrars'] as $provider => $row) {
        $this->line(sprintf('%s: %d TLDs%s', $provider, $row['tlds'], $row['error'] ? ' — '.$row['error'] : ''));
    }
})->purpose('Refresh registrar cost prices (the cheapest registrar wins new registrations)');

Artisan::command('onhost:registrar:scrape-prices {--registrar= : one registrar key} {--file=* : registrar=path to a saved HTML page instead of a live fetch}', function (RegistrarPriceScraper $scraper) {
    $html = [];
    foreach ((array) $this->option('file') as $pair) {
        [$key, $path] = array_pad(explode('=', (string) $pair, 2), 2, null);
        if ($key === null || $path === null || ! is_file($path)) {
            $this->error("--file expects registrar=path, got {$pair}");

            return 1;
        }
        $html[$key] = (string) file_get_contents($path);
    }
    $report = $scraper->scrape($this->option('registrar') ?: null, $html, CommandContext::system('registrar price scrape'));
    foreach ($report['registrars'] as $provider => $row) {
        $this->line(sprintf('%s: %d TLDs on the page, %d imported, %d kept (manual/api)%s', $provider, $row['tlds'], $row['imported'], $row['skipped'], $row['error'] ? ' — '.$row['error'] : ''));
    }

    return 0;
})->purpose('Import the public domain price lists of the registrars into the price book (retail = upper bound of cost)');

Artisan::command('onhost:marketplace:renew', function (MarketplaceService $marketplace) {
    $this->table(['renewed', 'failed', 'ended'], [$marketplace->renewDue()]);
})->purpose('Renew the monthly marketplace listings that are due (audit §5k-2)');

Artisan::command('onhost:status:verify-domains', function (OrganizationStatusService $status) {
    $this->info('verified: '.$status->verifyPending());
})->purpose('Verify the CNAME of the customers\' own status hosts (audit §5k-3)');

Artisan::command('onhost:provisioning:sample-nodes', function (NodeSampler $sampler) {
    $this->info('sampled: '.$sampler->snapshot());
})->purpose('Snapshot every node\'s usage into the hourly samples behind the trend (audit §5k-7)');

Artisan::command('onhost:marketplace:sla', function (MarketplaceService $marketplace) {
    $this->table(['warned', 'offered'], [$marketplace->sweepOverdue()]);
    $this->info('running periods reminded: '.$marketplace->sweepPeriods()['warned']); // §5n-2
})->purpose('Warn about overdue marketplace deliveries, offer refunds after the grace, remind partners of unserved periods (audit §5l-2, §5n-2)');

Artisan::command('onhost:loyalty:campaigns', function (MissionService $missions) {
    $this->table(['campaigns', 'organizations'], [$missions->announceCampaigns()]);
})->purpose('Announce mission campaigns whose window opened (audit §5l-5)');

Artisan::command('onhost:marketplace:auto-accept', function (MarketplaceService $marketplace) {
    $this->info('accepted after the window: '.$marketplace->autoAccept());
})->purpose('Count deliveries nobody answered within the window as accepted (audit §5j-1)');

Artisan::command('onhost:loyalty:missions', function (MissionService $missions) {
    $this->table(['organizations', 'awards', 'badges', 'streaks'], [$missions->evaluateAll()]);
})->purpose('Award completed monthly missions and detect on-time streaks (audit §5j-3)');

Artisan::command('onhost:chargebacks:analyse {--days=}', function (ChargebackAnalyst $analyst) {
    $opened = $analyst->run($this->option('days') !== null ? (int) $this->option('days') : null);
    $this->info('incidents opened: '.($opened === [] ? '0' : implode(', ', $opened)));
})->purpose('Cluster chargeback reasons per node and product; open an incident above the threshold (audit §5j-6)');

Artisan::command('onhost:rebalance:plan {--role=} {--basis=usage} {--mail : Send the plan to operations as an internal notification}', function (NodeRebalancer $rebalancer, OutboxPublisher $outbox) {
    $plan = $rebalancer->plan($this->option('role') ?: null, (string) $this->option('basis'));
    $this->table(['node', 'role', 'region', 'load %'], array_map(fn ($n) => [$n['name'], $n['role'], $n['region'], $n['load_pct']], $plan['nodes']));
    foreach ($plan['moves'] as $move) {
        $this->line(sprintf('  %s (%d MB) %s → %s', $move['label'], $move['ram_mb'], $move['from'], $move['to']));
    }
    if ($this->option('mail')) {
        $outbox->publish(GenericEvent::of('rebalance.plan', 'platform', 'rebalance', ['basis' => $plan['basis'], 'moves' => count($plan['moves']), 'hot' => array_values(array_map(fn ($n) => $n['name'].' '.$n['load_before_pct'].' %', array_filter($plan['nodes'], fn ($n) => ($n['load_before'] ?? $n['load']) > $plan['thresholds']['high']))), 'summary' => array_map(fn ($m) => $m['label'].': '.$m['from'].' → '.$m['to'], array_slice($plan['moves'], 0, 10))]));
    }
    $this->info('moves proposed: '.count($plan['moves']).' (basis '.$plan['basis'].')');
})->purpose('Dry-run rebalancing plan from the measured load (audit §5j-4); --mail sends it to operations');

Artisan::command('onhost:billing:expire-holds', function (WalletService $wallets) {
    $this->info('expired holds: '.$wallets->expireHolds());
})->purpose('Release expired wallet holds');

Artisan::command('onhost:billing:overdue', function (InvoiceService $invoices) {
    $this->info('marked overdue: '.$invoices->overdueSweep());
})->purpose('Mark unpaid invoices past due date as overdue (dunning input)');

Artisan::command('onhost:billing:renewals', function (SubscriptionService $subscriptions) {
    $this->table(['renewed', 'invoiced', 'failed', 'rolled', 'cancelled'], [$subscriptions->tick()]);
})->purpose('Renew due service subscriptions (wallet or postpaid invoice) and end cancelled ones');

Artisan::command('onhost:billing:meter', function (MeteringService $metering) {
    $this->table(['events', 'services'], [$metering->collect()]);
})->purpose('Record hourly/daily usage for metered services');

Artisan::command('onhost:billing:rate {--limit=500}', function (RatingService $rating) {
    $this->table(['rated', 'charged', 'deferred', 'capped'], [$rating->rate((int) $this->option('limit'))]);
})->purpose('Price recorded usage with the monthly cap and charge the wallet');

Artisan::command('onhost:billing:dunning', function (DunningService $dunning) {
    $this->table(['cases', 'notices', 'suspended', 'scheduled', 'terminated', 'resolved'], [$dunning->tick()]);
})->purpose('Advance dunning cases: notices, grace, suspension, scheduled termination');

Artisan::command('onhost:mail:health', function (MailHealth $health) {
    $r = $health->observe();
    $this->table(['state', 'sent', 'errors', 'waiting', 'oldest_minutes', 'dead', 'turn'], [[$r['state'], $r['sent'], $r['errors'], $r['waiting'], $r['oldest_minutes'], $r['dead'], $r['turn'] ?? '—']]);
    if ($r['last_error'] !== null) {
        $this->warn('latest error: '.$r['last_error']);
    }

    return $r['state'] === 'ok' ? 0 : 1;
})->purpose('Does the platform\'s own mail still leave? Alerts once when it stops and once when it flows again');

Artisan::command('onhost:mail:send {--limit=100}', function (NotificationService $notifications) {
    $this->table(['sent', 'failed'], [$notifications->sendQueued((int) $this->option('limit'))]);
})->purpose('Deliver queued transactional mails');

Artisan::command('onhost:bank:sync {--from=} {--to=}', function (BankStatementImporter $importer) {
    if ((string) config('onhost.payments.bank.fio_token', '') === '') {
        $this->warn('ONHOST_BANK_FIO_TOKEN is not set — bank statements have to be recorded by hand (Nastavení → Bankovní platby).');

        return 0;
    }
    $stats = $importer->syncFio(CommandContext::system('bank sync'), $this->option('from') ?: null, $this->option('to') ?: null);
    $this->table(['account', 'fetched', 'recorded', 'matched', 'skipped'], [[$stats['account'], $stats['fetched'], $stats['recorded'], $stats['matched'], $stats['skipped']]]);

    return 0;
})->purpose('Download new incoming transfers from the Fio bank API and settle the proformas / top-ups they pay');

Artisan::command('onhost:webhooks:retry', function (WebhookDispatcher $webhooks) {
    $this->table(['delivered', 'failed', 'dead'], [$webhooks->retryDue()]);
})->purpose('Retry pending/failed webhook deliveries with backoff');

Artisan::command('onhost:oncall:escalate', function (OnCallService $oncall, AutomationLedger $ledger) {
    $stats = $oncall->escalateDue();
    $ledger->record(OnCallService::RULE, $stats);
    $this->table(['escalated', 'exhausted'], [$stats]);
})->purpose('Re-page the on-call for alerts nobody acknowledged in time (audit §5q-1)');

Artisan::command('onhost:files:prune', function (FileStore $files, AutomationLedger $ledger) {
    $stats = $files->prune();
    $ledger->record('files.prune', $stats);
    $this->table(array_keys($stats), [$stats]);
})->purpose('Delete marketplace evidence past its retention and expired data exports (audit §5q-4)');

Artisan::command('onhost:files:scan', function (MarketplaceService $marketplace, AutomationLedger $ledger) {
    $stats = $marketplace->rescanEvidence();
    $ledger->record('files.scan', $stats);
    $this->table(array_keys($stats), [$stats]);
})->purpose('Scan evidence files again whose virus scan is missing or failed (audit §5r-4)');

Artisan::command('onhost:game:templates:verify', function (GameTemplates $templates, AutomationLedger $ledger) {
    $rows = [];
    foreach (ProviderInstance::query()->where('provider', 'pterodactyl')->where('state', 'active')->get() as $instance) {
        try {
            $r = $templates->verify($instance, CommandContext::system('cli:game:templates:verify'));
            $rows[] = [$r['instance'], $r['checked'], implode(', ', $r['missing']) ?: '—', $r['refreshed']];
        } catch (Throwable $e) {
            $rows[] = [$instance->key, 0, 'error: '.$e->getMessage(), 0];
        }
    }
    $rotation = $templates->operatorRotation(); // §5u-5: operator-held variables older than the rotation period
    if ($rotation['stale']) {
        $this->warn('Operator variables stored '.$rotation['days'].' days ago — rotate them.');
    }
    $services = $templates->auditServices(); // §5t-3: running servers whose customer input fails its rule
    $this->line('Servers checked: '.$services['checked'].' · need attention: '.$services['attention']);
    $ledger->record('game.templates.verify', ['servers_attention' => $services['attention'], 'instances' => count($rows), 'missing' => array_sum(array_map(fn ($r) => $r[2] === '—' || str_starts_with((string) $r[2], 'error') ? 0 : count(explode(', ', (string) $r[2])), $rows))]);
    $this->table(['instance', 'checked', 'missing', 'refreshed'], $rows);
})->purpose('Check the mapped game templates against the panel eggs and refresh their required variables (audit §5s)');

Artisan::command('onhost:game:operator-variable {env : Variable name, e.g. STEAM_USER} {--stdin : Read the value from standard input}', function (SecretStore $secrets) {
    $env = strtoupper(trim((string) $this->argument('env')));
    if (preg_match('/^[A-Z][A-Z0-9_]{1,63}$/', $env) !== 1) {
        $this->error('The variable name must look like STEAM_USER.');

        return 1;
    }
    $value = $this->option('stdin') ? trim((string) stream_get_contents(STDIN)) : (string) $this->secret("Value for {$env} (blank removes it)");
    $status = app(GameTemplates::class)->setOperatorVariable($env, $value, CommandContext::system('cli:game:operator-variable'));
    $this->info(($value === '' ? "Removed {$env}." : "Stored {$env}.").' Stored names: '.(implode(', ', $status['stored']) ?: '—'));
})->purpose('Store a read-only game template variable the operator holds (hidden prompt; audit §5s)');

Artisan::command('onhost:oncall:remind', function (OnCallRota $rota, AutomationLedger $ledger) {
    $sent = $ledger->enabled('oncall.remind') ? $rota->remindUpcoming() : 0;
    $ledger->record('oncall.remind', ['sent' => $sent]);
    $this->line("reminders: {$sent}");
})->purpose('Remind the next on-call an hour before the shift (audit §5t-4)');

Artisan::command('onhost:platform:backup', function (PlatformBackup $backup, AutomationLedger $ledger) {
    if (! $ledger->enabled('platform.backup')) {
        $this->warn('platform.backup is switched off');

        return 0;
    }
    try {
        $r = $backup->run();
    } catch (Throwable $e) {
        $ledger->record('platform.backup', [], $e->getMessage());
        $this->error('Backup failed: '.$e->getMessage());

        return 1;
    }
    $ledger->record('platform.backup', ['set' => $r['set'], 'bytes' => array_sum(array_column($r['files'], 'bytes')), 'pruned' => $r['pruned']]);
    $this->table(['file', 'bytes', 'sha256'], array_map(fn ($n, $f) => [$n, $f['bytes'], substr($f['sha256'], 0, 16).'…'], array_keys($r['files']), $r['files']));
    $this->info("Set {$r['set']} on disk ".config('onhost.platform_backup.disk')." ({$r['database']}); pruned {$r['pruned']}");

    return 0;
})->purpose('Back up the control plane database and private files to the backup disk (go-live checklist §1)');

Artisan::command('onhost:platform:backup:verify {set?}', function (PlatformBackup $backup, AutomationLedger $ledger) {
    $r = $backup->verify($this->argument('set'));
    $ledger->record('platform.backup.verify', ['set' => $r['set'], 'ok' => $r['ok']], $r['ok'] ? null : implode('; ', $r['problems']));
    foreach ($r['problems'] as $p) {
        $this->error($p);
    }
    $this->line(($r['ok'] ? 'OK ' : 'FAILED ').($r['set'] ?? '—').($r['created_at'] ? ' created '.$r['created_at'] : ''));

    return $r['ok'] ? 0 : 1;
})->purpose('Read the newest platform backup back and check it restores (sizes, checksums, dump integrity)');

Artisan::command('onhost:support:sla', function (TicketService $tickets, WorkOfferService $offers) {
    $this->table(['breached', 'closed', 'offers_expired'], [$tickets->tick() + ['offers_expired' => $offers->expire()]]);
})->purpose('Detect support SLA breaches (escalate) and auto-close resolved tickets');

Artisan::command('onhost:ledger:verify', function (LedgerService $ledger) {
    $result = $ledger->verifyInvariant();
    $ok = ($result['ok'] ?? ($result['balanced'] ?? false)) === true;
    $this->{$ok ? 'info' : 'error'}(json_encode($result));

    return $ok ? self::SUCCESS : self::FAILURE;
})->purpose('Verify the double-entry invariant (sum of debits == sum of credits)');

/*
|--------------------------------------------------------------------------
| Schedule (run `php artisan schedule:work` or a systemd timer for schedule:run)
|--------------------------------------------------------------------------
*/

Schedule::command('onhost:outbox:relay')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:mail:send')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:mail:health')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:webhooks:retry')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:oncall:escalate')->everyMinute()->withoutOverlapping()->onOneServer(); // unacknowledged pages escalate (audit §5q-1)
Schedule::command('onhost:files:prune')->dailyAt('04:25')->onOneServer(); // file retention (audit §5q-4)
Schedule::command('onhost:platform:backup')->dailyAt('02:15')->onOneServer(); // control plane backup (go-live §1)
Schedule::command('onhost:platform:backup:verify')->dailyAt('03:15')->onOneServer();
Schedule::command('onhost:oncall:remind')->everyFiveMinutes()->withoutOverlapping()->onOneServer(); // shift reminders (audit §5t-4)
Schedule::command('onhost:game:templates:verify')->dailyAt('05:10')->onOneServer(); // template drift against the panel (audit §5s)
Schedule::command('onhost:files:scan')->everyTenMinutes()->withoutOverlapping()->onOneServer(); // retry of the virus scan (audit §5r-4)
Schedule::command('onhost:support:sla')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:provisioning:tick')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:operations:forget-secrets')->everyTenMinutes()->withoutOverlapping()->onOneServer(); // finished operations stop holding the passwords they carried
Schedule::job(new QueueHeartbeat)->everyMinute()->onOneServer(); // the worker's proof of life (audit §5g-6)
Schedule::command('onhost:queue:scale --apply')->everyFiveMinutes()->onOneServer()->when(fn () => (bool) config('onhost.provisioning.autoscale.enabled', false)); // helper workers on the backlog gauge (audit §5i-3)
Schedule::command('onhost:integrations:health')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:provisioning:reconcile critical')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:provisioning:reconcile normal')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:registrar:poll')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:domains:renewals')->hourly()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:registrar:credit')->hourly()->onOneServer();
Schedule::command('onhost:registrar:costs')->dailyAt('03:40')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:registrar:scrape-prices')->weeklyOn(1, '04:10')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:registrar:reconcile')->dailyAt(sprintf('%02d:00', (int) config('onhost.provisioning.reconcile.domains_daily_hour', 4)))->withoutOverlapping()->onOneServer();
Schedule::command('onhost:bank:sync')->everyFiveMinutes()->withoutOverlapping()->onOneServer()->when(fn () => (string) config('onhost.payments.bank.fio_token', '') !== '');
Schedule::command('onhost:billing:expire-holds')->everyTenMinutes()->onOneServer();
Schedule::command('onhost:orders:settle')->everyTenMinutes()->withoutOverlapping()->onOneServer();
// a service left in SUSPENDING/RESUMING/RESIZING by a step the panel refused accepts nothing until it is put back; nothing is sent to a panel
Schedule::command('onhost:services:release-stranded --apply --minutes=30')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:services:rescue-expire')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:services:restore-test')->dailyAt('04:20')->withoutOverlapping()->onOneServer(); // the cadence is per service; this only asks who is owed one
Schedule::command('onhost:billing:meter')->hourlyAt(2)->withoutOverlapping()->onOneServer();
Schedule::command('onhost:billing:rate')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:billing:renewals')->hourlyAt(20)->withoutOverlapping()->onOneServer();
Schedule::command('onhost:billing:dunning')->dailyAt('06:00')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:billing:runway')->dailyAt('07:30')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:dns:platform-sync')->hourlyAt(25)->withoutOverlapping()->onOneServer();
Schedule::command('onhost:dns:drift')->dailyAt('03:40')->withoutOverlapping()->onOneServer(); // what the DNS providers serve vs what we hold
Schedule::command('onhost:fx:sync')->timezone('Europe/Prague')->weekdays()->at('14:40')->withoutOverlapping()->onOneServer(); // the national bank publishes at 14:30
Schedule::command('onhost:fx:sync')->timezone('Europe/Prague')->dailyAt('06:10')->withoutOverlapping()->onOneServer(); // and a morning pass: a late list, documents that wait
Schedule::command('onhost:certificates:issue-pending')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:registrars:sync-connections')->hourlyAt(40)->withoutOverlapping()->onOneServer();
Schedule::command('onhost:billing:overdue')->dailyAt('01:15')->onOneServer();
Schedule::command('onhost:ipam:release-quarantine')->dailyAt('03:30')->onOneServer();
Schedule::command('onhost:rebalance:plan --basis=trend --mail')->dailyAt('03:35')->onOneServer(); // nightly dry-run plan for operations from the 7-day trend (audit §5j-4, §5k-7)
Schedule::command('onhost:provisioning:sample-nodes')->hourly()->onOneServer(); // the hourly samples behind the trend and the measured power (audit §5k-6, §5k-7)
Schedule::command('onhost:status:verify-domains')->hourly()->onOneServer(); // the customers' own status hosts (audit §5k-3)
Schedule::command('onhost:marketplace:renew')->dailyAt('05:00')->withoutOverlapping()->onOneServer(); // monthly marketplace listings (audit §5k-2)
Schedule::command('onhost:marketplace:sla')->dailyAt('05:10')->onOneServer(); // overdue deliveries (audit §5l-2)
Schedule::command('onhost:loyalty:campaigns')->dailyAt('05:20')->onOneServer(); // campaign announcements (audit §5l-5)
Schedule::command('onhost:marketplace:auto-accept')->dailyAt('05:05')->onOneServer(); // deliveries nobody answered (audit §5j-1)
Schedule::command('onhost:loyalty:missions')->dailyAt('05:15')->withoutOverlapping()->onOneServer(); // missions and streaks (audit §5j-3)
Schedule::command('onhost:chargebacks:analyse')->dailyAt('05:25')->onOneServer(); // chargeback clusters → incidents (audit §5j-6)
Schedule::command('onhost:commerce:prune')->dailyAt('04:20')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:services:usage-watch')->hourlyAt(50)->withoutOverlapping()->onOneServer();
Schedule::command('onhost:billing:renewal-guard')->dailyAt('07:35')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:provisioning:board')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:digest:weekly')->weeklyOn(1, '07:00')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:nodes:check')->dailyAt('05:20')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:nodes:usage')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:sites:integrity')->dailyAt('04:40')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:dns:check')->dailyAt('05:10')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:mail:blocklist')->dailyAt('05:25')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:digest:staff-daily')->dailyAt('07:15')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:ledger:verify')->dailyAt('05:00')->onOneServer();

// ── status / SLA / compliance (blueprint §66, §73, §23) ──────────────────────
Artisan::command('onhost:sla:evaluate', function (SlaService $sla) {
    $result = $sla->evaluate();
    $this->info(json_encode($result));
})->purpose('Probe quorum, SLO windows, burn-rate alerts and auto-settlement of probe incidents');

Artisan::command('onhost:maintenance:tick', function (MaintenanceService $maintenance) {
    $this->table(['started', 'completed', 'unapproved'], [$maintenance->tick()]);
})->purpose('Start/complete maintenance windows and flag unapproved ones');

Artisan::command('onhost:compliance:timers', function (ComplianceService $compliance) {
    $this->table(['warned', 'missed'], [$compliance->tickTimers()]);
})->purpose('Warn at 75 % of regulatory deadlines (NIS2/GDPR/DSA/Data Act) and mark missed ones');

Artisan::command('onhost:compliance:data-requests', function (ComplianceService $compliance) {
    $this->table(['exported', 'deleted', 'expired', 'switching'], [$compliance->processDataRequests()]);
})->purpose('Build GDPR/Data Act exports, execute deletions, expire downloads');

Schedule::command('onhost:sla:evaluate')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:maintenance:tick')->everyFiveMinutes()->onOneServer();
Schedule::command('onhost:compliance:timers')->hourlyAt(7)->onOneServer();
Schedule::command('onhost:compliance:data-requests')->everyThirtyMinutes()->withoutOverlapping()->onOneServer();

// ── partner programme ────────────────────────────────────────────────────────
Artisan::command('onhost:partners:tiers', function (PartnerService $partners) {
    $this->info('recomputed: '.$partners->recomputeAllTiers());
})->purpose('Recompute partner tiers from the trailing three months of paid volume');

Artisan::command('onhost:partners:apply-models', function (PartnerService $partners) {
    $this->info('changes applied: '.$partners->applyPendingChanges());
})->purpose('Apply approved contract changes (model, rate lock, payout terms, white-label scope) whose day came (audit §5m-1, §5n-1)');

Artisan::command('onhost:partners:auto-payouts', function (PartnerService $partners) {
    $stats = $partners->autoPayouts();
    $this->info("payouts requested: {$stats['requested']} · skipped: {$stats['skipped']}");
})->purpose('Request the payable balance for partners on monthly or quarterly payout terms (audit §5n-1)');

Artisan::command('onhost:provisioning:capacity-forecast', function (CapacityForecast $forecast) {
    $this->table(['role', 'region', 'sellable MB', 'demand MB', 'growth MB/day', 'days left', 'low'], array_map(fn ($p) => [$p['role'], $p['region'] ?? '-', $p['sellable_mb'], max($p['sold_mb'], $p['used_mb']), $p['growth_mb_per_day'], $p['days_left'] ?? '∞', $p['low'] ? 'yes' : 'no'], $forecast->forecast()));
    $warned = $forecast->warn();
    $this->info('pools warned: '.($warned === [] ? '0' : implode(', ', $warned)));
    $plan = app(CapacityPlanner::class)->run(); // §5n-7: a short pool gets a capacity request; the rule may order it
    $this->info('requests proposed: '.count($plan['proposed']).' · ordered: '.count($plan['ordered']).' · delivered: '.count($plan['delivered']));
})->purpose('Days left before a role/region pool is sold out, from the 7-day trend; capacity requests for short pools (audit §5m-7, §5n-7)');

Artisan::command('onhost:partners:verify-domains', function (PartnerService $partners) {
    $this->info('verified: '.$partners->verifyWhitelabelDomains());
})->purpose('Verify white-label panel domains by CNAME');

Schedule::command('onhost:partners:tiers')->monthlyOn(1, '02:30')->onOneServer();
Schedule::command('onhost:partners:apply-models')->dailyAt('02:35')->onOneServer(); // approved contract changes take effect on the 1st (audit §5m-1, §5n-1)
Schedule::command('onhost:partners:auto-payouts')->monthlyOn(1, '06:00')->onOneServer(); // monthly / quarterly payout terms (audit §5n-1)
Schedule::command('onhost:provisioning:capacity-forecast')->dailyAt('03:45')->onOneServer(); // pools running short reach operations (audit §5m-7)
Schedule::command('onhost:partners:verify-domains')->hourly()->onOneServer();

// ── local development: a signed one-time sign-in link for a test account (never registered outside `local`) ──
Artisan::command('onhost:dev:login-link {email} {--next=/panel} {--minutes=10}', function () {
    if (! app()->environment('local')) {
        $this->error('Only available in the local environment.');

        return 1;
    }
    $user = User::query()->where('email', (string) $this->argument('email'))->first();
    if ($user === null) {
        $this->error('No such user.');

        return 1;
    }
    $this->line(URL::temporarySignedRoute('dev.login', now()->addMinutes((int) $this->option('minutes')), ['user' => $user->id, 'next' => (string) $this->option('next')]));

    return 0;
})->purpose('Print a short-lived signed sign-in link for a local test account');

// ── web toolkit: uptime monitoring, scheduled backups, certificates, CDN, staged files ──────────────
Artisan::command('onhost:monitoring:check {--limit=200}', function (UptimeMonitor $monitor) {
    $this->info('checked: '.$monitor->checkDue((int) $this->option('limit')));
})->purpose('Probe due uptime monitors; open and close site-down incidents');

Artisan::command('onhost:monitoring:prune', function (UptimeMonitor $monitor) {
    $this->info('pruned samples: '.$monitor->prune());
})->purpose('Drop uptime samples older than the retention window');

/*
 * One file that says how this installation stands — for whoever has to judge it without being able to log in to the
 * panels: the doctor's findings that are not OK, what every panel answered to the read-only probes (SelfProbing), how
 * long actions take, whether four eyes are in effect. `--check` asks the panels first. Nothing in it is a secret: keys
 * of instances, names of fields, counts and verdicts — and it still goes through the redactor before it is written.
 */
Artisan::command('onhost:staging:report {--check : ask every panel now (onhost:nodes:check) before reporting} {--path= : where to write it; default storage/app/onhost-staging-report.json}', function (NodePrerequisites $prerequisites) {
    if ($this->option('check')) {
        $prerequisites->checkAll();
    }
    Artisan::call('onhost:doctor', ['--json' => true]);
    $doctor = json_decode(Artisan::output(), true) ?: [];
    $instances = ProviderInstance::query()->orderBy('key')->get()->map(fn (ProviderInstance $i) => [
        'key' => $i->key, 'provider' => $i->provider, 'state' => $i->state, 'vendor_version' => $i->vendor_version,
        'prereqs' => array_intersect_key((array) data_get($i->capabilities, 'prereqs', []), array_flip(['checked_at', 'api', 'version', 'php_versions', 'cron_api', 'backup_api', 'datalog_api', 'jobqueue', 'mod_proxy', 'client_api', 'probes', 'warnings'])),
    ])->all();
    $report = app(Redactor::class)->redact([
        'generated_at' => now()->toIso8601String(), 'environment' => app()->environment(), 'app_version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: null,
        'doctor' => ['fail' => $doctor['fail'] ?? null, 'warn' => $doctor['warn'] ?? null, 'not_ok' => array_values(array_filter((array) ($doctor['checks'] ?? []), fn ($c) => ($c['status'] ?? '') !== 'OK'))],
        'instances' => $instances,
        'latency' => ['target_s' => OperationLatency::targetSeconds(), 'rows' => array_slice(app(OperationLatency::class)->summary(24 * 7), 0, 25)],
        'four_eyes' => ['enabled' => ApprovalService::enabled(), 'deciders' => ApprovalService::deciders()->count()],
        'operations' => ['holding_secrets' => Operation::query()->whereNull('secrets_scrubbed_at')->whereIn('state', [Operation::SUCCEEDED, Operation::CANCELLED])->count(), 'failed_24h' => Operation::query()->where('state', Operation::FAILED)->where('finished_at', '>=', now()->subDay())->count()],
    ]);
    $path = (string) ($this->option('path') ?: storage_path('app/onhost-staging-report.json'));
    file_put_contents($path, (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $this->info("written: {$path}");
    $this->line(sprintf('doctor: %s FAIL · %s WARN · %d panel instance(s) · %d slow action(s)', $report['doctor']['fail'] ?? '?', $report['doctor']['warn'] ?? '?', count($instances), count(array_filter($report['latency']['rows'], fn ($r) => $r['slow'] ?? false))));
})->purpose('Write one redacted report of how this installation stands: doctor findings, what the panels really answer, latency, four eyes');

Artisan::command('onhost:nodes:usage {--limit=200}', function (NodeUsageSync $usage, AutomationLedger $ledger) {
    if ($ledger->off('nodes.usage')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $usage->run((int) $this->option('limit'));
    $ledger->record('nodes.usage', $result);
    $this->table(['checked', 'updated', 'low', 'errors'], [$result]);
})->purpose('Ask every web node how full its disk is, so the placement rule that keeps a shared node from filling up has a number to work with');

Artisan::command('onhost:sites:integrity {--limit=200}', function (SiteIntegrityCheck $sites, AutomationLedger $ledger) {
    if ($ledger->off('sites.integrity')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $sites->run((int) $this->option('limit'));
    $ledger->record('sites.integrity', $result);
    $this->table(['checked', 'suspicious', 'skipped', 'errors'], [$result]);
})->purpose('Look at every site for the marks a compromise leaves (code in upload folders, web-shell fingerprints) and tell the operators; it never acts on its own');

Artisan::command('onhost:dns:check {--limit=200}', function (PublicDnsCheck $check, AutomationLedger $ledger) {
    if ($ledger->off('dns.check')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $check->run((int) $this->option('limit'));
    $ledger->record('dns.check', $result);
    $this->table(['checked', 'problems', 'repaired', 'told', 'errors'], [$result]);
})->purpose('Compare what public DNS answers for every customer domain with what the platform published for it: repair our own zones, tell the customer about the DNS they hold elsewhere');

Artisan::command('onhost:mail:blocklist {--limit=200}', function (BlocklistCheck $lists, AutomationLedger $ledger) {
    if ($ledger->off('mail.blocklist')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $lists->run((int) $this->option('limit'));
    $ledger->record('mail.blocklist', $result);
    $this->table(['checked', 'listed', 'told', 'errors'], [$result]);
})->purpose('Ask the mail blocklists whether the address a node sends from is listed: one spammer on a shared node makes every other customer on it bounce');

Artisan::command('onhost:nodes:check', function (NodePrerequisites $prerequisites, AutomationLedger $ledger) {
    if ($ledger->off('nodes.check')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $prerequisites->checkAll();
    $ledger->record('nodes.check', $result);
    $this->table(['checked', 'ok', 'warnings'], [$result]);
})->purpose('Record what every provider instance can deliver (API, PHP versions, cron API, job queue, mod_proxy) so features are offered only where the node supports them');

Artisan::command('onhost:digest:weekly', function (DigestService $digests, AutomationLedger $ledger) {
    if ($ledger->off('digest.weekly')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $digests->weekly();
    $ledger->record('digest.weekly', $result);
    $this->table(['organizations', 'sent', 'skipped'], [$result]);
})->purpose('Weekly customer digest: renewals and expiries ahead, credit, backups, monitors, plan usage');

Artisan::command('onhost:digest:staff-daily', function (DigestService $digests, AutomationLedger $ledger) {
    if ($ledger->off('digest.staff')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $digests->staffDaily();
    $ledger->record('digest.staff', ['recipients' => (int) $result['recipients'], 'lines' => count($result['lines'])]);
    $this->info($result['title'].' → '.$result['recipients'].' recipient(s)');
    foreach ($result['lines'] as $line) {
        $this->line('  · '.$line);
    }
})->purpose('Daily staff digest: stuck operations, failures, dunning, capacity, integrations');

Artisan::command('onhost:provisioning:board', function (OperationsBoard $board, AutomationLedger $ledger) {
    if ($ledger->off('operations.board')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $board->autoDrain();
    $ledger->record('operations.board', $result);
    $this->table(['checked', 'drained', 'resumed', 'probed'], [$result]);
})->purpose('Drain nodes whose operations keep failing on transient errors; resume the automatically drained ones once they succeed again');

Artisan::command('onhost:billing:renewal-guard {--days=7}', function (WalletForecast $forecast, AutomationLedger $ledger) {
    if ($ledger->off('renewal.guard')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $forecast->renewalGuard((int) $this->option('days'));
    $ledger->record('renewal.guard', $result);
    $this->table(['checked', 'underfunded', 'topped_up', 'notified'], [$result]);
})->purpose('Top up automatically (opt-in) or warn when the renewals of the coming days outrun the credit');

Artisan::command('onhost:services:usage-watch {--limit=200}', function (UsageWatch $watch, AutomationLedger $ledger) {
    if ($ledger->off('usage.watch')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $watch->run((int) $this->option('limit'));
    $ledger->record('usage.watch', $result);
    $this->table(['checked', 'warned', 'critical', 'upgraded', 'errors'], [$result]);
})->purpose('Measure active services against their plan; warn at 85 %, order the next plan at 95 % where the customer allowed it');

Artisan::command('onhost:commerce:prune {--quote-hours=24} {--cart-days=7}', function (CommerceHousekeeping $housekeeping, AutomationLedger $ledger) {
    if ($ledger->off('commerce.prune')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $housekeeping->prune((int) $this->option('quote-hours'), (int) $this->option('cart-days')) + ['unpaid_orders' => $housekeeping->expireUnpaid()];
    $ledger->record('commerce.prune', $result);
    $this->table(['quotes', 'carts', 'unpaid_orders'], [$result]);
})->purpose('Drop expired quotes no order references and stale open carts; cancel orders nobody paid in time');

Artisan::command('onhost:orders:settle {--limit=200}', function (OrderSettlement $settlement) {
    $this->info('settled orders: '.$settlement->sweep((int) $this->option('limit')));
})->purpose('Charge what an order delivered and give back what it did not (orders whose settlement was interrupted)');

Artisan::command('onhost:backups:run {--limit=100}', function (BackupScheduler $scheduler, AutomationLedger $ledger) {
    if ($ledger->off('backups.run')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $scheduler->tick((int) $this->option('limit'));
    $archives = app(FinalArchive::class);
    $result['archives_pruned'] = $archives->prune(); // final archives past their retention (audit §5aa)
    $verified = $archives->verifyStored((int) config('onhost.platform_backup.archive_verify_batch', 3)); // and the ones still held are re-hashed against their manifest
    $result['archives_verified'] = $verified['ok'];
    $result['archives_corrupt'] = $verified['failed'];
    $ledger->record('backups.run', $result);
    foreach ($verified['problems'] as $problem) {
        $this->warn('archive: '.$problem);
    }
    $this->table(['started', 'skipped', 'deleted', 'offsite', 'errors', 'archives', 'verified', 'corrupt'], [$result]);
})->purpose('Start scheduled backups, apply retention and generation caps, copy off-site');

/*
 * Servers and managed databases were sold with backups nobody took (TASK-0019). Starting them on existing services is
 * the owner's decision, so the rule `backups.compute` is off until staff switch it on; this lists, read-only, whom it
 * would start backing up and whether their Proxmox instance has a `backup_storage` to put the backups on.
 */
Artisan::command('onhost:backups:compute-plan {--limit=500}', function (BackupScheduler $scheduler, AutomationLedger $ledger) {
    $rows = $scheduler->computePlan(max(1, (int) $this->option('limit')));
    $this->table(['service', 'family', 'plan', 'frequency', 'days', 'generations', 'backup storage'], array_map(fn (array $r) => array_values($r), $rows));
    $this->info(sprintf('%d service(s) would be backed up · rule %s: %s · nothing was changed', count($rows), BackupScheduler::COMPUTE_RULE, $ledger->enabled(BackupScheduler::COMPUTE_RULE) ? 'on' : 'off'));
})->purpose('Dry run: servers and managed databases the backups.compute rule would start backing up (writes nothing)');

/*
 * Services stranded in a transient state (SUSPENDING, RESUMING, RESIZING) with no operation left to finish it. Until
 * 2026-09-19 a suspend or a resume the panel refused could not return the service to where it really was — the state
 * machine had no way back — and nothing is accepted in a transient state. This lists them; `--apply` puts each back
 * (a suspend that never happened → ACTIVE, a resume that never happened → SUSPENDED with its reason and holds, a
 * resize → ACTIVE) through the ordinary audited settle. Nothing is sent to a panel.
 */
// A rescue session nobody closed is a server booted from somebody else's image for as long as nobody looks (H233).
Artisan::command('onhost:services:rescue-expire {--limit=50 : how many services to look at}', function (RescueMode $rescue) {
    $stats = $rescue->expire(max(1, (int) $this->option('limit')));
    foreach ($stats['errors'] as $problem) {
        $this->warn($problem);
    }
    $this->info(sprintf('rescue sessions past their window: %d · put back: %d · failed: %d', $stats['checked'], $stats['ended'], count($stats['errors'])));
})->purpose('End the rescue sessions whose window has passed and put the servers back');

/*
 * A node nobody has qualified sells nothing (H471). Without arguments this lists what is waiting and what each one
 * still fails on; `--accept` puts one into the offer, and only when every required point passes. `--exception` records
 * what is knowingly accepted anyway (H478) — it is written on the node, never hidden.
 */
Artisan::command('onhost:integrations:versions {--accept= : the key of the instance whose held version is accepted} {--reason= : what was checked and where; it stays on the record}', function (PanelVersionGate $gate) {
    $accept = (string) ($this->option('accept') ?? '');
    if ($accept !== '') {
        $instance = ProviderInstance::query()->platform()->where('key', $accept)->first();
        if ($instance === null) {
            $this->error("Instanci {$accept} neznám.");

            return 1;
        }
        try {
            $record = $gate->accept($instance, (string) ($this->option('reason') ?? ''), CommandContext::system('cli:integrations:versions'), 'cli:'.(getenv('SUDO_USER') ?: getenv('USER') ?: getenv('USERNAME') ?: 'unknown'));
        } catch (DomainError $e) {
            $this->error($e->getMessage());

            return 1;
        }
        $this->info("{$instance->key} {$record['version']}: přijato — nové objednávky tam znovu jdou.");

        return 0;
    }
    $rows = [];
    foreach (ProviderInstance::query()->platform()->whereIn('provider', PanelVersionGate::SELF_HOSTED)->where('state', '!=', 'disabled')->orderBy('provider')->orderBy('key')->get() as $instance) {
        $record = (array) ($instance->version_gate ?? []);
        $rows[] = [$instance->key, (string) ($instance->vendor_version ?? '—'), implode(', ', $gate->declaredFor($instance)) ?: '—', (string) ($record['state'] ?? 'unknown'), mb_substr((string) ($record['why'] ?? $record['reason'] ?? ''), 0, 90)];
    }
    $this->table(['instance', 'runs', 'adapter verified on', 'state', 'why / reason'], $rows);
    $held = array_filter($rows, fn (array $row) => $row[3] === 'held');
    $held === [] ? $this->info('Žádná verze nečeká na ověření.') : $this->warn(count($held).' instance nebere nové objednávky — po kontrole: --accept=<instance> --reason="…"');

    return 0;
})->purpose('Panel versions: what each panel runs, what its adapter was verified on, and accepting a held one (H530)');

Artisan::command('onhost:nodes:qualify {--accept= : the id or name of the node to put into the offer} {--exception= : what is knowingly accepted anyway, and why} {--synthetic= : make one throw-away resource on this node and remove it again (H479)}', function (NodeQualification $qualification) {
    $synthetic = (string) ($this->option('synthetic') ?? '');
    if ($synthetic !== '') {
        $node = Node::query()->find($synthetic) ?? Node::query()->where('name', $synthetic)->first();
        if ($node === null) {
            $this->error("Uzel {$synthetic} neznám.");

            return 1;
        }
        $this->line("Zakládám a zase odstraňuji zkušební zdroj na {$node->name} …");
        $result = $qualification->synthetic($node, CommandContext::system('cli:nodes:qualify'));
        $line = sprintf('%s · %s · %d s', $result['status'], $result['detail'], $result['seconds']);
        match ($result['status']) {
            'ok' => $this->info($line),
            'not_configured' => $this->warn($line),
            default => $this->error($line.($result['leftover'] !== null ? ' — NA UZLU ZŮSTALO: '.$result['leftover'].', odstraňte ho ručně' : '')),
        };

        return $result['status'] === 'failed' ? 1 : 0;
    }
    $accept = (string) ($this->option('accept') ?? '');
    if ($accept === '') {
        $waiting = $qualification->waiting();
        $this->table(['uzel', 'role', 'region', 'prošel', 'co chybí'], array_map(fn (array $r) => [$r['node'], $r['role'], $r['region'], $r['passed'] ? 'ano' : 'ne', $r['failed']], $waiting['rows']));
        $this->info($waiting['waiting'] === 0 ? 'Žádný uzel nečeká na kvalifikaci.' : $waiting['waiting'].' uzel/uzly čekají — přijmi je pomocí --accept=<uzel>.');

        return;
    }
    $node = Node::query()->find($accept) ?? Node::query()->where('name', $accept)->first();
    if ($node === null) {
        $this->error("Uzel {$accept} neznám.");

        return 1;
    }
    try {
        $qualification->accept($node, CommandContext::system('cli:nodes:qualify'), (string) ($this->option('exception') ?? '') ?: null);
    } catch (DomainError $e) {
        $this->error($e->getMessage());

        return 1;
    }
    $this->info("Uzel {$node->name} je kvalifikovaný a v nabídce.");

    return 0;
})->purpose('List the nodes waiting to be qualified, and put a node that passes into the offer');

// The plan sells "test obnovy měsíčně" and nothing ever tested one (H458). Each due service gets a restore into
// databases of its own, compared by a round trip; the test databases are removed whatever happens.
Artisan::command('onhost:services:restore-test {--limit=20 : how many services to look at}', function (RestoreTest $tests) {
    $stats = $tests->tick(max(1, (int) $this->option('limit')));
    $this->info(sprintf('services looked at: %d · tests started: %d · not owed: %d · errors: %d', $stats['checked'], $stats['started'], $stats['skipped'], $stats['errors']));
})->purpose('Restore the newest backup of every service whose plan promises a restore test, into databases of its own');

/*
 * Jobs scheduled before the body was confined are still the node's root scripts (H438). They are recognised by their
 * own body — a confined one carries the site user and the here-document — and put right by rewriting them with the
 * command they already have, which sends them back through `cronBody()`. Read-only without `--apply`.
 */
Artisan::command('onhost:services:cron-confine {--apply : rewrite them; without it only the list} {--limit=200 : how many services to look at}', function (ServiceService $services, ServiceFeatures $features) {
    $rows = [];
    $found = 0;
    $unreadable = 0;
    $web = Service::query()->whereIn('family', ['web', 'managed'])->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED, ServiceStateMachine::SUSPENDED])->orderBy('created_at')->orderBy('id')->limit(max(1, (int) $this->option('limit')))->get();
    foreach ($web as $service) {
        try {
            $jobs = $features->resources($service, 'cron', true);
        } catch (Throwable $e) {
            $unreadable++; // a panel that did not answer says nothing about its jobs, in either direction
            $rows[] = [$service->name, '—', 'nelze načíst: '.mb_substr($e->getMessage(), 0, 50)];

            continue;
        }
        foreach ($jobs as $job) {
            if (($job['confined'] ?? true) !== false) {
                continue;
            }
            $status = 'nalezeno';
            if ((bool) $this->option('apply')) {
                try {
                    $services->requestAction($service, 'cron.update', CommandContext::system('cli:services:cron-confine'), 'cron-confine:'.$service->id.':'.$job['remote_id'],
                        ['remote_id' => (string) $job['remote_id'], 'command' => (string) $job['command']]);
                    $status = 'přepsáno';
                } catch (Throwable $e) {
                    $status = 'chyba: '.mb_substr($e->getMessage(), 0, 50);
                }
            }
            $found++;
            $rows[] = [$service->name, mb_substr((string) $job['command'], 0, 50), $status];
        }
    }
    $this->table(['služba', 'příkaz', 'stav'], $rows);
    $this->info(match (true) {
        $found > 0 => $found.' úloh(y) běží mimo uživatele webu — spusť s --apply.',
        $unreadable > 0 => 'Mezi službami, které odpověděly, neběží mimo uživatele svého webu žádná úloha.',
        default => 'Žádná plánovaná úloha neběží mimo uživatele svého webu.',
    });
    if ($unreadable > 0) { // said separately: a service we could not read is not a service we checked
        $this->warn($unreadable.' služba/služby neodpověděly — o jejich úlohách tenhle výpis neříká nic.');
    }
})->purpose('Find scheduled jobs still running as the node\'s root and rewrite them to run as the site user');

Artisan::command('onhost:services:release-stranded {--apply : put them back; without it only the list} {--minutes=15 : how long a transient state must have lasted}', function (ServiceService $services) {
    $back = [ServiceStateMachine::SUSPENDING => ServiceStateMachine::ACTIVE, ServiceStateMachine::RESUMING => ServiceStateMachine::SUSPENDED, ServiceStateMachine::RESIZING => ServiceStateMachine::ACTIVE];
    $stranded = ServiceService::stranded(max(1, (int) $this->option('minutes')));
    $rows = [];
    foreach ($stranded as $service) {
        $to = $back[$service->state];
        $done = 'listed';
        if ($this->option('apply')) {
            $operation = Operation::query()->where('service_id', $service->id)->orderByDesc('created_at')->first();
            if ($operation === null) {
                $done = 'skipped: no operation on record';
            } else {
                $services->settleTransient($service, $to, CommandContext::system('stranded transition released')->withScope($service->organization_id), 'stranded transition released', $operation);
                $done = 'released';
            }
        }
        $rows[] = [$service->id, $service->label ?: ($service->hostname ?: $service->name), $service->state, $to, $done];
    }
    $released = array_values(array_filter($rows, fn (array $r) => $r[4] === 'released'));
    if ($released !== []) { // staff hear about it: a panel refused something, and the platform put the service back by itself
        app(OutboxPublisher::class)->publish(GenericEvent::of('provisioning.stranded.released', 'platform', 'stranded', ['count' => count($released), 'services' => array_map(fn (array $r) => ['id' => $r[0], 'name' => $r[1], 'from' => $r[2], 'to' => $r[3]], array_slice($released, 0, 20))]));
    }
    $rows === [] ? $this->info('No stranded services.') : $this->table(['service', 'name', 'stranded in', 'belongs in', 'result'], $rows);
})->purpose('List or release services stranded in a transient state by a suspend, resume or resize the panel refused');

/*
 * The deletion lifecycle (audit §5ab): a cancelled service is archived, deactivated and kept for the grace window
 * (30 days by default, "Nastavení systému → Životní cyklus služeb"). This pass removes the ones whose window ran
 * out — every removal re-verifies the identity of the resource and refuses without a complete archive.
 */
Artisan::command('onhost:services:purge {--service= : one service id or name, otherwise everything that is due} {--force : ignore the grace window (needs --reason)} {--reason=} {--limit=20} {--dry-run}', function (ServiceService $services, DeletionPolicy $policy, AutomationLedger $ledger) {
    if ($ledger->off('services.purge')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $one = (string) ($this->option('service') ?? '');
    $due = Service::query()
        ->when($one !== '', fn ($q) => $q->where(fn ($w) => $w->where('id', $one)->orWhere('name', $one)->orWhere('hostname', $one)))
        ->when($one === '', fn ($q) => $q->whereNotNull('terminate_at')->where('terminate_at', '<=', now()))
        ->when($one === '', fn ($q) => $q->whereIn('state', [ServiceStateMachine::SUSPENDED, ServiceStateMachine::FAILED])) // a named service may also be one stuck mid-termination
        ->where('legal_hold', false)->orderBy('terminate_at')->limit(max(1, (int) $this->option('limit')))->get();
    if ($due->isEmpty()) {
        $this->info('nothing to remove ('.$policy->graceDays().' days of grace, '.$policy->retentionDays().' days of retention)');

        return;
    }
    $rows = [];
    foreach ($due as $service) {
        $archive = app(FinalArchive::class)->existing($service);
        $status = 'čeká';
        if ((bool) $this->option('dry-run')) {
            $status = 'dry-run';
        } else {
            try {
                $operation = $services->requestAction($service, 'purge', CommandContext::system('cli:services:purge'), 'purge:'.$service->id.':'.now()->format('YmdH'),
                    array_filter(['force' => (bool) $this->option('force'), 'reason' => (string) ($this->option('reason') ?? 'ochranná lhůta vypršela')]));
                $status = $operation->state;
            } catch (Throwable $e) {
                $status = 'chyba: '.mb_substr($e->getMessage(), 0, 60);
            }
        }
        $rows[] = [$service->id, $service->name, $service->terminate_at?->format('j. n. Y'), $archive?->id ?? '— bez archivu —', $status];
    }
    $ledger->record('services.purge', ['due' => $due->count(), 'dry_run' => (bool) $this->option('dry-run')]);
    $this->table(['služba', 'název', 'lhůta do', 'archiv', 'stav'], $rows);
})->purpose('Remove services whose restore window expired — after a fresh identity check and with the archive in place');

/*
 * The archive of one service: what is stored, whether the identity matches, and — with --create — building it now
 * without deleting anything (the way to prove the archive path of a panel before a real cancellation).
 */
/*
 * Access that ended on its date (Brain card H343). The permission stopped by itself at that second; this pass removes
 * the membership or the project role afterwards, the same way a removal by hand does — which is what takes the person's
 * collaborator accounts and SSH keys off the panels — and tells the organization.
 */
Artisan::command('onhost:access:expire', function (AccessExpiry $expiry, AutomationLedger $ledger, ServiceAccessService $shared) {
    if ($ledger->off('access.expire')) {
        $this->warn('switched off by staff (console → automation); expired permissions stay refused, only the clean-up waits');

        return;
    }
    $stats = $expiry->sweep() + ['shared_services' => $shared->expire()]; // one service shared until a date: the binding stopped at that second, this closes the record and lets a guest go
    $stats['approvals'] = app(ApprovalService::class)->expire(); // a request for a second person nobody decided in time (it stopped being usable at that second by itself)
    $ledger->record('access.expire', $stats);
    $this->table(['memberships', 'project_roles', 'errors', 'shared_services', 'approvals'], [$stats]);
})->purpose('Remove memberships and project roles whose access ended on its date, with the panel accounts and SSH keys that were theirs');

/*
 * SSH key revocations a panel has not taken yet (Brain card H185). Removing a member asks every panel to drop their
 * keys at once; this pass repeats the ones that failed or could not be queued — a locked panel, a site busy with
 * another operation — and tells staff about one that keeps failing. Until it is confirmed the key may still work.
 */
Artisan::command('onhost:ssh-keys:settle', function (SshKeyLedger $sshKeys, AutomationLedger $ledger) {
    if ($ledger->off('ssh_keys.settle')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $stats = $sshKeys->settle();
    $ledger->record('ssh_keys.settle', $stats);
    $this->table(['open', 'confirmed', 'retried', 'stuck'], [$stats]);
})->purpose('Repeat SSH key revocations the panels have not confirmed and report the ones that keep failing');

/*
 * Panel accounts that outlived a membership (Brain cards H332, H333). Removing a member deletes their collaborator
 * accounts at once; this pass finds what slipped through — the panel was down that day, the account was added by hand
 * later — and tells the organization. Nothing is removed here: an outside collaborator is the customer's call.
 */
Artisan::command('onhost:access:review {--organization= : only this organization}', function (DelegatedAccessReview $accessReview, AutomationLedger $ledger) {
    if ($ledger->off('access.review')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $stats = $accessReview->review($this->option('organization') ?: null);
    $ledger->record('access.review', $stats);
    $this->table(['organizations', 'services', 'findings', 'errors'], [$stats]);
})->purpose('Report collaborator accounts on game servers that belong to people who are no longer members of the organization');

Artisan::command('onhost:services:archive {service : service id, name or hostname} {--create : build the archive now (nothing is deleted)} {--package : build the downloadable zip of the newest archive} {--identity : only the identity verification}', function (ProviderRegistry $registry, FinalArchive $archives, ServiceIdentityCheck $identityCheck) {
    $key = (string) $this->argument('service');
    $service = Service::query()->where(fn ($w) => $w->where('id', $key)->orWhere('name', $key)->orWhere('hostname', $key))->withTrashed()->first();
    if ($service === null) {
        $this->error('service not found: '.$key);

        return 1;
    }
    $binding = $service->primaryBinding();
    $adapter = null;
    if ($service->provider_instance_id !== null) {
        $instance = ProviderInstance::query()->find($service->provider_instance_id);
        $adapter = $instance === null ? null : $registry->forInstance($instance);
    }
    $report = $identityCheck->verify($service, $adapter, $binding?->ref());
    $this->line('<info>'.$service->id.'</info> '.$service->name.' · '.$service->family.' · '.$service->state);
    $this->table(['bod', 'shoda', 'očekáváno', 'v panelu', 'poznámka'], array_map(fn (array $c) => [
        $c['label'], $c['ok'] === null ? '—' : ($c['ok'] ? 'ano' : 'NE'), (string) $c['expected'], (string) $c['actual'], (string) $c['note'],
    ], $report['checks']));
    $this->line(($report['ok'] ? '<info>ověřeno</info>' : '<error>NEOVĚŘENO</error>').': '.$report['matched'].'/'.$report['required'].' bodů'.($report['failed'] === [] ? '' : ', neshody: '.implode(', ', $report['failed'])));
    if ((bool) $this->option('identity')) {
        return $report['ok'] ? 0 : 1;
    }
    if ((bool) $this->option('create')) {
        if (! $report['ok']) {
            $this->error('identity not verified — the archive is refused');

            return 1;
        }
        $result = $archives->create($service, $adapter, $binding?->ref(), CommandContext::system('cli:services:archive'), null, $report);
        $this->info('archive created: '.$result['set']);
    }
    $rows = [];
    foreach (Backup::query()->where('service_id', $service->id)->where('kind', 'final')->orderByDesc('created_at')->limit(10)->get() as $backup) {
        $rows[] = [
            $backup->id, $backup->state, number_format((int) $backup->size_bytes / 1048576, 1).' MB', $backup->retention_until?->format('j. n. Y'),
            implode(', ', (array) data_get($backup->meta, 'parts', [])),
            mb_substr(implode(' | ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys((array) data_get($backup->meta, 'attempts', [])), array_values((array) data_get($backup->meta, 'attempts', [])))).' '.(string) data_get($backup->meta, 'error', ''), 0, 160),
        ];
    }
    $this->table(['archiv', 'stav', 'velikost', 'uchovat do', 'části', 'pokusy / chyba'], $rows);
    if ((bool) $this->option('package')) {
        $newest = $archives->existing($service);
        if ($newest === null) {
            $this->error('no completed archive to package');

            return 1;
        }
        $package = $archives->package($newest);
        $this->info('package: '.$package['path'].' · '.number_format($package['bytes'] / 1048576, 1).' MB · sha256 '.mb_substr($package['sha256'], 0, 16).'…');
    }

    return 0;
})->purpose('Show, verify or build the final archive of one service (nothing is deleted)');
Artisan::command('onhost:certificates:renew {--limit=20}', function (CertificateService $certificates) {
    $this->info('renewals started: '.$certificates->renewDue((int) $this->option('limit')));
})->purpose('Renew platform-issued (wildcard) certificates before they expire');

Artisan::command('onhost:cdn:refresh {--limit=100}', function (CdnService $cdn) {
    $this->table(['checked', 'activated', 'synced', 'errors'], [$cdn->refresh((int) $this->option('limit'))]);
})->purpose('Activate delegated CDN zones and mirror DNS changes to the edge');

Artisan::command('onhost:web-tools:prune {--hours=6}', function (WebFileStore $files) {
    $this->info('removed: '.$files->prune((int) $this->option('hours')));
})->purpose('Remove expired uploads and downloads staged by the web toolkit');

/*
 * Platform zones: a zone the operator already runs at a DNS provider (`onhost.cz` at WEDOS) becomes a zone of the platform's
 * own organization; hosting subdomains (<label>.web.onhost.cz) get their A/AAAA rows there on provisioning and lose them on
 * termination. Existing rows (the wildcard, MX, …) are imported as customer-managed so the platform never touches them.
 */
Artisan::command('onhost:dns:adopt {zone : the zone name, e.g. onhost.cz} {--provider=wedos_zone : DNS provider key} {--instance= : provider instance key (default: first enabled instance of the provider)}', function (DnsService $dns, ProviderRegistry $registry, OrganizationService $organizations) {
    $name = strtolower(trim((string) $this->argument('zone'), " .\t"));
    $provider = (string) $this->option('provider');
    $instance = $this->option('instance') ? ProviderInstance::query()->where('key', (string) $this->option('instance'))->first() : $registry->findInstance($provider, null, 'dns');
    if ($instance === null) {
        $this->error("No enabled DNS provider instance for {$provider} — create it in Nastavení systému → Integrace (capability dns) first.");

        return 1;
    }
    $adapter = $registry->forInstance($instance);
    if (! $adapter instanceof DnsProvider) {
        $this->error(get_class($adapter).' is not a DNS provider.');

        return 1;
    }
    if (! $adapter->zoneExists($name)) {
        $this->error("Zone {$name} does not exist at {$instance->key}.");

        return 1;
    }
    $slug = (string) config('onhost.dns.platform_organization_slug', 'onhost-platform');
    $org = Organization::query()->where('slug', $slug)->first();
    if ($org === null) {
        $owner = User::query()->where('is_staff', true)->orderBy('created_at')->first();
        if ($owner === null) {
            $this->error('No staff user exists to own the platform organization.');

            return 1;
        }
        $org = $organizations->create($owner, ['name' => 'ONhost platform', 'type' => 'company', 'billing_email' => (string) config('mail.from.address', 'hello@onhost.cz'), 'country' => 'CZ'], CommandContext::system('platform zone adoption'));
        $org->forceFill(['slug' => $slug])->save();
        $this->line("Platform organization {$org->id} created ({$slug}).");
    }
    $existing = DnsZone::query()->where('name', $name)->first();
    if ($existing !== null && $existing->organization_id !== $org->id) {
        $this->error("Zone {$name} is already managed by organization {$existing->organization_id}.");

        return 1;
    }
    $zone = $existing ?? DnsZone::query()->create([
        'organization_id' => $org->id, 'name' => $name, 'provider' => $provider, 'provider_instance_id' => $instance->id,
        'serial' => 0, 'version' => 0, 'state' => 'active', 'kind' => 'primary', 'nameservers' => $dns->nameservers($provider), 'committed_at' => now(),
    ]);
    $zone->forceFill(['state' => 'active', 'provider' => $provider, 'provider_instance_id' => $instance->id])->save();
    $imported = 0;
    foreach ($adapter->listRecords($name) as $r) {
        if (in_array($r['type'], ['SOA', 'NS'], true) && $r['name'] === '@') {
            continue;
        }
        $known = $zone->records()->where('name', $r['name'])->where('type', $r['type'])->get()->first(fn ($x) => rtrim(strtolower((string) $x->content), '.') === rtrim(strtolower((string) $r['content']), '.'));
        if ($known !== null) {
            continue;
        }
        DnsRecord::query()->create(['zone_id' => $zone->id, 'name' => $r['name'], 'type' => $r['type'], 'content' => $r['content'], 'ttl' => (int) ($r['ttl'] ?? 3600), 'prio' => $r['prio'] ?? null, 'managed_by' => 'customer', 'protected' => in_array($r['type'], ['MX', 'NS'], true), 'comment' => 'imported from '.$instance->key]);
        $imported++;
    }
    $this->info("Zone {$name} adopted as platform zone {$zone->id} on {$instance->key}; {$imported} existing rows imported, ".$zone->records()->count().' rows known.');
    if (! in_array($name, (array) config('onhost.dns.platform_zones', []), true)) {
        $this->warn("Add {$name} to ONHOST_PLATFORM_ZONES so provisioning uses it.");
    }

    return 0;
})->purpose('Adopt a zone the operator already runs (e.g. onhost.cz at WEDOS) as a platform zone for hosting subdomains');

Artisan::command('onhost:registrars:sync-connections {--connection=* : limit to these connection ids} {--force : sync even when auto_sync is off or the last sync is recent}', function (RegistrarConnectionService $connections) {
    $query = RegistrarConnection::query()->where('state', '!=', 'disabled')->orderBy('last_synced_at');
    if ($this->option('connection') !== []) {
        $query->whereIn('id', (array) $this->option('connection'));
    }
    $rows = [];
    foreach ($query->get() as $connection) {
        if (! $this->option('force') && (! $connection->setting('auto_sync') || ($connection->last_synced_at !== null && $connection->last_synced_at->gt(now()->subMinutes(50))))) {
            continue;
        }
        try {
            $summary = $connections->sync($connection, CommandContext::system('registrar connection sync'));
            $rows[] = [$connection->id, $connection->label, 'ok', json_encode($summary)];
        } catch (Throwable $e) {
            $rows[] = [$connection->id, $connection->label, 'failed', mb_substr($e->getMessage(), 0, 120)];
        }
    }
    $this->table(['connection', 'label', 'result', 'summary'], $rows);

    return 0;
})->purpose('Mirror every connected registrar account: domains, hosted zones, expiry notices, credit watch');

/*
 * The exchange rate list of the Czech National Bank for the currencies documents are issued in, and the CZK recap of the
 * documents that were issued while the rate was not known. The bank publishes at 14:30 on working days.
 */
Artisan::command('onhost:fx:sync {--date= : the day to fetch the list for (Y-m-d), default today}', function (CnbRates $rates, InvoiceService $invoices) {
    if (CnbRates::currencies() === []) {
        $this->info('documents are issued in CZK only; nothing to fetch');

        return 0;
    }
    try {
        $list = $rates->sync($this->option('date') ? Carbon::parse((string) $this->option('date')) : null);
        $this->info("list of {$list['valid_on']}: {$list['stored']} new rate(s)");
    } catch (Throwable $e) {
        $this->error('the exchange rate list could not be read: '.$e->getMessage());
    }
    $done = $invoices->completeCzkStatements();
    $this->info("documents completed with their VAT in CZK: {$done['completed']}, still waiting: {$done['waiting']}");

    return 0;
})->purpose('Fetch the Czech National Bank exchange rates and complete the CZK VAT recap of foreign-currency documents');

/*
 * What the DNS providers serve, compared with what the platform holds — a batch of zones a night, oldest comparison first. A
 * record changed at the provider by hand, a commit that arrived only in part, a zone deleted there: operations hear about it
 * the first night, the doctor shows it until it is gone. Nothing is repaired by itself — which side is right is a decision.
 */
Artisan::command('onhost:dns:drift {--limit= : zones to compare in this run (default: onhost.dns.drift_batch)}', function (DnsService $dns) {
    $stats = $dns->checkDrift((int) ($this->option('limit') ?: config('onhost.dns.drift_batch', 200)));
    $this->info("compared {$stats['checked']} zone(s): {$stats['drifted']} differ, {$stats['errors']} could not be asked");
})->purpose('Compare every DNS zone with what its provider serves and report the differences');

Artisan::command('onhost:dns:platform-sync {--service=* : limit to these service ids} {--dry-run : only report what would change}', function (DnsService $dns) {
    $query = Service::query()->whereIn('family', ['web', 'managed'])->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED]);
    if ($this->option('service') !== []) {
        $query->whereIn('id', (array) $this->option('service'));
    }
    $done = 0;
    foreach ($query->orderBy('created_at')->get() as $service) {
        $platform = $dns->platformZoneFor((string) $service->hostname);
        if ($platform === null) {
            continue;
        }
        [$zone, $relative] = $platform;
        $node = $service->node_id ? Node::query()->find($service->node_id) : null;
        $instance = $service->provider_instance_id ? ProviderInstance::query()->find($service->provider_instance_id) : null;
        $ipv4 = (string) ($node?->tags['public_ipv4'] ?? $instance?->option('public_ipv4', '') ?? '');
        $ipv6 = (string) ($node?->tags['public_ipv6'] ?? $instance?->option('public_ipv6', '') ?? '');
        if ($ipv4 === '' && $ipv6 === '') {
            $this->warn("{$service->hostname}: node {$node?->name} has no public address (tags.public_ipv4) — skipped");

            continue;
        }
        if ($this->option('dry-run')) {
            $this->line("{$service->hostname} → {$relative} in {$zone->name}: A {$ipv4}".($ipv6 !== '' ? " AAAA {$ipv6}" : ''));

            continue;
        }
        $version = $dns->syncHostname($zone, $relative, $ipv4 ?: null, $ipv6 ?: null, CommandContext::system('dns platform sync'), "service:{$service->id}", "web hosting {$service->id}");
        $this->line("{$service->hostname} → {$relative} in {$zone->name}: ".($version === null ? 'up to date' : "committed version {$version->version}"));
        $done++;
    }
    $this->info("{$done} hostname(s) synced.");

    return 0;
})->purpose('Create or refresh the A/AAAA rows of active hosting subdomains in the platform zones');

Artisan::command('onhost:billing:runway', function (WalletForecast $forecast) {
    $this->table(['checked', 'warned'], [$forecast->warnLow()]);
})->purpose('Warn organizations whose credit will not cover the renewals of the next two weeks');

Artisan::command('onhost:certificates:issue-pending {--limit=20}', function (CertificateAutoIssuer $issuer, AutomationLedger $ledger) {
    if ($ledger->off('certificates.issue')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $issuer->run((int) $this->option('limit'));
    $ledger->record('certificates.issue', $result);
    $this->table(['checked', 'resolved', 'requested'], [$result]);
})->purpose('Issue the certificate of sites whose DNS did not point at the node at provisioning time, once it does');

Artisan::command('onhost:discord:register-commands', function (DiscordService $discord) {
    $result = $discord->registerCommands();
    $this->info('registered: '.implode(', ', array_map(fn ($c) => (string) ($c['name'] ?? '?'), $result)));
})->purpose('Register the /onhost slash command with Discord (needs the application id and the bot token secret)');

Schedule::command('onhost:monitoring:check')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:monitoring:prune')->dailyAt('04:20')->onOneServer();
Schedule::command('onhost:backups:run')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:certificates:renew')->dailyAt('03:10')->withoutOverlapping()->onOneServer();

Artisan::command('onhost:certificates:watch {--limit=200}', function (CertificateWatch $watch, AutomationLedger $ledger) {
    if ($ledger->off('certificates.watch')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $watch->run((int) $this->option('limit'));
    $ledger->record('certificates.watch', $result);
    $this->table(['checked', 'renewed', 'told', 'errors'], [$result]);
})->purpose('Read the certificate every site really serves (one handshake to its own node): tell the operators about one that expired or covers somebody else, and ask for a new one when the panel has not renewed in time');

Schedule::command('onhost:certificates:watch')->dailyAt('04:40')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:services:purge')->dailyAt('03:40')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:access:review')->weeklyOn(1, '04:50')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:access:expire')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:ssh-keys:settle')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:cdn:refresh')->hourlyAt(35)->withoutOverlapping()->onOneServer();
Schedule::command('onhost:web-tools:prune')->hourlyAt(50)->onOneServer();
