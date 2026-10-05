<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Identity\Authorization\CapabilityMatrix;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Access\AccessLevels;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Commands\ServiceAccessCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * Sharing one service with another person (panel → service → „Přístupy“). Who a service is shared with is a decision
 * about people, so the list and both writes ask for `organization.members.manage` — managing the service is not enough.
 */
final class ServiceAccessController extends ApiController
{
    public function index(Request $request, ServiceAccessService $access, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);
        // TASK-0043 (permission program S1-03): the ticks come with what they let a person do, and the levels of the service family
        // (CapabilityMatrix) with the ticks each of them is — the access wizard (S1-04) offers levels, the share stays the ticks
        $locale = CapabilityMatrix::locale(is_string($request->query('locale')) ? (string) $request->query('locale') : (string) (Organization::query()->whereKey($model->organization_id)->value('locale') ?? 'cs'));

        return response()->json(['data' => $access->forService($model), 'capabilities' => ServiceAccessService::catalogue($locale)] + AccessLevels::forService($model, $locale));
    }

    public function store(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'], 'capabilities' => ['required', 'array', 'min:1', 'max:'.count(ServiceAccessService::CAPABILITIES)], 'capabilities.*' => ['string', 'max:20'],
            'access_until' => ['nullable', 'date', 'after:now'], 'note' => ['nullable', 'string', 'max:250'],
        ]);

        return $this->dispatch(new ServiceAccessCommand($model->organization_id, $this->idempotencyKey($request, 'service.access.share'), ['op' => 'share', 'service_id' => $model->id] + $data), $this->api->context($request), 201);
    }

    public function destroy(Request $request, string $service, string $grant): JsonResponse
    {
        $model = $this->resolve($request, $service);

        return $this->dispatch(new ServiceAccessCommand($model->organization_id, $this->idempotencyKey($request, 'service.access.revoke'), ['op' => 'revoke', 'service_id' => $model->id, 'grant_id' => $grant]), $this->api->context($request));
    }

    /** What was shared with the signed-in person, in every organization — their way to the services they look after. */
    public function mine(Request $request, ServiceAccessService $access): JsonResponse
    {
        return response()->json(['data' => $access->sharedWith($this->api->user($request))]);
    }

    private function resolve(Request $request, string $id): Service
    {
        $service = Service::query()->find($id);
        if ($service === null) {
            throw DomainError::notFound('service');
        }
        $this->api->authorizeOrNotFound($request, 'organization.members.manage', CommandScope::organization($service->organization_id), 'service'); // a stranger: 404 (TASK-0098)

        return $service;
    }
}
