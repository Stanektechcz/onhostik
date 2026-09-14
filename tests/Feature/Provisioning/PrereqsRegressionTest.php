<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\NodePrerequisites;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * Nightly checks against the real vendors (audit §5p-4): the prerequisites pass remembers yesterday's warnings; a new
 * warning or a panel that stopped answering reaches operations, a panel that cleared its warnings is reported once.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::preventStrayRequests();
});

it('reports a regression and a recovery of a game panel between two nightly passes', function () {
    [$user, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $mode = 'ok';
    Http::fake(function (Request $request) use (&$mode) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        if ($mode === 'down') {
            return Http::response(['errors' => [['code' => 'HttpException', 'detail' => 'gateway down']]], 502);
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $list = fn (array $items, string $object) => Http::response(['object' => 'list', 'data' => array_map(fn ($a) => ['object' => $object, 'attributes' => $a], $items), 'meta' => ['pagination' => ['total_pages' => 1]]]);

        return match (true) {
            str_ends_with($path, '/api/application/nodes') => $list([['id' => 2, 'name' => 'games01', 'memory' => 131072, 'disk' => 2048000, 'allocated_resources' => ['memory' => 0, 'disk' => 0], 'maintenance_mode' => false]], 'node'),
            str_ends_with($path, '/api/application/nests') => $list([['id' => 1, 'name' => 'Minecraft']], 'nest'),
            str_ends_with($path, '/api/application/nests/1/eggs') => $list([['id' => 3, 'name' => 'Paper', 'docker_image' => 'x', 'docker_images' => [], 'startup' => 'x', 'config' => ['startup' => ['privileged' => false]]]], 'egg'),
            str_ends_with($path, '/api/application/servers') => $list([], 'server'),
            str_ends_with($path, '/api/application/nodes/2/allocations') => $list([['id' => 20, 'ip' => '203.0.113.10', 'alias' => null, 'port' => 25565, 'assigned' => false]], 'allocation'),
            str_ends_with($path, '/api/client/account') => $mode === 'noclient' ? Http::response(['errors' => [['code' => 'AuthenticationException', 'detail' => 'bad key']]], 401) : Http::response(['object' => 'user', 'attributes' => ['id' => 1, 'admin' => true]]),
            default => Http::response(['errors' => [['code' => 'NotFoundHttpException', 'detail' => "no fake for {$path}"]]], 404),
        };
    });
    $prereqs = app(NodePrerequisites::class);

    // the first pass records a baseline and tells nobody; a second identical pass tells nobody either
    $first = $prereqs->check($instance, CommandContext::system('test'));
    expect($first['api'])->toBe('up');
    $baseline = $first['warnings'];
    $prereqs->check($instance->refresh(), CommandContext::system('test'));
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->whereIn('event', ['integration.prereqs.regressed', 'integration.prereqs.recovered'])->count())->toBe(0);

    // the client key stops working: a new warning → regression to operations
    $mode = 'noclient';
    $worse = $prereqs->check($instance->refresh(), CommandContext::system('test'));
    expect(count($worse['warnings']))->toBeGreaterThan(count($baseline));
    app(OutboxPublisher::class)->relayPending();
    $note = Notification::query()->where('audience', 'internal')->where('event', 'integration.prereqs.regressed')->firstOrFail();
    expect($note->title)->toBe('Noční kontrola integrace: pterodactyl-games01 se zhoršila')->and($note->severity)->toBe('warn')->and($note->body)->not->toBe('');
    $prereqs->check($instance->refresh(), CommandContext::system('test')); // the same warnings again: no second notice
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'integration.prereqs.regressed')->count())->toBe(1);

    // the panel goes down entirely: another regression; back to a clean state only when every warning is gone
    $mode = 'down';
    expect($prereqs->check($instance->refresh(), CommandContext::system('test'))['api'])->toBe('down');
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'integration.prereqs.regressed')->count())->toBe(2);
    $mode = 'ok';
    $back = $prereqs->check($instance->refresh(), CommandContext::system('test'));
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'integration.prereqs.recovered')->exists())->toBe($back['warnings'] === []);
});
