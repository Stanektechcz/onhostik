<?php

declare(strict_types=1);

use Database\Seeders\LegalEntitySeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Payments\Models\PaymentIntent;
use Onhost\Domain\WalletLedger\LedgerService;
use Onhost\Domain\WalletLedger\WalletService;
use Onhost\Platform\Money\Money;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/*
 * TASK-0063 (phase C, C1): the browser modules in apps/surfaces/api call only routes the server has.
 *
 * Every string literal that starts with "/" in apps/surfaces/api/*.js is read together with what is concatenated to it
 * ('/services/' + id + '/actions' → /services/{p}/actions), its query and hash are dropped, and the real router is asked
 * whether it knows it: a literal that is the path of an API call (OnhostApi.get/post/put/patch/del/upload/all, the modules'
 * own load/post/put/call helpers) must match a /v1 route with that method; any other literal must match a /v1 route, be the
 * start of one (a base the module extends later), or be a page of the web routes. A literal nothing matches fails the
 * build — fix the call, or add it to frontendRouteAllowList() with the reason it is not an API path.
 */

/** Literals that are not paths the server routes, each with the reason. Keep it short; every entry is a promise. */
function frontendRouteAllowList(): array
{
    return [
        '/v1' => 'the API base itself (ONHOST.apiBase, the read-only foot notes), every call adds its own path to it',
        // DomainController::holder exists and says it is registered, but routes/api.php has no line for it: the holder-contact
        // screen (TASK-0056) answers 404. routes/api.php belongs to another worker in phase C; remove this entry with that line.
        '/domains/{p}/holder' => 'MISSING ROUTE: POST /v1/domains/{domain}/holder is not registered (DomainController::holder is unreachable)',
        '/mo' => 'the English price unit of the game configurator ("/měs", "/mo"), text and not a path',
    ];
}

/**
 * The string literals of one JS file that start with "/", each with the concatenation it heads, its line and, where the
 * call it is the first argument of says so, the HTTP method.
 *
 * @return list<array{path:string,method:?string,line:int,prefix:bool}>
 */
function frontendRouteLiterals(string $source, string $file = ''): array
{
    $tokens = frontendRouteTokens($source);
    $out = [];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if ($t['type'] !== 'str' || ! preg_match('@^/[A-Za-z][A-Za-z0-9_.~%/-]*(?:[?#{].*)?$@s', $t['value'])) {
            continue; // not a path (prose such as '/ měsíc', a unit '/mo · …')
        }
        $prev = $tokens[$i - 1] ?? null;
        if ($prev !== null && $prev['type'] === 'punct' && $prev['value'] === '+') {
            continue; // the tail of a concatenation whose head is not a literal (location.origin + '/panel') — not a path of its own
        }
        // the concatenation this literal heads: literal (+ operand)*; a non-literal operand is a parameter
        $path = $t['value'];
        $j = $i + 1;
        while (($tokens[$j] ?? null) !== null && $tokens[$j]['type'] === 'punct' && $tokens[$j]['value'] === '+') {
            $j++;
            if (($tokens[$j] ?? null) !== null && $tokens[$j]['type'] === 'str') {
                $path .= $tokens[$j]['value'];
                $j++;

                continue;
            }
            $depth = 0;
            for (; $j < $count; $j++) {
                $v = $tokens[$j]['value'];
                if ($tokens[$j]['type'] === 'punct') {
                    if (in_array($v, ['(', '[', '{'], true)) {
                        $depth++;
                    } elseif (in_array($v, [')', ']', '}'], true)) {
                        if ($depth === 0) {
                            break;
                        }
                        $depth--;
                    } elseif ($depth === 0 && in_array($v, ['+', ',', ';', ':', '?'], true)) {
                        break;
                    }
                }
            }
            $path .= '{p}';
        }
        $how = frontendRouteMethod($tokens, $i, $file);
        $out[] = ['path' => $how['base'].$path, 'method' => $how['method'], 'line' => $t['line']] + frontendRouteNormalise($how['base'].$path);
    }

    return $out;
}

/**
 * The modules' own helpers that take only the end of a path and put a base in front of it, per file: helper => [base, method,
 * the source the helper must still contain]. The last one keeps the table honest: a helper that changes its base fails the test.
 */
