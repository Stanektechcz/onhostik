<?php

declare(strict_types=1);

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use OnHost\Sdk\Client;

/**
 * The official PHP SDK is a standalone library under sdk/php — it does not touch
 * the running app. These tests drive it against a mocked HTTP transport to prove
 * it authenticates, targets the right paths, and behaves idempotently.
 */
beforeEach(function (): void {
    require_once base_path('sdk/php/src/Client.php');
});

/**
 * @param  list<Response>  $queue
 * @param  array<int, array<string, mixed>>  $history  populated by reference
 */
function sdkWithMock(array $queue, array &$history): Client
{
    $stack = HandlerStack::create(new MockHandler($queue));
    $stack->push(Middleware::history($history));

    $http = new HttpClient([
        'handler'  => $stack,
        'base_uri' => 'https://onhost.cz/api/v1/',
        'headers'  => ['Authorization' => 'Bearer pat_test', 'Accept' => 'application/json'],
    ]);

    return new Client('pat_test', 'https://onhost.cz/api/', 'v1', $http);
}

it('sends the bearer token and hits the right path on a read', function (): void {
    $history = [];
    $client = sdkWithMock([
        new Response(200, [], json_encode(['data' => [['id' => 'svc_1']]])),
    ], $history);

    $result = $client->services();

    /** @var \GuzzleHttp\Psr7\Request $sent */
    $sent = $history[0]['request'];
    expect((string) $sent->getUri())->toEndWith('/api/v1/services')
        ->and($sent->getHeaderLine('Authorization'))->toBe('Bearer pat_test')
        ->and($result['data'][0]['id'])->toBe('svc_1');
});

it('attaches an Idempotency-Key and JSON body on a write', function (): void {
    $history = [];
    $client = sdkWithMock([
        new Response(201, [], json_encode(['status' => 'ok'])),
    ], $history);

    $client->topUpCredit(50000, 'CZK');

    /** @var \GuzzleHttp\Psr7\Request $sent */
    $sent = $history[0]['request'];
    expect($sent->getMethod())->toBe('POST')
        ->and($sent->getHeaderLine('Idempotency-Key'))->not->toBe('')
        ->and(json_decode((string) $sent->getBody(), true))->toBe(['amount' => 50000, 'currency' => 'CZK']);
});

it('generates a fresh idempotency key per write', function (): void {
    $history = [];
    $client = sdkWithMock([
        new Response(201, [], '{}'),
        new Response(201, [], '{}'),
    ], $history);

    $client->createTicket('A', 'body a');
    $client->createTicket('B', 'body b');

    $k1 = $history[0]['request']->getHeaderLine('Idempotency-Key');
    $k2 = $history[1]['request']->getHeaderLine('Idempotency-Key');

    // Two distinct requests → two distinct keys (not a replay of the same op).
    expect($k1)->not->toBe($k2);
});

it('ships both PHP and JS SDKs with a README', function (): void {
    expect(is_file(base_path('sdk/php/src/Client.php')))->toBeTrue()
        ->and(is_file(base_path('sdk/js/onhost.js')))->toBeTrue()
        ->and(is_file(base_path('sdk/README.md')))->toBeTrue()
        ->and(is_file(base_path('sdk/php/composer.json')))->toBeTrue();
});
