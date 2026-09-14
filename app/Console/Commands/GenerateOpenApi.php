<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Generates `contracts/openapi/onhost-v1.yaml` from the registered routes so the contract never drifts from
 * the code. Operation ids, tags, security requirements and parameters come from the route definitions;
 * shared schemas (Money, Problem, pagination) are declared once below. Request/response bodies are
 * documented per tag in the controllers' FormRequest rules (validated at runtime) and in docs/api.
 */
final class GenerateOpenApi extends Command
{
    protected $signature = 'onhost:openapi {--out=contracts/openapi/onhost-v1.yaml}';

    protected $description = 'Write the OpenAPI 3.1 contract for /v1 from the registered routes';

    public function handle(Router $router): int
    {
        $paths = [];
        foreach ($router->getRoutes() as $route) {
            /** @var Route $route */
            $uri = $route->uri();
            if (! str_starts_with($uri, 'v1')) {
                continue;
            }
            $methods = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
            $path = '/'.preg_replace('/\{([a-zA-Z_]+)\??\}/', '{$1}', substr($uri, 2) === '' ? '' : substr($uri, 3));
            $path = $path === '/' ? '/' : '/'.ltrim($path, '/');
            $middleware = $route->gatherMiddleware();
            $auth = in_array('auth:sanctum', $middleware, true);
            $staff = str_starts_with($uri, 'v1/staff');
            $tag = $this->tag($uri);
            foreach ($methods as $method) {
                $operation = [
                    'operationId' => $this->operationId($route, $method),
                    'tags' => [$tag],
                    'summary' => $this->summary($route, $method),
                    'parameters' => $this->parameters($route, $method),
                    'responses' => $this->responses($method, $auth),
                ];
                if ($auth) {
                    $operation['security'] = [['session' => []], ['bearer' => $staff ? ['staff'] : []]];
                }
                if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                    $operation['requestBody'] = ['required' => false, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'additionalProperties' => true]]]];
                    $operation['parameters'][] = ['$ref' => '#/components/parameters/IdempotencyKey'];
                }
                if ($auth) {
                    $operation['parameters'][] = ['$ref' => '#/components/parameters/Organization'];
                }
                $paths[$path][strtolower($method)] = $operation;
            }
        }
        ksort($paths);

        $document = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'ONhost Cloud Platform API',
                'version' => (string) config('onhost.version', '4.0'),
                'description' => "Control-plane API of the ONhost hosting platform (blueprint §17). JSON only. Errors use `{error, message, status, errors?}`.\nLists accept `?limit=&offset=` and return `X-Total-Count`. Mutations honour `Idempotency-Key`. Money is `{minor, currency, decimal}`.\nGenerated from routes by `php artisan onhost:openapi` — do not edit by hand.",
                'contact' => ['name' => 'ONhost API', 'url' => (string) config('onhost.portal_url')],
            ],
            'servers' => [['url' => rtrim((string) config('onhost.portal_url'), '/').'/v1']],
            'tags' => array_map(fn (string $t) => ['name' => $t], array_values(array_unique(array_map(fn ($ops) => $ops[array_key_first($ops)]['tags'][0], $paths)))),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'session' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'onhost_session', 'description' => 'Sanctum SPA session (login via POST /auth/login, CSRF cookie required).'],
                    'bearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Personal/service API token `onh_live_…` with documented scopes (POST /tokens).'],
                ],
                'parameters' => [
                    'IdempotencyKey' => ['name' => 'Idempotency-Key', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string', 'maxLength' => 120], 'description' => 'Replay-safe key; the same key returns the stored result within the TTL.'],
                    'Organization' => ['name' => 'X-Organization', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Organization context for users that belong to several organizations.'],
                    'Limit' => ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 25]],
                    'Offset' => ['name' => 'offset', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 0, 'default' => 0]],
                ],
                'schemas' => [
                    'Money' => ['type' => 'object', 'required' => ['minor', 'currency', 'decimal'], 'properties' => ['minor' => ['type' => 'integer', 'description' => 'Amount in minor units (haléře/cents).'], 'currency' => ['type' => 'string', 'enum' => ['CZK', 'EUR']], 'decimal' => ['type' => 'string', 'example' => '1234.56']]],
                    'Problem' => ['type' => 'object', 'required' => ['error', 'message', 'status'], 'properties' => ['error' => ['type' => 'string', 'description' => 'Machine slug, e.g. step_up_required, access_not_approved, invalid_transition, not_found'], 'message' => ['type' => 'string'], 'status' => ['type' => 'integer'], 'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]], 'requirement' => ['type' => 'string', 'enum' => ['step_up', 'approval', 'human']]]],
                    'List' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'array', 'items' => ['type' => 'object']]]],
                    'Envelope' => ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => ['type' => 'object']]],
                ],
                'headers' => ['X-Total-Count' => ['schema' => ['type' => 'integer'], 'description' => 'Total rows for paginated lists.'], 'X-Correlation-Id' => ['schema' => ['type' => 'string']]],
            ],
        ];

        $out = base_path((string) $this->option('out'));
        if (! is_dir(dirname($out))) {
            mkdir(dirname($out), 0777, true);
        }
        file_put_contents($out, Yaml::dump($document, 12, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));
        $this->info(sprintf('%s: %d paths, %d operations', $out, count($paths), array_sum(array_map('count', $paths))));

        return self::SUCCESS;
    }

    private function tag(string $uri): string
    {
        $parts = explode('/', $uri);
        $first = $parts[1] ?? '';
        if ($first === 'staff') {
            return 'staff-'.($parts[2] ?? 'root');
        }

        return match (true) {
            in_array($first, ['catalog', 'domains'], true) && ($parts[2] ?? '') === 'check' => 'catalog',
            in_array($first, ['auth', 'me', 'tokens'], true) => 'identity',
            in_array($first, ['posts', 'kb', 'changelog', 'locations', 'stock', 'leads', 'tender', 'reseller'], true) => 'content',
            in_array($first, ['status', 'incidents', 'probes', 'my', 'sla-credits'], true) => 'status',
            in_array($first, ['abuse', 'abuse-cases', 'data-requests'], true) => 'compliance',
            in_array($first, ['wallet', 'payments', 'invoices', 'subscriptions', 'usage', 'dunning', 'webhooks'], true) => 'billing',
            in_array($first, ['tickets', 'assistant', 'notifications'], true) => 'support',
            default => $first === '' ? 'root' : $first,
        };
    }

    private function operationId(Route $route, string $method): string
    {
        $action = $route->getActionName();
        if (str_contains($action, '@')) {
            [$class, $fn] = explode('@', $action);
            $short = Str::of(class_basename($class))->replace('Controller', '')->lower();
            $prefix = str_contains($class, '\\Staff\\') ? 'staff' : '';

            return Str::camel(trim($prefix.'_'.$short.'_'.$fn, '_'));
        }

        return Str::camel(strtolower($method).'_'.str_replace(['/', '{', '}'], ['_', '', ''], $route->uri()));
    }

    private function summary(Route $route, string $method): string
    {
        $uri = '/'.ltrim(substr($route->uri(), 2), '/');

        return strtoupper($method).' '.$uri;
    }

    private function parameters(Route $route, string $method): array
    {
        $params = [];
        foreach ($route->parameterNames() as $name) {
            $params[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
        }
        if ($method === 'GET' && ! str_contains($route->uri(), '{')) {
            $params[] = ['$ref' => '#/components/parameters/Limit'];
            $params[] = ['$ref' => '#/components/parameters/Offset'];
        }

        return $params;
    }

    private function responses(string $method, bool $auth): array
    {
        $ok = $method === 'POST' ? '201' : '200';
        $responses = [
            $ok => ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['oneOf' => [['$ref' => '#/components/schemas/Envelope'], ['$ref' => '#/components/schemas/List']]]]]],
            '422' => ['description' => 'Validation or domain rule violation', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Problem']]]],
            '429' => ['description' => 'Rate limited', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Problem']]]],
        ];
        if ($method === 'POST') {
            $responses['200'] = ['description' => 'Success (idempotent replay or synchronous result)', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Envelope']]]];
        }
        if ($auth) {
            $responses['401'] = ['description' => 'Unauthenticated', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Problem']]]];
            $responses['403'] = ['description' => 'Forbidden — `access_not_approved`, `step_up_required` (fresh MFA) or `approval_required` (four-eyes)', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Problem']]]];
        }
        $responses['404'] = ['description' => 'Not found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Problem']]]];

        return $responses;
    }
}
