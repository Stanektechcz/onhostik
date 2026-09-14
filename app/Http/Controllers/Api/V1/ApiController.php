<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;

abstract class ApiController extends Controller
{
    public function __construct(protected readonly ApiContext $api, protected readonly CommandBus $bus) {}

    /** Dispatch through the bus (AuthZ → step-up/approval → idempotency → transaction → audit → outbox). */
    protected function dispatch(Command $command, CommandContext $context, int $status = 200): JsonResponse
    {
        $this->api->assertTokenScope(request(), $command->permission()); // bearer tokens are limited to their documented scopes

        return response()->json($this->bus->dispatch($command, $context), $status);
    }

    /** Idempotency key: client-supplied header, otherwise derived from the actor + route + body (safe replay within the TTL). */
    protected function idempotencyKey(Request $request, string $prefix): string
    {
        $header = $request->headers->get('Idempotency-Key');
        if (is_string($header) && $header !== '') {
            return $prefix.':'.$header;
        }

        return $prefix.':'.hash('sha256', ($request->user()?->getAuthIdentifier() ?? $request->ip()).'|'.$request->path().'|'.json_encode($request->all()));
    }

    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(is_array($data) && array_key_exists('data', $data) ? $data : ['data' => $data], $status);
    }
}
