<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\PersonalAccessToken;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Identity\StepUp\Totp;
use Onhost\Domain\Loyalty\Models\LoyaltyPoint;
use Onhost\Domain\Notifications\Models\WebhookEndpoint;
use Onhost\Domain\Notifications\WebhookDispatcher;
use Onhost\Domain\Notifications\Webhooks\WebhookCommandHandler;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\IdempotencyStore;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/*
 * F10: docs/manual-tests/10-api.md is a manual a person follows with curl. Every `curl` in it is replayed here, in the order of the
 * page, against the real routes — method, path, headers and body exactly as the page writes them — and must answer what the line
 * `# expect:` above it says. A page that promises a status the API no longer gives, a header that is gone, or an operation the
 * contract does not have fails here.
 *
 * Markers (plain bash comments above the command, see the table at the top of the page):
 *   expect, expect-error, expect-header, expect-json, expect-count, expect-contains, expect-not-contains  what the answer must be
 *   scope   `any` (contract: []), `none` (contract: null, the portal only) or a token scope the contract lists for the operation
 *   save    keeps a field of the answer in a variable the next examples use ($ONHOST_TOKEN, $ENDPOINT_ID, …)
 *   arrange what a person does by hand or waits for (time, a request that is still running, a receiver that is down)
 *
 * What is real: the sign-in, the step-up (a TOTP code that is valid at the moment of use, each one once), the cookie session of the
 * browser (Set-Cookie of an answer is sent back under the cookie file's name), the tokens, the webhook endpoint, its signature.
 * What is a double: the webhook receiver (Http::fake). What has no route: a member of the organization, three services, a key
 * that is held by a request that is still running, a failure counter at 19 — each said where it happens (`arrange`).
 */

/** The state one replay of the page carries from example to example. */
final class ApiManualRun
{
    /** @var array<string,string> */
    public array $vars = [];

    /** @var array<string,array<string,string>> cookie file name => cookie name => value */
    public array $jars = [];

    /** @var list<HttpClientRequest> requests that reached the webhook receiver */
    public array $seen = [];

    public int $receiverStatus = 204;

    /** @var list<callable> put back after the example */
    public array $restore = [];

    /** @var list<string> */
    public array $ran = [];

    /** @var list<string> */
    public array $routes = [];

    public int $totpUses = 0;

    public ?User $owner = null;

    public ?Organization $org = null;

    public ?string $totpSecret = null;
}

const API_MANUAL_PAGE = 'docs/manual-tests/10-api.md';

/** @return list<array<string,mixed>> the examples of the page in order: kind curl|run|setup|orphan, line, section, notes, exports, cmd|code|name */
function apiManualParse(string $markdown): array
{
    $lines = preg_split('/\R/', $markdown) ?: [];
    $entries = [];
    $section = '';
    $count = count($lines);
    for ($i = 0; $i < $count; $i++) {
        if (preg_match('/^#{2,3} (.+)$/', $lines[$i], $m)) {
            $section = $m[1];
        }
        if (preg_match('/^```(bash|php)\s*$/', $lines[$i], $m)) {
            $body = [];
            $start = $i + 2;
            for ($i++; $i < $count && $lines[$i] !== '```'; $i++) {
                $body[] = [$i + 1, $lines[$i]];
            }
            array_push($entries, ...apiManualBlock($m[1], $body, $section, $start));
        }
    }

    return $entries;
}

/**
 * @param  list<array{0:int,1:string}>  $body
 * @return list<array<string,mixed>>
 */
function apiManualBlock(string $language, array $body, string $section, int $start): array
{
    $text = implode("\n", array_column($body, 1));
    if ($language === 'php') {
        return [preg_match('~^// run: ([a-z-]+)\s*$~m', $text, $m) ? ['kind' => 'run', 'line' => $start, 'section' => $section, 'name' => $m[1], 'code' => $text] : ['kind' => 'orphan', 'line' => $start, 'section' => $section]];
    }
    if (preg_match('/^# run: ([a-z-]+)\s*$/m', $text, $m)) {
        return [['kind' => 'run', 'line' => $start, 'section' => $section, 'name' => $m[1], 'code' => $text]];
    }
    $entries = [];
    $notes = [];
    $exports = [];
    for ($k = 0; $k < count($body); $k++) {
        [$number, $line] = $body[$k];
        if (preg_match('/^#\s*(expect-error|expect-header|expect-json|expect-contains|expect-not-contains|expect-count|expect|scope|arrange|save):\s*(.*)$/', $line, $m)) {
            $notes[] = [$m[1], trim($m[2])];
        } elseif (preg_match('/^export ([A-Z_]+)=(.*)$/', $line, $m)) {
            $exports[] = [$m[1], $m[2]];
        } elseif (str_starts_with($line, 'curl ')) {
            $command = $line;
            while (str_ends_with($command, '\\') && isset($body[$k + 1])) {
                $command = substr($command, 0, -1).' '.$body[++$k][1];
            }
            $entries[] = ['kind' => 'curl', 'line' => $number, 'section' => $section, 'cmd' => $command, 'notes' => $notes, 'exports' => $exports];
            $notes = [];
            $exports = [];
        }
    }
    if ($entries === []) {
        return [preg_match('/^# setup\s*$/m', $text) ? ['kind' => 'setup', 'line' => $start, 'section' => $section] : ['kind' => 'orphan', 'line' => $start, 'section' => $section]];
    }

    return $entries;
}