function frontendRouteSuffixHelpers(): array
{
    return [
        'onhost-panel-workbench.api.js' => [
            'dpost' => ['/domains/{p}', 'POST', "API.post('/domains/' + encodeURIComponent(sel.id) + path"],
            'zpost' => ['/dns/zones/{p}', 'POST', "API.post('/dns/zones/' + encodeURIComponent(zone.id) + path"],
        ],
        'onhost-panel-tools.api.js' => [
            'upload' => ['/services/{p}', 'POST', "API.upload('/services/' + ctx.sel.id + path"],
        ],
    ];
}

/**
 * The call a literal is an argument of: its name, whether it is a method (`x.name(`), the argument position and the literal
 * argument before this one. Null when the literal is not directly an argument of a call.
 *
 * @return array{name:string,dotted:bool,arg:int,prevArg:?string}|null
 */
function frontendRouteCall(array $tokens, int $i): ?array
{
    $depth = 0;
    $arg = 0;
    $prevArg = ($tokens[$i - 1]['value'] ?? '') === ',' && ($tokens[$i - 2]['type'] ?? '') === 'str' ? (string) $tokens[$i - 2]['value'] : null;
    for ($k = $i - 1; $k >= 0 && $k > $i - 400; $k--) {
        $t = $tokens[$k];
        if ($t['type'] !== 'punct') {
            continue;
        }
        if (in_array($t['value'], [')', ']', '}'], true)) {
            $depth++;
        } elseif (in_array($t['value'], ['(', '[', '{'], true)) {
            if ($depth > 0) {
                $depth--;

                continue;
            }
            if ($t['value'] !== '(' || ($tokens[$k - 1]['type'] ?? '') !== 'word') {
                return null;
            }

            return ['name' => (string) $tokens[$k - 1]['value'], 'dotted' => ($tokens[$k - 2]['value'] ?? '') === '.', 'arg' => $arg, 'prevArg' => $prevArg];
        } elseif ($depth === 0 && $t['value'] === ',') {
            $arg++;
        } elseif ($depth === 0 && in_array($t['value'], [';', '=', ':', '?'], true)) {
            return null;
        }
    }

    return null;
}

/**
 * The method of the call this literal is the path of, or null when the test cannot tell; `base` is set when a suffix helper
 * puts a base in front of the literal.
 *
 * @return array{method:?string,base:string}
 */
function frontendRouteMethod(array $tokens, int $i, string $file): array
{
    $methods = ['get' => 'GET', 'all' => 'GET', 'load' => 'GET', 'post' => 'POST', 'upload' => 'POST', 'put' => 'PUT', 'patch' => 'PATCH', 'del' => 'DELETE', 'delete' => 'DELETE'];
    $call = frontendRouteCall($tokens, $i);
    if ($call === null) {
        return ['method' => null, 'base' => ''];
    }
    $helper = frontendRouteSuffixHelpers()[$file][$call['name']] ?? null;
    if ($helper !== null && ! $call['dotted']) {
        return ['method' => $helper[1], 'base' => $helper[0]];
    }
    // A().get( / OnhostApi.post( / a.put( — or the modules' own helpers load( post( put( (never someObject.load( of something else)
    if ($call['arg'] === 0 && isset($methods[$call['name']]) && ($call['dotted'] || $call['name'] !== 'delete') && ! ($call['name'] === 'load' && $call['dotted'])) {
        return ['method' => $methods[$call['name']], 'base' => ''];
    }
    // load(key, '/path') — the staff console's GET helper takes the data key first
    if ($call['arg'] === 1 && $call['name'] === 'load' && ! $call['dotted']) {
        return ['method' => 'GET', 'base' => ''];
    }
    // call('post', '/path', …) / send(cmp, X, 'POST', '/path', …) — a wrapper that names the method just before the path
    if ($call['prevArg'] !== null && isset($methods[strtolower($call['prevArg'])])) {
        return ['method' => $methods[strtolower($call['prevArg'])], 'base' => ''];
    }

    return ['method' => null, 'base' => ''];
}

/**
 * The route pattern of a concatenated path: query and hash dropped, a parameter glued to the end of a word
 * ('/domains/' + id + path → /domains/{p}{p}) makes the rest unknown, so only the part before it is checked, as a prefix.
 *
 * @return array{pattern:string,prefix:bool}
 */
