<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\ProviderRegistry;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflows\ProvisionGameServerWorkflow;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\GameToolsProvider;
use Onhost\Providers\Contracts\ResourceSpec;
use Onhost\Providers\Pterodactyl\PterodactylGameProvider;

/*
 * A game panel user belongs to one organization (TASK-0033, permission program P0-02 / IF-6 / D12, exploit PA-01).
 *
 * The panel user of an order used to be the first hit of `GET /api/application/users?filter[email]=<billing e-mail>`, and
 * the billing e-mail is whatever the customer typed: an organization that wrote somebody else's address got its server
 * created under that person's panel account, and `panel.password` then reset that person's password — every server of
 * the stranger's account opened to the attacker. The user is now found only by `GET /users/external/{organizationId}`,
 * and only when the panel's answer carries exactly that external id; new users get a synthetic e-mail; the password
 * and a new collaborator are refused on a server whose panel user is not the service's organization's.
 */

beforeEach(fn () => Http::preventStrayRequests());

/**
 * The lab panel with a users table that answers like Pterodactyl 1.11 on MySQL: `filter[email]` is a LIKE match and
 * `users/external/{id}` compares under a case- and trailing-space-insensitive collation.
 *
 * @param  array{users: array<int, array<string, mixed>>, calls: list<string>, created: list<array<string, mixed>>, patched: list<int>, subusers: array<string, array<string, mixed>>}  $state
 */
