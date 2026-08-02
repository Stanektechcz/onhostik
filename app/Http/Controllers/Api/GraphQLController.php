<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\GraphQL\ApiSchema;
use App\Http\Controllers\Controller;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Single GraphQL endpoint (POST /api/graphql) over the read-only ApiSchema.
 *
 * Authentication is the same Sanctum token used by the REST API — the resolved
 * User is passed to the schema as the query context, so every resolver is scoped
 * to that account. Depth and complexity limits guard against pathological
 * queries; debug messages are only surfaced when app.debug is on, so the token
 * key or internal details never leak in production error payloads.
 */
final class GraphQLController extends Controller
{
    public function __construct(private readonly ApiSchema $schema) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->input('query');
        $query = is_string($query) ? $query : null;

        // Automatic Persisted Queries (Apollo APQ): a client may send only a
        // sha256 hash to save bandwidth. First registration sends hash + query
        // (we verify + cache); afterwards the hash alone resolves the cached text.
        $apq = $this->persistedQuery($request, $query);

        if ($apq instanceof JsonResponse) {
            return $apq; // PersistedQueryNotFound / hash mismatch
        }

        $query = $apq;

        if ($query === null || trim($query) === '') {
            return response()->json(['errors' => [['message' => 'No GraphQL query provided.']]], 400);
        }

        $variables = $request->input('variables');

        if (is_string($variables)) {
            /** @var array<string, mixed>|null $decoded */
            $decoded   = json_decode($variables, true);
            $variables = is_array($decoded) ? $decoded : null;
        }

        $operationName = $request->input('operationName');

        // Replace the (disabled-by-default) depth/complexity rules by their
        // class key so ours actually take effect — merging leaves the disabled
        // defaults in place and they win.
        $rules                        = DocumentValidator::allRules();
        $rules[QueryDepth::class]      = new QueryDepth(10);
        $rules[QueryComplexity::class] = new QueryComplexity(200);

        // Introspection maps the whole schema; useful in development, but in
        // production it hands an attacker the full attack surface for free
        // (audit 500 #164). The published docs remain the contract.
        if (config('graphql.introspection', config('app.debug') === true) !== true) {
            $rules[DisableIntrospection::class] = new DisableIntrospection(DisableIntrospection::ENABLED);
        }

        $result = GraphQL::executeQuery(
            schema: $this->schema->make(),
            source: $query,
            rootValue: null,
            contextValue: $request->user(),
            variableValues: is_array($variables) ? $variables : null,
            operationName: is_string($operationName) ? $operationName : null,
            fieldResolver: null,
            validationRules: $rules,
        );

        $debug = config('app.debug') === true
            ? DebugFlag::INCLUDE_DEBUG_MESSAGE
            : DebugFlag::NONE;

        return response()->json($result->toArray($debug));
    }

    /**
     * Resolve an Automatic Persisted Query. Returns the query text to run, or a
     * JsonResponse when the protocol short-circuits (query not found / mismatch),
     * or the incoming $query unchanged when APQ is not in use.
     */
    private function persistedQuery(Request $request, ?string $query): string|JsonResponse|null
    {
        $extensions = $request->input('extensions');
        $hash       = is_array($extensions) ? ($extensions['persistedQuery']['sha256Hash'] ?? null) : null;

        if (! is_string($hash) || $hash === '') {
            return $query; // not an APQ request
        }

        $cacheKey = 'graphql:apq:' . $hash;

        // Registration: hash + query — verify the hash then cache the text.
        if ($query !== null && trim($query) !== '') {
            if (! hash_equals($hash, hash('sha256', $query))) {
                return response()->json([
                    'errors' => [['message' => 'provided sha256Hash does not match query',
                        'extensions' => ['code' => 'PERSISTED_QUERY_HASH_MISMATCH']]],
                ]);
            }

            Cache::put($cacheKey, $query, now()->addDay());

            return $query;
        }

        // Lookup: hash only — serve the cached text, or ask the client to resend.
        $cached = Cache::get($cacheKey);

        if (! is_string($cached)) {
            return response()->json([
                'errors' => [['message' => 'PersistedQueryNotFound',
                    'extensions' => ['code' => 'PERSISTED_QUERY_NOT_FOUND']]],
            ]);
        }

        return $cached;
    }
}
