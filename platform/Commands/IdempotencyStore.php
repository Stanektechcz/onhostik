<?php

declare(strict_types=1);

namespace Onhost\Platform\Commands;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\DB;
use Onhost\Platform\Errors\DomainError;

/**
 * Exactly-once business semantics on top of at-least-once delivery. The key is
 * scoped to the acting organization (or actor for global commands) so two tenants
 * can never collide. Replays return the stored result reference.
 */
final class IdempotencyStore
{
    public function __construct(private readonly int $ttlHours = 24) {}

    public function find(string $key, CommandContext $context): mixed
    {
        $row = DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('scope', $this->scope($context))
            ->first();
        if ($row === null) {
            return null;
        }
        if ($row->expires_at !== null && strtotime((string) $row->expires_at) < time()) {
            DB::table('idempotency_keys')->where('id', $row->id)->delete();

            return null;
        }
        $payload = json_decode((string) $row->result, true) ?? [];
        if (isset($payload['model'], $payload['id']) && is_string($payload['model']) && class_exists($payload['model'])) {
            $model = $payload['model']::query()->find($payload['id']);
            if ($model !== null) {
                return $model;
            }
        }
        if (array_key_exists('value', $payload)) {
            return $payload['value'];
        }

        return $payload;
    }

    public function remember(string $key, CommandContext $context, mixed $result): void
    {
        $stored = $result instanceof EloquentModel
            ? ['model' => $result::class, 'id' => $result->getKey()]
            : ['value' => is_scalar($result) || is_array($result) || $result === null ? $result : (string) json_encode($result)];

        DB::table('idempotency_keys')->updateOrInsert(
            ['key' => $key, 'scope' => $this->scope($context)],
            [
                'result' => json_encode($stored),
                'created_at' => now(),
                'expires_at' => now()->addHours($this->ttlHours),
            ],
        );
    }

    /** HTTP-level replay for `Idempotency-Key` header (public API contract: same key + different body => 409). */
    public function rememberHttp(string $key, string $scope, string $requestHash, int $status, string $body): void
    {
        DB::table('idempotency_keys')->updateOrInsert(
            ['key' => 'http:'.$key, 'scope' => $scope],
            [
                'request_hash' => $requestHash,
                'response_status' => $status,
                'result' => $body,
                'created_at' => now(),
                'expires_at' => now()->addHours($this->ttlHours),
            ],
        );
    }

    /** @return array{status:int, body:string}|null */
    public function findHttp(string $key, string $scope, string $requestHash): ?array
    {
        $row = DB::table('idempotency_keys')->where('key', 'http:'.$key)->where('scope', $scope)->first();
        if ($row === null || ($row->expires_at !== null && strtotime((string) $row->expires_at) < time())) {
            return null;
        }
        if ($row->request_hash !== $requestHash) {
            throw DomainError::conflict('idempotency_key_reused', 'The same Idempotency-Key was used with a different request body.', [
                'hint' => 'Use a new key for a different request; the original response is not returned to avoid silent overwrites.',
            ]);
        }

        return ['status' => (int) $row->response_status, 'body' => (string) $row->result];
    }

    private function scope(CommandContext $context): string
    {
        return $context->organizationId ?? ($context->actorType.':'.($context->actorId ?? 'anonymous'));
    }
}
