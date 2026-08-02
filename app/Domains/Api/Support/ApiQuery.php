<?php

declare(strict_types=1);

namespace App\Domains\Api\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

/**
 * Shared query semantics for API list endpoints (audit 500 #202/#203):
 *
 *  - `?sort=-created_at,name`  ordering, `-` prefix = descending
 *  - `?filter[status]=active`  whitelisted equality filters
 *  - `?per_page=50`            page size (capped)
 *  - `?cursor=…`               cursor pagination — stable under inserts and
 *                              O(1) on deep pages, unlike OFFSET
 *  - `?fields=id,label`        sparse fieldsets applied to the serialised rows
 *
 * Everything is whitelist-driven: a caller can only sort/filter by columns the
 * endpoint explicitly allows, so no query parameter can reach an arbitrary
 * column.
 */
final class ApiQuery
{
    public const MAX_PER_PAGE = 100;

    /**
     * Apply sorting + filtering to a builder.
     *
     * @template TModel of Model
     * @param  Builder<TModel>  $query
     * @param  list<string>  $sortable
     * @param  list<string>  $filterable
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, Request $request, array $sortable, array $filterable = []): Builder
    {
        foreach (self::sorts($request, $sortable) as [$column, $direction]) {
            $query->orderBy($column, $direction);
        }

        /** @var array<string, mixed> $filters */
        $filters = (array) $request->query('filter', []);

        foreach ($filters as $column => $value) {
            if (! in_array($column, $filterable, true) || ! is_scalar($value)) {
                continue;
            }

            $query->where($column, $value);
        }

        return $query;
    }

    /**
     * Cursor-paginate a prepared builder.
     *
     * @template TModel of Model
     * @param  Builder<TModel>  $query
     * @return CursorPaginator<int, TModel>
     */
    public static function paginate(Builder $query, Request $request): CursorPaginator
    {
        return $query->cursorPaginate(self::perPage($request))->withQueryString();
    }

    public static function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 25);

        return max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Reduce serialised rows to the requested `?fields=` subset (sparse
     * fieldsets). Unknown field names are ignored rather than erroring, so a
     * client asking for a field a newer/older version lacks still gets data.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function sparse(array $rows, Request $request): array
    {
        $fields = trim((string) $request->query('fields', ''));

        if ($fields === '') {
            return $rows;
        }

        $wanted = array_values(array_filter(array_map('trim', explode(',', $fields))));

        if ($wanted === []) {
            return $rows;
        }

        return array_map(
            static fn (array $row): array => array_intersect_key($row, array_flip($wanted)),
            $rows,
        );
    }

    /**
     * Standard envelope for a cursor-paginated collection.
     *
     * @template TModel of Model
     * @param  CursorPaginator<int, TModel>  $paginator
     * @param  list<array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    public static function envelope(CursorPaginator $paginator, array $data): array
    {
        return [
            'data' => $data,
            'meta' => [
                'per_page'    => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more'    => $paginator->hasMorePages(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ];
    }

    /**
     * @param  list<string>  $sortable
     * @return list<array{0: string, 1: string}>
     */
    private static function sorts(Request $request, array $sortable): array
    {
        $raw = trim((string) $request->query('sort', ''));

        if ($raw === '') {
            return [];
        }

        $out = [];

        foreach (explode(',', $raw) as $field) {
            $field     = trim($field);
            $direction = 'asc';

            if (str_starts_with($field, '-')) {
                $direction = 'desc';
                $field     = substr($field, 1);
            }

            if ($field !== '' && in_array($field, $sortable, true)) {
                $out[] = [$field, $direction];
            }
        }

        return $out;
    }
}
