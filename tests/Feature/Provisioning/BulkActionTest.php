<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\BulkActionService;
use Onhost\Domain\Provisioning\Models\BulkJob;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;

/*
 * Bulk staff actions (audit §5e-7): one action across the services a filter selects, one ordinary operation each,
 * a job that reports per service; refusals stay in the report, data-destroying actions are not offered.
 */

it('rolls a PHP version out to every active site of an instance as one job with a per-service report', function () {
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    [$user, $org] = $this->customerWithOrganization();
    $first = featureWebService($org, 'aapanel');
    $instance = ProviderInstance::query()->where('key', 'aapanel-managed01')->firstOrFail();
    $second = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting Start', 'hostname' => 'blog.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'node_id' => $first->node_id, 'entitlements' => $first->entitlements, 'desired_spec' => ['domain' => 'blog.cz', 'php_version' => '8.2'], 'sla_class' => 'standard', 'activated_at' => now()]);
    ProviderBinding::query()->create(['service_id' => $second->id, 'provider_instance_id' => $instance->id, 'remote_type' => 'site', 'remote_id' => '42', 'remote_node' => 'aapanel-managed01', 'meta' => ['name' => 'blog.cz', 'path' => '/www/wwwroot/blog.cz'], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => 'bulk-test:second', 'adapter_version' => '1.0.0']);
    $suspended = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting Start', 'hostname' => 'old.cz', 'state' => ServiceStateMachine::SUSPENDED, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id, 'entitlements' => [], 'desired_spec' => [], 'sla_class' => 'standard']);

    $staff = $this->staff('shared_hosting_admin');
    $this->actingAs($staff, 'sanctum');
    // the filter must name something; destroying actions are not offered
    $this->postJson('/v1/staff/bulk-jobs', ['action' => 'php.set', 'params' => ['version' => '8.3'], 'filter' => []])->assertStatus(422);
    $this->postJson('/v1/staff/bulk-jobs', ['action' => 'terminate', 'params' => [], 'filter' => ['provider_instance_id' => $instance->id]])->assertStatus(422);

    $job = $this->postJson('/v1/staff/bulk-jobs', ['action' => 'php.set', 'params' => ['version' => '8.3'], 'filter' => ['provider_instance_id' => $instance->id], 'reason' => 'PHP 8.1 EOL'])->assertCreated()->json();
    // the test queue runs operations inline, so the job may already be finished when the response comes back
    expect($job['total'])->toBe(2)->and($job['refused'])->toBe(0)->and($job['running'] + $job['succeeded'])->toBe(2)
        ->and(collect($job['items'])->pluck('service_id')->sort()->values()->all())->toBe(collect([$first->id, $second->id])->sort()->values()->all())
        ->and(collect($job['items'])->pluck('service_id'))->not->toContain($suspended->id);

    driveOperations();
    $done = $this->getJson("/v1/staff/bulk-jobs/{$job['id']}")->assertOk()->json('data');
    expect($done['state'])->toBe('finished')->and($done['succeeded'])->toBe(2)->and($done['failed'])->toBe(0)->and($done['finished_at'])->not->toBeNull()
        ->and(Service::query()->findOrFail($first->id)->desired_spec['php_version'])->toBe('8.3')->and(Service::query()->findOrFail($second->id)->desired_spec['php_version'])->toBe('8.3');
    Http::assertSentCount(2 + collect(Http::recorded())->filter(fn ($p) => ! str_contains($p[0]->url(), 'SetPHPVersion'))->count());
    expect(collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), 'SetPHPVersion'))->count())->toBe(2);

    // the list, the refusal report and the page
    expect(collect($this->getJson('/v1/staff/bulk-jobs')->assertOk()->json('data'))->pluck('id')->all())->toContain($job['id']);
    $refusal = app(BulkActionService::class)->start(['service_ids' => [$first->id]], 'proxies.set', ['items' => [['name' => 'x', 'target' => 'ftp://nope']]], $this->contextFor($staff, null), 'bad params');
    expect($refusal->refused)->toBe(1)->and($refusal->items[0]['error'])->toBe('action_param_invalid')->and($refusal->state)->toBe('finished');
    expect(BulkJob::query()->count())->toBe(2);
    $this->actingAs($user, 'sanctum')->getJson('/v1/staff/bulk-jobs')->assertForbidden();
    $this->actingAs($staff)->get('/sprava/nastaveni/hromadne-akce')->assertOk()->assertSee('Hromadné akce')->assertSee('php.set');
});
