<?php

declare(strict_types=1);

use App\Http\Support\CurrentOrganization;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\InvoiceService;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Http\Middleware\IdempotencyKey;
use Symfony\Component\HttpFoundation\Response;

/*
 * Phase D5 — the HTTP `Idempotency-Key` contract.
 *
 *  - The key was scoped to the user alone and the organization was not part of the fingerprint: a person in two organizations
 *    who sent the same key and body to each was answered with the first organization's result, and nothing ran for the second.
 *    Two API tokens of one person shared their keys the same way.
 *  - Nothing was reserved while a request ran: a duplicate arriving before the first answer was stored executed a second time
 *    (two payments, two servers). Now the key is reserved by an atomic insert first; the duplicate gets 409
 *    `idempotency_in_progress`.
 *  - DELETE was not covered at all.
 *  - The middleware's own error answers had no `status` (EndpointSweepTest now asks it of every 4xx JSON answer).
 */

/** Sends one request through the middleware; $run counts how often the controller behind it really ran. */
function idkSend(string $method, string $key, ?User $user = null, array $headers = [], string $body = '{"a":1}', string $uri = '/v1/idk', ?Closure $inner = null, int $status = 201): Response
{
    $request = Request::create($uri, $method, [], [], [], ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '198.51.100.9'], $body);
    $request->headers->set('Idempotency-Key', $key);
    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }
    $request->setUserResolver(fn () => $user);

    return app(IdempotencyKey::class)->handle($request, function () use ($inner, $status) {
        test()->idkRuns = (test()->idkRuns ?? 0) + 1;
        if ($inner !== null) {
            return $inner();
        }

        return response()->json(['run' => test()->idkRuns], $status);
    });
}

/** @return array<string,mixed> */
function idkJson(Response $response): array
{
    return json_decode((string) $response->getContent(), true) ?? [];
}

beforeEach(function () {
    $this->idkRuns = 0;
});

it('executes the same key and body separately for another organization, and replays it within the same one', function () {
    $user = User::factory()->create();

    $first = idkSend('POST', 'idk-org', $user, ['X-Organization' => 'org_a']);
    $otherOrg = idkSend('POST', 'idk-org', $user, ['X-Organization' => 'org_b']);
    $sameOrg = idkSend('POST', 'idk-org', $user, ['X-Organization' => 'org_a']);

    expect($this->idkRuns)->toBe(2)
        ->and(idkJson($first)['run'])->toBe(1)
        ->and(idkJson($otherOrg)['run'])->toBe(2)
        ->and($otherOrg->headers->get('Idempotent-Replayed'))->toBeNull()
        ->and(idkJson($sameOrg)['run'])->toBe(1)
        ->and($sameOrg->headers->get('Idempotent-Replayed'))->toBe('true');
});

it('treats an organization named in the query as the organization of the request, and reads the session choice under the panel key', function () {
    $user = User::factory()->create();

    idkSend('POST', 'idk-query', $user, [], uri: '/v1/idk?organization=org_a');
    idkSend('POST', 'idk-query', $user, [], uri: '/v1/idk?organization=org_b');

    expect($this->idkRuns)->toBe(2)
        ->and(IdempotencyKey::SESSION_ORGANIZATION)->toBe(CurrentOrganization::SESSION_KEY); // the session's chosen organization is read under the panel's own key
});

it('keeps the keys of two API tokens of one person apart', function () {
    $user = User::factory()->create();
    $tokenA = $user->createToken('a', ['services:read'])->accessToken;
    $tokenB = $user->createToken('b', ['services:read'])->accessToken;

    idkSend('POST', 'idk-token', User::query()->find($user->id)->withAccessToken($tokenA));
    idkSend('POST', 'idk-token', User::query()->find($user->id)->withAccessToken($tokenB));
    $replay = idkSend('POST', 'idk-token', User::query()->find($user->id)->withAccessToken($tokenA));

    expect($this->idkRuns)->toBe(2)->and($replay->headers->get('Idempotent-Replayed'))->toBe('true');
});

