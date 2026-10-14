<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Catalog\CatalogRevisions;
use Onhost\Domain\Notifications\DigestService;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Workflows\VerifiedRestoreWorkflow;
use Onhost\Domain\Services\Addons;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Models\VerifiedRestoreRun;
use Onhost\Domain\Services\SecurityPosture;
use Onhost\Domain\Services\ServiceService;
use Onhost\Domain\Services\Web\RestoreTarget;
use Onhost\Domain\Services\Web\RestoreTest;
use Onhost\Domain\Services\Web\VerifiedRestore;
use Onhost\Domain\Services\Web\VerifiedRestoreProtocol;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * W2-4 "Ověřená obnova": a paid add-on that restores the newest COMPLETE set of a web service into an isolated copy (never the
 * live site), checks it (database import, HTTP 200, a login only with a configured test identity), measures RPO and RTO, removes
 * the copy and checks it is gone, and writes a protocol of facts. The copy itself is the staging facility on the node
 * (StagingRestoreTarget); here a double stands in for it and records what it was asked.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('local');
    Date::setTestNow('2026-10-12 03:00:00');
});

afterEach(fn () => Date::setTestNow());

/**
 * A copy that answers as told and remembers every call.
 *
 * @param  array{ready_after?:int, ready?:bool, restore_fail?:bool, probe?:?int, gone?:bool, prepare_error?:string}  $options
 */
function vrDouble(array $options = []): object
{
    $double = new class($options) implements RestoreTarget
    {
        /** @var list<array<int, mixed>> */
        public array $calls = [];

        private int $looks = 0;

        public function __construct(private readonly array $options) {}

        public function prepare(Service $production, VerifiedRestoreRun $run, CommandContext $context): array
        {
            $this->calls[] = ['prepare', $production->id];
            if (isset($this->options['prepare_error'])) {
                return ['service_id' => null, 'domain' => null, 'error' => $this->options['prepare_error']];
            }

            return ['service_id' => 'svc_vr_copy', 'domain' => 'shop-cz-rvtest.web.onhost.cz', 'error' => null];
        }

        public function ready(VerifiedRestoreRun $run): ?bool
        {
            $this->calls[] = ['ready'];
            if (($this->options['ready'] ?? true) === false) {
                return false;
            }

            return ++$this->looks > (int) ($this->options['ready_after'] ?? 1) ? true : null;
        }

        public function restore(VerifiedRestoreRun $run, Service $production, Backup $backup): array
        {
            $this->calls[] = ['restore', $backup->id, (string) data_get($run->target, 'service_id')];
            $failed = (bool) ($this->options['restore_fail'] ?? false);

            return [
                'files' => ['status' => VerifiedRestoreRun::PASS, 'detail' => 'site-files.tar.gz rozbaleno do kopie'],
                'databases' => ['status' => $failed ? VerifiedRestoreRun::FAIL : VerifiedRestoreRun::PASS, 'detail' => $failed ? 'shop_db: 0/3 tabulek (import selhal)' : 'shop_db: 3/3 tabulek',
                    'rows' => [['database' => 'shop_db', 'tables' => 3, 'restored' => $failed ? 0 : 3, 'verified' => ! $failed]]],
                'created' => [['remote_id' => '901', 'name' => 'ohvrcopy_rvabcdef']],
                'suppression' => ['applied' => ['Plánované úlohy WordPressu (WP-Cron) jsou vypnuté (DISABLE_WP_CRON).'], 'not_applied' => ['Funkci PHP mail() na sdíleném uzlu platforma pro jeden web vypnout neumí.']],
                'probe_allowed' => ! $failed, 'probe_refusal' => '', 'wordpress' => true,
            ];
        }

        public function probe(VerifiedRestoreRun $run): array
        {
            $this->calls[] = ['probe', (string) data_get($run->target, 'domain')];
            $status = $this->options['probe'] ?? 200;

            return ['status' => $status, 'detail' => 'GET https://'.data_get($run->target, 'domain').'/ → HTTP '.$status];
        }

        public function login(VerifiedRestoreRun $run, array $identity): array
        {
            $this->calls[] = ['login', $identity['user']];

            return ['status' => VerifiedRestoreRun::PASS, 'detail' => 'testovací účet se přihlásil'];
        }

        public function cleanup(VerifiedRestoreRun $run): array
        {
            $this->calls[] = ['cleanup', (array) data_get($run->target, 'databases', [])];

            return ['requested' => true, 'detail' => ($this->options['gone'] ?? true) ? 'odstranění vyžádáno' : 'databáze ohvrcopy_rvabcdef: panel odmítl smazání'];
        }

        public function cleanedUp(VerifiedRestoreRun $run): ?bool
        {
            $this->calls[] = ['cleaned'];

            return (bool) ($this->options['gone'] ?? true);
        }

        /** @return list<string> */
        public function verbs(): array
        {
            return array_values(array_unique(array_map(fn (array $c) => (string) $c[0], $this->calls)));
        }
    };
    app()->instance(RestoreTarget::class, $double);

    return $double;
}

