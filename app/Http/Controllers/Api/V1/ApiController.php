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
    /** The longest key a controller hands to the bus; what the domains add in front of it still fits a column of 200. */
    private const KEY_LENGTH = 120;

    /**
     * The key of an action that must not happen twice (money). With an `Idempotency-Key` the key is the header alone: a retry a
     * minute later is the SAME request — a time bucket in the prefix made it a new one, and the credit was given twice. Without
     * a header the minute is part of the key, so that the same amount with the same note can be credited again another day.
     */
    protected function onceKey(Request $request, string $prefix): string
    {
        $header = $request->headers->get('Idempotency-Key');

        return $this->idempotencyKey($request, is_string($header) && $header !== '' ? $prefix : $prefix.':'.now()->format('YmdHi'));
    }

    protected function idempotencyKey(Request $request, string $prefix): string
    {
        $header = $request->headers->get('Idempotency-Key');
        if (is_string($header) && $header !== '') {
            // A key is stored in columns of 200 characters, with prefixes of its own on the way (`staff-credit:`, `ledger:`). The
            // header may be 200 characters by itself: SQLite does not mind, PostgreSQL refuses the statement — a 500 for the caller.
            // A long header is kept as its digest: the same header is still the same key.
            return strlen($prefix.':'.$header) <= self::KEY_LENGTH ? $prefix.':'.$header : $prefix.':h:'.hash('sha256', $header);
        }

        return $prefix.':'.hash('sha256', ($request->user()?->getAuthIdentifier() ?? $request->ip()).'|'.$request->path().'|'.json_encode($request->all()));
    }

    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(is_array($data) && array_key_exists('data', $data) ? $data : ['data' => $data], $status);
    }
}
