<?php

declare(strict_types=1);

namespace Onhost\Domain\Notifications\Webhooks;

use Onhost\Platform\Outbox\OutboxMessage;

/**
 * The public face of an outbox event on a customer webhook (D4): the envelope `data` object, built from the allow-list in
 * WebhookEvents. The raw `$message->payload` never goes on the wire and is not stored with the delivery either — what a
 * delivery row holds is exactly what the customer receives.
 */
final class WebhookPayload
{
    private const CODE = '/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/';

    private const MONEY_KEYS = ['minor', 'currency', 'decimal', 'formatted'];

    private const MAX_STRING = 500;

    private const MAX_LIST = 50;

    /** marks a value that is not copied (null is a value) */
    private const DROP = '__onhost_webhook_drop__';

    /** @return array{aggregate: array{type: string, id: string|null}, organization_id: string|null, payload: array<string, mixed>}|null null when the event is not public */
    public static function of(OutboxMessage $message): ?array
    {
        $fields = WebhookEvents::fields($message->name);
        if ($fields === null) {
            return null;
        }

        return [
            'aggregate' => ['type' => (string) $message->aggregate_type, 'id' => $message->aggregate_id !== null ? (string) $message->aggregate_id : null],
            'organization_id' => $message->organization_id,
            'payload' => self::pick((array) $message->payload, $fields),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    public static function pick(array $payload, array $fields): array
    {
        $out = [];
        foreach ($fields as $spec) {
            [$path, $type] = array_pad(explode(':', $spec, 2), 2, null);
            if (! self::has($payload, $path)) {
                continue;
            }
            $value = $type === 'code' ? self::code(data_get($payload, $path)) : self::value(data_get($payload, $path));
            if ($value === self::DROP) {
                continue;
            }
            data_set($out, $path, $value);
        }

        return $out;
    }

    /** @param array<string, mixed> $payload */
    private static function has(array $payload, string $path): bool
    {
        $node = $payload;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return false;
            }
            $node = $node[$segment];
        }

        return true;
    }

    private static function code(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return is_string($value) && preg_match(self::CODE, $value) === 1 ? $value : self::DROP;
    }

    private static function value(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            return mb_substr($value, 0, self::MAX_STRING);
        }
        if (! is_array($value)) {
            return self::DROP;
        }
        if ($value !== [] && array_is_list($value)) {
            $scalars = array_filter($value, fn ($v) => is_scalar($v) || $v === null);

            return count($scalars) === count($value) ? array_slice(array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, self::MAX_STRING) : $v, $value), 0, self::MAX_LIST) : self::DROP;
        }
        if ($value === []) {
            return [];
        }
        // a money value keeps its own shape; any other object would need fields of its own in the allow-list
        $isMoney = array_key_exists('minor', $value) && array_key_exists('currency', $value) && array_diff(array_keys($value), self::MONEY_KEYS) === [];

        return $isMoney ? array_map(fn ($v) => is_scalar($v) ? $v : null, $value) : self::DROP;
    }
}
