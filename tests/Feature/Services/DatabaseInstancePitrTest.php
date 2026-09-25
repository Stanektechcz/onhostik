<?php

declare(strict_types=1);

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\DatabaseInstance;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;

/*
 * Owner decision 2 (2026-09-25): point-in-time recovery is not provided. A managed database sold on a version that still
 * says `pitr_days` used to get an instance row claiming `pitr = true`; nothing takes WAL archives, so the row lied. A new
 * instance never claims it now; an instance that already exists keeps its stored flag (no mass change of existing rows).
 */

function pitrDataService(array $entitlements = ['vcpu' => 2, 'ram_mb' => 4096, 'nvme_gb' => 40, 'pitr_days' => 7, 'backup_days' => 14]): Service
{
    [, $org] = test()->customerWithOrganization();

    return Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'database', 'family' => 'data', 'name' => 'DB S', 'hostname' => 'db-pitr.cust.onhost.cz', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1',
        'entitlements' => $entitlements, 'desired_spec' => ['family' => 'data', 'engine' => 'postgresql-16', 'entitlements' => $entitlements], 'sla_class' => 'standard', 'tags' => ['billing' => 'included'],
    ]);
}

it('does not let a newly activated managed database claim point-in-time recovery, whatever its plan version says', function () {
    $service = pitrDataService();

    app(ServiceService::class)->activate($service, CommandContext::system('test:pitr'), new Operation, ['ipv4' => '198.51.100.20']);

    $instance = DatabaseInstance::query()->where('service_id', $service->id)->sole();
    expect($instance->pitr)->toBeFalse()->and($instance->engine)->toBe('postgresql')->and($instance->port)->toBe(5432)->and($instance->host)->toBe('198.51.100.20');
});

it('leaves the stored pitr flag of an existing instance alone when the service is activated again', function () {
    $service = pitrDataService();
    DatabaseInstance::query()->create(['service_id' => $service->id, 'engine' => 'postgresql', 'version' => '16', 'host' => 'old-host', 'port' => 5432, 'pitr' => true, 'external_access' => false, 'allowlist' => [], 'state' => 'active']);

    app(ServiceService::class)->activate($service, CommandContext::system('test:pitr'), new Operation, ['ipv4' => '198.51.100.21']);

    $instance = DatabaseInstance::query()->where('service_id', $service->id)->sole();
    expect($instance->pitr)->toBeTrue()->and($instance->host)->toBe('198.51.100.21');
});
