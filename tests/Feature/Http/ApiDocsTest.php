<?php

declare(strict_types=1);

use App\Http\Controllers\Web\ApiDocsController;
use App\Http\Support\PublicApiDocs;
use App\Http\Support\SurfaceRenderer;
use Illuminate\Routing\Router;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\WebhookDelivery;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Domain\Notifications\Webhooks\WebhookCommandHandler;
use Onhost\Domain\Notifications\Webhooks\WebhookEvents;
use Onhost\Platform\Errors\DomainError;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Tests\FakeHostResolver;

/*
 * Audit 2026-10 package D3: the public API pages (/api, /dokumentace, /dokumentace/api) say what the API does and nothing else.
 * Every statement is held against the routes, the config, the dispatcher and the source — a page that drifts fails here.
 */

/** @return list<string> "METHOD /uri" of every route, as the router has it */
function apiDocsRoutes(): array
{
    $set = [];
    foreach (app(Router::class)->getRoutes() as $route) {
        foreach ($route->methods() as $method) {
            if ($method !== 'HEAD') {
                $set[] = $method.' /'.$route->uri();
            }
        }
    }

    return $set;
}

/** @return list<string> every slug of `new DomainError('slug'` found independently of PublicApiDocs' own scanner */
function apiDocsSourceSlugs(): array
{
    $slugs = [];
    $files = (new Finder)->files()->in([base_path('app'), base_path('domains'), base_path('platform'), base_path('providers')])->name('*.php');
    foreach ($files as $file) {
        $code = $file->getContents();
        if (str_contains($code, 'DomainError') && preg_match_all("~new\\s+DomainError\\(\\s*'([a-z][a-z0-9_.\\-]*)'~", $code, $m)) {
            array_push($slugs, ...$m[1]);
        }
    }

    return array_values(array_unique($slugs));
}

it('shows only endpoints that exist in the routes, with the scope the token middleware demands', function () {
    $routes = apiDocsRoutes();
    $contract = Yaml::parseFile(base_path('contracts/openapi/onhost-v1.yaml'));
    $endpoints = PublicApiDocs::endpoints();

    expect($endpoints)->not->toBeEmpty();
    foreach ($endpoints as $e) {
        expect($routes)->toContain($e['m'].' '.$e['p']);
        $operation = $contract['paths'][substr($e['p'], 3)][strtolower($e['m'])] ?? null;
        expect($operation)->not->toBeNull("{$e['m']} {$e['p']} is not in the OpenAPI contract");
        $scopes = $operation['x-token-scope'] ?? null;
        if (str_contains($e['scope'], ':') && ! str_contains($e['scope'], ' ')) {
            expect($scopes)->toContain($e['scope']);
        }
        if ($e['scope'] === 'jen přihlášený panel') {
            expect($scopes ?? [])->toBe([]); // not reachable with a token: the contract carries no scope for it
        }
    }
});

it('removes the endpoints and the header the prototype invented from /api', function () {
    $js = $this->get('/surfaces/onhost-public.js')->assertOk()->getContent();
    $docs = $this->get('/surfaces/onhost-docs.js')->assertOk()->getContent();
    $page = $this->get('/api')->assertOk()->getContent();

    foreach (['/v1/servers', '/v1/backups', '/v1/audit', '/v1/export', 'X-Confirm-Delete', 'X-Onhost-Confirm', 'X-Onhost-Accept-Cost', 'api.onhost.cz', 'DELETE bez potvrzení'] as $invented) {
        // the served script still carries the prototype's text first (it is replaced at run time), so look at what the replacement leaves
        expect($page)->not->toContain($invented);
    }
    // what the script says after the replacement: the data the page reads
    expect($js)->toContain('"/v1/services"')->toContain('"/v1/tickets"')
        ->and($js)->toContain("filter(function (m) { return m.t !== 'DELETE bez potvrzení'; })")
        ->and($docs)->toContain('API_ARTICLES')->toContain('První volání za 5 minut');
    // the prototype pages print text as written: what this class appends carries no markdown backticks
    expect(substr($js, (int) strpos($js, ';(function () {')))->not->toContain('`')
        ->and(substr($docs, (int) strpos($docs, 'const API_ARTICLES')))->not->toContain('`');
});

