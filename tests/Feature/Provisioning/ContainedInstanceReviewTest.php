<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Domains\Models\RegistrarConnection;
use Onhost\Domain\Domains\RegistrarConnectionService;
use Onhost\Domain\Provisioning\IntegrationHealthProbe;
use Onhost\Domain\Provisioning\Jobs\TransferGameArchive;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\NodeOrders;
use Onhost\Domain\Provisioning\OperationRunner;
use Onhost\Domain\Provisioning\ProviderInstanceService;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflow\Step;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Provisioning\Workflows\GameMigrationWorkflow;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\NodeOrderProvider;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ProviderHealth;

/*
 * TASK-0045 review round 1 (security MEDIUM-A…F and the LOWs): the places that still reached a contained panel, or
 * turned the refusal into a failure, or lifted a containment behind the owner's back.
 */

final class ReviewCountingAdapter implements ProviderAdapter
{
    public static int $calls = 0;

    /** @var (callable(): void)|null runs inside health(), i.e. while a probe is in flight */
    public static $during = null;

    public static function providerKey(): string
    {
        return 'ispconfig';
    }

    public static function adapterVersion(): string
    {
        return '1.0.0';
    }

    public static function supportedVendorVersions(): array
    {
        return [];
    }

    public function capabilities(): array
    {
        return [];
    }

    public function health(): ProviderHealth
    {
        self::$calls++;
        if (is_callable(self::$during)) {
            (self::$during)();
        }

        return new ProviderHealth(true, '3.2.11');
    }

    public function vendorVersion(): ?string
    {
        return '3.2.11';
    }
}

/**
 * Steps that swallow the registry's refusal the way real workflow steps do (ServiceActionWorkflow: `catch (DomainError $e)`
 * → `fail(…, ['error' => $e->error])`; many others: `catch (Throwable)` → `fail($e->getMessage())`), and one that waits.
 */
final class ReviewSwallowingStep implements Step
{
    public function __construct(private readonly string $mode) {}

    public function label(): string
    {
        return 'Krok, který chybu spolkne';
    }

    public function run(StepContext $context): StepResult
    {
        if ($this->mode === 'first-done' && $context->get('first_done') !== true) {
            return StepResult::done(['first_done' => true]);
        }
        if ($this->mode === 'best-effort') {
            try {
                $context->adapter((string) $context->desired('second_instance'))->health(); // a cleanup on the second panel, best effort (CertificateWorkflow)
            } catch (Throwable) {
            }

            return StepResult::fail('certificate install failed: the web server refused the certificate');
        }
        if ($this->mode === 'wait') {
            $context->adapter()->health();

            return StepResult::wait(new AsyncHandle('review_task', 'task-1', null, [], 5, 1800));
        }
        try {
            $context->adapter((string) $context->desired('second_instance'))->health();
        } catch (DomainError $e) {
            return $this->mode === 'throwable' ? StepResult::fail('the second panel failed: '.$e->getMessage()) : StepResult::fail('záloha se nepodařila: '.$e->getMessage(), $e->status >= 500, ['error' => $e->error], 120);
        }

        return StepResult::done();
    }

    public function poll(StepContext $context, AsyncHandle $handle): StepResult
    {
        $context->adapter()->health();

        return StepResult::wait($handle);
    }
}

final class ReviewWorkflow implements Workflow
{
    public static int $compensated = 0;

    public static function kind(): string
    {
        return 'test.review';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-ispconfig';
    }

    public function steps(Operation $operation): array
    {
        $mode = (string) data_get($operation->desired, 'mode', 'domain-error');

        return $mode === 'first-done' ? [new ReviewSwallowingStep('first-done'), new ReviewSwallowingStep('first-done')] : [new ReviewSwallowingStep($mode)];
    }

    public function compensate(StepContext $context): void
    {
        self::$compensated++;
    }
}

const REVIEW_ENV = ['PVE_KEPT_TOKEN' => 't', 'PVE_LIVE_TOKEN' => 't', 'ISP_KEPT_REMOTE_USER' => 'onhost', 'ISP_KEPT_REMOTE_PASSWORD' => 'secret'];