function gpiPanel(array &$state): void
{
    Http::fake(function (Request $request) use (&$state) {
        if (! str_starts_with($request->url(), PTERO)) {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $m = $request->method();
        $state['calls'][] = $m.' '.$path.($query !== [] ? '?'.urldecode(http_build_query($query)) : '');
        $notFound = Http::response(['errors' => [['code' => 'NotFoundHttpException', 'status' => '404', 'detail' => "no fake for {$m} {$path}"]]], 404);
        $user = fn (array $a) => Http::response(['object' => 'user', 'attributes' => $a]);
        $loose = fn (?string $value) => mb_strtolower(rtrim((string) $value));

        return match (true) {
            $path === '/api/application/users' && $m === 'GET' => Http::response(['object' => 'list', 'data' => array_values(array_map(fn ($a) => ['object' => 'user', 'attributes' => $a], array_filter($state['users'], fn ($a) => str_contains(mb_strtolower((string) $a['email']), mb_strtolower((string) data_get($query, 'filter.email', '')))))), 'meta' => ['pagination' => ['total_pages' => 1]]]),
            preg_match('~^/api/application/users/external/(.+)$~', $path, $x) === 1 && $m === 'GET' => (function () use (&$state, $x, $user, $notFound, $loose) {
                foreach ($state['users'] as $a) {
                    if ($a['external_id'] !== null && $loose($a['external_id']) === $loose(rawurldecode($x[1]))) {
                        return $user($a);
                    }
                }

                return $notFound;
            })(),
            $path === '/api/application/users' && $m === 'POST' => (function () use (&$state, $request) {
                $id = max(array_keys($state['users']) ?: [100]) + 1;
                $state['created'][] = $request->data();
                $state['users'][$id] = ['id' => $id, 'external_id' => $request['external_id'], 'email' => $request['email'], 'username' => $request['username'], 'first_name' => $request['first_name'], 'last_name' => $request['last_name'], 'language' => 'en', 'root_admin' => false];

                return Http::response(['object' => 'user', 'attributes' => $state['users'][$id]], 201);
            })(),
            preg_match('~^/api/application/users/(\d+)$~', $path, $x) === 1 && $m === 'GET' => isset($state['users'][(int) $x[1]]) ? $user($state['users'][(int) $x[1]]) : $notFound,
            preg_match('~^/api/application/users/(\d+)$~', $path, $x) === 1 && $m === 'PATCH' => (function () use (&$state, $x, $user) {
                $state['patched'][] = (int) $x[1];

                return $user($state['users'][(int) $x[1]]);
            })(),
            $path === '/api/application/servers/77' && $m === 'GET' => Http::response(['object' => 'server', 'attributes' => ['id' => 77, 'external_id' => 'feature-test', 'identifier' => 'e4c1abcd', 'uuid' => 'e4c1abcd-uuid', 'user' => 9, 'node' => 2]]),
            str_starts_with($path, '/api/application/servers/external/') => (function () use (&$state, $path, $notFound, $loose) {
                foreach ($state['servers'] ?? [] as $a) {
                    if ($loose($a['external_id']) === $loose(rawurldecode(substr($path, strlen('/api/application/servers/external/'))))) {
                        return Http::response(['object' => 'server', 'attributes' => $a]);
                    }
                }

                return $notFound;
            })(),
            $path === '/api/client/servers/e4c1abcd/users' && $m === 'GET' => Http::response(['object' => 'list', 'data' => array_values(array_map(fn ($a) => ['object' => 'server_subuser', 'attributes' => $a], $state['subusers']))]),
            $path === '/api/client/servers/e4c1abcd/users' && $m === 'POST' => (function () use (&$state, $request) {
                $uuid = 'su-'.(count($state['subusers']) + 1);
                $state['subusers'][$uuid] = ['uuid' => $uuid, 'email' => $request['email'], 'permissions' => $request['permissions'], 'created_at' => null];

                return Http::response(['object' => 'server_subuser', 'attributes' => $state['subusers'][$uuid]]);
            })(),
            preg_match('~^/api/client/servers/e4c1abcd/users/([\w-]+)$~', $path, $x) === 1 && $m === 'DELETE' => (function () use (&$state, $x) {
                unset($state['subusers'][$x[1]]);

                return Http::response('', 204);
            })(),
            default => $notFound,
        };
    });
}

/** Panel user 9 (the owner of lab server 77), with the external id and flags a case needs. */
function gpiState(?string $externalId, bool $rootAdmin = false): array
{
    return [
        'users' => [9 => ['id' => 9, 'external_id' => $externalId, 'email' => 'hrac@obet.cz', 'username' => 'obet_ab12cd', 'first_name' => 'Obet', 'last_name' => 'Customer', 'language' => 'en', 'root_admin' => $rootAdmin]],
        'calls' => [], 'created' => [], 'patched' => [], 'subusers' => ['su-1' => ['uuid' => 'su-1', 'email' => 'kamos@obet.cz', 'permissions' => ['control.console'], 'created_at' => null]],
    ];
}

function gpiAdapter(): PterodactylGameProvider
{
    $adapter = app(ProviderRegistry::class)->forInstance(ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail());
    expect($adapter)->toBeInstanceOf(PterodactylGameProvider::class);

    return $adapter;
}

/** @param list<string> $calls */
function gpiWrites(array $calls): array
{
    return array_values(array_filter($calls, fn (string $c) => ! str_starts_with($c, 'GET ')));
}

/** Run the "panel user" step of the game provisioning saga for a paid service of the organization. */
function gpiRunUserStep(Organization $org): StepResult
{
    $instance = ProviderInstance::query()->where('key', 'pterodactyl-games01')->firstOrFail();
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'game', 'family' => 'game', 'name' => 'Herní server 8 GB', 'state' => ServiceStateMachine::PROVISIONING, 'region_code' => 'cz1', 'provider_instance_id' => $instance->id,
        'desired_spec' => ['executor' => 'pterodactyl', 'family' => 'game', 'egg' => 'minecraft-paper'], 'entitlements' => ['ram_mb' => 8192], 'sla_class' => 'standard']);
    $operation = Operation::query()->create(['service_id' => $service->id, 'organization_id' => $org->id, 'provider_instance_id' => $instance->id, 'kind' => 'provision.game', 'workflow' => ProvisionGameServerWorkflow::class, 'state' => Operation::RUNNING, 'step' => 1, 'steps_total' => 5, 'actor_type' => 'system', 'idempotency_key' => 'gpi:'.$service->id, 'correlation_id' => 'c', 'desired' => (array) $service->desired_spec, 'context' => [], 'queue' => 'q', 'queued_at' => now(), 'next_run_at' => now(), 'retry_until' => now()->addHour()]);
    $step = (new ProvisionGameServerWorkflow)->steps($operation)[1];

    return $step->run(new StepContext($operation, $service, app(ProviderRegistry::class), app(), CommandContext::system('test')));
}

