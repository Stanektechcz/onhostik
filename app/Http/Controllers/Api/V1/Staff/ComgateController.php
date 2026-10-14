<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Payments\ComgateCheck;
use Onhost\Domain\Payments\Commands\ComgateCheckCommand;
use Onhost\Platform\Commands\CommandScope;

/**
 * Nastavení → Platební brána Comgate (owner decision H-R8, 2026-10-07): whether the platform holds the Comgate credentials (never
 * their values), the gateway's mode, the last check — and the checks themselves: a connection check and a 1 Kč test payment
 * (step-up), and the test-mode switch (step-up and a second person). Every write is a `ComgateCheckCommand` on the bus.
 */
final class ComgateController extends ApiController
{
    public function show(Request $request, ComgateCheck $check): JsonResponse
    {
        $this->api->authorize($request, 'provider.instance.read', CommandScope::global());

        return $this->ok($check->status());
    }

    public function check(Request $request): JsonResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:'.implode(',', ComgateCheck::KINDS)]]);

        // every run is a new check: the key carries the minute unless the client sends its own Idempotency-Key
        return $this->dispatch(new ComgateCheckCommand($this->onceKey($request, 'payments.comgate.check:'.$data['kind']), ['op' => 'check', 'kind' => $data['kind']]), $this->api->context($request));
    }

    public function setTestMode(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => ['present', 'nullable', 'boolean'], 'reason' => ['required', 'string', 'min:5', 'max:250']]);
        $enabled = $data['enabled'] === null ? null : (bool) $data['enabled'];
        $state = $enabled === null ? 'env' : ($enabled ? 'on' : 'off');

        return $this->dispatch(
            new ComgateCheckCommand($this->onceKey($request, 'payments.comgate.test_mode:'.$state), ['op' => 'test_mode.set', 'enabled' => $enabled, 'reason' => $data['reason']]),
            $this->api->context($request, null, $data['reason']),
        );
    }
}