function reviewInstance(string $key, string $state = 'active', array $attributes = []): ProviderInstance
{
    $instance = ProviderInstance::query()->create(array_merge(['key' => $key, 'provider' => 'ispconfig', 'name' => $key, 'base_url' => 'https://'.$key.'.mgmt.test:8080', 'secret_ref' => 'env://'.strtoupper(str_replace('-', '_', $key)), 'state' => $state,
        'capabilities' => ['web.create' => true], 'options' => ['verify_tls' => false, 'server_id' => 1]], $attributes));
    app(ProviderRegistry::class)->useAdapter($instance->id, new ReviewCountingAdapter);

    return $instance;
}

function reviewOperation(ProviderInstance $instance, array $desired = [], array $attributes = []): Operation
{
    return Operation::query()->create(array_merge(['provider_instance_id' => $instance->id, 'kind' => 'test.review', 'workflow' => ReviewWorkflow::class, 'state' => Operation::PENDING, 'step' => 0, 'steps_total' => 1,
        'actor_type' => 'system', 'idempotency_key' => 'review-'.uniqid(), 'correlation_id' => 'c-'.uniqid(), 'desired' => $desired, 'context' => [], 'queue' => 'provider-ispconfig',
        'queued_at' => now(), 'next_run_at' => now(), 'retry_until' => now()->addHours(4)], $attributes));
}

beforeEach(function () {
    ReviewCountingAdapter::$calls = 0;
    ReviewCountingAdapter::$during = null;
    ReviewWorkflow::$compensated = 0;
    Http::fake();
    // env:// secrets of the lab instances, so a call that is not refused really gets as far as the panel (removed after each test)
    foreach (REVIEW_ENV as $key => $value) {
        $_ENV[$key] = $value;
    }
});

afterEach(function () {
    foreach (array_keys(REVIEW_ENV) as $key) {
        unset($_ENV[$key]);
    }
});

// ── MEDIUM-A: node ordering and the ISPConfig restore command read the instance's credentials themselves ──

it('orders no node through a contained instance: NodeOrders hands out no vendor for it', function () {
    $orders = app(NodeOrders::class);
    $vendor = Mockery::mock(NodeOrderProvider::class);
    $orders->register('review', fn (array $credentials) => $vendor);
    $contained = reviewInstance('pve-kept', 'contained', ['provider' => 'proxmox', 'options' => ['node_order' => ['driver' => 'review']]]);
    $active = reviewInstance('pve-live', 'active', ['provider' => 'proxmox', 'options' => ['node_order' => ['driver' => 'review']]]);

    expect($orders->for($contained))->toBeNull()
        ->and($orders->for($active))->toBe($vendor);
    $active->forceFill(['state' => 'disabled'])->save();
    expect($orders->for($active))->toBeNull();
});

it('refuses to restore an ISPConfig site through a contained instance, before anything is sent', function () {
    reviewInstance('isp-kept', 'contained');

    $code = Artisan::call('onhost:ispconfig:restore-site', ['domain' => 'shop.cz', '--instance' => 'isp-kept', '--dry-run' => true]);

    expect($code)->toBe(1)->and(Artisan::output())->toContain('contained');
    Http::assertNothingSent();
});

// ── MEDIUM-B: steps that turn the refusal into their own failure ──

it('parks a run whose step swallowed the refusal into its own failure, instead of failing and compensating it', function (string $mode) {
    $source = reviewInstance('isp-src-'.$mode);
    $target = reviewInstance('isp-dst-'.$mode, 'contained');
    $operation = reviewOperation($source, ['mode' => $mode, 'second_instance' => $target->id]);

    app(OperationRunner::class)->tick($operation);
    $operation->refresh();

    expect($operation->state)->toBe(Operation::PENDING)
        ->and($operation->next_run_at->isFuture())->toBeTrue()
        ->and(data_get($operation->error, 'detail.contained'))->toBeTrue()
        ->and(data_get($operation->error, 'detail.instance'))->toBe('isp-dst-'.$mode)
        ->and($operation->attempts)->toBe(0)
        ->and(ReviewWorkflow::$compensated)->toBe(0);
})->with(['domain-error', 'throwable']);