/** What `$NAME`, `${NAME}` and `$(uuidgen)` stand for at the moment of use; anything else a shell would compute is empty. */
function apiManualDollar(string $text, int &$i, ApiManualRun $run): string
{
    $rest = substr($text, $i + 1);
    if (str_starts_with($rest, '(')) {
        $end = strpos($rest, ')');
        $inner = $end === false ? '' : substr($rest, 1, $end - 1);
        $i += ($end === false ? strlen($rest) : $end + 1);

        return $inner === 'uuidgen' ? (string) Str::uuid() : '';
    }
    if (str_starts_with($rest, '{') && ($end = strpos($rest, '}')) !== false) {
        $name = substr($rest, 1, $end - 1);
        $i += $end + 1;
    } elseif (preg_match('/^[A-Za-z_][A-Za-z0-9_]*/', $rest, $m)) {
        $name = $m[0];
        $i += strlen($name);
    } else {
        return '$';
    }

    return $name === 'ONHOST_TOTP' ? apiManualTotp($run) : ($run->vars[$name] ?? '');
}

/** The authenticator's code of the owner at this moment; each use takes the next window (-1, 0, +1), because a code is never accepted twice. */
function apiManualTotp(ApiManualRun $run): string
{
    $offset = [-30, 0, 30][$run->totpUses] ?? null;
    expect($offset)->not->toBeNull('the page asks for more than three TOTP codes: the replay hands out one per accepted window (-1, 0, +1)');
    $run->totpUses++;

    return Totp::code((string) $run->totpSecret, time() + $offset);
}

/** @return list<string> the words of one shell command line (quotes, escapes, variables), up to a pipe */
function apiManualWords(string $command, ApiManualRun $run): array
{
    $words = [];
    $word = '';
    $open = false;
    $length = strlen($command);
    for ($i = 0; $i < $length; $i++) {
        $c = $command[$i];
        if ($c === "'") {
            $end = strpos($command, "'", $i + 1);
            $end = $end === false ? $length : $end;
            $word .= substr($command, $i + 1, $end - $i - 1);
            $open = true;
            $i = $end;
        } elseif ($c === '"') {
            $open = true;
            for ($i++; $i < $length && $command[$i] !== '"'; $i++) {
                if ($command[$i] === '\\' && in_array($command[$i + 1] ?? '', ['"', '\\', '$', '`'], true)) {
                    $word .= $command[++$i];
                } elseif ($command[$i] === '$') {
                    $word .= apiManualDollar($command, $i, $run);
                } else {
                    $word .= $command[$i];
                }
            }
        } elseif ($c === '|') {
            break;
        } elseif (ctype_space($c)) {
            if ($open) {
                $words[] = $word;
                $word = '';
                $open = false;
            }
        } elseif ($c === '\\') {
            $word .= $command[++$i] ?? '';
            $open = true;
        } elseif ($c === '$') {
            $word .= apiManualDollar($command, $i, $run);
            $open = true;
        } else {
            $word .= $c;
            $open = true;
        }
    }
    if ($open) {
        $words[] = $word;
    }

    return $words;
}

