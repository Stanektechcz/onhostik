<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Provisioning\CapacityPlanner;
use Onhost\Domain\Provisioning\Models\CapacityRequest;
use Onhost\Domain\Provisioning\NodeBootstrap;
use Onhost\Platform\Errors\DomainError;

/** The readiness call-back of a vendor-ordered node (audit §5o-7): no session, a one-time token in the body. */
final class CapacityController extends ApiController
{
    public function ready(Request $request, NodeBootstrap $bootstrap, string $capacityRequest): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:80'], 'hostname' => ['nullable', 'string', 'max:120'], 'ip' => ['nullable', 'string', 'max:45'], 'os' => ['nullable', 'string', 'max:120'], 'cpu_cores' => ['nullable', 'integer', 'min:0'], 'ram_mb' => ['nullable', 'integer', 'min:0'], 'disk_gb' => ['nullable', 'integer', 'min:0']]);
        $model = CapacityRequest::query()->find($capacityRequest) ?? throw DomainError::notFound('capacity_request');

        $ready = $bootstrap->ready($model, $data['token'], $data);

        return response()->json(['data' => CapacityPlanner::present($ready['request']) + ['activate_token' => $ready['activate_token'], 'activate_url' => $bootstrap->activateUrl($ready['request'])]], 202); // the playbook posts the activation token when the hypervisor is installed (§5p-7)
    }

    /** The playbook reports the hypervisor installed (audit §5p-7): the node goes active, the request is delivered. */
    public function activate(Request $request, NodeBootstrap $bootstrap, string $capacityRequest): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:80'], 'hostname' => ['nullable', 'string', 'max:120'], 'ip' => ['nullable', 'string', 'max:45'], 'os' => ['nullable', 'string', 'max:120'], 'cpu_cores' => ['nullable', 'integer', 'min:0'], 'ram_mb' => ['nullable', 'integer', 'min:0']]);
        $model = CapacityRequest::query()->find($capacityRequest) ?? throw DomainError::notFound('capacity_request');

        return response()->json(['data' => CapacityPlanner::present($bootstrap->activate($model, $data['token'], $data))], 202);
    }
}
