<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Node;
use Onhost\Domain\Provisioning\ServiceMigrationService;
use Onhost\Platform\Commands\CommandContext;

/*
 * Evacuating a node is what an operator does before they touch the hardware: it drains the node and moves everything
 * off it. Only two families have a migration saga (game servers and virtual machines), and the query that looked for
 * services to move asked **only for those** — so the web hostings on a shared node were not moved, not refused, and
 * not even listed: the operator read `started: 2, skipped: 0` and believed the node was empty. Everything living on
 * the node is counted now, and what cannot move says so, by name and with the reason.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('names the services that stay on the node instead of leaving them out of the answer', function () {
    Http::preventStrayRequests();
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');           // a web hosting moves now (WebMigrationWorkflow)
    $mail = featureMailService($org);                        // its family still has no saga: it stays, and says so
    $node = Node::query()->findOrFail($service->node_id);
    $mail->forceFill(['node_id' => $node->id])->save();

    $result = app(ServiceMigrationService::class)->evacuate($node, null, 'hardware swap', CommandContext::system('test'));

    expect($result['drained'])->toBeTrue()
        ->and($result['staying'])->toBe(1)                       // the operator is told the node is not empty
        ->and(array_column($result['skipped'], 'service_id'))->toBe([$mail->id])
        ->and($result['skipped'][0]['error'])->toBe('migration_unsupported')
        ->and($result['skipped'][0]['family'])->toBe('mail')
        ->and(collect($result['started'])->pluck('service_id')->all())->toBe([$service->id]);
});

it('leaves a node with nothing on it saying nothing stays', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $node = Node::query()->findOrFail($service->node_id);
    $service->forceFill(['node_id' => null])->save();

    $result = app(ServiceMigrationService::class)->evacuate($node->refresh(), null, 'hardware swap', CommandContext::system('test'));

    expect($result['staying'])->toBe(0)->and($result['skipped'])->toBe([]);
});
