<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Catalog\CatalogService;
use Onhost\Domain\Catalog\Models\Product;
use Onhost\Domain\Catalog\Models\TldPolicy;
use Onhost\Domain\Catalog\PlanPromises;
use Onhost\Domain\Catalog\WafLevels;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Domains\DomainStateMachine;
use Onhost\Domain\Domains\Models\Domain;
use Onhost\Domain\Domains\Models\RegistrarTldCost;
use Onhost\Domain\Domains\RegistrarClient;
use Onhost\Domain\Domains\RegistrarPricing;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Models\Approval;
use Onhost\Domain\Identity\Authorization\RoleCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Invoicing\Models\LegalEntity;
use Onhost\Domain\Notifications\MailHealth;
use Onhost\Domain\Orders\Models\Order;
use Onhost\Domain\Orders\OrderStateMachine;
use Onhost\Domain\Provisioning\AutomationLedger;
use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\PlanPlacement;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;
use Onhost\Domain\Provisioning\Models\VmidReservation;
use Onhost\Domain\Provisioning\OperationLatency;
use Onhost\Domain\Provisioning\PanelVersionGate;
use Onhost\Domain\Provisioning\PlacementService;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Domain\Services\Addons;
use Onhost\Domain\Services\DeletionPolicy;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\SshKeyGrant;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Domain\Support\Assistant\AssistantBudget;
use Onhost\Domain\Tax\CnbRates;
use Onhost\Domain\Tax\Models\ExchangeRate;
use Onhost\Domain\WalletLedger\AutoTopup;
use Onhost\Platform\Files\VirusScanner;
use Onhost\Platform\Ops\PlatformBackup;

/**
 * Production readiness self-check (docs/runbooks/go-live-checklist.md). Every row is a fact the control plane can
 * verify about itself: environment, storage, secrets, outbound TLS, provider instances and their health, payments,
 * documents, identity policy, mail and observability. Blocking findings are FAIL in production and WARN elsewhere;
 * the exit code is 1 when anything fails, so the deploy pipeline can gate on it.
 */
final class Doctor extends Command
{
    protected $signature = 'onhost:doctor {--json : Machine-readable report}';

    protected $description = 'Production readiness self-check: environment, storage, secrets, TLS, providers, payments, documents, identity, mail, observability';

    /** @var list<array{area:string, check:string, status:string, detail:string}> */
    private array $rows = [];

    private bool $production = false;

