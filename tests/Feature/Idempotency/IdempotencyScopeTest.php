<?php

declare(strict_types=1);

use App\Http\Support\CurrentOrganization;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
