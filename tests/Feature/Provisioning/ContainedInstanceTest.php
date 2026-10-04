<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\IntegrationHealthProbe;
use Onhost\Domain\Provisioning\Models\IntegrationHealth;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\OperationRunner;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflow\Step;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\ControlPlaneStatus;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Web\BackupScheduler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\AsyncHandle;
use Onhost\Providers\Contracts\ProviderAdapter;
use Onhost\Providers\Contracts\ProviderHealth;

/*
 * TASK-0045 — a contained panel stays contained (staging pre-mortem 2026-09-27, BLOCKER). The instance state
 * `disabled` was not a control: ProviderRegistry::forInstance handed out an adapter with the stored credentials whatever
 * the state, the operation runner never asked, and the backup scheduler pruned backups on the panels inline, as the
 * system. `contained` (and `disabled`) is now refused for every actor, the system included, before any adapter exists.
 */

/** An adapter that only counts: every call it receives would have been a call to the panel. */
final class ContainedCountingAdapter implements ProviderAdapter
{
    public static int $calls = 0;

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

        return new ProviderHealth(true, '3.2.11');
    }

    public function vendorVersion(): ?string
    {
        return '3.2.11';
    }
}

/** One step that asks its instance (and, when the run names one, a second instance) for its health. */
final class ContainedAskingStep implements Step
{
    public function label(): string
    {
        return 'Zeptat se panelu';
    }

    public function run(StepContext $context): StepResult
    {
        $context->adapter()->health();
        $second = $context->desired('second_instance');
        if (is_string($second) && $second !== '') {
            $context->adapter($second)->health();
        }

        return StepResult::done(['asked' => true]);
    }

    public function poll(StepContext $context, AsyncHandle $handle): StepResult
    {
        return $this->run($context);
    }
}

final class ContainedAskingWorkflow implements Workflow
{
    public static int $compensated = 0;

    public static function kind(): string
    {
        return 'test.contained';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-ispconfig';
    }

    public function steps(Operation $operation): array
    {
        return [new ContainedAskingStep];
    }

    public function compensate(StepContext $context): void
    {
        self::$compensated++;
    }
}

function containedLabInstance(string $key, string $state = 'active'): ProviderInstance
{
    $instance = ProviderInstance::query()->create(['key' => $key, 'provider' => 'ispconfig', 'name' => $key, 'base_url' => 'https://'.$key.'.mgmt.test:8080', 'secret_ref' => 'env://'.strtoupper(str_replace('-', '_', $key)), 'state' => $state,
        'capabilities' => ['web.create' => true], 'options' => ['verify_tls' => false, 'server_id' => 1]]);
    app(ProviderRegistry::class)->useAdapter($instance->id, new ContainedCountingAdapter);

    return $instance;
}

function containedOperation(ProviderInstance $instance, array $desired = []): Operation
{
    return Operation::query()->create(['provider_instance_id' => $instance->id, 'kind' => 'test.contained', 'workflow' => ContainedAskingWorkflow::class, 'state' => Operation::PENDING, 'step' => 0, 'steps_total' => 1,
        'actor_type' => 'system', 'idempotency_key' => 'contained-'.uniqid(), 'correlation_id' => 'c-'.uniqid(), 'desired' => $desired, 'context' => [], 'queue' => 'provider-ispconfig',
        'queued_at' => now(), 'next_run_at' => now(), 'retry_until' => now()->addHours(4)]);
}

/** The slug of the DomainError a call ends in, the class of anything else it throws, or null when it returns. */
function containedRefusal(callable $call): ?string
{
    try {
        $call();
    } catch (DomainError $e) {
        return $e->error;
    } catch (Throwable $e) {
        return get_class($e);
    }

    return null;
}

beforeEach(function () {
    ContainedCountingAdapter::$calls = 0;
    ContainedAskingWorkflow::$compensated = 0;
    Http::fake(); // anything that still reached a panel would be recorded here
});