it('answers a duplicate that arrives while the first request still runs with 409 idempotency_in_progress', function () {
    $user = User::factory()->create();
    $duplicate = null;

    $first = idkSend('POST', 'idk-flight', $user, inner: function () use ($user, &$duplicate) {
        $duplicate = idkSend('POST', 'idk-flight', $user); // the same request again, before the first one has answered

        return response()->json(['paid' => true], 201);
    });

    expect($first->getStatusCode())->toBe(201)
        ->and($this->idkRuns)->toBe(1) // the duplicate never reached the controller
        ->and($duplicate->getStatusCode())->toBe(409)
        ->and(idkJson($duplicate))->toMatchArray(['error' => 'idempotency_in_progress', 'status' => 409])
        ->and($duplicate->headers->get('Retry-After'))->not->toBeNull();

    // once the first answer is stored, the same request is a replay of it
    $replay = idkSend('POST', 'idk-flight', $user);
    expect($replay->getStatusCode())->toBe(201)->and(idkJson($replay))->toBe(['paid' => true])->and($this->idkRuns)->toBe(1);
});

it('frees the key again when the request did not complete: a server error, a refusal before it ran, an exception', function () {
    $user = User::factory()->create();

    idkSend('POST', 'idk-500', $user, status: 500);
    $retried = idkSend('POST', 'idk-500', $user);
    expect($this->idkRuns)->toBe(2)->and($retried->getStatusCode())->toBe(201);

    idkSend('POST', 'idk-403', $user, status: 403);
    idkSend('POST', 'idk-403', $user);
    expect($this->idkRuns)->toBe(4);

    expect(fn () => idkSend('POST', 'idk-throw', $user, inner: fn () => throw new RuntimeException('boom')))->toThrow(RuntimeException::class);
    idkSend('POST', 'idk-throw', $user);
    expect($this->idkRuns)->toBe(6)
        ->and(DB::table('idempotency_keys')->whereNull('response_status')->count())->toBe(0); // no reservation is left behind
});

it('takes over a reservation that ran out: the request holding it died without an answer', function () {
    $user = User::factory()->create();
    $takeover = null;

    idkSend('POST', 'idk-stale', $user, inner: function () use ($user, &$takeover) {
        $row = DB::table('idempotency_keys')->where('key', 'http:idk-stale')->first();
        expect($row)->not->toBeNull()->and($row->response_status)->toBeNull(); // reserved while it runs
        // this worker hangs past the reservation's lifetime (or was killed): the row is all that is left of it
        DB::table('idempotency_keys')->where('id', $row->id)->update(['expires_at' => now()->subSecond()]);
        $takeover = idkSend('POST', 'idk-stale', $user);

        return response()->json(['first' => true], 201);
    });

    expect($takeover->getStatusCode())->toBe(201)->and($takeover->headers->get('Idempotent-Replayed'))->toBeNull()->and($this->idkRuns)->toBe(2);
});

it('covers DELETE: a repeated delete with the same key is answered from the first one', function () {
    $user = User::factory()->create();

    idkSend('DELETE', 'idk-delete', $user, body: '', uri: '/v1/idk/svc_1', status: 200);
    $replay = idkSend('DELETE', 'idk-delete', $user, body: '', uri: '/v1/idk/svc_1', status: 200);
    $otherResource = fn () => idkSend('DELETE', 'idk-delete', $user, body: '', uri: '/v1/idk/svc_2', status: 200);

    expect($this->idkRuns)->toBe(1)->and($replay->headers->get('Idempotent-Replayed'))->toBe('true');
    expect($otherResource)->toThrow(DomainError::class, 'different request');
});

it('names the status in its own error answers', function () {
    $user = User::factory()->create();

    $tooLong = idkSend('POST', str_repeat('k', 201), $user);
    expect($tooLong->getStatusCode())->toBe(422)->and(idkJson($tooLong))->toMatchArray(['error' => 'invalid_idempotency_key', 'status' => 422]);

    idkSend('POST', 'idk-secret', $user, inner: fn () => response()->json(['generated_password' => implode('-', ['tajne', 'heslo'])], 201));
    $shownOnce = idkSend('POST', 'idk-secret', $user);
    expect($shownOnce->getStatusCode())->toBe(409)->and(idkJson($shownOnce))->toMatchArray(['error' => 'already_done', 'status' => 409, 'original_status' => 201]);
});

