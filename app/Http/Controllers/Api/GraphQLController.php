<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\GraphQL\ApiSchema;
use App\Http\Controllers\Controller;
use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        if (! is_string($query) || trim($query) === '') {
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
}