/** A finished platform set of the service on the backup disk (database dump + files); `incomplete` marks it as BackupCompleteness would. */
function vrSet(Service $service, string $finishedAt, bool $incomplete = false): Backup
{
    $set = FinalArchive::PREFIX.'/'.$service->organization_id.'/'.$service->id.'-'.str_replace([' ', ':'], '-', $finishedAt).'-'.substr(md5($finishedAt), 0, 4);
    Storage::disk('local')->put($set.'/database-shop_db.sql', "CREATE TABLE `orders` (id int);\nCREATE TABLE `customers` (id int);\nCREATE TABLE `invoices` (id int);\n");
    Storage::disk('local')->put($set.'/site-files.tar.gz', str_repeat('x', 64));
    Storage::disk('local')->put($set.'/manifest.json', (string) json_encode(['parts' => ['database-shop_db.sql' => [], 'site-files.tar.gz' => []], 'databases' => ['shop_db']]));
    $meta = ['set' => $set, 'family' => 'web'] + ($incomplete ? ['incomplete' => ['missing' => ['shop_db'], 'problems' => [], 'checked_at' => now()->toIso8601String()]] : []);

    return Backup::query()->create(['service_id' => $service->id, 'organization_id' => $service->organization_id, 'provider_instance_id' => $service->provider_instance_id,
        'kind' => 'scheduled', 'state' => 'completed', 'started_at' => $finishedAt, 'finished_at' => $finishedAt, 'protected' => false,
        'retention_until' => now()->addDays(30), 'meta' => $meta]);
}

/** The add-on bought for a web service, delivered through the Addons registry. */
function vrAddon(Service $parent, string $cadence = 'monthly'): Service
{
    $addon = Service::query()->create([
        'organization_id' => $parent->organization_id, 'product_key' => VerifiedRestore::ADDON, 'family' => 'addon', 'name' => 'Ověřená obnova', 'state' => ServiceStateMachine::ACTIVE, 'activated_at' => now(),
        'region_code' => $parent->region_code, 'provider_instance_id' => $parent->provider_instance_id, 'node_id' => $parent->node_id, 'entitlements' => ['verified_restore' => $cadence],
        'sla_class' => $parent->sla_class, 'tags' => ['parent_service_id' => $parent->id], 'desired_spec' => ['parent_service_id' => $parent->id, 'addon' => VerifiedRestore::ADDON],
    ]);
    app(Addons::class)->apply($parent->fresh(), $addon);

    return $addon->fresh();
}

function vrOperation(): Operation
{
    return Operation::query()->where('kind', VerifiedRestoreWorkflow::kind())->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
}