it('keeps the invoice in the bus key of an invoice payment', function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    [$owner, $org] = $this->customerWithOrganization([], ['country' => 'CZ', 'ico' => '12345678']);
    $service = app(InvoiceService::class);
    $ctx = $this->contextFor($owner, $org);
    $line = ['sku' => 'web-hosting-start', 'description' => 'Webhosting Start', 'qty' => 1, 'unit_net' => 8900, 'discount' => 0, 'net' => 8900, 'tax_rate' => '21', 'tax_category' => 'S', 'tax' => 1869, 'total' => 10769, 'period_from' => '2026-09-01', 'period_to' => '2026-09-30'];
    $invoice = $service->issue($service->draft($org, 'invoice', 'CZK', [$line], $ctx, null, ['postpaid' => true, 'payment_method' => 'bank']), $ctx);

    $this->actingAs($owner, 'sanctum')->postJson("/v1/invoices/{$invoice->id}/pay", ['method' => 'bank'], ['Idempotency-Key' => 'idk-invoice'])->assertOk();

    $busKeys = DB::table('idempotency_keys')->where('key', 'like', 'invoice.pay:%')->pluck('key');
    expect($busKeys)->toHaveCount(1)->and($busKeys->first())->toContain($invoice->id)->toEndWith(':idk-invoice');
});

/*
 * Phase D5, security review of PR #68.
 *
 *  H1 — a reservation that ran out while its request was still running was taken over by a duplicate (B). When the slow request
 *       (A) finished, it wrote its answer over B's: B's client then replays an answer B never gave, and A executed although B
 *       did too. Each reservation now carries its own token; only the holder of the token completes or frees it.
 *  M1 — the query string was not part of the fingerprint: `DELETE /x?force=1` replayed the answer of `DELETE /x`.
 *  M2 — Retry-After grows with the age of the reservation; the free-on-exception path is proved through a real route.
 *  L1 — an organization sent as an array is no organization (as ApiContext reads it), not an error.
 *  L4 — the longest key comes from config('onhost.api.idempotency_key_max_length').
 */

it('H1: lets no slow request whose reservation was taken over write over the answer of the request that took it', function () {
    $user = User::factory()->create();
    $takeover = null;

    $slow = idkSend('POST', 'idr-slow', $user, inner: function () use ($user, &$takeover) {
        // A runs longer than its reservation lives: B arrives after the expiry and takes the key over
        DB::table('idempotency_keys')->where('key', 'http:idr-slow')->update(['expires_at' => now()->subSecond()]);
        $takeover = idkSend('POST', 'idr-slow', $user, inner: fn () => response()->json(['by' => 'B'], 201));

        return response()->json(['by' => 'A'], 201);
    });

    expect(idkJson($slow))->toBe(['by' => 'A'])->and(idkJson($takeover))->toBe(['by' => 'B']);
    $replay = idkSend('POST', 'idr-slow', $user);
    expect(idkJson($replay))->toBe(['by' => 'B']) // B's answer stays the answer of the key
        ->and($replay->headers->get('Idempotent-Replayed'))->toBe('true')
        ->and($this->idkRuns)->toBe(2);
});

it('H1: lets no slow request free a reservation it no longer holds', function () {
    $user = User::factory()->create();
    $held = null;

    idkSend('POST', 'idr-free', $user, status: 500, inner: function () use (&$held) {
        DB::table('idempotency_keys')->where('key', 'http:idr-free')->update(['expires_at' => now()->subSecond()]);
        // B takes the key over and is still running when A fails
        $row = DB::table('idempotency_keys')->where('key', 'http:idr-free')->first();
        DB::table('idempotency_keys')->where('id', $row->id)->delete();
        DB::table('idempotency_keys')->insert(['key' => 'http:idr-free', 'scope' => $row->scope, 'request_hash' => $row->request_hash, 'response_status' => null, 'result' => 'reservation:of-b', 'created_at' => now(), 'expires_at' => now()->addMinutes(10)]);
        $held = $row->scope;

        return response()->json(['error' => 'boom', 'status' => 500], 500);
    });

    expect(DB::table('idempotency_keys')->where('key', 'http:idr-free')->where('scope', $held)->value('result'))->toBe('reservation:of-b');
    $duplicate = idkSend('POST', 'idr-free', $user);
    expect($duplicate->getStatusCode())->toBe(409)->and(idkJson($duplicate)['error'])->toBe('idempotency_in_progress');
});