    public function handle(ProviderInstanceService $instances, AutomationLedger $ledger): int
    {
        $this->rows = []; // the command instance is reused within one process (tests, tinker): every run reports its own rows
        $this->production = app()->environment('production');
        $this->environment();
        $this->storage();
        $this->automation($ledger);
        $this->secretsAndTls();
        $this->providers($instances);
        $this->payments();
        $this->documents();
        $this->identity();
        $this->mailAndObservability();
        $this->deletionLifecycle();
        $this->authorizationCatalog();
        $this->controlPoints();

        $fails = count(array_filter($this->rows, fn ($r) => $r['status'] === 'FAIL'));
        $warns = count(array_filter($this->rows, fn ($r) => $r['status'] === 'WARN'));
        if ($this->option('json')) {
            $this->line((string) json_encode(['environment' => app()->environment(), 'fail' => $fails, 'warn' => $warns, 'checks' => $this->rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(['Area', 'Check', 'Status', 'Detail'], array_map(fn ($r) => [$r['area'], $r['check'], $r['status'], $r['detail']], $this->rows));
            $this->line(sprintf('%s · %d checks · %d FAIL · %d WARN', app()->environment(), count($this->rows), $fails, $warns));
        }

        return $fails > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The end of a service (audit §5ab): the archive disk has to be writable *before* a cancellation needs it, the
     * policy has to stay inside its safe bounds, no archive may be left failed or unverified, and nothing may sit
     * past its restore window without being removed — that would quietly keep customer data alive.
     */
    /**
     * Roles and permissions are code, the authorizer reads the database: a deploy that did not seed them leaves the old
     * rights in force — a role keeps a permission the catalog took away, or lacks one a new feature checks.
     */
    private function authorizationCatalog(): void
    {
        $stored = DB::table('role_permissions')->get()->groupBy('role_key')->map(fn ($rows) => $rows->pluck('permission_key')->sort()->values()->all());
        $drift = [];
        foreach (RoleCatalog::all() as $key => $role) {
            $expected = $role['permissions'];
            sort($expected);
            if (($stored[$key] ?? []) !== $expected) {
                $drift[] = $key;
            }
        }
        $this->add('identity', 'roles in the database match the catalog', $drift === [], $drift === [] ? count(RoleCatalog::all()).' roles'
            : count($drift).' differ ('.implode(', ', array_slice($drift, 0, 6)).(count($drift) > 6 ? ', …' : '').') — php artisan db:seed --class=AuthorizationSeeder --force');
    }

    private function deletionLifecycle(): void
    {
        // a key revocation no panel has confirmed for an hour is a key that may still open a session (H185); not blocking, but never silent
        $openKeys = SshKeyGrant::query()->where('state', SshKeyGrant::REVOKING)->where('revoke_requested_at', '<', now()->subHour())->count();
        $this->add('access', 'SSH key revocations confirmed by the panels', $openKeys === 0, $openKeys === 0 ? 'none open for longer than an hour' : "{$openKeys} open for longer than an hour — GET /v1/staff/provisioning/ssh-key-revocations", false);
        $strandedServices = ServiceService::stranded()->count(); // nothing is accepted in a transient state: a stranded service is unusable until released
        $this->add('lifecycle', 'no service stranded in a transient state', $strandedServices === 0, $strandedServices === 0 ? 'none' : "{$strandedServices} in SUSPENDING/RESUMING/RESIZING with no open operation — php artisan onhost:services:release-stranded", false);
        $policy = app(DeletionPolicy::class)->all();
        $this->add('lifecycle', 'restore window and retention set', $policy['grace_days'] >= 1 && $policy['retention_days'] >= 30,
            $policy['grace_days'].' days to restore · archive kept '.$policy['retention_days'].' days · '.$policy['identity_checks'].' identity points · download '.number_format($policy['download_fee_minor']['CZK'] / 100, 0, ',', ' ').' Kč', false);

        $disk = (string) config('onhost.platform_backup.disk', 'local');
        $probe = FinalArchive::PREFIX.'/.doctor-'.now()->format('Ymd-His');
        $writable = false;
        try {
            Storage::disk($disk)->put($probe, 'probe');
            $writable = Storage::disk($disk)->exists($probe);
            Storage::disk($disk)->delete($probe);
        } catch (\Throwable $e) {
            $this->add('lifecycle', 'archive disk writable', false, $disk.': '.mb_substr($e->getMessage(), 0, 120));

            return;
        }
        $this->add('lifecycle', 'archive disk writable', $writable, $disk.' · '.FinalArchive::PREFIX);

        $failed = Backup::query()->where('kind', 'final')->where('state', 'failed')->where('created_at', '>', now()->subDays(30))->count();
        $this->add('lifecycle', 'no failed archive in the last 30 days', $failed === 0, $failed === 0 ? '' : $failed.' × — onhost:services:archive <service> shows the attempts', false);

        $unverified = Backup::query()->whereNotNull('meta->set')->where('state', 'completed')->where('verify_status', '!=', 'ok')->count(); // every set on the backup disk: final archives and the backups of web services
        $stale = Backup::query()->where('state', 'running')->where('started_at', '<', now()->subHours(6))->count();
        $this->add('lifecycle', 'no backup stuck in progress', $stale === 0, $stale === 0 ? '' : $stale.' × running for more than six hours — the operation behind it died; see docs/runbooks/backups.md', false);
        $this->add('lifecycle', 'archives verified', $unverified === 0, $unverified === 0 ? '' : $unverified.' × without a checksum verification — onhost:backups:run re-verifies', false);

        $overdue = Service::query()->withTrashed()->whereNotNull('terminate_at')->where('terminate_at', '<', now()->subDay())
            ->whereIn('state', [ServiceStateMachine::SUSPENDED, ServiceStateMachine::FAILED])->where('legal_hold', false)->count();
        $this->add('lifecycle', 'nothing past its restore window', $overdue === 0, $overdue === 0 ? '' : $overdue.' service(s) should already be removed — onhost:services:purge (scheduler 03:40)', false);

        $orphan = Backup::query()->where('kind', 'final')->where('state', 'completed')->whereNull('retention_until')->count();
        $this->add('lifecycle', 'every archive has a retention date', $orphan === 0, $orphan === 0 ? '' : $orphan.' × without retention_until — they would never be pruned', false);
        // an archive past its date whose copy at the provider could not be removed still holds a cancelled customer's data (H488)
        $blocked = Backup::query()->where('state', 'completed')->where('retention_until', '<', now())->whereNotNull('meta->expiry_blocked')->count();
        $this->add('lifecycle', 'expired archives are gone from the provider too', $blocked === 0,
            $blocked === 0 ? '' : $blocked.' archive(s) past retention still at the provider — backups.meta.expiry_blocked says why', false);

        // a schedule that keeps missing its slot is a backup the customer paid for and did not get (H434, H446)
        $stalled = Service::query()->whereIn('family', ['web', 'managed', 'mail'])->where('tags->backup_schedule->missed', '>=', BackupScheduler::MISSES_BEFORE_ALARM)->count();
        $this->add('lifecycle', 'backup schedules keeping up', $stalled === 0, $stalled === 0 ? '' : $stalled.' service(s) have missed '.BackupScheduler::MISSES_BEFORE_ALARM.'+ slots in a row — tags.backup_schedule says why', false);

        // a node nobody looked at is a node the scheduler would sell (H471): the waiting ones, and the ones already
        // in the offer that have never been through a qualification at all
        $waiting = Node::query()->where('state', Node::QUALIFYING)->count();
        $this->add('capacity', 'no node is waiting to be qualified', $waiting === 0,
            $waiting === 0 ? 'none waiting' : $waiting.' node(s) discovered and not yet in the offer — php artisan onhost:nodes:qualify', false);
        // something made for a test and not removed is on a node on nobody's bill (H479)
        $leftovers = Node::query()->whereNotNull('qualification->synthetic->leftover')->get(['name', 'qualification'])
            ->map(fn (Node $n) => $n->name.': '.(string) data_get($n->qualification, 'synthetic.leftover'))->all();
        $this->add('capacity', 'no synthetic test resource was left on a node', $leftovers === [],
            $leftovers === [] ? 'none' : implode(', ', array_slice($leftovers, 0, 5)).' — remove by hand, then run the synthetic check again');
        $unqualified = Node::query()->where('state', Node::ACTIVE)->whereNull('qualified_at')->count();
        $this->add('capacity', 'every node in the offer has been qualified', $unqualified === 0,
            $unqualified === 0 ? 'all of them' : $unqualified.' node(s) carry customers without a qualification on record — onhost:nodes:qualify --accept=<node>', false);
        // a number somebody took at Proxmox before the platform's clone arrived: VMs are being made by hand inside the range the
        // platform gives out (H488) — every such collision costs an order a retry
        $burned = VmidReservation::query()->whereNotNull('burned_at')->where('burned_at', '>', now()->subDays(30))->count();
        $this->add('capacity', 'guest numbers are the platform\'s own', $burned === 0,
            $burned === 0 ? 'no number taken from under an order in 30 days' : $burned.' number(s) taken at Proxmox before the clone arrived in 30 days — VMs made by hand in the platform\'s range; raise vmid_min or number them elsewhere (docs/provider-adapters/proxmox.md)', false);

        // the plan sells a restore test; a service that has never had one has a promise nobody kept (H458)
        $promised = BackupPolicy::query()->whereNotNull('restore_test')->pluck('service_id')->all();
        $untested = $promised === [] ? 0 : Service::query()->whereIn('id', $promised)->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])
            ->where(fn ($q) => $q->whereNull('tags->restore_test->last_at')->orWhere('tags->restore_test->outcome', '!=', 'ok'))->count();
        $this->add('lifecycle', 'every promised restore test has been made', $untested === 0,
            $untested === 0 ? (count($promised) === 0 ? 'no plan promises one' : count($promised).' service(s) with a tested backup') : $untested.' service(s) sold a restore test have none that passed — onhost:services:restore-test', false);

        // a schedule that stopped itself after repeated failures waits for a person and nothing else will start it (H447)
        $pausedSchedules = Service::query()->whereIn('family', ['web', 'managed', 'mail'])->whereNotNull('tags->backup_schedule->paused_at')->count();
        $this->add('lifecycle', 'no backup schedule is waiting for a person', $pausedSchedules === 0,
            $pausedSchedules === 0 ? '' : $pausedSchedules.' schedule(s) stopped after '.BackupScheduler::FAILURES_BEFORE_PAUSE.' failures in a row — fix the cause, then set the schedule again', false);

        // an add-on is a billing row that changes its parent; one the platform cannot apply would be charged for nothing (audit §5ac)
        $undelivered = Addons::unsellable();
        $this->add('catalog', 'every add-on on sale is one the platform delivers', $undelivered === [],
            $undelivered === [] ? implode(', ', Addons::handled()) : 'sold and never applied: '.implode(', ', $undelivered).' — Onhost\Domain\Services\Addons::handled()');
        // a version published in the administration can put a number back long after the guard test was written (audit §5ad)
        $promises = PlanPromises::onSaleProblems(PlanPromises::readInSource());
        $this->add('catalog', 'no plan on sale promises a number nothing applies', $promises === [],
            $promises === [] ? 'every number is enforced, applied, measured, or declared fair use' : implode(' · ', array_map(fn (string $plan, array $keys) => $plan.': '.implode(', ', $keys), array_keys($promises), $promises)), false);
        // a WAF level is a line on the price list; the panels do not do the same things, and a level nobody defined
        // used to mean nothing at all (audit §5ad, the same rule as the numbers above)
        $waf = WafLevels::onSaleProblems(['ispconfig' => ServiceFeatures::securitySupports('ispconfig'), 'aapanel' => ServiceFeatures::securitySupports('aapanel')]);
        $this->add('catalog', 'every plan on sale keeps its WAF promise on its own panel', $waf === [],
            $waf === [] ? 'every level names only rules the site\'s server applies' : implode(' · ', array_map(fn (string $plan, array $rules) => $plan.': '.implode(', ', $rules), array_keys($waf), $waf)), false);
        $addonsWithoutParent = Service::query()->where('family', 'addon')->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])
            ->whereNull('tags->parent_service_id')->count();
        $this->add('catalog', 'every add-on knows the service it belongs to', $addonsWithoutParent === 0,
            $addonsWithoutParent === 0 ? '' : $addonsWithoutParent.' × without a parent service — they change nothing and bill anyway', false);
    }

