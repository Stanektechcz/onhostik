<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\CustomIso\CustomIsoLibrary;
use Onhost\Domain\Services\CustomIso\UploadCustomIsoCommand;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;

/**
 * A customer's own installation images, through the server whose plan allows them (TASK-0110, owner decision G-R5).
 *
 *   GET  /v1/services/{service}/isos   the organization's images, its quota and the size this server's plan allows (service.read)
 *   POST /v1/services/{service}/isos   upload one (multipart `file`): plan, size, ISO 9660, virus scan, quota (service.manage)
 *
 * Attaching, detaching and deleting are service actions (`POST /v1/services/{service}/actions`: `iso.attach` asks the console,
 * `iso.detach` managing, `iso.delete` deleting data and a fresh step-up) — one door for every write to a server.
 */
final class CustomIsoController extends ApiController
{
    public function index(Request $request, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.read');

        return $this->ok(CustomIsoLibrary::listing($model));
    }

    public function store(Request $request, CustomIsoLibrary $library, string $service): JsonResponse
    {
        $model = $this->resolve($request, $service, 'service.manage'); // who may not keep an image here is told so before anything is read
        $data = $request->validate(['file' => ['required', 'file'], 'reason' => ['nullable', 'string', 'max:250']]);
        /** @var UploadedFile $file */
        $file = $data['file'];
        $context = $this->api->context($request, Organization::query()->find($model->organization_id), $data['reason'] ?? null);
        $staged = $library->stage($model, $file, $context);
        try {
            // two different files are two uploads, the same file again is the image it already is (CustomIsoLibrary::store)
            $key = $this->idempotencyKey($request, ServiceActionCommand::keyPrefix($model->id, 'iso.upload', $context).':'.substr($staged['sha256'], 0, 32));

            return $this->dispatch(new UploadCustomIsoCommand($model->organization_id, $key, ['service_id' => $model->id, 'project_id' => $model->project_id, 'token' => $staged['token']]), $context, 201);
        } finally {
            $library->discard($staged['token']); // whatever the bus did, nothing staged outlives the request
        }
    }

    private function resolve(Request $request, string $id, string $permission): Service
    {
        $service = Service::query()->find($id) ?? throw DomainError::notFound('service');
        $this->api->authorizeOrNotFound($request, $permission, CommandScope::resource($service->id, $service->organization_id, $service->project_id), 'service'); // a stranger: 404 (TASK-0098)

        return $service;
    }
}