/** @return array{method:string,url:string,headers:array<string,string>,body:?string,jar_in:?string,jar_out:?string} */
function apiManualCurl(string $command, ApiManualRun $run): array
{
    $words = apiManualWords($command, $run);
    expect(array_shift($words))->toBe('curl');
    $request = ['method' => null, 'url' => '', 'headers' => [], 'body' => null, 'jar_in' => null, 'jar_out' => null];
    $withArgument = ['-X', '-H', '-d', '--data', '--data-raw', '--data-binary', '-b', '--cookie', '-c', '--cookie-jar', '-o', '-w', '--max-time', '--connect-timeout'];
    for ($i = 0; $i < count($words); $i++) {
        $word = $words[$i];
        if (in_array($word, $withArgument, true)) {
            $value = $words[++$i] ?? '';
            match ($word) {
                '-X' => $request['method'] = strtoupper($value),
                '-H' => $request['headers'][trim(explode(':', $value, 2)[0])] = trim(explode(':', $value, 2)[1] ?? ''),
                '-d', '--data', '--data-raw', '--data-binary' => $request['body'] = $value,
                '-b', '--cookie' => $request['jar_in'] = $value,
                '-c', '--cookie-jar' => $request['jar_out'] = $value,
                default => null,
            };
        } elseif (! str_starts_with($word, '-')) {
            $request['url'] = $word;
        }
    }
    $request['method'] ??= $request['body'] !== null ? 'POST' : 'GET';

    return $request;
}

/** The test client as a stranger: no session, no bearer, no cookie of an earlier request. */
function apiManualForget(object $test): void
{
    app('auth')->forgetGuards();
    $test->flushHeaders();
    $test->flushSession();
    (function () {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->withCredentials = false;
    })->call($test);
}

/** The request as the server sees it: `/v1/...` (what the page calls `$ONHOST_API`) or a path of the host. */
function apiManualPath(string $url, ApiManualRun $run): string
{
    foreach ([$run->vars['ONHOST_API'] => '/v1', $run->vars['ONHOST_BASE'] => ''] as $prefix => $replacement) {
        if (str_starts_with($url, (string) $prefix)) {
            return $replacement.substr($url, strlen((string) $prefix));
        }
    }

    return $url;
}

/** @param array<string,string> $headers @return array<string,string> */
function apiManualServer(array $headers, ?string $body): array
{
    $server = [];
    foreach ($headers as $name => $value) {
        $key = strtoupper(str_replace('-', '_', $name));
        $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_'.$key] = $value;
    }
    if ($body !== null) {
        $server['CONTENT_LENGTH'] = (string) strlen($body);
    }

    return $server;
}

/** @param array<string,mixed> $request as apiManualCurl */
function apiManualSend(object $test, array $request, ApiManualRun $run): TestResponse
{
    apiManualForget($test);
    $uri = apiManualPath($request['url'], $run);
    $cookies = $request['jar_in'] !== null ? ($run->jars[$request['jar_in']] ?? []) : [];
    $response = $test->call($request['method'], $uri, [], $cookies, [], apiManualServer($request['headers'], $request['body']), $request['body']);
    $jar = $request['jar_out'] ?? $request['jar_in'];
    if ($jar !== null && $request['jar_out'] !== null) {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() < time()) {
                unset($run->jars[$jar][$cookie->getName()]);
            } else {
                $run->jars[$jar][$cookie->getName()] = (string) $cookie->getValue();
            }
        }
    }

    return $response;
}

/** A field of an answer by a dotted path (`data.0.id`); null when it is not there. */
function apiManualField(mixed $json, string $path): mixed
{
    foreach (explode('.', $path) as $key) {
        if (! is_array($json) || ! array_key_exists($key, $json)) {
            return null;
        }
        $json = $json[$key];
    }

    return $json;
}

function apiManualString(mixed $value): string
{
    return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
}

/** @param list<array{0:string,1:string}> $notes */
function apiManualNote(array $notes, string $key): ?string
{
    foreach ($notes as [$name, $value]) {
        if ($name === $key) {
            return $value;
        }
    }

    return null;
}

/** The text of a marker with its variables (`$ORG_ID`) put in. */
function apiManualFill(string $text, ApiManualRun $run): string
{
    $out = '';
    for ($i = 0; $i < strlen($text); $i++) {
        $out .= $text[$i] === '$' ? apiManualDollar($text, $i, $run) : $text[$i];
    }

    return $out;
}

/**
 * What a person does by hand or waits for, said in the page as `# arrange: name`.
 *
 * @param  array<string,mixed>  $entry
 * @param  array<string,mixed>  $request
 */