it('carries the real limits, the key length and the page size from the config', function () {
    config(['onhost.api.default_rate_limit_per_minute' => 137, 'onhost.api.public_rate_limit_per_minute' => 911, 'onhost.api.idempotency_key_max_length' => 77, 'onhost.api.page_size' => 23, 'onhost.api.max_page_size' => 333, 'onhost.api.idempotency_ttl_hours' => 6]);

    $payload = PublicApiDocs::payload();
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    expect($json)->toContain('137 / min')->toContain('911')->toContain('nejvýš 77 znaků')->toContain('6 h')
        ->and($json)->not->toContain('600 / min')->not->toContain('24 h');

    $html = $this->get('/dokumentace/api')->assertOk()->getContent();
    expect($html)->toContain('<strong>137</strong>')->toContain('<strong>911</strong>')->toContain('nejvýš 77 znaků')->toContain('nejvýš 333')
        ->and($html)->toContain('X-Organization')->toContain('X-Total-Count')->toContain('Retry-After')->toContain('Idempotency-Key');
});

it('mentions the API version header and the in-progress answer exactly when the code can produce them', function () {
    $headers = collect(PublicApiDocs::headers())->pluck(0)->implode(' ');
    $versionInCode = PublicApiDocs::codeHas('app/Http/Middleware/ApiDeprecation.php', "'X-API-Version'");
    $progressInCode = PublicApiDocs::codeHas('platform/Http/Middleware/IdempotencyKey.php', "'idempotency_in_progress'");

    expect(str_contains($headers, 'X-API-Version'))->toBe($versionInCode)
        ->and(str_contains($headers, 'idempotency_in_progress'))->toBe($progressInCode)
        ->and(array_key_exists('idempotency_in_progress', PublicApiDocs::errorSlugs()))->toBe($progressInCode);
});

/*
 * Every claim of docs/api/CHANGELOG.md has code behind it. The claims whose code lives in a pull request that is not merged yet
 * are skipped with the reason (never silently dropped): they start running by themselves the moment the code is on the branch.
 */
it('has code behind the API version header of the changelog', function () {
    $this->get('/v1/status')->assertOk()->assertHeader('X-API-Version');
})->skip(fn () => ! PublicApiDocs::codeHas('app/Http/Middleware/ApiDeprecation.php', "'X-API-Version'"), 'the X-API-Version middleware (D7) is not on this branch yet');

it('has code behind the in-progress answer and the replay header of the changelog', function () {
    $source = (string) file_get_contents(base_path('platform/Http/Middleware/IdempotencyKey.php'));
    expect($source)->toContain("'idempotency_in_progress'")->toContain("'Retry-After'")->toContain("'Idempotent-Replayed'")->toContain("'already_done'")->toContain("'invalid_idempotency_key'");
})->skip(fn () => ! PublicApiDocs::codeHas('platform/Http/Middleware/IdempotencyKey.php', "'idempotency_in_progress'"), 'the atomic idempotency reservation (D5) is not on this branch yet');

it('has code behind the staff guard and the removed panel-login GET of the changelog', function () {
    $routes = apiDocsRoutes();
    expect($routes)->not->toContain('GET /v1/staff/services/{service}/panel-login')->toContain('POST /v1/staff/services/{service}/panel-login');

    [$owner] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');
    $this->getJson('/v1/staff/customers')->assertForbidden()->assertJsonPath('error', 'staff_only');
    $this->getJson('/v1/staff/services/svc_x/panel-login')->assertStatus(405);
});

it('has code behind the operationId and x-token-scope claims of the changelog', function () {
    $contract = Yaml::parseFile(base_path('contracts/openapi/onhost-v1.yaml'));

    expect($contract['paths']['/services']['get']['operationId'])->toBe('getServices')
        ->and($contract['paths']['/domains/{domain}/holder']['post']['operationId'])->toBe('postDomainsByDomainHolder')
        ->and($contract['paths']['/services']['get']['x-token-scope'])->toBe(['services:read']);
});

