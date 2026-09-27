<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/**
 * The key a caller's operation is kept under (permission program IF-12, audit SE-5 / G12) — one rule for every domain that starts
 * operations from a key somebody sent: ServiceService (service actions, TASK-0036) and DomainService (registrations, renewals,
 * transfers, nameservers — red-team round of the Phase-0 chain).
 *
 * `operations.idempotency_key` is unique across the whole platform, and it was looked up by the caller's key alone: the same
 * `Idempotency-Key` from another customer (or another member of the same one) was answered with the first caller's operation —
 * its id and parameters handed out — and nothing ran for the second caller. A key a person sends is theirs for one
 * organization, target, operation and actor, and carries a keyed fingerprint of what was asked, so the same key for a different
 * request is refused (409) instead of silently replayed. The system's own keys are built from internal ids (a dunning case, an
 * order item, a renewal job) and are left exactly as they are: retries across the scheduler, and every place that finds such an
 * operation by its key, keep working.
 */
final class OperationKey
{
    /**
     * @param  list<string|null>  $namespace  what the key is scoped to, the actor excluded (it is added here): organization, target, operation
     * @param  array<mixed>  $params  what was asked; hashed with `$operation` in front
     * @return array{0: string, 1: ?string} the key to store and the request hash (null for the system)
     */
    public static function scoped(CommandContext $context, array $namespace, string $key, string $operation, array $params): array
    {
        if ($context->actorType === 'system') {
            return [$key, null];
        }
        $actor = $context->actorType.':'.($context->onBehalfOfUserId ?? $context->actorId ?? '');
        $scope = substr(hash('sha256', implode('|', [...array_map(fn ($part) => (string) $part, $namespace), $actor])), 0, 24);
        $scoped = $key.'@'.$scope; // the caller's key stays readable in front; the column holds 200, provider bindings append to it
        if (strlen($scoped) > 160) {
            $scoped = 'h:'.hash('sha256', $key).'@'.$scope;
        }

        // keyed (TASK-0036 review round 1): the parameters carry passwords and transfer codes. A plain digest of them would stay in
        // `desired` as an offline check for a guessed secret.
        return [$scoped, hash_hmac('sha256', $operation.'|'.(string) json_encode(self::canonical($params)), (string) config('app.key'))];
    }

    /**
     * The operation already started under the scoped key, or null. The same key with another request is refused (409): a
     * replay answers only the request that made it.
     */
    public static function replay(string $key, ?string $requestHash, string $message): ?Operation
    {
        $existing = Operation::query()->where('idempotency_key', $key)->first();
        if ($existing === null) {
            return null;
        }
        $stored = data_get($existing->desired, 'request_hash');
        if ($requestHash !== null && is_string($stored) && ! hash_equals($stored, $requestHash)) {
            throw DomainError::conflict('idempotency_key_reused', $message, ['operation_id' => $existing->id]);
        }

        return $existing;
    }

    /** What to add to `desired`: the request hash, when there is one. @return array<string, string> */
    public static function desired(?string $requestHash): array
    {
        return $requestHash === null ? [] : ['request_hash' => $requestHash];
    }

    /** Parameters with their keys in a fixed order, so the same request hashes the same however its JSON was ordered. @param array<mixed> $params @return array<mixed> */
    public static function canonical(array $params): array
    {
        if (! array_is_list($params)) {
            ksort($params);
        }

        return array_map(fn ($value) => is_array($value) ? self::canonical($value) : $value, $params);
    }
}
