<?php

declare(strict_types=1);

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
use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsRecord;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\DomainRenewalScheduler;
use Onhost\Domain\Domains\DomainService;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Domains\RegistrarConnectionService;
use Onhost\Domain\Domains\RegistrarCreditMonitor;
use Onhost\Domain\Domains\RegistrarPollWorker;
use Onhost\Domain\Domains\RegistrarPriceScraper;
use Onhost\Domain\Domains\RegistrarPricing;
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
use Onhost\Domain\Notifications\NotificationService;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Domain\Orders\CommerceHousekeeping;
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
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\NodePrerequisites;
use Onhost\Domain\Provisioning\NodeSampler;
use Onhost\Domain\Provisioning\OperationsBoard;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\QueueScaler;
use Onhost\Domain\Provisioning\Reconciler;
use Onhost\Domain\Provisioning\Scheduling\NodeRebalancer;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\UsageWatch;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Domain\Services\Web\CdnService;
use Onhost\Domain\Services\Web\CertificateAutoIssuer;
use Onhost\Domain\Services\Web\CertificateService;
use Onhost\Domain\Services\Web\UptimeMonitor;
use Onhost\Domain\Services\Web\WebFileStore;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\WalletForecast;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Files\FileStore;
use Onhost\Platform\Ops\PlatformBackup;
use Onhost\Platform\Outbox\OutboxPublisher;
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

Artisan::command('onhost:support:sla', function (TicketService $tickets) {
    $this->table(['breached', 'closed'], [$tickets->tick()]);
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
Schedule::command('onhost:billing:meter')->hourlyAt(2)->withoutOverlapping()->onOneServer();
Schedule::command('onhost:billing:rate')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('onhost:billing:renewals')->hourlyAt(20)->withoutOverlapping()->onOneServer();
Schedule::command('onhost:billing:dunning')->dailyAt('06:00')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:billing:runway')->dailyAt('07:30')->withoutOverlapping()->onOneServer();
Schedule::command('onhost:dns:platform-sync')->hourlyAt(25)->withoutOverlapping()->onOneServer();
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
    $result = $housekeeping->prune((int) $this->option('quote-hours'), (int) $this->option('cart-days'));
    $ledger->record('commerce.prune', $result);
    $this->table(['quotes', 'carts'], [$result]);
})->purpose('Drop expired quotes no order references and stale open carts (browsing leaves both behind)');

Artisan::command('onhost:backups:run {--limit=100}', function (BackupScheduler $scheduler, AutomationLedger $ledger) {
    if ($ledger->off('backups.run')) {
        $this->warn('switched off by staff (console → automation)');

        return;
    }
    $result = $scheduler->tick((int) $this->option('limit'));
    $ledger->record('backups.run', $result);
    $this->table(['started', 'skipped', 'deleted', 'offsite', 'errors'], [$result]);
})->purpose('Start scheduled backups, apply retention and generation caps, copy off-site');

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
Schedule::command('onhost:cdn:refresh')->hourlyAt(35)->withoutOverlapping()->onOneServer();
Schedule::command('onhost:web-tools:prune')->hourlyAt(50)->onOneServer();