function apiManualArrange(string $what, object $test, ApiManualRun $run, array $entry, array $request): void
{
    $where = "line {$entry['line']}";
    switch (true) {
        case str_starts_with($what, 'services='): // three services of the organization: ordering them has its own manual (01) and is not what this page tests
            $have = Service::query()->where('organization_id', $run->org->id)->count();
            for ($n = $have; $n < (int) substr($what, 9); $n++) {
                Service::query()->create(['organization_id' => $run->org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => "Web {$n}", 'hostname' => "manual-{$n}.example.cz", 'state' => ServiceStateMachine::ACTIVE, 'desired_spec' => [], 'entitlements' => [], 'sla_class' => 'standard', 'activated_at' => now()]);
            }
            break;
        case $what === 'reserve_key': // a request with this key that is still running: the key is held as the middleware holds it for the first copy
            $key = $run->vars['KEY_BUSY'] ?? '';
            $token = PersonalAccessToken::query()->findOrFail($run->vars['TOKEN_ID']);
            $url = parse_url(apiManualPath($request['url'], $run));
            $hash = hash('sha256', $request['method'].'|'.ltrim((string) $url['path'], '/').'?'.Request::normalizeQueryString((string) ($url['query'] ?? '')).'|'.($request['headers']['X-Organization'] ?? '').'|'.$request['body']);
            $scope = 'user:'.$run->owner->id.'|'.substr(hash('sha256', 'org:'.($request['headers']['X-Organization'] ?? '').'|token:'.$token->getKey()), 0, 32);
            $held = app(IdempotencyStore::class)->reserveHttp($key, $scope, $hash, 600);
            expect($held['token'])->not->toBeNull("{$where}: the key could not be held (a request with it ran before)");
            break;
        case $what === 'long_key':
            $run->vars['LONG_KEY'] = str_repeat('x', 201);
            break;
        case $what === 'low_rate_limit': // 121st request of the minute without sending 120: the limit of this one key drops to 1 and one request is spent
            $limited = PersonalAccessToken::query()->findOrFail($run->vars['TOKEN_ID']);
            $was = $limited->rate_limit_per_minute;
            $limited->forceFill(['rate_limit_per_minute' => 1])->save(); // the key's own limit (what the portal calls "limit na klíč") is what the limiter reads
            $run->restore[] = fn () => $limited->forceFill(['rate_limit_per_minute' => $was])->save();
            apiManualSend($test, ['method' => 'GET', 'url' => $run->vars['ONHOST_API'].'/services?limit=1', 'headers' => $request['headers'], 'body' => null, 'jar_in' => null, 'jar_out' => null], $run);
            break;
        case $what === 'revoke_step_up': // the confirmation ran out
            app(StepUpService::class)->revokeAll($run->owner);
            break;
        case $what === 'advance_31s': // wait out the ping cooldown
            $test->travel(31)->seconds();
            break;
        case $what === 'near_suspension': // the 20th failed attempt in a row: 19 are already behind the endpoint (twenty attempts take days), and the receiver is down
            WebhookEndpoint::query()->whereKey($run->vars['ENDPOINT_ID'])->update(['failures' => WebhookDispatcher::SUSPEND_AFTER - 1]);
            $run->receiverStatus = 503;
            break;
        case $what === 'healthy_receiver':
            $run->receiverStatus = 204;
            break;
        case $what === 'catalog': // G3: the price list and the tax rules a staging installation has (ordering has its own manual, 01)
            $test->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
            break;
        case str_starts_with($what, 'loyalty_points='): // G3: points the organization earned earlier (support awards them on staging; earning is the loyalty tests' concern)
            LoyaltyPoint::query()->create(['organization_id' => $run->org->id, 'rule' => 'manual', 'reference' => 'manual-api:'.uniqid(), 'points' => (int) substr($what, 15), 'note' => 'ruční test API']);
            break;
        default:
            throw new RuntimeException("{$where}: the page asks for an unknown arrangement `{$what}`");
    }
}

/** Every operation the page calls is in the contract, with the scope the page claims for it. */
function apiManualContract(array $contract, string $method, string $path, string $claim, string $where): void
{
    $operation = null;
    foreach ($contract['paths'] as $template => $operations) {
        if (preg_match('~^'.preg_replace('~\\\\\{[^}]+\\\\\}~', '[^/]+', preg_quote((string) $template, '~')).'$~', $path) === 1 && isset($operations[strtolower($method)])) {
            // a literal path beats a template that also matches (`/tokens` over `/{x}`)
            if ($operation === null || ! str_contains((string) $template, '{')) {
                $operation = $operations[strtolower($method)];
            }
        }
    }
    expect($operation)->not->toBeNull("{$where}: {$method} {$path} is not in contracts/openapi/onhost-v1.yaml");
    $scopes = $operation['x-token-scope'] ?? null;
    if ($claim === 'none') {
        expect($scopes)->toBeNull("{$where}: the page says {$method} {$path} is the portal's own endpoint, the contract offers it to tokens: ".json_encode($scopes));
    } elseif ($claim === 'any') {
        expect($scopes)->toBe([], "{$where}: the page says {$method} {$path} takes any key or none, the contract says ".json_encode($scopes));
    } else {
        expect($scopes)->toBeArray("{$where}: the page says {$method} {$path} needs {$claim}, the contract offers it to no token")->toContain($claim);
    }
}