it('refuses an adapter for a contained or disabled instance to every caller, the system included, before any adapter exists', function () {
    $registry = app(ProviderRegistry::class);
    foreach (['contained', 'disabled'] as $state) {
        $instance = containedLabInstance('isp-'.$state, $state);

        expect(containedRefusal(fn () => $registry->forInstance($instance)))->toBe('provider_instance_contained', "state {$state}: forInstance")
            ->and(containedRefusal(fn () => $registry->forKey($instance->key)))->toBe('provider_instance_contained', "state {$state}: forKey")
            // an access being tried before it is stored goes to the same host: refused as well
            ->and(containedRefusal(fn () => $registry->trial($instance, ['remote_user' => 'x', 'remote_password' => 'y'])))->toBe('provider_instance_contained', "state {$state}: trial");
    }
    expect(ContainedCountingAdapter::$calls)->toBe(0);
    Http::assertNothingSent();

    // a worker holding the instance from before the containment asks with a stale model: the stored state decides
    $active = containedLabInstance('isp-live');
    $held = ProviderInstance::query()->findOrFail($active->id);
    expect(containedRefusal(fn () => $registry->forInstance($held)->health()))->toBeNull();
    ProviderInstance::query()->whereKey($active->id)->update(['state' => 'contained']);
    expect($held->state)->toBe('active')
        ->and(containedRefusal(fn () => $registry->forInstance($held)))->toBe('provider_instance_contained')
        ->and(ContainedCountingAdapter::$calls)->toBe(1);
});

it('parks an operation of a contained instance instead of running or failing it, and runs it once the containment is lifted', function () {
    $instance = containedLabInstance('isp-parked', 'contained');
    $operation = containedOperation($instance);

    $state = app(OperationRunner::class)->tick($operation);
    $operation->refresh();

    expect($state)->toBe(Operation::PENDING)
        ->and($operation->state)->toBe(Operation::PENDING) // re-runnable, not FAILED: nothing went wrong at the panel, nobody asked it
        ->and($operation->attempts)->toBe(0)
        ->and($operation->next_run_at->isFuture())->toBeTrue()
        ->and(data_get($operation->error, 'message'))->toContain('contained')
        ->and(data_get($operation->error, 'retryable'))->toBeTrue()
        ->and(data_get($operation->error, 'detail.instance'))->toBe('isp-parked')
        ->and($operation->attempts()->count())->toBe(0)
        ->and(ContainedCountingAdapter::$calls)->toBe(0)
        ->and(ContainedAskingWorkflow::$compensated)->toBe(0);
    Http::assertNothingSent();

    // the owner lifts the containment: the same operation runs from where it stood
    $instance->forceFill(['state' => 'active'])->save();
    $done = driveOperation($operation);
    expect($done->state)->toBe(Operation::SUCCEEDED)->and(ContainedCountingAdapter::$calls)->toBe(1);
});

it('parks a run whose step reaches a second, contained instance, without counting it as a failed attempt', function () {
    $source = containedLabInstance('isp-source');
    $target = containedLabInstance('isp-target', 'contained');
    $operation = containedOperation($source, ['second_instance' => $target->id]);

    app(OperationRunner::class)->tick($operation);
    $operation->refresh();

    expect($operation->state)->toBe(Operation::PENDING)
        ->and($operation->attempts)->toBe(0)
        ->and($operation->next_run_at->isFuture())->toBeTrue()
        ->and(data_get($operation->error, 'detail.instance'))->toBe('isp-target')
        ->and($operation->attempts()->value('outcome'))->toBe('parked')
        ->and(ContainedAskingWorkflow::$compensated)->toBe(0)
        ->and(ContainedCountingAdapter::$calls)->toBe(1); // the source was asked; the contained target never was
});

