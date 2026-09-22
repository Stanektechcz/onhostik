<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ResourceRef;

/*
 * A game server is deleted with everything of it, or not at all (Brain cards H505, H488).
 *
 * The adapter called `DELETE /api/application/servers/{id}/force`. Force skips whatever does not answer: when Wings or
 * the database host is down at that moment, the panel deletes the record anyway and the world stays on the Wings disk
 * while the databases stay "dangling on the host instance" (the panel's own words) — for ever, with a cancelled
 * customer's data in them and nothing left that knows they exist.
 */

/** @param list<string> $calls */
function gameDeletionFake(array &$calls, int $deleteStatus = 204): void
{
    Http::fake(function (Request $r) use (&$calls, $deleteStatus) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);
        $calls[] = $r->method().' '.$path;
        if ($r->method() === 'DELETE') {
            return $deleteStatus === 204 ? Http::response('', 204) : Http::response(['errors' => [['code' => 'DaemonConnectionException', 'status' => (string) $deleteStatus, 'detail' => 'An error was encountered while processing this request.']]], $deleteStatus);
        }
        if ($path === '/api/application/servers/77') {
            return Http::response(['object' => 'server', 'attributes' => ['id' => 77, 'uuid' => 'u', 'identifier' => 'e4c1abcd', 'name' => 'mc', 'suspended' => false, 'status' => null, 'user' => 9, 'node' => 2, 'allocation' => 11, 'nest' => 1, 'egg' => 3,
                'limits' => ['memory' => 4096, 'disk' => 20480, 'cpu' => 200], 'feature_limits' => ['databases' => 1, 'allocations' => 1, 'backups' => 2], 'container' => ['installed' => 1, 'environment' => []]]]);
        }

        return Http::response(['errors' => [['status' => '404']]], 404);
    });
}

function gameDeletionAdapter(array $options = []): object
{
    [, $org] = test()->customerWithOrganization();
    featureGameService($org); // the `pterodactyl-games01` instance and its secrets
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $instance->forceFill(['options' => array_merge((array) $instance->options, $options)])->save();

    return app(ProviderRegistry::class)->forInstance($instance->refresh());
}

it('deletes a server without force, so the panel removes its files and databases or says it could not', function () {
    $calls = [];
    gameDeletionFake($calls);

    $result = gameDeletionAdapter()->terminate(new ResourceRef('server', '77', '2'));

    expect($result->data)->toMatchArray(['deleted' => true, 'forced' => false])
        ->and($calls)->toContain('DELETE /api/application/servers/77')
        ->and($calls)->not->toContain('DELETE /api/application/servers/77/force');
});

it('does not take a refusal for a deletion: the step fails and the operation tries again when the node is back', function () {
    $calls = [];
    gameDeletionFake($calls, 500); // Wings did not answer; without force the panel keeps the server and says so

    expect(fn () => gameDeletionAdapter()->terminate(new ResourceRef('server', '77', '2')))->toThrow(ProviderException::class);
    expect(collect($calls)->filter(fn (string $c) => str_starts_with($c, 'DELETE'))->values()->all())->toBe(['DELETE /api/application/servers/77']);
});

it('forces only when an operator switched the instance to it, and says so on the result', function () {
    $calls = [];
    gameDeletionFake($calls);

    $result = gameDeletionAdapter(['terminate_force' => true])->terminate(new ResourceRef('server', '77', '2'));

    expect($result->data['forced'])->toBeTrue()->and($calls)->toContain('DELETE /api/application/servers/77/force');
});
