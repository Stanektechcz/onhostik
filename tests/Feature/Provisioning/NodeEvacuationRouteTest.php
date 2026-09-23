<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;

/*
 * Emptying a node is an operator's job on any node, but the only way to ask for it went through a URL that says
 * `game`: `integrations/{instance}/game/nodes/{node}/evacuate`. A web node — the kind a hosting company actually has
 * to retire — could not be emptied from the console at all, even after web hostings learned to move. The route is
 * the node's own now, it finds the node by its id, and it answers with what it started and what stays behind.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('empties a web node from its own route and says what stays behind', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $mail = featureMailService($org); // a family with no saga of its own: it stays, and the operator is told
    $node = Node::query()->findOrFail($service->node_id);
    $mail->forceFill(['node_id' => $node->id])->save();

    $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');
    $result = $this->withHeader('Idempotency-Key', 'evac-web-1')
        ->postJson("/v1/staff/nodes/{$node->id}/evacuate", ['reason' => 'hardware swap'])->assertStatus(202)->json();

    expect($result['drained'])->toBeTrue()
        ->and($result['staying'])->toBe(1)
        ->and(collect($result['skipped'])->pluck('service_id')->all())->toBe([$mail->id])
        ->and(collect($result['started'])->pluck('service_id')->all())->toBe([$service->id])
        ->and(Node::query()->findOrFail($node->id)->state)->toBe('draining');
});

it('is staff only', function () {
    [$customer, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');

    $this->actingAs($customer, 'sanctum')->withHeader('X-Organization', $org->id)->withHeader('Idempotency-Key', 'evac-web-2')
        ->postJson("/v1/staff/nodes/{$service->node_id}/evacuate", [])->assertForbidden();
});

it('says so when the node is not there', function () {
    $this->actingAs($this->staff('infrastructure_admin'), 'sanctum');

    $this->withHeader('Idempotency-Key', 'evac-web-3')
        ->postJson('/v1/staff/nodes/nd_nothing/evacuate', [])->assertNotFound();
});