/** The answer is what the lines above the command promise. */
function apiManualAssert(array $entry, array $request, TestResponse $response, ApiManualRun $run): void
{
    $notes = $entry['notes'];
    $where = "line {$entry['line']} ({$entry['section']}): {$request['method']} {$request['url']}";
    $body = $response->getContent();
    $why = "{$where} answered {$response->getStatusCode()}: ".substr((string) $body, 0, 400);
    $expect = apiManualNote($notes, 'expect');
    expect($expect)->not->toBeNull("{$where}: no `# expect:` line");
    expect($response->getStatusCode())->toBe((int) $expect, $why);
    $json = json_decode((string) $body, true);
    foreach ($notes as [$key, $value]) {
        $value = apiManualFill($value, $run);
        switch ($key) {
            case 'expect-error':
                expect($json['error'] ?? null)->toBe($value, $why);
                break;
            case 'expect-header':
                [$name, $wanted] = array_pad(explode(': ', $value, 2), 2, null);
                $given = $response->headers->get($name);
                expect($given)->not->toBeNull("{$where}: header {$name} is missing");
                if ($wanted !== null) {
                    expect($given)->toBe($wanted, "{$where}: header {$name}");
                }
                break;
            case 'expect-json':
                [$path, $wanted] = explode('=', $value, 2);
                expect(apiManualString(apiManualField($json, $path)))->toBe($wanted, "{$where}: field {$path} of ".substr((string) $body, 0, 300));
                break;
            case 'expect-count':
                [$path, $wanted] = explode('=', $value, 2);
                expect(apiManualField($json, $path))->toBeArray()->toHaveCount((int) $wanted, "{$where}: {$path}");
                break;
            case 'expect-contains':
                expect(str_contains((string) $body, $value))->toBeTrue("{$where}: the answer lacks `{$value}`: ".substr((string) $body, 0, 300));
                break;
            case 'expect-not-contains':
                expect($value)->not->toBe('', "{$where}: variable is empty, the check proves nothing");
                expect(str_contains((string) $body, $value))->toBeFalse("{$where}: the answer must not contain `{$value}`");
                break;
            case 'save':
                [$name, $path] = explode('=', $value, 2);
                $saved = apiManualField($json, $path);
                expect($saved)->not->toBeNull("{$where}: nothing at {$path} to save in {$name}: ".substr((string) $body, 0, 300));
                $run->vars[$name] = apiManualString($saved);
                break;
        }
    }
}

/** @return array{0:string,1:string,2:string} timestamp, signature header, raw body of the last request the receiver got for `$event` */
function apiManualReceived(ApiManualRun $run, string $event): array
{
    $matching = array_values(array_filter($run->seen, fn (HttpClientRequest $r) => ($r->header('X-ONhost-Event')[0] ?? '') === $event));
    expect($matching)->not->toBeEmpty("the receiver never got a {$event} before the signature example");
    $last = end($matching);

    return [$last->header('X-ONhost-Timestamp')[0], $last->header('X-ONhost-Signature')[0], $last->body()];
}

