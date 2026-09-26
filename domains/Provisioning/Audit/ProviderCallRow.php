<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Audit;

/**
 * One logged ISPConfig call as the audit reads it: the request body the platform sent and the vendor's answer.
 *
 * ProviderCallLogger stores the request summary `{method, url, query, body, headers}` and the whole vendor JSON
 * `{code, message, response}`, each masked and cut at 16 KB. A cut or otherwise unreadable row is `parsed = false`
 * and proves nothing — the audit counts it and never guesses. Nothing read here is ever printed as it is.
 */
final class ProviderCallRow
{
    private const TRUNCATED = '…[truncated]';

    /** @param array<string,mixed> $body */
    private function __construct(public readonly bool $parsed, public readonly array $body, public readonly mixed $answer) {}

    public static function of(object $row): self
    {
        $request = self::decode($row->request ?? null);
        $response = self::decode($row->response ?? null);
        if (! is_array($request) || ! is_array($response)) {
            return new self(false, [], null);
        }

        return new self(true, is_array($request['body'] ?? null) ? $request['body'] : [], $response['response'] ?? null);
    }

    /** Only the request, for a write whose answer does not matter. */
    public static function requestOf(object $row): self
    {
        $request = self::decode($row->request ?? null);

        return is_array($request) ? new self(true, is_array($request['body'] ?? null) ? $request['body'] : [], null) : new self(false, [], null);
    }

    public function get(string $path): mixed
    {
        return data_get($this->body, $path);
    }

    /** The integer record id the call named, or null when it named a filter (an array) or nothing. */
    public function primaryId(): ?int
    {
        return self::intOf($this->body['primary_id'] ?? null);
    }

    /** @return array<string,mixed> the filter of a listing call (`primary_id` as an array) */
    public function filter(): array
    {
        $filter = $this->body['primary_id'] ?? null;

        return is_array($filter) ? $filter : [];
    }

    /** @return array<string,mixed> */
    public function params(): array
    {
        $params = $this->body['params'] ?? null;

        return is_array($params) ? $params : [];
    }

    /** @return list<array<string,mixed>> the records of the answer: a list, or one record */
    public function rows(): array
    {
        if (! is_array($this->answer) || $this->answer === []) {
            return [];
        }

        return array_is_list($this->answer) ? array_values(array_filter($this->answer, 'is_array')) : [$this->answer];
    }

    /** The id an `_add` call answered with. */
    public function createdId(): ?int
    {
        $answer = is_array($this->answer) ? ($this->answer['id'] ?? null) : $this->answer;

        return self::intOf($answer);
    }

    public static function intOf(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            return (int) $value > 0 ? (int) $value : null;
        }

        return null;
    }

    private static function decode(mixed $json): mixed
    {
        if (! is_string($json) || $json === '' || str_ends_with($json, self::TRUNCATED)) {
            return null;
        }

        return json_decode($json, true);
    }
}
