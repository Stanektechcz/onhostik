<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Middleware\TokenRouteScope;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Http\Middleware\IdempotencyKey;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Yaml\Yaml;

/**
 * Generates `contracts/openapi/onhost-v1.yaml` from the registered routes so the contract never drifts from
 * the code. Operation ids, tags, security requirements and parameters come from the route definitions;
 * shared schemas (Money, Problem, pagination) are declared once below. Request/response bodies are
 * documented per tag in the controllers' FormRequest rules (validated at runtime) and in docs/api.
 */
final class GenerateOpenApi extends Command
{
    protected $signature = 'onhost:openapi {--out=contracts/openapi/onhost-v1.yaml} {--check : Write nothing; exit 1 when the file differs from what the routes generate}';

    protected $description = 'Write the OpenAPI 3.1 contract for /v1 from the registered routes';

    /**
     * What a route alone cannot tell (TASK-0066): documented request bodies and the error slugs an operation refuses with, keyed by
     * "METHOD /path". Added here, not by hand in the YAML, so a regeneration (every deploy runs it) keeps them.
     *
     * @var array<string, array{body?: array<string, mixed>, errors?: array<int, list<string>>}>
     */
    private const DETAILS = [
        'POST /domains/transfer-in' => [
            'body' => ['required' => ['fqdn', 'auth_info', 'consent'], 'properties' => [
                'fqdn' => ['type' => 'string', 'maxLength' => 253],
                'order_item_id' => ['type' => 'string', 'maxLength' => 40, 'description' => 'The PAID order line `action: transfer` for this name (TASK-0058). Required for customers; only staff acting as staff may transfer without one.'],
                'auth_info' => ['type' => 'string', 'maxLength' => 64, 'writeOnly' => true, 'description' => 'Transfer code (AUTH-ID) from the current registrar; kept encrypted until the registrar has it, never returned.'],
                'registrant_contact_id' => ['type' => 'string'], 'registrant' => ['type' => 'object'], 'nameservers' => ['type' => 'array', 'items' => ['type' => 'string']],
                'period' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'description' => 'Ignored with an order line: the paid years decide.'],
                'consent' => ['type' => 'object', 'required' => ['person'], 'properties' => ['person' => ['type' => 'string', 'maxLength' => 190]]],
            ]],
            'errors' => ['422' => ['transfer_needs_order', 'transfer_order_mismatch', 'domain_invalid'], '409' => ['transfer_order_not_paid', 'transfer_already_submitted', 'transfer_line_closed', 'domain_taken']],
        ],
        'POST /domains/{domain}/holder' => [
            'body' => ['properties' => [
                'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 190], 'phone' => ['type' => 'string', 'maxLength' => 40], 'street' => ['type' => 'string', 'maxLength' => 190],
                'city' => ['type' => 'string', 'maxLength' => 120], 'postal_code' => ['type' => 'string', 'maxLength' => 20], 'country' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 2],
            ], 'description' => 'How the holder is reached (e-mail, phone, address), changed at the registrar first. HIGH: needs a fresh step-up. The holder himself (name, company, IČO, DIČ) is a transfer of the domain and is refused.'],
        ],
        'POST /cart/quote' => ['errors' => ['422' => ['domain_action_invalid']]],
        'POST /orders' => ['errors' => ['422' => ['domain_action_invalid']]],
        'POST /checkout/guest' => ['errors' => ['422' => ['domain_action_invalid']]],
    ];

    public function handle(Router $router): int
    {
        $paths = [];
        $operationIds = [];
        foreach ($router->getRoutes() as $route) {
            /** @var Route $route */
            $uri = $route->uri();
            if ($uri !== 'v1' && ! str_starts_with($uri, 'v1/')) {
                continue;
            }
            $methods = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
            $path = '/'.preg_replace('/\{([a-zA-Z_]+)\??\}/', '{$1}', substr($uri, 2) === '' ? '' : substr($uri, 3));
            $path = $path === '/' ? '/' : '/'.ltrim($path, '/');
            $middleware = $route->gatherMiddleware();
            $auth = in_array('auth:sanctum', $middleware, true);
            $resolved = $router->gatherRouteMiddleware($route);
            $idempotent = in_array(IdempotencyKey::class, $resolved, true);
            $tokenScoped = in_array(TokenRouteScope::class, $resolved, true);
            $paging = $this->paging($route);
            $tag = $this->tag($uri);
            foreach ($methods as $method) {
                $operation = [
                    'operationId' => $this->operationId($path, $method),
                    'tags' => [$tag],
                    'summary' => $this->summary($route, $method),
                    'parameters' => $this->parameters($route, $method, $paging),
                    'responses' => $this->responses($method, $auth),
                ];
                // what a token may do here is asked of TokenRouteScope itself (the one map), not copied: null = not for tokens
                $scopes = $auth && $tokenScoped ? $this->tokenScopes($route, $method) : [];
                $operation['security'] = []; // public unless a sign-in is required: stated, not left to a default
                if ($auth) {
                    $operation['security'] = [['session' => []]];
                    if ($scopes !== null) {
                        $operation['security'][] = ['bearer' => []];
                    }
                }
                $operation['x-token-scope'] = $scopes;
                if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                    $operation['requestBody'] = ['required' => false, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'additionalProperties' => true]]]];
                }
                if ($idempotent && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                    $operation['parameters'][] = ['$ref' => '#/components/parameters/IdempotencyKey'];
                }
                if ($auth) {
                    $operation['parameters'][] = ['$ref' => '#/components/parameters/Organization'];
                }
                $key = strtolower($method);
                if (isset($paths[$path][$key])) {
                    $this->error("Two routes answer {$method} {$path}; the contract holds one operation per method and path");

                    return self::FAILURE;
                }
                if (isset($operationIds[$operation['operationId']])) {
                    $this->error("operationId {$operation['operationId']} is produced by both {$operationIds[$operation['operationId']]} and {$method} {$path}");

                    return self::FAILURE;
                }
                $operationIds[$operation['operationId']] = "{$method} {$path}";
                $paths[$path][$key] = $this->detailed($operation, self::DETAILS["{$method} {$path}"] ?? []);
            }
        }
        $paths = array_map(function (array $operations): array {
            ksort($operations);

            return $operations;
        }, $paths);
        ksort($paths);

        $pageSize = (int) config('onhost.api.page_size', 40);
        $maxPage = (int) config('onhost.api.max_page_size', 200);
        $keyMax = (int) config('onhost.api.idempotency_key_max_length', 200);
        $ttl = (int) config('onhost.api.idempotency_ttl_hours', 24);

        $document = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'ONhost Cloud Platform API',
                'version' => (string) config('onhost.version', '4.0'),
                'description' => "Control-plane API of the ONhost hosting platform (blueprint §17). JSON only. Errors use `{error, message, status, errors?}`.\nLists accept `?limit=&offset=` (default {$pageSize}, at most {$maxPage}) and return `X-Total-Count`. Mutations behind the idempotency middleware honour `Idempotency-Key` (at most {$keyMax} characters, replayed for {$ttl} h). Money is `{minor, currency, decimal}`.\nGenerated from routes by `php artisan onhost:openapi` — do not edit by hand.",
                'contact' => ['name' => 'ONhost API'],
            ],
            'servers' => [['url' => '/v1', 'description' => 'Relative to the host that serves this contract; the file does not depend on the environment that generated it, so a deploy can compare it with the committed one.']],
            'tags' => array_map(fn (string $t) => ['name' => $t, 'description' => "Operations of the {$t} area."], array_values(array_unique(array_map(fn ($ops) => $ops[array_key_first($ops)]['tags'][0], $paths)))),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'session' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'onhost_session', 'description' => 'Sanctum SPA session (login via POST /auth/login, CSRF cookie required).'],
                    'bearer' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'Personal/service API token `onh_live_…` with documented scopes (POST /tokens).'],
                ],
                'parameters' => [
                    'IdempotencyKey' => ['name' => 'Idempotency-Key', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string', 'maxLength' => $keyMax], 'description' => 'Replay-safe key; the same key returns the stored result within the TTL.'],
                    'Organization' => ['name' => 'X-Organization', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Organization context for users that belong to several organizations.'],
                    'Limit' => ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $maxPage, 'default' => $pageSize]],
                    'LimitOnly' => ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $maxPage], 'description' => 'Page size; this list has its own default and takes no offset.'],
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
        $yaml = Yaml::dump($document, 12, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        $summary = sprintf('%s: %d paths, %d operations', $out, count($paths), array_sum(array_map('count', $paths)));

        if ($this->option('check')) {
            $current = is_file($out) ? file_get_contents($out) : false;
            if ($current === $yaml) {
                $this->info("{$summary} - up to date");

                return self::SUCCESS;
            }
            $this->error(($current === false ? "{$out} is missing" : "{$out} differs from the routes").' - run `php artisan onhost:openapi` and commit the result');

            return self::FAILURE;
        }
        if (! is_dir(dirname($out)) && ! mkdir(dirname($out), 0777, true) && ! is_dir(dirname($out))) {
            $this->error("Cannot create the directory of {$out}");

            return self::FAILURE;
        }
        if (file_put_contents($out, $yaml) === false) {
            $this->error("Cannot write {$out}");

            return self::FAILURE;
        }
        $this->info($summary);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array{body?: array<string, mixed>, errors?: array<int, list<string>>}  $details
     * @return array<string, mixed>
     */
    private function detailed(array $operation, array $details): array
    {
        if (isset($details['body'], $operation['requestBody'])) {
            $operation['requestBody']['content']['application/json']['schema'] = ['type' => 'object'] + $details['body'];
        }
        foreach ($details['errors'] ?? [] as $status => $slugs) {
            $listed = implode(', ', array_map(fn (string $slug) => "`{$slug}`", $slugs));
            $base = $operation['responses'][$status]['description'] ?? ((string) $status === '409' ? 'Conflict with the current state' : 'Refused');
            $operation['responses'][$status] = ['description' => "{$base} — error: {$listed}", 'content' => ['application/json' => ['schema' => ['allOf' => [['$ref' => '#/components/schemas/Problem'], ['properties' => ['error' => ['enum' => $slugs]]]]]]]];
        }

        return $operation;
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

    /** Unique by construction: the method and the path are what an operation is (`POST /domains/{domain}/holder` becomes postDomainsByDomainHolder). */
    private function operationId(string $path, string $method): string
    {
        $words = [];
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }
            $words[] = preg_match('/^\{(.+)\}$/', $segment, $m) === 1 ? 'by_'.$m[1] : $segment;
        }

        return Str::camel(preg_replace('/[^A-Za-z0-9]+/', '_', strtolower($method).'_'.implode('_', $words === [] ? ['root'] : $words)));
    }

    private function summary(Route $route, string $method): string
    {
        $uri = '/'.ltrim(substr($route->uri(), 2), '/');

        return strtoupper($method).' '.$uri;
    }

    /**
     * How the list is paged, read from the controller method itself: `ApiContext::paginate` (limit + offset + X-Total-Count),
     * its own `query('limit')` (limit only), or not at all.
     *
     * @return 'full'|'limit'|null
     */
    private function paging(Route $route): ?string
    {
        if (! in_array('GET', $route->methods(), true) || ! str_contains($route->getActionName(), '@')) {
            return null;
        }
        [$class, $name] = explode('@', $route->getActionName());
        try {
            $method = new \ReflectionMethod($class, $name);
            $file = $method->getFileName();
            $source = $file === false ? [] : array_slice(file($file) ?: [], $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
        } catch (\ReflectionException) {
            return null;
        }
        $body = implode('', $source);

        return match (true) {
            str_contains($body, '->paginate($request') => 'full',
            str_contains($body, "query('limit'") => 'limit',
            default => null,
        };
    }

    /** @param 'full'|'limit'|null $paging */
    private function parameters(Route $route, string $method, ?string $paging): array
    {
        $params = [];
        foreach ($route->parameterNames() as $name) {
            $params[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
        }
        if ($method === 'GET' && $paging === 'full') {
            $params[] = ['$ref' => '#/components/parameters/Limit'];
            $params[] = ['$ref' => '#/components/parameters/Offset'];
        } elseif ($method === 'GET' && $paging === 'limit') {
            $params[] = ['$ref' => '#/components/parameters/LimitOnly'];
        }

        return $params;
    }

    /**
     * The scopes a bearer token needs on this operation, found by running TokenRouteScope itself: a token with no scope, then one
     * token per known scope. null = the middleware refuses every token here, [] = it asks for no scope, otherwise the scopes that
     * get a token in (a generic action endpoint is decided by the action, so every action is tried).
     *
     * @return list<string>|null
     */
    private function tokenScopes(Route $route, string $method): ?array
    {
        $uri = '/'.preg_replace('/\{[^}]+\}/', 'x', $route->uri());
        $inputs = [[]];
        if ($method === 'POST' && str_ends_with($route->uri(), '/{service}/actions')) {
            $inputs = array_map(fn (string $action) => ['action' => $action], ServiceActionWorkflow::ACTIONS);
            $inputs[] = ['action' => '?'];
        }
        $scopes = [];
        $open = false;
        foreach ($inputs as $input) {
            if ($this->tokenPasses($uri, $method, $input, [])) {
                $open = true;

                continue;
            }
            foreach (TokenScopes::ALL as $scope) {
                if ($this->tokenPasses($uri, $method, $input, [$scope])) {
                    $scopes[] = $scope;
                }
            }
        }
        $scopes = array_values(array_unique($scopes));
        sort($scopes);

        return $scopes !== [] ? $scopes : ($open ? [] : null);
    }

    /** @param array<string, mixed> $input @param list<string> $abilities */
    private function tokenPasses(string $uri, string $method, array $input, array $abilities): bool
    {
        $token = new PersonalAccessToken;
        $token->organization_id = 'probe'; // bound to an organization: the unbound refusal is a deployment switch, not a route property
        $token->abilities = $abilities;
        $user = new class($token)
        {
            public function __construct(private readonly PersonalAccessToken $token) {}

            public function currentAccessToken(): PersonalAccessToken
            {
                return $this->token;
            }
        };
        $request = Request::create($uri, $method, $input);
        $request->setUserResolver(fn () => $user);
        try {
            (new TokenRouteScope)->handle($request, fn () => response(''));

            return true;
        } catch (DomainError|AccessDeniedHttpException) { // the refusals TokenRouteScope throws; anything else is a bug and must show
            return false;
        }
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