it('lets the game data transfer say the panel is contained, and parks the migration so the transfer starts again after the lift', function () {
    $source = reviewInstance('ptero-src');
    $target = reviewInstance('ptero-dst', 'contained');
    $operation = Operation::query()->create(['provider_instance_id' => $source->id, 'kind' => 'game.migrate', 'workflow' => GameMigrationWorkflow::class, 'state' => Operation::WAITING, 'step' => 6, 'steps_total' => 10,
        'actor_type' => 'system', 'idempotency_key' => 'review-gmig', 'correlation_id' => 'c-gmig', 'desired' => [], 'context' => ['source_instance_id' => $source->id, 'target_instance_id' => $target->id], 'queue' => 'provider-pterodactyl',
        'external_handle' => (new AsyncHandle('onhost_transfer', 'x', null, [], 15, 6 * 3600))->toArray(), 'attempts' => 1, 'queued_at' => now(), 'started_at' => now(), 'next_run_at' => now()->subSecond(), 'retry_until' => now()->addHours(4)]);

    (new TransferGameArchive($operation->id))->handle(app(ProviderRegistry::class), app(CacheRepository::class));
    expect(app(CacheRepository::class)->get(TransferGameArchive::cacheKey($operation->id)))->toMatchArray(['state' => 'contained', 'instance_state' => 'contained']);

    app(OperationRunner::class)->tick($operation);
    $operation->refresh();

    expect($operation->state)->toBe(Operation::PENDING) // not FAILED: the transfer refused nothing at the panel, it was never sent
        ->and($operation->step)->toBe(6)
        ->and($operation->external_handle)->toBeNull() // the step runs again from its start after the lift: it dispatches the transfer anew
        ->and(data_get($operation->error, 'detail.contained'))->toBeTrue()
        // the record stays until the step runs again (run() forgets it before it dispatches): review round 2 LOW
        ->and(app(CacheRepository::class)->get(TransferGameArchive::cacheKey($operation->id)))->toMatchArray(['state' => 'contained']);
});

// ── MEDIUM-C: the staff switch of a customer's registrar account ──

it('never writes over a containment when staff switch a customer registrar account on or off', function () {
    [, $org] = $this->customerWithOrganization();
    $registrar = reviewInstance('wedos-c-kept', 'contained', ['provider' => 'wedos', 'organization_id' => $org->id]);
    $dns = reviewInstance('wedos-zone-c-kept', 'active', ['provider' => 'wedos_zone', 'organization_id' => $org->id]);
    $connection = RegistrarConnection::query()->create(['organization_id' => $org->id, 'provider' => 'wedos', 'label' => 'WEDOS', 'login' => 'a@b.cz', 'secret_ref' => 'db://registrar-connections/x',
        'registrar_instance_id' => $registrar->id, 'dns_instance_id' => $dns->id, 'state' => 'active']);
    $service = app(RegistrarConnectionService::class);
    $context = CommandContext::system('review');

    $service->setEnabled($connection, false, $context, 'abuse check');
    expect($registrar->fresh()->state)->toBe('contained')->and($dns->fresh()->state)->toBe('disabled');
    $service->setEnabled($connection->fresh(), true, $context);
    expect($registrar->fresh()->state)->toBe('contained')->and($dns->fresh()->state)->toBe('active');
});

// ── MEDIUM-D: a probe that ends an expired maintenance lock ──

it('does not lift a containment set while a probe of the maintenance lock was in flight', function () {
    $instance = reviewInstance('isp-lock', 'maintenance', ['maintenance_until' => now()->subHour(), 'state_reason' => 'upgrade']);
    ReviewCountingAdapter::$during = fn () => ProviderInstance::query()->whereKey($instance->id)->update(['state' => 'contained', 'state_reason' => 'incident']);

    app(IntegrationHealthProbe::class)->probeInstance($instance->fresh());

    expect(ReviewCountingAdapter::$calls)->toBe(1)
        ->and($instance->fresh()->state)->toBe('contained')
        ->and($instance->fresh()->state_reason)->toBe('incident');
});