function frontendRouteNormalise(string $path): array
{
    $path = preg_replace('/[?#].*$/s', '', $path) ?? $path;
    $prefix = false;
    if (preg_match('/^(.*?)(?<!\/)\{p\}/', $path, $m) === 1) { // a parameter that does not start a segment: the rest is unknown
        $path = $m[1];
        $prefix = true;
    }
    if (preg_match('/^(.*?\{p\})\{p\}/', $path, $m) === 1) {
        $path = $m[1];
        $prefix = true;
    }

    return ['pattern' => $path === '' ? '/' : $path, 'prefix' => $prefix];
}

/** A tiny JS tokenizer: strings, words, punctuation; comments, regex literals and template literals are understood. */
function frontendRouteTokens(string $s): array
{
    $tokens = [];
    $n = strlen($s);
    $line = 1;
    $i = 0;
    $regexAllowed = true;
    while ($i < $n) {
        $c = $s[$i];
        if ($c === "\n") {
            $line++;
            $i++;

            continue;
        }
        if (ctype_space($c)) {
            $i++;

            continue;
        }
        if ($c === '/' && ($s[$i + 1] ?? '') === '/') {
            $end = strpos($s, "\n", $i);
            $i = $end === false ? $n : $end;

            continue;
        }
        if ($c === '/' && ($s[$i + 1] ?? '') === '*') {
            $end = strpos($s, '*/', $i + 2);
            $chunk = substr($s, $i, ($end === false ? $n : $end + 2) - $i);
            $line += substr_count($chunk, "\n");
            $i = $end === false ? $n : $end + 2;

            continue;
        }
        if ($c === '/' && $regexAllowed) {
            $j = $i + 1;
            $inClass = false;
            while ($j < $n && $s[$j] !== "\n") {
                if ($s[$j] === '\\') {
                    $j += 2;

                    continue;
                }
                if ($s[$j] === '[') {
                    $inClass = true;
                } elseif ($s[$j] === ']') {
                    $inClass = false;
                } elseif ($s[$j] === '/' && ! $inClass) {
                    break;
                }
                $j++;
            }
            $j++;
            while ($j < $n && ctype_alpha($s[$j])) {
                $j++;
            }
            $tokens[] = ['type' => 'regex', 'value' => substr($s, $i, $j - $i), 'line' => $line];
            $i = $j;
            $regexAllowed = false;

            continue;
        }
        if ($c === '"' || $c === "'" || $c === '`') {
            $j = $i + 1;
            $value = '';
            $startLine = $line;
            while ($j < $n && $s[$j] !== $c) {
                if ($s[$j] === '\\') {
                    $value .= $s[$j + 1] ?? '';
                    $j += 2;

                    continue;
                }
                if ($c === '`' && $s[$j] === '$' && ($s[$j + 1] ?? '') === '{') { // ${…} is a parameter
                    $depth = 1;
                    $j += 2;
                    while ($j < $n && $depth > 0) {
                        $depth += $s[$j] === '{' ? 1 : ($s[$j] === '}' ? -1 : 0);
                        $j++;
                    }
                    $value .= '{p}';

                    continue;
                }
                if ($s[$j] === "\n") {
                    $line++;
                }
                $value .= $s[$j];
                $j++;
            }
            $tokens[] = ['type' => 'str', 'value' => $value, 'line' => $startLine];
            $i = $j + 1;
            $regexAllowed = false;

            continue;
        }
        if (ctype_alnum($c) || $c === '_' || $c === '$') {
            $j = $i;
            while ($j < $n && (ctype_alnum($s[$j]) || $s[$j] === '_' || $s[$j] === '$')) {
                $j++;
            }
            $word = substr($s, $i, $j - $i);
            $tokens[] = ['type' => 'word', 'value' => $word, 'line' => $line];
            $i = $j;
            $regexAllowed = in_array($word, ['return', 'typeof', 'case', 'in', 'of', 'else', 'void', 'new', 'delete', 'throw'], true);

            continue;
        }
        $tokens[] = ['type' => 'punct', 'value' => $c, 'line' => $line];
        $i++;
        $regexAllowed = ! in_array($c, [')', ']', '}'], true);
    }

    return $tokens;
}