it('restores the newest complete set into an isolated copy, measures RPO and RTO and writes a protocol of facts', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $addon = vrAddon($service);
    $backup = vrSet($service, '2026-10-11 22:00:00'); // five hours before the run
    $copy = vrDouble(['ready_after' => 2]);

    expect($service->fresh()->entitlements['verified_restore'])->toBe('monthly')
        ->and(data_get($addon->tags, 'addon.patch'))->toBe(['verified_restore' => 'monthly']);

    $stats = app(RestoreTest::class)->tick();
    expect($stats['verified'])->toBe(1);
    $operation = vrOperation();
    expect($operation->service_id)->toBeNull() // the run holds no lock on the live service
        ->and(data_get($operation->desired, 'service_id'))->toBe($service->id)
        ->and(data_get($operation->desired, 'backup_id'))->toBe($backup->id);
    expect(driveOperation($operation)->state)->toBe(Operation::SUCCEEDED, json_encode($operation->fresh()->error));

    $run = VerifiedRestoreRun::query()->where('service_id', $service->id)->sole();
    expect($run->outcome)->toBe('passed')->and($run->state)->toBe(VerifiedRestoreRun::FINISHED)->and($run->trigger)->toBe('schedule')
        ->and($run->rpo_seconds)->toBe(5 * 3600)
        ->and($run->rto_seconds)->toBeGreaterThan(0)->toBe((int) $run->started_at->diffInSeconds($run->checks_done_at, true));
    $statuses = collect($run->steps)->mapWithKeys(fn (array $s) => [$s['key'] => $s['status']])->all();
    expect($statuses)->toBe([
        'backup_selected' => 'pass', 'target_prepared' => 'pass', 'files_restored' => 'pass', 'database_imported' => 'pass',
        'side_effects_suppressed' => 'not_verified', // PHP mail() could not be switched off, and the protocol says so
        'http_200' => 'pass', 'login' => 'not_verified', 'cleanup' => 'pass',
    ]);
    expect($run->step('login')['detail'])->toContain('nebylo ověřeno')
        ->and($run->suppression['not_applied'][0])->toContain('mail()')
        ->and(collect($run->steps)->every(fn (array $s) => is_string($s['at']) && $s['at'] !== ''))->toBeTrue();

    // the copy was asked for exactly this, and the backup came from the platform's disk into the copy, never onto production
    expect($copy->verbs())->toBe(['prepare', 'ready', 'restore', 'probe', 'cleanup', 'cleaned']);
    expect(collect($copy->calls)->firstWhere(0, 'restore'))->toBe(['restore', $backup->id, 'svc_vr_copy'])
        ->and(collect($copy->calls)->firstWhere(0, 'cleanup')[1])->toBe([['remote_id' => '901', 'name' => 'ohvrcopy_rvabcdef']]);

    // the result reaches the security posture and the monthly report
    $health = RestoreTest::health($service->fresh());
    expect($health)->toMatchArray(['outcome' => 'ok', 'kind' => 'verified', 'run_id' => $run->id, 'rpo_seconds' => 5 * 3600]);
    $control = collect(app(SecurityPosture::class)->run($service->fresh(), ['findings' => [], 'name' => 'shop.cz'])['controls'])->firstWhere('id', 'backup-restore');
    expect($control['status'])->toBe('pass')->and($control['evidence'][0]['source'])->toBe('verified_restore');
    $lines = app(DigestService::class)->customerMonthly($org)['sections'][(string) ($service->hostname ?: ($service->label ?: $service->name))];
    expect(collect($lines)->first(fn ($l) => str_starts_with($l, 'Ověřená obnova')))->toContain('RPO 5 h')->toContain('RTO')->toContain('ověřeno 12. 10. 2026');

    // the customer lists the protocol and downloads the PDF of their own service
    $this->actingAs($owner, 'sanctum')->getJson("/v1/services/{$service->id}/verified-restores")->assertOk()
        ->assertJsonPath('data.0.id', $run->id)->assertJsonPath('data.0.outcome', 'passed')->assertJsonPath('data.0.rpo_seconds', 5 * 3600)
        ->assertJsonPath('data.0.pdf', "/v1/services/{$service->id}/verified-restores/{$run->id}/pdf");
    $pdf = $this->actingAs($owner, 'sanctum')->get("/v1/services/{$service->id}/verified-restores/{$run->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr((string) $pdf->getContent(), 0, 4))->toBe('%PDF');

    // and it is not asked for again until the month is up
    expect(app(RestoreTest::class)->tick()['verified'])->toBe(0);
    Date::setTestNow(now()->addDays(31));
    vrDouble();
    expect(app(RestoreTest::class)->tick()['verified'])->toBe(1);
});

it('passes over a newer incomplete set, restores the previous complete one and names the skipped set', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    vrAddon($service, 'quarterly');
    $complete = vrSet($service, '2026-10-10 02:00:00');
    $incomplete = vrSet($service, '2026-10-11 02:00:00', incomplete: true);
    $copy = vrDouble();

    app(RestoreTest::class)->tick();
    driveOperation(vrOperation());

    $run = VerifiedRestoreRun::query()->where('service_id', $service->id)->sole();
    expect($run->backup_id)->toBe($complete->id)->and($run->outcome)->toBe('passed')
        ->and($run->rpo_seconds)->toBe(49 * 3600) // the age of the set that was really restored
        ->and($run->skipped_sets)->toHaveCount(1)
        ->and($run->skipped_sets[0]['backup_id'])->toBe($incomplete->id)->and($run->skipped_sets[0]['reason'])->toContain('shop_db');
    expect(collect($copy->calls)->firstWhere(0, 'restore')[1])->toBe($complete->id);
});