it('gives no two error rows the same anchor', function () {
    $html = $this->get('/dokumentace/api')->assertOk()->getContent();
    preg_match_all('~\bid="([^"]+)"~', $html, $m);
    $duplicates = array_keys(array_filter(array_count_values($m[1]), fn (int $n) => $n > 1));
    expect($duplicates)->toBe([]);

    // two slugs that differ only in `_` against `-` would share the help anchor
    $byAnchor = [];
    foreach (array_keys(PublicApiDocs::errorSlugs()) as $slug) {
        $byAnchor[PublicApiDocs::anchor($slug)][] = $slug;
    }
    expect(array_filter($byAnchor, fn (array $slugs) => count($slugs) > 1))->toBe([]);
});

it('dates the guides by the newest changelog entry, not by the day they are served', function () {
    $newest = PublicApiDocs::changelog()[0]['date'];
    expect(PublicApiDocs::docsArticles()['api-start']['cs']['updated'])->toBe($newest);
});

it('keeps the error cache key tied to the code that is deployed', function () {
    expect(PublicApiDocs::buildMarker())->toMatch('~^\d{9,}$~');
});

it('describes the webhook retry the dispatcher really performs', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    FakeHostResolver::$hosts['hooks.docs-example.cz'] = ['203.0.113.50'];
    Http::fake(fn () => Http::response('down', 500));
    $created = $this->postJson('/v1/webhooks', ['url' => 'https://hooks.docs-example.cz/in'])->assertCreated()->json('data');
    $dispatcher = app(WebhookDispatcher::class);
    $delivery = WebhookDelivery::query()->create(['endpoint_id' => $created['id'], 'event' => 'service.activated', 'payload' => ['x' => 1], 'state' => 'pending', 'attempts' => 0]);

    Carbon::setTestNow(Carbon::parse('2026-10-04 12:00:00'));
    $start = now();
    $offsets = [];
    try {
        for ($i = 0; $i < 20; $i++) {
            $offsets[] = (int) $start->diffInMinutes(now(), true);
            $state = $dispatcher->deliver($delivery, WebhookEndpoint::query()->findOrFail($created['id']));
            $delivery->refresh();
            if ($state === 'dead') {
                break;
            }
            Carbon::setTestNow($delivery->next_attempt_at);
        }
    } finally {
        Carbon::setTestNow();
    }

    $schedule = PublicApiDocs::retrySchedule();
    expect($offsets)->toBe($schedule['offsets'])
        ->and(count($offsets))->toBe($schedule['attempts'])
        ->and($delivery->state)->toBe('dead')
        ->and(PublicApiDocs::retrySentence())->toContain((string) $schedule['attempts'].' pokusů')->toContain(PublicApiDocs::duration($schedule['last_minutes']));
    // the whole backoff is waited out: the last retry comes 876 minutes (14.6 h) after the first attempt, six attempts in all
    expect($schedule['last_minutes'])->toBe(array_sum(WebhookDispatcher::BACKOFF_MINUTES))->toBe(876)->and($schedule['attempts'])->toBe(6)
        ->and($schedule['offsets'])->toBe([0, ...WebhookDispatcher::retryScheduleMinutes()]);
});

it('anchors every error slug of the code on /dokumentace/api, in the form the help link uses and as the raw slug', function () {
    $html = $this->get('/dokumentace/api')->assertOk()->getContent();
    $slugs = apiDocsSourceSlugs();

    expect(count($slugs))->toBeGreaterThan(300);
    $missing = [];
    foreach ($slugs as $slug) {
        $hyphenated = str_replace('_', '-', $slug);
        if (! str_contains($html, 'id="'.$hyphenated.'"')) {
            $missing[] = $slug;
        }
        if ($hyphenated !== $slug && ! str_contains($html, 'id="'.$slug.'"')) {
            $missing[] = $slug.' (raw)';
        }
    }
    expect($missing)->toBe([]);

    // what a response carries in `help` lands on an anchor
    $problem = (new DomainError('idempotency_key_reused', 'x', 409))->toProblem();
    expect($problem['help'])->toBe('/dokumentace/api#idempotency-key-reused')
        ->and($html)->toContain('id="idempotency-key-reused"');
    // the answers the framework and the provider layer give have a row too
    foreach (['validation_failed', 'rate_limited', 'unauthenticated', 'provider_transient', 'provider_circuit_open'] as $slug) {
        expect($html)->toContain('id="'.str_replace('_', '-', $slug).'"');
    }
});