it('keeps the backup scheduler off the services of a contained instance', function () {
    [, $org] = $this->customerWithOrganization();
    $contained = featureWebService($org, 'ispconfig');
    [, $other] = $this->customerWithOrganization();
    $live = featureWebService($other, 'aapanel');
    foreach ([$contained, $live] as $service) {
        BackupPolicy::query()->create(['service_id' => $service->id, 'product_key' => 'backup-plus', 'schedule' => ['frequency' => 'daily'], 'retention' => ['days' => 30, 'generations' => 30], 'offsite' => false, 'restore_test' => [], 'state' => 'active']);
    }
    ProviderInstance::query()->whereKey($contained->provider_instance_id)->update(['state' => 'contained']);

    $stats = app(BackupScheduler::class)->tick();

    expect(Operation::query()->where('service_id', $contained->id)->exists())->toBeFalse()
        ->and(Operation::query()->where('service_id', $live->id)->exists())->toBeTrue()
        ->and($stats['started'])->toBe(1)
        ->and($stats['missed'])->toBe(0) // not a missed slot of the customer's: the owner stopped the panel
        ->and(data_get(Service::query()->findOrFail($contained->id)->tags, 'backup_schedule.missed'))->toBeNull();
});

it('leaves a contained instance out of the health probe and shows its services as not manageable', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $instance = ProviderInstance::query()->findOrFail($service->provider_instance_id);
    $instance->forceFill(['state' => 'contained', 'state_reason' => 'staging Path A'])->save();

    $stats = app(IntegrationHealthProbe::class)->run();

    expect(IntegrationHealth::query()->where('provider_instance_id', $instance->id)->exists())->toBeFalse()
        ->and($stats['checked'])->toBe(0);
    Http::assertNothingSent();
    $control = ControlPlaneStatus::of($service->fresh());
    expect($control['available'])->toBeFalse()->and($control['state'])->toBe('disabled');
});

it('sets and lifts containment only through the staff state action, with a fresh step-up', function () {
    $instance = containedLabInstance('isp-staff');
    $staff = $this->staff('infrastructure_admin');
    $this->actingAs($staff, 'sanctum');

    $this->postJson('/v1/staff/integrations/isp-staff/state', ['state' => 'contained', 'reason' => 'staging Path A'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect($instance->fresh()->state)->toBe('active');

    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    $this->withHeader('Idempotency-Key', 'contain-1')->postJson('/v1/staff/integrations/isp-staff/state', ['state' => 'contained', 'reason' => 'staging Path A'])->assertOk();
    expect($instance->fresh()->state)->toBe('contained')->and($instance->fresh()->state_reason)->toBe('staging Path A');

    // an edit of the instance cannot lift it: neither a state in the edit, nor a new panel address
    $this->withHeader('Idempotency-Key', 'contain-2')->putJson('/v1/staff/integrations/isp-staff', ['key' => 'isp-staff', 'provider' => 'ispconfig', 'base_url' => 'https://isp-staff.mgmt.test:8080', 'state' => 'active'])
        ->assertStatus(409)->assertJsonPath('error', 'instance_contained');
    $this->withHeader('Idempotency-Key', 'contain-3')->putJson('/v1/staff/integrations/isp-staff', ['key' => 'isp-staff', 'provider' => 'ispconfig', 'base_url' => 'https://other-host.mgmt.test:8080', 'confirm_host_change' => true])->assertSuccessful();
    expect($instance->fresh()->state)->toBe('contained');

    $this->withHeader('Idempotency-Key', 'contain-4')->postJson('/v1/staff/integrations/isp-staff/state', ['state' => 'active', 'reason' => 'phase 2 go-ahead'])->assertOk();
    expect($instance->fresh()->state)->toBe('active');
    Http::assertNothingSent();
});

it('lists the contained instances in the doctor as a warning, never a failure', function () {
    containedLabInstance('isp-kept', 'contained')->forceFill(['state_reason' => 'staging Path A'])->save();

    foreach ([false, true] as $production) {
        if ($production) {
            app()->instance('env', 'production');
        }
        Artisan::call('onhost:doctor', ['--json' => true]);
        $row = collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', 'no provider instance is contained');

        expect($row)->not->toBeNull()
            ->and($row['status'])->toBe('WARN')
            ->and($row['detail'])->toContain('isp-kept')->toContain('staging Path A');
    }
});