it('fails the protocol when the restore into the copy fails, still removes the copy, and tells the customer and staff', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    vrAddon($service);
    vrSet($service, '2026-10-11 22:00:00');
    $copy = vrDouble(['restore_fail' => true]);

    app(RestoreTest::class)->tick();
    expect(driveOperation(vrOperation())->state)->toBe(Operation::SUCCEEDED);

    $run = VerifiedRestoreRun::query()->where('service_id', $service->id)->sole();
    expect($run->outcome)->toBe('failed')
        ->and($run->stepStatus('database_imported'))->toBe('fail')
        ->and($run->stepStatus('http_200'))->toBe('not_verified') // a copy whose data did not come back is not requested
        ->and($run->stepStatus('cleanup'))->toBe('pass');
    expect($copy->verbs())->toContain('cleanup')->not->toContain('probe');
    expect(RestoreTest::health($service->fresh())['outcome'])->toBe('failed');
    expect(collect(app(SecurityPosture::class)->run($service->fresh(), ['findings' => [], 'name' => 'shop.cz'])['controls'])->firstWhere('id', 'backup-restore')['status'])->toBe('fail');

    app(OutboxPublisher::class)->relayPending();
    $told = Notification::query()->where('event', 'service.verified_restore.failed')->get();
    expect($told->pluck('audience')->unique()->sort()->values()->all())->toBe(['customer', 'internal'])
        ->and($told->firstWhere('audience', 'customer')->body)->toContain('nezměnili')->toContain('database_imported');
});

it('reports a copy that could not be removed to staff, without calling the restore failed', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    vrAddon($service);
    vrSet($service, '2026-10-11 22:00:00');
    vrDouble(['gone' => false]);

    app(RestoreTest::class)->tick();
    driveOperation(vrOperation());

    $run = VerifiedRestoreRun::query()->where('service_id', $service->id)->sole();
    expect($run->outcome)->toBe('passed')->and($run->stepStatus('cleanup'))->toBe('fail')
        ->and($run->step('cleanup')['detail'])->toContain('panel odmítl smazání');

    app(OutboxPublisher::class)->relayPending();
    $left = Notification::query()->where('event', 'service.verified_restore.cleanup_failed')->get();
    expect($left)->toHaveCount(1)->and($left->first()->audience)->toBe('internal')->and($left->first()->title)->toContain('shop-cz-rvtest.web.onhost.cz')
        ->and(Notification::query()->where('event', 'service.verified_restore.failed')->exists())->toBeFalse();
});

it('stops scheduling once the add-on is cancelled', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $addon = vrAddon($service);
    vrSet($service, '2026-10-11 22:00:00');
    vrDouble();

    app(ServiceService::class)->requestAction($addon, 'terminate', CommandContext::system('test')->withScope($org->id), 'vr-addon-off', ['reason' => 'nechci']);

    expect($service->fresh()->entitlements)->not->toHaveKey('verified_restore')
        ->and(data_get($addon->fresh()->tags, 'addon.restored'))->toBe(['verified_restore' => null]);
    expect(app(RestoreTest::class)->tick()['verified'])->toBe(0)
        ->and(Operation::query()->where('kind', VerifiedRestoreWorkflow::kind())->exists())->toBeFalse();
});

it('shows a protocol only to the organization that owns the service', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    vrAddon($service);
    vrSet($service, '2026-10-11 22:00:00');
    vrDouble();
    app(RestoreTest::class)->tick();
    driveOperation(vrOperation());
    $run = VerifiedRestoreRun::query()->where('service_id', $service->id)->sole();

    [$stranger] = $this->customerWithOrganization();
    $this->actingAs($stranger, 'sanctum')->getJson("/v1/services/{$service->id}/verified-restores")->assertNotFound();
    $this->actingAs($stranger, 'sanctum')->get("/v1/services/{$service->id}/verified-restores/{$run->id}/pdf")->assertNotFound();

    // a run of another service is not found through this one either
    $other = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Druhý web', 'hostname' => 'druhy.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'entitlements' => [], 'tags' => [], 'desired_spec' => []]);
    app('auth')->forgetGuards();
    $this->actingAs($owner, 'sanctum')->get("/v1/services/{$other->id}/verified-restores/{$run->id}/pdf")->assertNotFound();
});