it('names a framework slug only when the code produces it', function () {
    $sources = file_get_contents(base_path('bootstrap/app.php')).file_get_contents(base_path('platform/Http/Middleware/IdempotencyKey.php')).file_get_contents(base_path('app/Http/Support/ApiContext.php'));
    $reflection = new ReflectionClassConstant(PublicApiDocs::class, 'FRAMEWORK_SLUGS');
    foreach (array_keys($reflection->getValue()) as $slug) {
        $produced = str_contains($sources, "'{$slug}'") || in_array($slug, apiDocsSourceSlugs(), true);
        // a described slug is produced, and a produced one is described
        expect(array_key_exists($slug, PublicApiDocs::errorSlugs()))->toBe($produced, "{$slug}: described=".($produced ? 'no' : 'yes').' but produced='.($produced ? 'yes' : 'no'));
    }
});

it('serves the documentation page under its own policy: no eval, no third-party host, a vendored Redoc', function () {
    $response = $this->get('/dokumentace/api')->assertOk();
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toBe(ApiDocsController::POLICY)
        ->and($csp)->not->toContain('unsafe-eval')->not->toContain('http')->not->toContain('*')
        ->and($csp)->toContain("script-src 'self'");
    $html = $response->getContent();
    preg_match_all('~<(?:script|link)[^>]+(?:src|href)="([^"]+)"~', $html, $m);
    foreach ($m[1] as $url) {
        expect(str_starts_with($url, '/') || str_starts_with($url, '#'))->toBeTrue("external resource {$url}");
    }
    expect($html)->toContain('/vendor/redoc/redoc.standalone.js')->not->toContain('<script>')
        ->and(file_exists(public_path('vendor/redoc/redoc.standalone.js')))->toBeTrue()
        ->and(file_exists(public_path('vendor/redoc/api-docs.js')))->toBeTrue()
        ->and($html)->toContain('data-spec-url="/openapi.yaml"');
    $this->get('/openapi.yaml')->assertOk();
});

it('keeps the vendored Redoc bundle free of eval and pins its two new Function uses', function () {
    $bundle = (string) file_get_contents(public_path('vendor/redoc/redoc.standalone.js'));
    // a direct eval() call would need 'unsafe-eval'. The bundle's two `new Function` uses (Ajv's generated validators and a global-object lookup)
    // did not run in the Playwright smoke (no securitypolicyviolation); pinned, so an update of the bundle that adds one is looked at
    expect(preg_match('~[^a-zA-Z_.$]eval\(~', $bundle))->toBe(0)
        ->and(substr_count($bundle, 'new Function'))->toBe(2);
});

it('keeps the first-call guide of the page and docs/api/FIRST-CALL.md identical line by line', function () {
    $md = (string) file_get_contents(base_path('docs/api/FIRST-CALL.md'));
    preg_match_all('~```bash\n(.*?)\n```~s', $md, $m);
    $fromMarkdown = $m[1];
    $fromCode = array_map(fn (array $s) => str_replace('{base}', '{API_BASE}', $s['code']), PublicApiDocs::firstCall());

    expect($fromMarkdown)->toBe($fromCode);
});

it('reads the API changelog from docs/api/CHANGELOG.md, one entry per change the clients notice', function () {
    $changes = PublicApiDocs::changelog();
    $titles = implode(' | ', array_column($changes, 't'));

    expect($changes)->not->toBeEmpty()
        ->and($titles)->toContain('operationId')->toContain('personál')->toContain('Idempotency-Key')->toContain('verze')
        ->and(array_column($changes, 'tag'))->toContain('nekompatibilní')->toContain('kompatibilní');
    $html = $this->get('/dokumentace/api')->assertOk()->getContent();
    foreach ($changes as $c) {
        expect($html)->toContain(e($c['t']));
    }
});