/** The page's own bash and PHP snippets, run on a real delivery. */
function apiManualRunSnippet(array $entry, ApiManualRun $run): void
{
    $where = "line {$entry['line']} ({$entry['section']}): {$entry['name']}";
    $secret = $run->vars['ONHOST_WEBHOOK_SECRET'];
    [$timestamp, $signature, $body] = apiManualReceived($run, 'webhook.ping');
    $stale = (string) (time() - 600);
    $staleSignature = 'v1='.hash_hmac('sha256', $stale.'.'.$body, $secret);
    switch ($entry['name']) {
        case 'signature-bash':
            $bash = (new ExecutableFinder)->find('bash');
            $probe = $bash === null ? null : new Process([$bash, '-c', 'command -v openssl >/dev/null && command -v cut >/dev/null && printf ready']);
            if ($probe === null || $probe->run() !== 0 || $probe->getOutput() !== 'ready') {
                $run->ran[] = 'skipped:signature-bash (no bash with openssl on this machine)';

                return;
            }
            $check = function (string $ts, string $sig, string $payload, string $key) use ($bash, $entry): string {
                $process = new Process([$bash, '-c', preg_replace('/^# run:.*\n/', '', $entry['code'])], null, ['TS' => $ts, 'SIG' => $sig, 'BODY' => $payload, 'ONHOST_WEBHOOK_SECRET' => $key]);
                $process->run();

                return trim($process->getOutput());
            };
            expect($check($timestamp, $signature, $body, $secret))->toBe('OK', "{$where}: a genuine delivery");
            expect($check($timestamp, $signature, $body.' ', $secret))->toBe('FAIL', "{$where}: a changed body");
            expect($check($timestamp, $signature, $body, 'whsec_other'))->toBe('FAIL', "{$where}: another secret");
            expect($check($stale, $staleSignature, $body, $secret))->toBe('FAIL', "{$where}: a correctly signed but old delivery");
            break;
        case 'signature-php':
            if (! function_exists('onhostSignatureValid')) {
                eval(preg_replace(['/^<\?php/', '~^// run:.*$~m'], '', $entry['code']));
            }
            expect(onhostSignatureValid($secret, $timestamp, $body, $signature))->toBeTrue("{$where}: a genuine delivery")
                ->and(onhostSignatureValid($secret, $timestamp, $body.' ', $signature))->toBeFalse("{$where}: a changed body")
                ->and(onhostSignatureValid('whsec_other', $timestamp, $body, $signature))->toBeFalse("{$where}: another secret")
                ->and(onhostSignatureValid($secret, $stale, $body, $staleSignature))->toBeFalse("{$where}: a correctly signed but old delivery")
                ->and(onhostSignatureValid($secret, $timestamp, $body, substr($signature, 3)))->toBeFalse("{$where}: a header without v1=");
            break;
        case 'dedupe-php':
            if (! function_exists('onhostFirstSeen')) {
                eval(preg_replace(['/^<\?php/', '~^// run:.*$~m'], '', $entry['code']));
            }
            $db = new PDO('sqlite::memory:');
            $db->exec('CREATE TABLE seen_deliveries (delivery_id TEXT PRIMARY KEY)');
            expect(onhostFirstSeen($db, 'dlv_1'))->toBeTrue("{$where}: first sight")->and(onhostFirstSeen($db, 'dlv_1'))->toBeFalse("{$where}: the same delivery again")->and(onhostFirstSeen($db, 'dlv_2'))->toBeTrue("{$where}: another delivery");
            break;
        default:
            throw new RuntimeException("{$where}: unknown snippet");
    }
    $run->ran[] = $entry['name'];
}

/** The owner (authenticator on), an organization admin (password only) and the receiver of the webhook. */
function apiManualWorld(object $test): ApiManualRun
{
    $run = new ApiManualRun;
    $password = 'Owner-'.Str::random(24).'-9aZ'; // made for this run: no password of anybody is in the repository
    [$owner, $org] = (fn () => $this->customerWithOrganization(['email' => 'vlastnik@example.cz', 'password' => $password]))->call($test);
    $run->totpSecret = app(Totp::class)->generateSecret();
    $owner->forceFill(['totp_secret' => $run->totpSecret, 'totp_confirmed_at' => now()])->save();
    $adminPassword = 'Admin-'.Str::random(24).'-9aZ';
    $admin = (fn () => $this->customer(['email' => 'spravce@example.cz', 'password' => $adminPassword]))->call($test);
    app(OrganizationService::class)->attachMember($org, $admin, 'org_admin', CommandContext::system('test'), true);
    [$run->owner, $run->org] = [$owner, $org];
    $run->vars = [
        'ONHOST_BASE' => 'http://localhost', 'ONHOST_API' => 'http://localhost/v1',
        'ONHOST_EMAIL' => 'vlastnik@example.cz', 'ONHOST_PASSWORD' => $password,
        'ONHOST_ADMIN_EMAIL' => 'spravce@example.cz', 'ONHOST_ADMIN_PASSWORD' => $adminPassword,
        'JAR' => 'jar-owner', 'JAR_ADMIN' => 'jar-admin', 'ONHOST_HOOK_URL' => 'https://hooks.example.cz/onhost',
        'ONHOST_TOKEN_INVALID' => 'onh_live_neplatny', 'XSRF' => '', 'XSRF_ADMIN' => '', // the CSRF check is off under test; the page shows how a person reads the cookie
    ];
    Http::preventStrayRequests();
    Http::fake(function (HttpClientRequest $request) use ($run) {
        if (! str_contains($request->url(), 'hooks.example.cz')) {
            return null;
        }
        $run->seen[] = $request;

        return Http::response($run->receiverStatus === 204 ? '' : 'receiver says no', $run->receiverStatus);
    });

    return $run;
}