it('lets staff start a run through the bus and refuses a customer who asks for one', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    vrSet($service, '2026-10-11 22:00:00');
    vrDouble();

    $this->actingAs($owner, 'sanctum')->withHeader('Idempotency-Key', 'vr-customer')
        ->postJson("/v1/services/{$service->id}/actions", ['action' => 'restore.verify'])->assertStatus(403)->assertJsonPath('error', 'operator_only');

    app('auth')->forgetGuards();
    $this->actingAs($this->steppedUpStaff('platform_owner'), 'sanctum');
    $id = $this->withHeader('Idempotency-Key', 'vr-staff')->postJson("/v1/staff/services/{$service->id}/actions", ['action' => 'restore.verify', 'reason' => 'ověření před migrací'])
        ->assertStatus(202)->json('operation_id');
    expect(driveOperation(Operation::query()->findOrFail($id))->state)->toBe(Operation::SUCCEEDED);
    $run = VerifiedRestoreRun::query()->where('service_id', $service->id)->sole();
    expect($run->trigger)->toBe('staff')->and($run->outcome)->toBe('passed');

    // one run per service at a time
    VerifiedRestoreRun::query()->whereKey($run->id)->update(['state' => VerifiedRestoreRun::RUNNING]);
    expect(fn () => app(ServiceService::class)->requestAction($service->fresh(), VerifiedRestore::ACTION, CommandContext::system('test')->withScope($org->id), 'vr-second'))
        ->toThrow(DomainError::class, 'už běží');
});

it('prints a protocol of facts with its limits and no word that promises', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    vrAddon($service);
    vrSet($service, '2026-10-11 22:00:00');
    vrDouble();
    app(RestoreTest::class)->tick();
    driveOperation(vrOperation());
    $run = VerifiedRestoreRun::query()->where('service_id', $service->id)->sole();

    $html = app(VerifiedRestoreProtocol::class)->html($run, $service->fresh());
    $text = html_entity_decode(strip_tags($html));
    expect($text)->not->toMatch('/bezpečn|garant/iu')
        ->and($text)->toContain('ne celé datacentrum')->toContain('RPO')->toContain('RTO')->toContain('neověřeno')->toContain('prošlo')
        ->and($text)->toContain('2026-10-11 22:00:00'); // the set's own timestamp
    // the template and the fixed texts carry no promise either (not only what the filter would catch)
    $template = (string) file_get_contents(resource_path('views/restore/verified-protocol.blade.php'));
    expect($template.implode(' ', VerifiedRestoreProtocol::limits()))->not->toMatch('/bezpečn|garant/iu');
    expect(substr(app(VerifiedRestoreProtocol::class)->render($run, $service->fresh()), 0, 4))->toBe('%PDF');
});

it('sells the add-on only for a web hosting, at a price staff set', function () {
    [, $org] = $this->customerWithOrganization();
    $staging = featureWebService($org, 'aapanel');
    $staging->forceFill(['tags' => ['staging_of' => 'svc_other', 'parent_service_id' => 'svc_other']])->save();

    expect(Addons::sellable(VerifiedRestore::ADDON))->toBeTrue();
    $addon = new Service(['product_key' => VerifiedRestore::ADDON, 'entitlements' => ['verified_restore' => 'monthly'], 'tags' => []]);
    expect(fn () => app(Addons::class)->apply($staging, $addon))->toThrow(DomainError::class);

    // the catalogue defines the product without a price: it is created as a draft at zero and staff price it
    $definition = CatalogRevisions::PRODUCTS[VerifiedRestore::ADDON];
    expect($definition['state'])->toBe('draft')->and($definition['meta']['admin_priced'])->toBeTrue();
    foreach ($definition['plans'] as $plan) {
        expect($plan)->not->toHaveKey('prices');
    }
    expect(array_column($definition['plans'], 'entitlements'))->toBe([['verified_restore' => 'monthly'], ['verified_restore' => 'quarterly']]);
});

it('starts no more scheduled runs at once than the platform allows, and asks again later', function () {
    config(['onhost.verified_restore.max_parallel' => 1]);
    Queue::fake(); // the runs stay asked for, not yet running
    [, $org] = $this->customerWithOrganization();
    $first = featureWebService($org, 'aapanel');
    $second = $first->replicate(['hostname', 'name_prefix'])->fill(['hostname' => 'druhy.cz']);
    $second->save();
    ProviderBinding::query()->create(['service_id' => $second->id, 'provider_instance_id' => $second->provider_instance_id, 'remote_type' => 'site', 'remote_id' => '43', 'remote_node' => 'aapanel-managed01',
        'meta' => ['name' => 'druhy.cz'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "vr-second:{$second->id}", 'adapter_version' => '1.0.0']);
    foreach ([$first, $second] as $service) {
        vrAddon($service);
        vrSet($service, '2026-10-11 22:00:00');
    }
    vrDouble();

    $stats = app(RestoreTest::class)->tick();
    expect($stats['verified'])->toBe(1)->and($stats['skipped'])->toBe(1)
        ->and(Operation::query()->where('kind', VerifiedRestoreWorkflow::kind())->count())->toBe(1);
});
