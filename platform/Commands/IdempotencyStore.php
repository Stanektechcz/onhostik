<?php

declare(strict_types=1);

namespace Onhost\Platform\Commands;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\DB;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Redaction\Redactor;

/**
 * Exactly-once business semantics on top of at-least-once delivery. The key is
 * scoped to the acting organization (or actor for global commands) so two tenants
 * can never collide. Replays return the stored result reference.
 *
 * TASK-0041 (P0-16 red team, TASK-0036 follow-up — the rule of OperationKey, permission program IF-12): a key a person sends is
 * theirs, for one organization, one actor and one request. The scope was the organization alone: another member of it sending
 * the same key was answered with the first member's result and nothing ran for them, and the same key with another body was
 * silently answered with the first run. Now the actor (the person acted for, as OperationKey) is part of the scope and a keyed
 * hash of the command is kept with the answer — the same key with another command is refused (409 `idempotency_key_reused`).
 * The system's own keys are left exactly as they were (built from internal ids; a sweep's retry is answered by its first run).
 */
final class IdempotencyStore
{
    public function __construct(private readonly int $ttlHours = 24) {}

    /**
     * The keyed fingerprint of what a command asks, or null for the system (its keys are not checked). Keyed with app.key: the
     * payload carries passwords and transfer codes, a plain digest of them would be an offline check for a guessed secret.
     */
    public static function requestHash(Command $command, CommandContext $context): ?string
    {
        if ($context->actorType === 'system') {
            return null;
        }
        $fields = self::canonical(get_object_vars($command));

        return hash_hmac('sha256', $command::class.'|'.$command->name().'|'.(string) json_encode($fields, JSON_PARTIAL_OUTPUT_ON_ERROR), (string) config('app.key'));
    }

    public function find(string $key, CommandContext $context, ?string $requestHash = null): mixed
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
        if ($requestHash !== null && is_string($row->request_hash) && ! hash_equals($row->request_hash, $requestHash)) {
            throw DomainError::conflict('idempotency_key_reused', 'This idempotency key was already used for another request.', [
                'hint' => 'Use a new key for a different request; the first answer is not returned for it.',
            ]);
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

    public function remember(string $key, CommandContext $context, mixed $result, ?string $requestHash = null): void
    {
        $stored = $result instanceof EloquentModel
            ? ['model' => $result::class, 'id' => $result->getKey()]
            : ['value' => is_scalar($result) || is_array($result) || $result === null ? $result : (string) json_encode($result)];
        // A result may hand out a secret exactly once (a domain's transfer code shown inline, a new API token, a generated
        // password). The replay store kept it in clear text for a day; a replay now answers with the same result, the secret masked.
        if (is_array($stored['value'] ?? null)) {
            $stored['value'] = (new Redactor)->redact(json_decode((string) json_encode($stored['value']), true) ?? []);
        }

        DB::table('idempotency_keys')->updateOrInsert(
            ['key' => $key, 'scope' => $this->scope($context)],
            [
                'request_hash' => $requestHash,
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
        return self::scopeOf($context);
    }

    /** The scope a command's answer is kept under (DomainController looks for keys it already spent in it). */
    public static function scopeOf(CommandContext $context): string
    {
        if ($context->actorType === 'system') {
            return $context->organizationId ?? ($context->actorType.':'.($context->actorId ?? 'anonymous'));
        }
        // TASK-0041: the organization AND the person (acted for, as OperationKey) — hashed, the column holds 120
        $actor = $context->actorType.':'.($context->onBehalfOfUserId ?? $context->actorId ?? 'anonymous');

        return ($context->organizationId ?? 'global').'|'.substr(hash('sha256', $actor), 0, 32);
    }

    /** @param array<mixed> $values @return array<mixed> keys in a fixed order, so the same command hashes the same */
    private static function canonical(array $values): array
    {
        if (! array_is_list($values)) {
            ksort($values);
        }

        return array_map(fn ($value) => is_array($value) ? self::canonical($value) : $value, $values);
    }
}