it('replays every curl example of the API manual and gets the status the page promises', function () {
    $entries = apiManualParse((string) file_get_contents(base_path(API_MANUAL_PAGE)));
    $contract = Yaml::parseFile(base_path('contracts/openapi/onhost-v1.yaml'));
    $run = apiManualWorld($this);
    $replayed = 0;

    foreach ($entries as $entry) {
        expect($entry['kind'])->not->toBe('orphan', "line {$entry['line']} ({$entry['section']}): a code block that is no curl example, no setup (`# setup`) and no snippet to run (`# run:`)");
        if ($entry['kind'] === 'run') {
            apiManualRunSnippet($entry, $run);

            continue;
        }
        if ($entry['kind'] !== 'curl') {
            continue; // `# setup`: the environment of the person, supplied by this test
        }
        foreach ($entry['exports'] as [$name, $value]) {
            $run->vars[$name] = apiManualFill(trim($value, '"'), $run); // `$(uuidgen)` is a fresh id; what a shell would compute otherwise (the long key) is supplied by an arrangement
        }
        $uses = $run->totpUses;
        $request = apiManualCurl($entry['cmd'], $run); // a first look, for the arrangements: it must not spend a code of the authenticator
        $run->totpUses = $uses;
        foreach ($entry['notes'] as [$key, $value]) {
            if ($key === 'arrange') {
                apiManualArrange($value, $this, $run, $entry, $request);
            }
        }
        $request = apiManualCurl($entry['cmd'], $run); // again: an arrangement may have supplied a variable of the command (a long key, a held key)
        $where = "line {$entry['line']} ({$entry['section']}): {$request['method']} {$request['url']}";
        $claim = apiManualNote($entry['notes'], 'scope');
        expect($claim)->not->toBeNull("{$where}: no `# scope:` line");

        $response = apiManualSend($this, $request, $run);
        apiManualAssert($entry, $request, $response, $run);
        foreach (array_reverse($run->restore) as $restore) {
            $restore();
        }
        $run->restore = [];

        $path = apiManualPath($request['url'], $run);
        if ($path === '/sanctum/csrf-cookie') {
            expect($claim)->toBe('none', "{$where}: the CSRF cookie route belongs to no contract operation");
        } else {
            expect($path)->toStartWith('/v1/', "{$where}: the path is outside the API");
            apiManualContract($contract, $request['method'], substr((string) parse_url($path, PHP_URL_PATH), 3), (string) $claim, $where);
        }
        $run->routes[] = $request['method'].' '.parse_url($path, PHP_URL_PATH);
        $replayed++;
    }

    expect($replayed)->toBeGreaterThanOrEqual(50);
    // the sections the task names are all exercised
    foreach (['X-Organization', 'X-Total-Count', 'Idempotent-Replayed', 'idempotency_key_reused', 'idempotency_in_progress', 'X-API-Version', 'Retry-After', 'webhook_ping_cooldown', 'person_required'] as $topic) {
        expect(file_get_contents(base_path(API_MANUAL_PAGE)))->toContain($topic);
    }
    // a redelivery is the same delivery again: one id, one body, two attempts at the receiver
    $deliveries = [];
    foreach ($run->seen as $seen) {
        $deliveries[$seen->header('X-ONhost-Delivery')[0] ?? ''][] = $seen->body();
    }
    $again = array_filter($deliveries, fn (array $bodies) => count($bodies) > 1);
    expect($again)->toHaveCount(1)->and(array_key_first($again))->toBe($run->vars['DELIVERY_ID'])->and(count(array_unique(array_values($again)[0])))->toBe(1);
    // the rest of the flow reached the end
    expect($run->vars['ONHOST_TOKEN'])->toContain('|onh_live_')->and($run->totpUses)->toBe(3)->and(in_array('signature-php', $run->ran, true))->toBeTrue()->and(in_array('dedupe-php', $run->ran, true))->toBeTrue();
});