// ── MEDIUM-E: parking before the claim ──

it('never parks a run another worker holds, nor rewrites a finished one', function () {
    $instance = reviewInstance('isp-held', 'contained');
    $held = reviewOperation($instance, [], ['state' => Operation::RUNNING, 'started_at' => now(), 'attempts' => 1]);

    app(OperationRunner::class)->tick($held);

    expect($held->fresh()->state)->toBe(Operation::RUNNING)->and($held->fresh()->error)->toBeNull();
});

// ── MEDIUM-F: a long containment is not counted as the run's own time, and a parked run is not asked every few minutes for ever ──

it('does not time out a waiting run for the time it spent parked, and parks less and less often', function () {
    $instance = reviewInstance('isp-wait');
    $operation = reviewOperation($instance, ['mode' => 'wait']);
    $runner = app(OperationRunner::class);
    expect($runner->tick($operation))->toBe(Operation::WAITING); // the panel has a task (timeout 30 min)

    $instance->forceFill(['state' => 'contained'])->save();
    $gaps = [];
    for ($i = 0; $i < 4; $i++) {
        $this->travelTo($operation->fresh()->next_run_at->copy()->addSecond());
        $before = now();
        $runner->tick($operation);
        $gaps[] = (int) round($before->diffInMinutes($operation->fresh()->next_run_at, true));
    }
    expect($gaps[1])->toBeGreaterThan($gaps[0])->and($gaps[3])->toBeGreaterThan($gaps[2]) // backing off
        ->and(max($gaps))->toBeLessThanOrEqual(OperationRunner::PARK_MAX_MINUTES);

    $this->travel(2)->hours(); // far beyond the task's 30 minutes, all of it parked
    $instance->forceFill(['state' => 'active'])->save();
    $this->travelTo($operation->fresh()->next_run_at->copy()->addSecond());

    expect($runner->tick($operation))->toBe(Operation::WAITING) // was: FAILED ("provider task exceeded its timeout") + compensation
        ->and(ReviewWorkflow::$compensated)->toBe(0)
        ->and(data_get($operation->fresh()->context, '_parked.since'))->toBeNull()
        ->and((int) data_get($operation->fresh()->context, '_parked.seconds'))->toBeGreaterThan(7200);
});

it('names the runs parked for more than a week in the doctor, as a warning', function () {
    $instance = reviewInstance('isp-week', 'contained');
    $operation = reviewOperation($instance);
    app(OperationRunner::class)->tick($operation);
    $this->travel(8)->days();

    Artisan::call('onhost:doctor', ['--json' => true]);
    $row = collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', 'no operation parked for more than 7 days');

    expect($row)->not->toBeNull()->and($row['status'])->toBe('WARN')->and($row['detail'])->toContain($operation->id)
        // cancelling runs no compensation (OperationService::cancel): the advice must not send staff there (review round 2)
        ->and($row['detail'])->not->toContain('provisioning.operation.cancel')->toContain('compensation');
});

// ── LOWs ──

it('counts the attempt of a tick that completed a step before it parked', function () {
    $source = reviewInstance('isp-first');
    $target = reviewInstance('isp-second', 'contained');
    $operation = reviewOperation($source, ['mode' => 'first-done', 'second_instance' => $target->id], ['steps_total' => 2]);

    app(OperationRunner::class)->tick($operation);

    expect($operation->fresh()->step)->toBe(1)->and($operation->fresh()->state)->toBe(Operation::PENDING)
        ->and($operation->fresh()->attempts)->toBe(1); // the tick did run a step; only a tick that ran nothing gives its attempt back
});