/** Whether the router knows this path with this method (null = any). {p} is tried with a few sample values. */
function frontendRouteKnown(string $pattern, ?string $method, bool $prefix): bool
{
    $routes = Route::getRoutes();
    $methods = $method === null ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : [$method];
    foreach (['abc', '1', 'a-1'] as $sample) {
        $uri = str_replace('{p}', $sample, $pattern);
        foreach ($methods as $m) {
            try {
                $routes->match(Request::create($uri, $m));

                return true;
            } catch (HttpException) {
                // not this one
            }
        }
    }
    if ($prefix || $method === null) { // a base a module extends later: some route starts with it
        $re = '#^'.str_replace('\{p\}', '[^/]+', preg_quote(trim($pattern, '/'), '#')).'(/|$)#';
        foreach ($routes->getRoutes() as $route) {
            $uri = preg_replace('/\{[^}]+\}/', 'abc', $route->uri()) ?? '';
            if (preg_match($re, $uri) === 1 && ($method === null || in_array($method, $route->methods(), true))) {
                return true;
            }
        }
    }

    return false;
}

/** @return list<string> every literal the router does not know, as "file:line METHOD path" */
function frontendRouteUnknown(): array
{
    $allow = frontendRouteAllowList();
    $unknown = [];
    foreach (glob(base_path('apps/surfaces/api/*.js')) ?: [] as $file) {
        foreach (frontendRouteLiterals((string) file_get_contents($file), basename($file)) as $lit) {
            if (isset($allow[$lit['pattern']])) {
                continue;
            }
            $api = '/v1'.$lit['pattern'];
            $known = $lit['method'] !== null
                ? frontendRouteKnown($api, $lit['method'], $lit['prefix'])
                : (frontendRouteKnown($api, null, $lit['prefix']) || frontendRouteKnown($lit['pattern'], 'GET', $lit['prefix']));
            if (! $known) {
                $unknown[] = basename($file).':'.$lit['line'].' '.($lit['method'] ?? 'ANY').' '.$lit['pattern'];
            }
        }
    }

    return $unknown;
}

it('reads paths, methods and parameters out of the module source', function () {
    $src = "A().post('/services/' + encodeURIComponent(s.id) + '/actions', { a: 1 }, A().key());\n"
        ."a.get('/staff/customers?limit=200'); // '/not/a/path'\n"
        ."load('customers', '/staff/maintenance');\n"
        ."var run = API.post('/domains/' + encodeURIComponent(sel.id) + path, body);\n"
        ."if (/^\\/(leads|x)/.test(p)) location.href = location.origin + '/panel/x';\n"
        ."call('get', '/services/' + sid + '/logs', null);";
    $lits = collect(frontendRouteLiterals($src))->map(fn ($l) => [$l['method'], $l['pattern'], $l['prefix']])->all();

    expect($lits)->toBe([
        ['POST', '/services/{p}/actions', false],
        ['GET', '/staff/customers', false],
        ['GET', '/staff/maintenance', false],
        ['POST', '/domains/{p}', true],
        ['GET', '/services/{p}/logs', false],
    ]);
});

it('knows a real route and refuses an invented one', function () {
    expect(frontendRouteKnown('/v1/services/{p}/actions', 'POST', false))->toBeTrue()
        ->and(frontendRouteKnown('/v1/services/{p}/actions', 'DELETE', false))->toBeFalse()
        ->and(frontendRouteKnown('/v1/services/{p}/reboot-now', 'POST', false))->toBeFalse()
        ->and(frontendRouteKnown('/panel/fakturace', 'GET', false))->toBeTrue();
});

it('calls only routes the server has from every browser module', function () {
    $unknown = frontendRouteUnknown();

    expect($unknown)->toBe([], "Browser modules call paths no route answers:\n".implode("\n", $unknown));
});

it('keeps the suffix helpers on the base the table says', function () {
    foreach (frontendRouteSuffixHelpers() as $file => $helpers) {
        $source = (string) file_get_contents(base_path('apps/surfaces/api/'.$file));
        foreach ($helpers as $name => [$base, $method, $builds]) {
            expect($source)->toContain('function '.$name.'(')->toContain($builds);
        }
    }
});

