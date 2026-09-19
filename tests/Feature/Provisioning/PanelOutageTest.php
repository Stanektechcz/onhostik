<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\OperationService;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Providers\Contracts\Naming;

/*
 * The control system and the services are two things (Brain card H02). When a panel is unreachable the customer's
 * service keeps its state, the change they asked for waits in a durable queue — and once the panel is back it is carried
 * out exactly once, however often the queue hands the job over and however often the customer presses the button.
 */

beforeEach(fn () => Http::preventStrayRequests());

it('keeps the service as it is while the panel is away, and carries the waiting change out exactly once when it is back', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    $panelUp = false;
    $panel = ['databases' => [], 'created' => 0, 'unreachable' => 0];
    Http::fake(function (Request $request) use (&$panelUp, &$panel) {
        if (! $panelUp) {
            $panel['unreachable']++;

            throw new ConnectionException('cURL error 7: Failed to connect to managed01 port 8888: Connection refused');
        }
        if (str_contains($request->url(), 'table=databases')) {
            return Http::response(['data' => array_values($panel['databases'])]);
        }
        if (str_contains($request->url(), 'AddDatabase')) {
            $panel['created']++;
            $panel['databases'][] = ['id' => 9, 'name' => $request->data()['name'], 'username' => $request->data()['db_user'], 'codeing' => 'utf8mb4'];

            return Http::response(['status' => true, 'msg' => 'ok']);
        }

        return Http::response(['status' => true, 'msg' => 'ok', 'data' => []]);
    });
    $this->actingAs($owner, 'sanctum');
    $ask = fn () => $this->withHeader('Idempotency-Key', 'outage-db-1')->postJson("/v1/services/{$service->id}/actions", ['action' => 'database.create', 'params' => ['name' => 'shop', 'password' => 'Correct-Horse-Battery-9']]);

    // the panel is away: the request is accepted into the queue, nothing is lost and nothing is decided about the service
    $operationId = $ask()->assertStatus(202)->json('operation_id');
    $waiting = Operation::query()->findOrFail($operationId);
    expect($waiting->state)->toBe(Operation::PENDING)->and($waiting->attempts)->toBeGreaterThan(0)->and(data_get($waiting->error, 'retryable'))->toBeTrue()->and($waiting->next_run_at)->not->toBeNull()
        ->and($panel['unreachable'])->toBeGreaterThan(0)->and($panel['created'])->toBe(0)
        ->and($service->fresh()->state)->toBe(ServiceStateMachine::ACTIVE); // the site runs; only its management waits

    // the customer presses the button again: the same operation, not a second one
    expect($ask()->assertStatus(202)->json('operation_id'))->toBe($operationId)
        ->and(Operation::query()->where('service_id', $service->id)->count())->toBe(1);

    // the panel is back: the queue picks the change up, and a job delivered twice does not make a second database
    $panelUp = true;
    $done = driveOperation($waiting);
    expect($done->state)->toBe(Operation::SUCCEEDED)->and($panel['created'])->toBe(1);
    app(OperationService::class)->dispatch($done->fresh());
    app(OperationService::class)->dispatch($done->fresh());
    expect($panel['created'])->toBe(1)->and($done->fresh()->state)->toBe(Operation::SUCCEEDED)
        ->and(collect($this->getJson("/v1/services/{$service->id}/resources/databases?fresh=1")->assertOk()->json('data'))->count())->toBe(1);
});

it('still enforces the plan\'s limit on a change it accepted while the panel was away', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel'); // the plan allows two databases
    $panelUp = false;
    $created = 0;
    $prefix = Naming::prefix($service->id);
    Http::fake(function (Request $request) use (&$panelUp, &$created, $prefix) {
        if (! $panelUp) {
            throw new ConnectionException('cURL error 7: Connection refused');
        }
        if (str_contains($request->url(), 'table=databases')) {
            return Http::response(['data' => [['id' => 1, 'name' => "{$prefix}_a", 'username' => "{$prefix}_a"], ['id' => 2, 'name' => "{$prefix}_b", 'username' => "{$prefix}_b"]]]); // both slots of this site are taken
        }
        if (str_contains($request->url(), 'AddDatabase')) {
            $created++;
        }

        return Http::response(['status' => true, 'msg' => 'ok', 'data' => []]);
    });
    $this->actingAs($owner, 'sanctum');

    // the count cannot be had, so the request is not refused for it: the limit travels with the operation
    $id = $this->withHeader('Idempotency-Key', 'outage-db-2')->postJson("/v1/services/{$service->id}/actions", ['action' => 'database.create', 'params' => ['name' => 'third', 'password' => 'Correct-Horse-Battery-9']])->assertStatus(202)->json('operation_id');
    expect(Operation::query()->findOrFail($id)->desired['_limit'])->toBe(['kind' => 'databases', 'limit' => 2]);

    // the panel is back and shows the plan is full: the step counts before it touches anything
    $panelUp = true;
    $operation = driveOperation(Operation::query()->findOrFail($id));
    expect($operation->state)->toBeIn([Operation::FAILED, 'COMPENSATED'])->and($operation->error['message'])->toContain('the plan allows 2')->and($created)->toBe(0);

    // with the panel up the same request is refused at the door, as before
    $this->withHeader('Idempotency-Key', 'outage-db-3')->postJson("/v1/services/{$service->id}/actions", ['action' => 'database.create', 'params' => ['name' => 'fourth', 'password' => 'Correct-Horse-Battery-9']])->assertStatus(422)->assertJsonPath('error', 'feature_limit_reached');
});