it('H1: holds a reservation longer than the longest request, and the window is configuration', function () {
    $user = User::factory()->create();
    $limit = (int) ini_get('max_execution_time');
    $seen = null;

    idkSend('POST', 'idr-window', $user, inner: function () use (&$seen) {
        $seen = DB::table('idempotency_keys')->where('key', 'http:idr-window')->value('expires_at');

        return response()->json(['ok' => true], 201);
    });
    expect(strtotime((string) $seen) - time())->toBeGreaterThan(max($limit, 600) - 5);

    config(['onhost.api.idempotency_in_flight_seconds' => 7200]);
    idkSend('POST', 'idr-window-2', $user, inner: function () use (&$seen) {
        $seen = DB::table('idempotency_keys')->where('key', 'http:idr-window-2')->value('expires_at');

        return response()->json(['ok' => true], 201);
    });
    expect(strtotime((string) $seen) - time())->toBeGreaterThan(7190);
});

it('M1: keeps a delete with ?force=1 apart from the same delete without it', function () {
    $user = User::factory()->create();

    idkSend('DELETE', 'idr-force', $user, body: '', uri: '/v1/idr/svc_1', status: 200);
    $forced = fn () => idkSend('DELETE', 'idr-force', $user, body: '', uri: '/v1/idr/svc_1?force=1', status: 200);

    expect($forced)->toThrow(DomainError::class, 'different request');
    // the same query in another order is the same request
    idkSend('DELETE', 'idr-order', $user, body: '', uri: '/v1/idr/svc_1?a=1&b=2', status: 200);
    $reordered = idkSend('DELETE', 'idr-order', $user, body: '', uri: '/v1/idr/svc_1?b=2&a=1', status: 200);
    expect($reordered->headers->get('Idempotent-Replayed'))->toBe('true')->and($this->idkRuns)->toBe(2);
});

it('M2: tells a duplicate to wait longer the longer the first request has been running', function () {
    $user = User::factory()->create();
    $young = null;
    $old = null;

    idkSend('POST', 'idr-retry', $user, inner: function () use ($user, &$young, &$old) {
        $young = idkSend('POST', 'idr-retry', $user);
        DB::table('idempotency_keys')->where('key', 'http:idr-retry')->update(['created_at' => now()->subSeconds(90)]);
        $old = idkSend('POST', 'idr-retry', $user);

        return response()->json(['ok' => true], 201);
    });

    expect((int) $young->headers->get('Retry-After'))->toBeGreaterThanOrEqual(1)
        ->and((int) $old->headers->get('Retry-After'))->toBeGreaterThan((int) $young->headers->get('Retry-After'))
        ->and((int) $old->headers->get('Retry-After'))->toBeLessThanOrEqual(60);
});

it('M2: frees the key when the controller behind a real route throws, so the client may repeat the request', function () {
    $runs = 0;
    Route::middleware(['api', 'auth:sanctum', 'idempotency'])->post('v1/idr-throws', function () use (&$runs) {
        $runs++;
        if ($runs === 1) {
            throw new RuntimeException('the first attempt fails');
        }

        return response()->json(['run' => $runs], 201);
    });
    [$owner] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');

    $this->postJson('/v1/idr-throws', ['a' => 1], ['Idempotency-Key' => 'idr-throws'])->assertStatus(500);
    expect(DB::table('idempotency_keys')->where('key', 'http:idr-throws')->count())->toBe(0);
    $this->postJson('/v1/idr-throws', ['a' => 1], ['Idempotency-Key' => 'idr-throws'])->assertCreated()->assertJsonPath('run', 2);
    $this->postJson('/v1/idr-throws', ['a' => 1], ['Idempotency-Key' => 'idr-throws'])->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    expect($runs)->toBe(2);
});

it('L1: reads an organization sent as an array as no organization, as ApiContext does', function () {
    $user = User::factory()->create();

    $first = idkSend('POST', 'idr-array', $user, uri: '/v1/idr?organization[]=org_a');
    $plain = idkSend('POST', 'idr-array', $user, uri: '/v1/idr?organization[]=org_a');

    expect($first->getStatusCode())->toBe(201)->and($plain->headers->get('Idempotent-Replayed'))->toBe('true')->and($this->idkRuns)->toBe(1);
});

it('L4: takes the longest key from configuration', function () {
    $user = User::factory()->create();
    config(['onhost.api.idempotency_key_max_length' => 40]);

    $tooLong = idkSend('POST', str_repeat('k', 41), $user);
    $fits = idkSend('POST', str_repeat('k', 40), $user);

    expect($tooLong->getStatusCode())->toBe(422)->and(idkJson($tooLong))->toMatchArray(['error' => 'invalid_idempotency_key', 'status' => 422])
        ->and($fits->getStatusCode())->toBe(201);
});