it('never hands an order the panel user that the customer-typed billing e-mail finds: a new user with a synthetic e-mail and the organization as external id', function () {
    [, $victim] = $this->customerWithOrganization([], ['billing_email' => 'hrac@obet.cz']);
    [, $attacker] = $this->customerWithOrganization([], ['name' => 'Utocnik s.r.o.', 'billing_email' => 'hrac@obet.cz']);
    featureGameService($victim); // the lab panel, its node and the victim's server 77 under panel user 9
    $state = gpiState($victim->id);
    gpiPanel($state);

    $result = gpiRunUserStep($attacker);

    expect($result->outcome)->toBe(StepResult::DONE)->and($result->context['ptero_user_id'])->not->toBe(9)->and($result->context['ptero_user_created'])->toBeTrue();
    expect($state['created'])->toHaveCount(1)->and($state['created'][0]['external_id'])->toBe($attacker->id)->and($state['created'][0]['root_admin'])->toBeFalse();
    expect($state['created'][0]['email'])->not->toBe('hrac@obet.cz')->toEndWith('@'.PterodactylGameProvider::SYNTHETIC_EMAIL_DOMAIN); // the e-mail is the platform's, never the customer's (O2: new users only)
    expect(collect($state['calls'])->filter(fn ($c) => str_contains($c, 'filter[email]'))->all())->toBe([]) // nothing is ever looked up by e-mail again
        ->and($state['calls'])->toContain('GET /api/application/users/external/'.$attacker->id)
        ->and($state['patched'])->toBe([]);
});

it('reuses the organization\'s own panel user found by its exact external id, whatever e-mail it has', function () {
    [, $org] = $this->customerWithOrganization([], ['billing_email' => 'hrac@liga.cz']);
    featureGameService($org);
    $state = gpiState($org->id);
    gpiPanel($state);

    $result = gpiRunUserStep($org);
    expect($result->outcome)->toBe(StepResult::DONE)->and($result->context)->toMatchArray(['ptero_user_id' => 9, 'ptero_user_created' => false])->and($state['created'])->toBe([]);

});

it('ignores a partial match of the external id: the panel\'s lookup is collation-insensitive, the platform is not', function (string $variant) {
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $state = gpiState($variant === 'upper' ? mb_strtoupper($org->id) : $org->id.' ');
    gpiPanel($state);

    expect(fn () => gpiAdapter()->ensureUser('hrac@liga.cz', 'Liga', $org->id))->toThrow(ProviderException::class, 'does not belong');
    expect($state['created'])->toBe([])->and(gpiWrites($state['calls']))->toBe([]); // refused to triage: neither adopted nor shadowed by a second user
})->with(['upper', 'trailing space']);

it('never adopts a panel administrator as a customer account, even under the organization\'s external id', function () {
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $state = gpiState($org->id, rootAdmin: true);
    gpiPanel($state);

    expect(fn () => gpiAdapter()->ensureUser('hrac@liga.cz', 'Liga', $org->id))->toThrow(ProviderException::class, 'administrator');
    expect(gpiWrites($state['calls']))->toBe([]);
});

it('refuses the panel password and the panel account of a server whose panel user is not the service\'s organization\'s', function (string $case) {
    [, $org] = $this->customerWithOrganization();
    [, $stranger] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $state = gpiState(match ($case) {
        'another organization' => $stranger->id,
        'unmarked legacy user' => null,
        'partial match' => mb_strtoupper($org->id),
    });
    gpiPanel($state);
    $adapter = gpiAdapter();
    $ref = $service->primaryBinding()->ref();

    expect(fn () => $adapter->setPanelPassword($ref, 'Nove-Heslo-1234567'))->toThrow(ProviderException::class, 'does not belong');
    expect(fn () => $adapter->panelAccount($ref))->toThrow(ProviderException::class, 'does not belong'); // not even the stranger's e-mail is shown
    expect($state['patched'])->toBe([])->and(gpiWrites($state['calls']))->toBe([]);
})->with(['another organization', 'unmarked legacy user', 'partial match']);