it('replaces the prototype sentences and the two scripts exactly where the seams expect them', function () {
    $renderer = app(SurfaceRenderer::class);
    $misses = array_filter($renderer->anchorReport('public'), fn ($a) => in_array($a['anchor'], array_keys(PublicApiDocs::pageCopy()), true) && $a['hits'] !== 1);
    expect($misses)->toBe([])
        ->and(count(array_filter($renderer->anchorReport('public'), fn ($a) => in_array($a['anchor'], array_keys(PublicApiDocs::pageCopy()), true))))->toBe(count(PublicApiDocs::pageCopy()));

    $page = $this->get('/api')->assertOk()->getContent();
    expect($page)->not->toContain('retried for 24 hours')->not->toContain('opakujeme 24 hodin')->not->toContain('Výchozí okno je 40 záznamů.');

    // the module seam: the prototype's docs module keeps its shape, otherwise the guides silently fall back to the prototype's text
    $module = (string) file_get_contents(base_path('apps/surfaces/onhost-docs.js'));
    expect(PublicApiDocs::docsModule($module))->not->toBeNull()->toContain('export function docsArticles(cs) {')
        ->and(PublicApiDocs::docsModule('export function docsArticles(cs) { /* x */ }'."\n".'export function docsArticles(cs) {'))->toBeNull();
    foreach (['api-start', 'api-limity'] as $id) {
        expect($module)->toContain("id: '{$id}'");
    }
});

it('serves the prototype scripts untouched in demo mode', function () {
    config(['onhost.ui.demo' => true]);
    $js = $this->get('/surfaces/onhost-public.js')->assertOk();

    expect($js->streamedContent())->toBe((string) file_get_contents(base_path('apps/surfaces/onhost-public.js')));
});

it('never lists a customer-facing error slug with no anchor on the /api page copy', function () {
    foreach (PublicApiDocs::payload()['api']['errors'] as $row) {
        expect(PublicApiDocs::errorSlugs())->toHaveKey($row['slug']);
        expect(Str::contains($row['body'], '/dokumentace/api#'.PublicApiDocs::anchor($row['slug'])))->toBeTrue();
    }
});

it('keeps the webhook numbers of the changelog and the pages equal to the dispatcher', function () {
    $entry = collect(PublicApiDocs::changelog())->first(fn (array $c) => str_contains($c['t'], 'Webhooky'));
    expect($entry)->not->toBeNull();
    foreach (WebhookDispatcher::retryScheduleMinutes() as $minutes) {
        expect($entry['d'])->toContain((string) $minutes);
    }
    expect($entry['d'])->toContain('po '.WebhookDispatcher::SUSPEND_AFTER.' neúspěšných')->toContain('nejvýš '.WebhookDispatcher::MAX_ATTEMPTS.'krát')
        ->toContain('port '.implode(' nebo ', WebhookDispatcher::ALLOWED_PORTS))->toContain('jednou za '.WebhookCommandHandler::PING_COOLDOWN_SECONDS.' s');
    $html = $this->get('/dokumentace/api')->assertOk()->getContent();
    expect($html)->toContain('X-ONhost-Delivery')->toContain('aggregate')->toContain('2 h 36 min')->toContain('14 h 36 min')->and(PublicApiDocs::eventFamilies())->toBe(WebhookEvents::FAMILIES);
});

it('has code behind the service account and dns:read claims of the changelog', function () {
    $routes = apiDocsRoutes();
    foreach (['GET /v1/service-accounts', 'POST /v1/service-accounts', 'POST /v1/service-accounts/{account}/tokens', 'DELETE /v1/service-accounts/{account}/tokens/{token}'] as $route) {
        expect($routes)->toContain($route);
    }
    $contract = Yaml::parseFile(base_path('contracts/openapi/onhost-v1.yaml'));
    expect($contract['paths'])->toHaveKey('/service-accounts/{account}/tokens')
        ->and(TokenScopes::ALL)->toContain('dns:read')
        ->and(TokenScopes::IMPLIED_BY['dns:read'])->toContain('dns:write')
        ->and(PublicApiDocs::scopeOf('GET', '/v1/domains/{zone}/zone'))->toBe('dns:read')
        ->and(PublicApiDocs::scopeOf('POST', '/v1/domains/{zone}/zone/commit'))->toBe('dns:write')
        ->and(PublicApiDocs::scopes())->toContain('dns:read')
        ->and(PublicApiDocs::errorSlugs())->toHaveKey('person_required');
});