it('keeps every allow-list entry needed and explained', function () {
    foreach (frontendRouteAllowList() as $pattern => $reason) {
        expect(strlen((string) $reason))->toBeGreaterThan(10, "allow-list entry {$pattern} needs a reason")
            // an entry the router now knows is no longer an exception: remove it, so it cannot hide a later drift
            ->and(frontendRouteKnown('/v1'.$pattern, null, false) || frontendRouteKnown($pattern, 'GET', false))->toBeFalse("allow-list entry {$pattern} is a real route now — remove it");
    }
});

/*
 * C4: one user intent, one key. tests/js/frontend-keys.harness.mjs runs the real session bridge and the modules in a vm against
 * a recording fetch (12 checks: double click and retry on top-up, invoice payment and payout, keys on PUT / PATCH / DELETE,
 * headers on a GET, the fixed calls, errors instead of empty lists, X-Total-Count pagination). The server half follows: the key
 * the panel sends is what makes a retry the same command.
 */
it('runs the browser-side harness of stable keys, real routes and visible read errors', function () {
    $node = (new ExecutableFinder)->find('node');
    if ($node === null) {
        $this->markTestSkipped('node is not installed; the harness is tests/js/frontend-keys.harness.mjs');
    }
    $process = new Process([$node, base_path('tests/js/frontend-keys.harness.mjs')], base_path(), null, null, 120);
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())->toContain('12/12 passed');
});

it('makes a retried wallet top-up with the panel\'s key one payment, and a new key a new one', function () {
    $this->seed([LegalEntitySeeder::class]);
    [$owner, $org] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');
    $body = ['amount' => 500, 'currency' => 'CZK', 'method' => null, 'provider' => 'bank']; // what OnhostPanelBilling.topUp sends for a transfer
    $intents = fn () => PaymentIntent::query()->where('organization_id', $org->id)->count();

    $first = $this->postJson('/v1/payments/init', $body, ['Idempotency-Key' => 'ui-topupretry1'])->assertCreated();
    // a double click that reached the server after the first answer was stored: the HTTP layer replays it
    $this->postJson('/v1/payments/init', $body, ['Idempotency-Key' => 'ui-topupretry1'])->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    // the retry after a lost answer (a 5xx, a dropped connection): nothing stored to replay, the bus knows the command by the key
    DB::table('idempotency_keys')->where('key', 'like', 'http:%')->delete();
    $this->travel(5)->seconds();
    $again = $this->postJson('/v1/payments/init', $body, ['Idempotency-Key' => 'ui-topupretry1']);

    expect($again->isSuccessful())->toBeTrue($again->getContent())
        ->and($intents())->toBe(1)
        ->and($again->json('instructions.variable_symbol'))->toBe($first->json('instructions.variable_symbol'));

    // the next top-up after the first one succeeded carries a new key (the bridge settles the intent): a second payment
    $this->postJson('/v1/payments/init', $body, ['Idempotency-Key' => 'ui-topupnext2'])->assertCreated();
    expect($intents())->toBe(2);
});

it('pays an invoice from credit once when the panel retries it with the same key', function () {
    $this->seed([LegalEntitySeeder::class]);
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ']);
    $ctx = $this->contextFor($owner, $org);
    app(WalletService::class)->topup($org, Money::minor(200000, 'CZK'), 'bank', 'topup-c4', $ctx);
    $service = app(InvoiceService::class);
    $line = [['sku' => 'x', 'description' => 'Hosting', 'qty' => 1, 'unit_net' => 100000, 'discount' => 0, 'net' => 100000, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 21000, 'total' => 121000]];
    $invoice = $service->issue($service->draft($org, 'invoice', 'CZK', $line, $ctx, null, ['postpaid' => true]), $ctx);
    $this->actingAs($owner, 'sanctum');
    $headers = ['X-Organization' => $org->id, 'Idempotency-Key' => 'ui-invoicepay1'];

    $this->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'wallet'], $headers)->assertOk()->assertJsonPath('state', Invoice::PAID);
    DB::table('idempotency_keys')->where('key', 'like', 'http:%')->delete();
    $again = $this->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'wallet'], $headers);

    expect($again->isSuccessful())->toBeTrue($again->getContent())
        ->and(app(LedgerService::class)->balance(LedgerService::walletAccount($org->id, 'CZK'), 'CZK')->minor)->toBe(200000 - 121000);
});