it('asks for a reason when a containment is set or lifted', function () {
    $instance = reviewInstance('isp-reason');
    $service = app(ProviderInstanceService::class);
    $context = CommandContext::system('review');

    expect(fn () => $service->setState($instance, 'contained', $context))->toThrow(DomainError::class, 'reason');
    $service->setState($instance, 'contained', $context, 'staging Path A');
    expect(fn () => $service->setState($instance->fresh(), 'active', $context, '  '))->toThrow(DomainError::class, 'reason')
        ->and($instance->fresh()->state)->toBe('contained');
    $service->setState($instance->fresh(), 'active', $context, 'phase 2 go-ahead');
    expect($instance->fresh()->state)->toBe('active');
});

// ── review round 2 ──

it('keeps one park record for a run parked behind a contained second panel for days, backs off, and never re-runs its step early', function () {
    $source = reviewInstance('isp-days-src');
    $target = reviewInstance('isp-days-dst', 'contained');
    $operation = reviewOperation($source, ['mode' => 'domain-error', 'second_instance' => $target->id]);
    $runner = app(OperationRunner::class);
    $start = now()->copy();

    $runner->tick($operation);
    $since = data_get($operation->fresh()->context, '_parked.since');
    $waits = [(int) data_get($operation->fresh()->error, 'detail.next_look_minutes')];
    $looks = 1;
    // the queue job re-dispatches a PENDING run every ten minutes at most (RunOperation): simulate eight days of that
    for ($minute = 10; $minute <= 8 * 24 * 60; $minute += 10) {
        $this->travelTo($start->copy()->addMinutes($minute));
        $due = $operation->fresh()->next_run_at->lte(now());
        $runner->tick($operation);
        if ($due) {
            $looks++;
            $waits[] = (int) data_get($operation->fresh()->error, 'detail.next_look_minutes');
        }
    }

    $fresh = $operation->fresh();
    expect($fresh->state)->toBe(Operation::PENDING)
        ->and(data_get($fresh->context, '_parked.since'))->toBe($since) // one containment, one record
        ->and($fresh->attempts()->count())->toBe($looks) // the step ran only when the run was due, not at every re-dispatch
        ->and($looks)->toBeLessThan(45)
        ->and(array_slice($waits, 0, 6))->toBe([15, 30, 60, 120, 240, OperationRunner::PARK_MAX_MINUTES])
        ->and(max($waits))->toBe(OperationRunner::PARK_MAX_MINUTES)
        ->and(ReviewWorkflow::$compensated)->toBe(0);

    Artisan::call('onhost:doctor', ['--json' => true]);
    $row = collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', 'no operation parked for more than 7 days');
    expect($row['status'])->toBe('WARN')->and($row['detail'])->toContain($operation->id);

    // staff lift the containment: the parked run is due at once, not at its next look up to six hours away
    app(ProviderInstanceService::class)->setState($target->fresh(), 'active', CommandContext::system('review'), 'phase 2 go-ahead');
    expect($operation->fresh()->next_run_at->lte(now()))->toBeTrue();
    app(ProviderRegistry::class)->useAdapter($target->id, new ReviewCountingAdapter); // setState() drops the held adapter; the lab has no credentials
    expect($runner->tick($operation))->toBe(Operation::SUCCEEDED)
        ->and((int) data_get($operation->fresh()->context, '_parked.seconds'))->toBeGreaterThan(7 * 86400);
});

it('fails and compensates a step that swallowed a best-effort refusal and then failed for a reason of its own', function () {
    $source = reviewInstance('isp-cert-src');
    $target = reviewInstance('isp-cert-dst', 'disabled');
    $operation = reviewOperation($source, ['mode' => 'best-effort', 'second_instance' => $target->id]);

    app(OperationRunner::class)->tick($operation);
    $fresh = $operation->fresh();

    expect($fresh->state)->toBe(Operation::FAILED) // not parked for ever behind a panel that has nothing to do with the failure
        ->and(ReviewWorkflow::$compensated)->toBe(1)
        ->and(data_get($fresh->error, 'message'))->toContain('the web server refused the certificate')
        ->and($fresh->attempts()->value('error'))->toContain('a contained panel was refused during this step'); // the refusal is on record in the attempt
});