    /**
     * Control points of the rules the platform keeps about itself: operations forget their secrets, every web service has a
     * recent backup, and what a person waits for is done in seconds — read as numbers, not believed.
     */
    private function controlPoints(): void
    {
        $holding = Operation::query()->whereNull('secrets_scrubbed_at')->whereIn('state', [Operation::SUCCEEDED, Operation::CANCELLED])->where('finished_at', '<', now()->subHours(2))->count();
        $this->add('security', 'finished operations hold no secrets', $holding === 0, $holding === 0 ? 'the sweep is up to date' : "{$holding} finished operation(s) not cleaned yet — onhost:operations:forget-secrets works them off (the scheduler runs it every ten minutes)", false);

        $days = max(1, (int) config('onhost.backups.coverage_days', 3));
        $services = Service::query()->whereIn('family', ['web', 'managed'])->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])->where('created_at', '<', now()->subDays($days))->pluck('id');
        $covered = Backup::query()->whereIn('service_id', $services->all())->where('state', 'completed')->where('finished_at', '>=', now()->subDays($days))->distinct()->pluck('service_id');
        $bare = $services->diff($covered)->values();
        $this->add('lifecycle', "every web service has a backup from the last {$days} days", $bare->isEmpty(), $bare->isEmpty() ? $services->count().' service(s) covered' : $bare->count().' without one: '.$bare->take(5)->implode(', ').($bare->count() > 5 ? ' …' : '').' — docs/runbooks/backups.md', false);

        // money: an order that is being delivered was paid and documented (a state written by hand used to skip both), and no
        // document was credited for more than it was issued for
        $unpaid = Order::query()->whereIn('state', [OrderStateMachine::PAID, OrderStateMachine::PROVISIONING, OrderStateMachine::PARTIALLY_ACTIVE, OrderStateMachine::ACTIVE])->where('total_minor', '>', 0)
            ->where(fn ($q) => $q->whereNull('paid_at')->orWhereNotIn('id', Invoice::query()->whereIn('type', ['statement', 'invoice'])->whereNotNull('order_id')->select('order_id')))->limit(50)->pluck('number');
        $this->add('money', 'every order being delivered was paid and documented', $unpaid->isEmpty(), $unpaid->isEmpty() ? 'none without a payment or a tax document' : $unpaid->count().' order(s): '.$unpaid->take(5)->implode(', ').($unpaid->count() > 5 ? ' …' : '').' — finance review (docs/runbooks/billing-dunning.md)');
        $over = Invoice::query()->where('credited_minor', '>', 0)->whereColumn('credited_minor', '>', 'total_minor')->limit(50)->pluck('number');
        if (CnbRates::currencies() !== []) {
            // a tax document in another currency states its VAT in CZK; one issued while the bank did not answer waits for `onhost:fx:sync`
            $waiting = Invoice::query()->where('meta->czk_pending', true)->where('state', '!=', Invoice::DRAFT)->where('issued_at', '<', now()->subDay())->limit(50)->pluck('number');
            $this->add('money', 'every tax document in another currency states its VAT in CZK', $waiting->isEmpty(), $waiting->isEmpty() ? 'none waits for the national bank\'s rate for more than a day' : $waiting->count().' document(s) without the CZK recap: '.$waiting->take(5)->implode(', ').' — run onhost:fx:sync and see why the list cannot be read', false);
            $latest = ExchangeRate::query()->where('source', CnbRates::SOURCE)->max('valid_on');
            $fresh = $latest !== null && CarbonImmutable::parse((string) $latest)->gte(now()->subDays(5)->startOfDay());
            $this->add('money', 'the national bank\'s exchange rates are fresh', $fresh, $latest === null ? 'no list was ever stored — run onhost:fx:sync (documents in '.implode(', ', CnbRates::currencies()).' wait for it)' : 'latest list: '.CarbonImmutable::parse((string) $latest)->format('Y-m-d'), false);
        }
        $this->add('money', 'no document is credited for more than it was issued for', $over->isEmpty(), $over->isEmpty() ? 'credit notes fit their documents' : $over->take(5)->implode(', ').' — finance review', false);

        // what a service owns on a shared node is recognised by its name prefix; two services with one prefix own each other's databases
        $shared = Service::query()->withTrashed()->whereNull('name_prefix')->limit(50)->pluck('id');
        $this->add('security', 'every service has a node name prefix of its own', $shared->isEmpty(), $shared->isEmpty() ? 'prefixes are unique' : $shared->count().' service(s) share a prefix with an older one: '.$shared->take(5)->implode(', ').' — move their databases and FTP accounts before anything else (docs/runbooks/security-boundaries.md §14)');

        if ((bool) config('onhost.ai.enabled', false)) {
            $ai = app(AssistantBudget::class)->today();
            $share = $ai['tokens_per_day'] > 0 ? (int) round($ai['tokens_today'] * 100 / $ai['tokens_per_day']) : null;
            $this->add('automation', 'the assistant\'s model use is inside its daily budget', $share === null || $share < 80, $share === null ? $ai['model_runs_today'].' model answer(s) today; no daily ceiling of tokens is set (ONHOST_AI_TOKENS_PER_DAY=0)' : $ai['model_runs_today'].' model answer(s), '.$ai['tokens_today'].' of '.$ai['tokens_per_day']." tokens today ({$share} %) — past the ceiling the assistant answers from the help centre", false);
        }
        // a domain no registrar lists, and the registrar says it does not have: closed after it stayed away (DomainService::missingAtRegistrar)
        $away = Domain::query()->whereNotNull('meta->missing_since')->limit(50)->pluck('fqdn_ascii');
        $this->add('lifecycle', 'no domain is missing at its registrar', $away->isEmpty(), $away->isEmpty() ? 'every domain is in its registrar\'s account' : $away->count().' domain(s) not listed and not known to the registrar: '.$away->take(5)->implode(', ').' — closed (renewals stopped) once they stayed away for '.(int) config('onhost.domains.missing_confirm_hours', 36).' h; if that is wrong, look at the registrar account now', false);
        $orphaned = DnsZone::query()->whereIn('domain_id', Domain::query()->whereIn('state', [DomainStateMachine::DELETED, DomainStateMachine::TRANSFERRED_OUT])->select('id'))->limit(50)->pluck('name');
        $this->add('dns', 'no DNS zone outlives its domain unnoticed', $orphaned->isEmpty(), $orphaned->isEmpty() ? 'no zone belongs to a domain that was deleted or transferred away' : $orphaned->count().' zone(s) of domains that are gone: '.$orphaned->take(5)->implode(', ').' — the customer may still use our DNS for a domain held elsewhere; delete the zone when they do not', false);

        $drifted = DnsZone::query()->whereNotNull('drift')->limit(50)->pluck('name');
        $this->add('dns', 'every DNS zone equals what its provider serves', $drifted->isEmpty(), $drifted->isEmpty() ? 'no differences at the last comparison (onhost:dns:drift, nightly)' : $drifted->count().' zone(s) differ: '.$drifted->take(5)->implode(', ').' — compare in the console and publish or import', false);

        $unasked = DnsZone::query()->whereNotNull('drift_error')->limit(50)->get(['name', 'drift_error']);
        $this->add('dns', 'every DNS zone could be compared with its provider', $unasked->isEmpty(), $unasked->isEmpty() ? 'the provider answered for every zone at the last comparison' : $unasked->count().' zone(s) could not be compared: '.$unasked->take(5)->map(fn (DnsZone $z) => $z->name.' ('.$z->getAttribute('drift_error').')')->implode(', ').' — the provider did not answer; see the provider health and `onhost:dns:drift`', false);

        $slow = app(OperationLatency::class)->slow();
        $target = OperationLatency::targetSeconds();
        $this->add('speed', "actions a person waits for finish within {$target} s (p95, 24 h)", $slow === [], $slow === [] ? 'nothing slower' : implode('; ', array_map(fn (array $r) => "{$r['provider']} {$r['action']}: p95 {$r['p95_s']} s (queue {$r['wait_p95_s']} s, {$r['count']}×)", array_slice($slow, 0, 5))), false);
    }

    private function add(string $area, string $check, bool $ok, string $detail = '', bool $blocking = true): void
    {
        $this->rows[] = ['area' => $area, 'check' => $check, 'status' => $ok ? 'OK' : ($blocking && $this->production ? 'FAIL' : 'WARN'), 'detail' => $detail];
    }

    private function environment(): void
    {
        $this->add('app', 'APP_ENV is production', $this->production, (string) config('app.env'), false);
        $this->add('app', 'APP_DEBUG off', ! config('app.debug'), config('app.debug') ? 'debug pages would leak configuration' : '');
        $this->add('app', 'APP_KEY set', (string) config('app.key') !== '', '');
        $this->add('app', 'APP_URL uses https', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url'));
        $this->add('app', 'Sanctum stateful domains set', (array) config('sanctum.stateful', []) !== [], 'the surfaces authenticate with the session cookie');
    }

    private function storage(): void
    {
        $backup = app(PlatformBackup::class)->status(); // go-live §1: a verified backup younger than a day
        $verifiedAt = isset($backup['verified']['at']) ? CarbonImmutable::parse((string) $backup['verified']['at']) : null;
        $this->add('storage', 'platform backup verified within 26 h', $verifiedAt !== null && $verifiedAt->gt(now()->subHours(26)), $verifiedAt !== null ? 'set '.$backup['verified']['set'].' verified '.$verifiedAt->diffForHumans().' on disk '.$backup['disk'] : (isset($backup['last']['at']) ? 'last backup '.$backup['last']['at'].' not verified yet — onhost:platform:backup:verify' : 'no backup yet — onhost:platform:backup runs daily 02:15'), false);
        $this->add('storage', 'platform backup disk off the server', ! $this->production || $backup['disk'] !== 'local', $backup['disk'].($backup['disk'] === 'local' ? ' (production: an S3-compatible disk — ONHOST_PLATFORM_BACKUP_DISK)' : ''), false);
        try {
            DB::select('select 1');
            $this->add('storage', 'database reachable', true, (string) config('database.default'));
        } catch (\Throwable $e) {
            $this->add('storage', 'database reachable', false, $e->getMessage());
        }
        $this->add('storage', 'database engine', $this->production ? config('database.default') === 'pgsql' : true, (string) config('database.default').' (production: pgsql)', false);
        $queue = (string) config('queue.default');
        $this->add('storage', 'queue driver', $queue !== 'sync', $queue.($queue === 'database' ? ' (works; redis recommended)' : ''), $queue === 'sync');
        $this->add('storage', 'cache store', in_array(config('cache.default'), ['redis', 'memcached', 'dynamodb', 'array', 'file', 'database'], true) && (! $this->production || config('cache.default') === 'redis'), (string) config('cache.default').' (production: redis)', false);
        $this->add('storage', 'session driver', ! $this->production || in_array(config('session.driver'), ['redis', 'database'], true), (string) config('session.driver'), false);
        $this->add('storage', 'storage/app writable', is_writable(storage_path('app')), storage_path('app'));
    }

    /** The two machines every rule depends on (audit §5g-6) and the switches staff flipped (§5g-7). */
    private function automation(AutomationLedger $ledger): void
    {
        $live = $ledger->liveness();
        $since = fn (?string $at) => $at !== null ? CarbonImmutable::parse($at)->diffForHumans() : null;
        $this->add('automation', 'scheduler running', $live['scheduler']['alive'], $since($live['scheduler']['at']) !== null ? 'last provisioning tick '.$since($live['scheduler']['at']) : 'no tick recorded — is `php artisan schedule:run` in cron every minute?');
        $this->add('automation', 'queue worker alive', $live['worker']['alive'], $live['worker']['driver'] === 'sync' ? 'sync driver (development only)' : ($since($live['worker']['at']) !== null ? 'heartbeat '.$since($live['worker']['at']) : 'no heartbeat — is `php artisan queue:work` running?'));
        $off = $ledger->disabled();
        $this->add('automation', 'no rule switched off', $off === [], $off === [] ? 'every rule on' : 'off: '.implode(', ', $off), false);
        $backlog = $ledger->backlog();
        $this->add('automation', 'operation backlog', ! $backlog['alert'], $backlog['stale'].' operation(s) due for more than '.$backlog['age_minutes'].' min (threshold '.$backlog['threshold'].($backlog['jobs'] !== null ? ', '.$backlog['jobs'].' queued job(s)' : '').')', false);
    }

    private function secretsAndTls(): void
    {
        $driver = (string) config('onhost.secrets.driver');
        $this->add('secrets', 'secrets driver', $driver !== 'env', $driver.($driver === 'env' ? ' — environment secrets are for development only' : ($driver === 'db' ? ' (encrypted with APP_KEY; OpenBao recommended for HA)' : '')));
        $locations = function_exists('openssl_get_cert_locations') ? openssl_get_cert_locations() : [];
        $bundle = ini_get('curl.cainfo') ?: ini_get('openssl.cafile') ?: (string) ($locations['default_cert_file'] ?? '');
        $this->add('tls', 'CA bundle for outbound TLS', $bundle !== '' && is_file($bundle), $bundle !== '' ? $bundle : 'set curl.cainfo / openssl.cafile or install ca-certificates');
    }

    private function providers(ProviderInstanceService $instances): void
    {
        $regions = Region::query()->count();
        $this->add('providers', 'regions defined', $regions > 0, "{$regions} region(s)");
        $all = ProviderInstance::query()->platform()->orderBy('provider')->orderBy('key')->get();
        $this->add('providers', 'provider instances registered', $all->isNotEmpty(), $all->count().' instance(s): '.$all->pluck('key')->implode(', '));
        $health = IntegrationHealth::query()->get()->keyBy('provider_instance_id');
        foreach ($all as $instance) {
            $status = $instances->credentialStatus($instance);
            $missing = (array) ($status['missing'] ?? []);
            $h = $health->get($instance->id);
            $up = $h !== null && (bool) $h->up;
            $detail = ($missing !== [] ? 'missing credentials: '.implode(', ', $missing) : 'credentials stored').' · '.($h === null ? 'never probed' : ($up ? 'up' : 'down: '.mb_substr((string) $h->last_error, 0, 80)).' ('.$h->checked_at?->diffForHumans().')');
            $this->add('providers', "{$instance->key} ({$instance->provider}, {$instance->state})", $missing === [] && $up, $detail, $instance->state === 'active');
        }
        $roles = Node::query()->where('state', 'active')->get()->groupBy('role')->map->count();
        $this->add('providers', 'active nodes', $roles->isNotEmpty(), $roles->isEmpty() ? 'none — run Discover or register nodes' : $roles->map(fn ($n, $r) => "{$r}: {$n}")->implode(', '));
        // a panel on a version nobody verified takes no new orders (H530): the held ones, the baselines the adapter was never
        // verified on, and the panels whose version cannot be seen at all (an upgrade there would go unnoticed)
        $versioned = ProviderInstance::query()->platform()->whereIn('provider', PanelVersionGate::SELF_HOSTED)->where('state', '!=', 'disabled')->get();
        $named = fn (string $state) => $versioned->filter(fn (ProviderInstance $i) => data_get($i->version_gate, 'state') === $state)->map(fn (ProviderInstance $i) => $i->key.' '.data_get($i->version_gate, 'version'))->values()->all();
        $held = $named('held');
        $this->add('providers', 'no panel is held on an unverified version', $held === [], $held === [] ? 'none held' : implode(', ', $held).' — no new orders go there; php artisan onhost:integrations:versions', false);
        $baseline = $named('baseline');
        $this->add('providers', 'every panel runs a version its adapter was verified on', $baseline === [], $baseline === [] ? 'verified or accepted' : implode(', ', $baseline).' — in use before the version gate; check it, then onhost:integrations:versions --accept or upgrade', false);
        $silent = $versioned->filter(fn (ProviderInstance $i) => $i->provider !== 'pterodactyl' && (string) ($i->vendor_version ?? '') === '')->pluck('key')->all(); // the game panel's API has no version to give
        $this->add('providers', 'panels report their version', $silent === [], $silent === [] ? 'all of them' : implode(', ', $silent).' — an upgrade there would go unnoticed; check the API user\'s rights (ISPConfig: "Server functions")', false);
        $this->add('providers', 'WEDOS test mode off', ! config('onhost.wapi.test_mode'), config('onhost.wapi.test_mode') ? 'WEDOS_TEST_MODE=true makes registry operations dry runs' : '');
        $registrars = app(RegistrarClient::class)->instances();
        $this->add('providers', 'registrar available', $registrars !== [], $registrars === [] ? 'no usable registrar instance (wedos / subreg): domains cannot be registered' : implode(', ', array_map(fn ($i) => $i->key.' ('.$i->provider.')', $registrars)));
        $tlds = TldPolicy::query()->where('registrable', true)->pluck('tld')->all();
        $costs = RegistrarTldCost::query()->whereIn('tld', $tlds)->get()->groupBy('tld');
        $noCost = array_values(array_filter($tlds, fn ($t) => ! $costs->has($t)));
        $stale = $costs->flatten(1)->filter(fn ($c) => $c->isStale((int) config('onhost.domains.registrar.cost_ttl_hours', 24)))->map(fn ($c) => $c->registrar_provider.'/.'.$c->tld)->values()->all();
        $this->add('providers', 'registrar cost prices known for every TLD', $noCost === [] && $stale === [], ($noCost === [] ? count($tlds).' TLD(s) priced at '.$costs->flatten(1)->pluck('registrar_provider')->unique()->implode(', ') : 'no cost price for: .'.implode(', .', $noCost)).($stale !== [] ? ' · stale: '.implode(', ', $stale) : ''), false);
        // selling below the cheapest registrar is a pricing mistake, not a business decision: surface it before go-live
        $below = [];
        foreach ($costs as $tld => $rows) {
            try {
                $sell = app(CatalogService::class)->domainPrice((string) $tld, 'CZK')->register()->minor;
            } catch (\Throwable) {
                continue; // no CZK selling price
            }
            $cheapest = $rows->map(fn ($c) => $c->register_minor === null ? null : RegistrarPricing::toCzkMinor((int) $c->register_minor, $c->currency))->filter(fn ($v) => $v !== null)->min();
            if ($cheapest !== null && $sell < $cheapest) {
                $below[] = '.'.$tld.' ('.number_format($sell / 100, 0, ',', ' ').' < '.number_format($cheapest / 100, 0, ',', ' ').' Kč)';
            }
        }
        $this->add('providers', 'no TLD sold below its cheapest cost price', $below === [], $below === [] ? 'every priced TLD keeps a positive margin' : 'below cost: '.implode(', ', $below).' — raise the selling price (Nastavení → Domény a TLD) or pin a cheaper registrar', false);
        // every sellable plan must have somewhere to run: a placement (panel + server) or a schedulable node of its executor family
        $placements = PlanPlacement::query()->where('state', 'active')->with('providerInstance')->get();
        $usableProviders = $all->filter(fn ($i) => $i->isUsable())->pluck('provider')->unique()->all();
        $missing = [];
        foreach (Product::query()->where('state', 'active')->whereNotNull('executor')->get() as $product) {
            $family = PlacementService::COMPATIBLE[(string) $product->executor] ?? [(string) $product->executor];
            $pinned = $placements->first(fn ($p) => $p->product_key === $product->key && $p->providerInstance?->isUsable());
            if ($pinned === null && array_intersect($family, $usableProviders) === []) {
                $missing[] = $product->key.' ('.$product->executor.')';
            }
        }
        $this->add('providers', 'every product can be provisioned', $missing === [], $missing === [] ? $placements->count().' placement(s), executors covered' : 'no usable instance or placement for: '.implode(', ', $missing));
    }

    private function payments(): void
    {
        $gateway = (string) config('onhost.payments.default');
        $merchant = (string) config("onhost.payments.{$gateway}.merchant", '');
        $this->add('payments', "card gateway ({$gateway}) configured", $merchant !== '', $merchant !== '' ? "merchant {$merchant}" : 'merchant id missing — card payments fail with payment_provider_error');
        $this->add('payments', 'card gateway live mode', ! (bool) config("onhost.payments.{$gateway}.test", true), (bool) config("onhost.payments.{$gateway}.test", true) ? 'test flag on' : 'live');
        $this->add('payments', 'stored cards for automatic top-ups', app(AutoTopup::class)->supported(), app(AutoTopup::class)->supported() ? 'a gateway charges stored methods (recurring)' : 'no gateway charges stored methods — automatic top-ups only notify (COMGATE_MERCHANT + COMGATE_RECURRING)', false);
        $bank = (array) config('onhost.payments.bank');
        $this->add('payments', 'bank account for transfers', ($bank['iban'] ?? '') !== '' && ($bank['account_number'] ?? '') !== '', 'ONHOST_BANK_IBAN / ONHOST_BANK_ACCOUNT printed on proformas and top-up instructions');
        $this->add('payments', 'bank statement import', ($bank['fio_token'] ?? '') !== '', ($bank['fio_token'] ?? '') !== '' ? 'Fio API token set — onhost:bank:sync runs every 5 minutes' : 'ONHOST_BANK_FIO_TOKEN missing — transfers must be recorded by hand in Nastavení → Bankovní platby');
    }

    private function documents(): void
    {
        $entity = LegalEntity::query()->find((string) config('onhost.billing.legal_entity', 'onhost-cz'));
        $this->add('documents', 'legal entity exists', $entity !== null, (string) config('onhost.billing.legal_entity', 'onhost-cz'));
        if ($entity !== null) {
            $placeholder = str_starts_with((string) $entity->iban, 'CZ0000') || (string) $entity->iban === '';
            $this->add('documents', 'legal entity bank details real', ! $placeholder, $placeholder ? 'seeded placeholder IBAN — run LegalEntitySeeder with production values' : (string) $entity->iban);
            $this->add('documents', 'legal entity identification', (string) $entity->ico !== '' && (string) $entity->name !== '', "{$entity->name} · IČO {$entity->ico}");
        }
    }

    private function identity(): void
    {
        $this->add('identity', 'staff MFA required', (bool) config('onhost.identity.staff_mfa_required', true), config('onhost.identity.staff_mfa_required', true) ? '' : 'ONHOST_STAFF_MFA_REQUIRED=false');
        $demo = User::query()->whereIn('email', ['demo@onhost.cz', 'agentura@onhost.cz', 'admin@onhost.cz', 'noc@onhost.cz', 'finance@onhost.cz', 'support@onhost.cz'])->count();
        $this->add('identity', 'no development accounts', $demo === 0, $demo > 0 ? "{$demo} DevAccountSeeder account(s) present" : '');
        $staff = User::query()->where('is_staff', true)->count();
        $this->add('identity', 'staff users exist', $staff > 0, "{$staff} staff user(s)");
        // four eyes need two heads (docs/runbooks/approvals.md): with fewer than two people who may decide approvals, a critical action of the only one can never be approved
        $deciders = ApprovalService::deciders()->count();
        $fourEyes = ApprovalService::enabled();
        $this->add('identity', 'four eyes in effect', $fourEyes && $deciders >= 2, $fourEyes ? "{$deciders} member(s) of staff may decide approvals".($deciders >= 2 ? '' : ' — grant iam.approval.decide to a second person, or run ONHOST_FOUR_EYES=false deliberately') : 'ONHOST_FOUR_EYES=false: critical actions take one person and a step-up (single-operator mode)', false);
        $waiting = Approval::query()->where('state', 'pending')->where('expires_at', '>', now())->where('created_at', '<', now()->subHours(4))->count();
        $this->add('identity', 'no approval waiting for hours', $waiting === 0, $waiting === 0 ? '' : "{$waiting} request(s) older than four hours — /sprava/nastaveni/schvalovani", false);
    }

    private function mailAndObservability(): void
    {
        $mailer = (string) config('mail.default');
        $this->add('mail', 'transactional mailer', ! in_array($mailer, ['log', 'array'], true), $mailer);
        $this->add('mail', 'sender address set', (string) config('mail.from.address', '') !== '' && ! str_contains((string) config('mail.from.address'), 'example.com'), (string) config('mail.from.address'));
        $mail = app(MailHealth::class)->measure(); // H24: a configured mailer proves nothing about mail leaving
        $this->add('mail', 'outbox is leaving', $mail['state'] === 'ok', "sent {$mail['sent']} · errors {$mail['errors']} · waiting {$mail['waiting']} (oldest {$mail['oldest_minutes']} min) · gave up {$mail['dead']}".($mail['last_error'] !== null ? ' · '.$mail['last_error'] : ''));
        $this->add('observability', 'metrics token or allow-list', (string) config('onhost.metrics.token') !== '' || (array) config('onhost.metrics.allow_ips') !== [], (string) config('onhost.metrics.token') !== '' ? 'bearer token set' : 'allow-list only', false);
        $otlp = (string) config('onhost.observability.otlp_endpoint', ''); // §5u-2: spans exported and the console can open them
        $this->add('observability', 'traces exported and linked', $otlp !== '' && (string) config('onhost.observability.trace_url', '') !== '', $otlp === '' ? 'OTEL_EXPORTER_OTLP_ENDPOINT not set (infra/docker-compose.yml: otel-collector + tempo + grafana)' : ((string) config('onhost.observability.trace_url', '') === '' ? 'spans go to '.$otlp.' but ONHOST_TRACE_URL is empty — the console shows no trace links' : 'spans to '.$otlp), false);
        $scanner = app(VirusScanner::class); // §5t-6: uploads are scanned by a clamd with fresh signatures
        $clam = $scanner->version();
        $fresh = $clam !== null && $clam['signatures_at'] !== null && strtotime($clam['signatures_at']) > time() - 2 * 86400;
        $this->add('files', 'virus scanner (clamd)', $fresh, ! $scanner->enabled() ? 'ONHOST_CLAMAV_HOST not set — uploads are not scanned' : ($clam === null ? 'clamd not reachable' : $clam['engine'].' · db '.$clam['database'].' · signatures '.($clam['signatures_at'] ?? '?').($fresh ? '' : ' (older than 2 days — is freshclam running?)')));
        $this->add('observability', 'console relay key', (string) config('onhost.console.relay_key') !== '', 'ONHOST_CONSOLE_RELAY_KEY');
        // game panels (audit §5f): every registered panel needs the client key for the server tools and at least one mapped template to sell
        foreach (ProviderInstance::query()->platform()->where('provider', 'pterodactyl')->whereIn('state', ['active', 'draining', 'maintenance'])->get() as $panel) {
            $prereqs = (array) data_get($panel->capabilities, 'prereqs', []);
            $clientApi = (string) ($prereqs['client_api'] ?? 'unknown');
            $this->add('game', "{$panel->key}: client API key", $clientApi === 'ok', $clientApi === 'unknown' ? 'not checked yet — run onhost:nodes:check or the prerequisites check' : ($clientApi === 'ok' ? 'accepted' : "{$clientApi} — store client_key with onhost:integrations:secret {$panel->key} client_key --check"), $panel->state === 'active');
            $eggs = (array) $panel->option('eggs', []);
            $bad = array_keys(array_filter((array) ($prereqs['game']['eggs'] ?? []), fn ($state) => $state !== 'ok'));
            $this->add('game', "{$panel->key}: templates mapped", $eggs !== [] && $bad === [], $eggs === [] ? 'no template mapped (staff console → Šablony her) — game orders would fail' : (count($eggs).' mapped'.($bad !== [] ? '; not on the panel: '.implode(', ', $bad) : '')), $panel->state === 'active');
            $daemonsDown = array_column(array_filter((array) ($prereqs['game']['nodes'] ?? []), fn ($n) => ($n['daemon'] ?? '') === 'down'), 'name');
            $this->add('game', "{$panel->key}: node daemons", $daemonsDown === [], $daemonsDown === [] ? count((array) ($prereqs['game']['nodes'] ?? [])).' node(s) checked' : 'not answering: '.implode(', ', $daemonsDown), false);
        }
        $risk = (array) config('onhost.orders.risk');
        $this->add('orders', 'intake pre-check', (bool) ($risk['enabled'] ?? false), ($risk['enabled'] ?? false) ? 'hold at score '.($risk['hold_score'] ?? 60).' · '.count((array) ($risk['disposable_domains'] ?? [])).' disposable domains' : 'ONHOST_ORDER_RISK=false — suspicious paid orders provision immediately', false);
        $this->add('notifications', 'staff digest recipients', trim((string) config('onhost.notifications.staff_digest_to', '')) !== '', 'ONHOST_STAFF_DIGEST_TO', false);
        $this->add('observability', 'log channel', ! in_array(config('logging.default'), ['single'], true) || ! $this->production, (string) config('logging.default').' (production: ship to Loki/stack)', false);
    }
}
