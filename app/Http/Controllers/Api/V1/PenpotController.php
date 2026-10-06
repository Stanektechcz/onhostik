<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Penpot\PenpotAccessCommand;
use Onhost\Domain\Services\Penpot\PenpotInstances;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * Penpot for web hosting (TASK-0123): the customer panel card and the owner's password. Suspend, resume, backup, cancel and
 * restore are the ordinary service actions (`POST /v1/services/{service}/actions`).
 */
final class PenpotController extends ApiController
{
    /** The panel card: address to open, login e-mail, limits, the last backups. Reading the service is enough. */
    public function show(Request $request, string $service): JsonResponse
    {
        return $this->ok(PenpotInstances::summary($this->resolve($request, $service, 'service.read')));
    }

    /** The owner sets the password of their Penpot account (HIGH, fresh step-up); the node gets it, nobody stores it. */
    public function ownerPassword(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.read');
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'max:128']]);
        $context = $this->api->context($request, Organization::query()->find($model->organization_id));

        return $this->dispatch(new PenpotAccessCommand($model->organization_id, $this->idempotencyKey($request, 'penpot.owner:'.$model->id), [
            'service_id' => $model->id, 'project_id' => $model->project_id, 'op' => 'owner.password', 'password' => (string) $data['password'],
        ]), $context, 202);
    }

    private function resolve(Request $request, string $id, string $permission): Service
    {
        $service = Service::query()->find($id);
        if ($service === null) {
            throw DomainError::notFound('service');
        }
        // a stranger gets 404 (TASK-0098); a project role covers the services of its project
        $this->api->authorizeOrNotFound($request, $permission, CommandScope::resource($service->id, $service->organization_id, $service->project_id), 'service');
        if (! PenpotInstances::isPenpot($service)) {
            throw new DomainError('feature_unavailable', 'This service is not a Penpot instance.', 404);
        }

        return $service;
    }
}