it('keeps the numbers the manual states equal to the code that enforces them', function () {
    $page = (string) file_get_contents(base_path(API_MANUAL_PAGE));
    $backoff = WebhookDispatcher::BACKOFF_MINUTES;
    $total = array_sum($backoff);
    $contains = fn (string $needle) => expect($page)->toContain($needle);

    $contains('**'.implode(', ', array_slice($backoff, 0, -1)).' a '.end($backoff).' minutách**');
    $contains('**'.(count($backoff) + 1).' pokusů**');
    $contains('**'.number_format($total / 60, 1, ',', '').' hodiny** ('.$total.' minut)');
    $contains('Po **'.WebhookDispatcher::SUSPEND_AFTER.' neúspěšných pokusech za sebou**');
    $contains('**jednou za '.WebhookCommandHandler::PING_COOLDOWN_SECONDS.' sekund**');
    $contains('nejvýš '.WebhookDispatcher::MAX_ATTEMPTS.'krát celkem');
    $contains('odběr snese '.WebhookCommandHandler::REDELIVERS_PER_HOUR.' žádostí');
    $contains('**120 za minutu na klíč**');
    expect(config('onhost.api.default_rate_limit_per_minute'))->toBe(120)->and(config('onhost.api.public_rate_limit_per_minute'))->toBe(600)->and(config('onhost.api.failed_auth_per_minute'))->toBe(60);
    $contains('veřejné cesty '.config('onhost.api.public_rate_limit_per_minute').' za minutu');
    $contains('(výchozí 40, nejvýš 200)');
    expect(config('onhost.api.page_size'))->toBe(40)->and(config('onhost.api.max_page_size'))->toBe(200);
    $contains('Nejdelší klíč je '.config('onhost.api.idempotency_key_max_length').' znaků');
    $contains('(výchozí okno je '.config('onhost.identity.step_up_ttl_minutes').')');
    $contains('v panelu i e-mailem');
    expect(WebhookDispatcher::BACKOFF_MINUTES)->toBe([1, 5, 30, 120, 720])->and(TokenScopes::SERVICES_READ)->toBe('services:read')->and(TokenScopes::TICKETS_WRITE)->toBe('tickets:write');
});

it('has no code block on the page that the replay would skip without saying so', function () {
    $entries = apiManualParse((string) file_get_contents(base_path(API_MANUAL_PAGE)));
    $kinds = array_count_values(array_column($entries, 'kind'));

    expect($kinds['orphan'] ?? 0)->toBe(0)->and($kinds['curl'])->toBeGreaterThanOrEqual(50)->and($kinds['run'])->toBe(3);
    foreach ($entries as $entry) {
        if ($entry['kind'] !== 'curl') {
            continue;
        }
        $keys = array_column($entry['notes'], 0);
        expect(in_array('expect', $keys, true) && in_array('scope', $keys, true))->toBeTrue("line {$entry['line']}: an example without `# expect:` or `# scope:`");
        expect(str_contains($entry['cmd'], 'sk_live') || str_contains($entry['cmd'], 'onh_live_') || str_contains($entry['cmd'], 'whsec_'))->toBeFalse("line {$entry['line']}: a real-looking secret in an example");
    }
});

it('is true that every ping without an Idempotency-Key meets the cooldown, and a ping retried with its key is replayed (F12a)', function () {
    $run = apiManualWorld($this);
    $this->travelTo(now()->startOfMinute()->addSeconds(5)); // the whole test lies inside one minute
    app(StepUpService::class)->grant($run->owner, 'totp', null, '127.0.0.1');
    $created = $this->actingAs($run->owner, 'sanctum')->postJson('/v1/webhooks', ['url' => 'https://hooks.example.cz/onhost', 'events' => ['service.*']], ['X-Organization' => $run->org->id, 'Idempotency-Key' => (string) Str::uuid()])->assertCreated();
    $endpoint = (string) $created->json('data.id');
    $ping = fn (?string $key) => $this->actingAs($run->owner, 'sanctum')->postJson("/v1/webhooks/{$endpoint}/ping", [], ['X-Organization' => $run->org->id] + ($key === null ? [] : ['Idempotency-Key' => $key]));

    // F12a (TASK-0106): a ping without a key is a request of its own — the second one in the minute hears the cooldown
    $ping(null)->assertStatus(202);
    $ping(null)->assertStatus(429)->assertJsonPath('error', 'webhook_ping_cooldown');
    $this->travel(31)->seconds();
    $this->travelTo(now()->startOfMinute()->addSeconds(50)); // still one minute for the calls below
    $key = (string) Str::uuid();
    $first = $ping($key)->assertStatus(202);
    expect($ping($key)->assertStatus(202)->json('data.id'))->toBe($first->json('data.id')); // the same key: its answer again
    $ping((string) Str::uuid())->assertStatus(429)->assertJsonPath('error', 'webhook_ping_cooldown');
});
