<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Models\Region;

/*
 * TASK-0150: the staff API registers a Penpot node. ProviderInstanceService::upsertNode allowed role `penpot` (TASK-0123), but
 * the controller's allow-list did not, so the documented run order (docs/runbooks/penpot.md, step 4: POST
 * /v1/staff/integrations/{instance}/nodes with role penpot) answered 422 and the node could only be made by hand in the database.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('registers a Penpot node through the staff API, born qualifying', function () {
    Region::query()->firstOrCreate(['code' => 'cz1'], ['name' => 'Praha', 'country' => 'CZ', 'state' => 'active']);
    ProviderInstance::query()->create(['key' => 'penpot-cz1', 'provider' => 'penpot', 'name' => 'Penpot node cz1', 'region_code' => 'cz1', 'base_url' => 'ssh://198.51.100.20', 'secret_ref' => 'env://PENPOT_CZ1', 'state' => 'active', 'capabilities' => ['penpot.stack' => true], 'options' => ['ssh_host' => '198.51.100.20', 'ssh_fingerprint' => 'SHA256:'.str_repeat('A', 43)]]);
    $admin = $this->staff('infrastructure_admin');
    $this->actingAs($admin, 'sanctum');
    app(StepUpService::class)->grant($admin, 'totp', null, '127.0.0.1');

    $this->postJson('/v1/staff/integrations/penpot-cz1/nodes', ['name' => 'penpot01', 'role' => 'penpot', 'region_code' => 'cz1', 'failure_domain' => 'rack-a', 'capacity' => ['cpu_cores' => 8, 'ram_mb' => 24576, 'disk_gb' => 250], 'tags' => ['public_ipv4' => '198.51.100.20']])
        ->assertCreated()->assertJsonPath('name', 'penpot01');

    $node = Node::query()->where('name', 'penpot01')->firstOrFail();
    expect($node->role)->toBe('penpot')->and($node->state)->toBe(Node::QUALIFYING)->and($node->isSchedulable())->toBeFalse();

    // the allow-list still refuses a role nobody runs
    $this->postJson('/v1/staff/integrations/penpot-cz1/nodes', ['name' => 'penpot02', 'role' => 'kitchen'])->assertStatus(422);
});
