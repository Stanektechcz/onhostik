<?php

declare(strict_types=1);

namespace Onhost\Platform\Audit;

use DateTimeInterface;

final class HashChain
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /** Fields that participate in the chained hash (order fixed). */
    public const FIELDS = [
        'actor_type', 'actor_id', 'on_behalf_of', 'organization_id', 'project_id', 'resource_type', 'resource_id',
        'permission', 'action', 'result', 'request_id', 'correlation_id', 'before_hash', 'after_hash', 'detail',
        'reason', 'ticket_ref', 'ip', 'session_id', 'step_up_method', 'approval_ids',
    ];

    /** @param array<string,mixed> $payload */
    public static function next(string $prevHash, array $payload): string
    {
        $subset = [];
        foreach (self::FIELDS as $field) {
            $value = $payload[$field] ?? null;
            if (in_array($field, ['detail', 'approval_ids'], true) && is_string($value)) {
                $value = json_decode($value, true);
            }
            $subset[$field] = $value;
        }
        $subset['created_at'] = self::timestamp($payload['created_at'] ?? null);

        return hash('sha256', $prevHash.'|'.self::canonical($subset));
    }

    /** Recompute the hash of a stored row (used by the verify command and tests). */
    public static function verifyEvent(AuditEvent $event, string $prevHash): bool
    {
        $payload = $event->getAttributes();
        $payload['created_at'] = $event->created_at;

        return hash_equals($event->hash, self::next($prevHash, $payload));
    }

    public static function hashPayload(mixed $payload): string
    {
        return hash('sha256', self::canonical($payload));
    }

    public static function canonical(mixed $value): string
    {
        if (is_array($value)) {
            self::sortRecursively($value);
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /** Second precision in UTC: database timestamps do not keep microseconds. */
    private static function timestamp(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }
        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);

            return $ts === false ? $value : gmdate('Y-m-d\TH:i:s\Z', $ts);
        }

        return null;
    }

    private static function sortRecursively(array &$value): void
    {
        if (array_is_list($value)) {
            foreach ($value as &$item) {
                if (is_array($item)) {
                    self::sortRecursively($item);
                }
            }

            return;
        }
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::sortRecursively($item);
            }
        }
    }
}
