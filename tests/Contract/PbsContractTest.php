<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Pbs\PbsBackupProvider;

it('lists snapshots with verification state and verifies via task', function () {
    $_ENV['PBS_CZ1_TOKEN_ID'] = 'onhost@pbs!cp';
    $_ENV['PBS_CZ1_TOKEN_SECRET'] = 'pbs-secret';
    $instance = ProviderInstance::query()->create(['key' => 'pbs-cz1', 'provider' => 'pbs', 'name' => 'PBS', 'base_url' => 'https://pbs.mgmt.test:8007', 'secret_ref' => 'env://PBS_CZ1', 'state' => 'active', 'options' => ['verify_tls' => false]]);
    $registry = app(ProviderRegistry::class);
    $registry->register('pbs', PbsBackupProvider::class);
    /** @var PbsBackupProvider $pbs */
    $pbs = $registry->forInstance($instance);

    Http::fake([
        'pbs.mgmt.test:8007/api2/json/admin/datastore/main/snapshots*' => Http::response(['data' => [
            ['backup-type' => 'vm', 'backup-id' => '1042', 'backup-time' => 1757100000, 'size' => 21474836480, 'verification' => ['state' => 'ok', 'upid' => 'x'], 'protected' => true, 'ns' => 'cust/org_a'],
        ]]),
        'pbs.mgmt.test:8007/api2/json/admin/datastore/main/verify' => Http::response(['data' => 'UPID:pbs:0001:verify']),
        'pbs.mgmt.test:8007/api2/json/nodes/localhost/tasks/*/status' => Http::response(['data' => ['status' => 'stopped', 'exitstatus' => 'OK']]),
        'pbs.mgmt.test:8007/api2/json/admin/datastore/main/status' => Http::response(['data' => ['total' => 100, 'used' => 60, 'avail' => 40, 'gc-status' => ['upid' => 'x']]]),
    ]);
    $snapshots = $pbs->listSnapshots('main', 'cust/org_a', '1042');
    expect($snapshots)->toHaveCount(1)->and($snapshots[0]['protected'])->toBeTrue()->and($snapshots[0]['verification']['state'])->toBe('ok');
    $verify = $pbs->verify('main', 'cust/org_a', 'vm', '1042', 1757100000);
    expect($pbs->awaitStatus($verify->async)->state)->toBe(AsyncStatus::SUCCEEDED);
    expect($pbs->datastoreStatus('main')['avail'])->toBe(40);
    expect($pbs->capabilities()['backup.delete'])->toBeFalse();
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'PBSAPIToken=onhost@pbs!cp:pbs-secret'));
});