it('sets the panel password of the organization\'s own panel user', function () {
    [, $org] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $state = gpiState($org->id);
    gpiPanel($state);
    $adapter = gpiAdapter();
    $ref = $service->primaryBinding()->ref();

    expect($adapter->panelAccount($ref))->toMatchArray(['username' => 'obet_ab12cd', 'remote_id' => '9']);
    expect($adapter->setPanelPassword($ref, 'Nove-Heslo-1234567')->data['changed'])->toBeTrue()->and($state['patched'])->toBe([9]);
});

it('refuses a new collaborator on a server of a foreign panel user, and still lets every collaborator be removed', function () {
    [, $org] = $this->customerWithOrganization();
    [, $stranger] = $this->customerWithOrganization();
    $service = featureGameService($org);
    $state = gpiState($stranger->id);
    gpiPanel($state);
    $adapter = gpiAdapter();
    $ref = $service->primaryBinding()->ref();

    expect(fn () => $adapter->createSubuser($ref, 'kamos@utocnik.cz', GameToolsProvider::SUBUSER_PRESETS['files']))->toThrow(ProviderException::class, 'does not belong');
    expect(collect($state['calls'])->filter(fn ($c) => str_starts_with($c, 'POST '))->all())->toBe([]);
    // a revocation takes access away and is never held up (cancellation, suspension, migration clean-up)
    expect($adapter->deleteSubuser($ref, 'su-1')->data['deleted'])->toBeTrue()->and($state['subusers'])->toBe([]);

    // on the organization's own user a collaborator is created as before
    $state['users'][9]['external_id'] = $org->id;
    expect($adapter->createSubuser($ref, 'mod@liga.cz', GameToolsProvider::SUBUSER_PRESETS['files'])->data['email'])->toBe('mod@liga.cz');
});

it('adopts an existing server only under its exact external id', function () {
    [, $org] = $this->customerWithOrganization();
    featureGameService($org);
    $state = gpiState($org->id) + ['servers' => [['id' => 90, 'external_id' => 'ORD-9:PROVISION.GAME:V1', 'uuid' => 'x', 'identifier' => 'deadbeef', 'user' => 9, 'node' => 2, 'allocation' => 11]]];
    gpiPanel($state);

    expect(fn () => gpiAdapter()->provision(new ResourceSpec('srv_g1', 'game_server', 'ord-9:provision.game:v1', ['nest_id' => 1, 'egg_id' => 5, 'ptero_user_id' => 9, 'allocation_id' => 11])))->toThrow(ProviderException::class, 'external id');
    expect(gpiWrites($state['calls']))->toBe([]);
});

it('lists legacy mismatches for triage with a read-only dry run and changes nothing', function () {
    [, $org] = $this->customerWithOrganization(['email' => 'ok@liga.cz']);
    [, $victimised] = $this->customerWithOrganization(['email' => 'utocnik@liga.cz']);
    [, $stranger] = $this->customerWithOrganization();
    $own = featureGameService($org);
    $foreign = featureGameService($victimised, [], 78, 'f00dbabe');
    $foreign->primaryBinding()->forceFill(['meta' => ['identifier' => 'f00dbabe', 'user_id' => 12]])->save();
    $state = gpiState($org->id);
    $state['users'][12] = ['id' => 12, 'external_id' => $stranger->id, 'email' => 'cizi@obet.cz', 'username' => 'cizi', 'root_admin' => false];
    gpiPanel($state);

    expect(Artisan::call('onhost:game:panel-identity', ['--dry-run' => true]))->toBe(0);
    $out = Artisan::output();
    expect($out)->toContain($foreign->id)->toContain('foreign')->toContain($own->id)->toContain('owned')->toContain('neodpovídá: 1') // one mismatch
        ->and(gpiWrites($state['calls']))->toBe([]);
    expect(Artisan::call('onhost:game:panel-identity', ['--apply' => true]))->toBe(1); // no automatic re-homing exists
});
