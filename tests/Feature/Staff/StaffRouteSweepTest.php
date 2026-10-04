<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureStaff;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Tests\TestCase;

/*
 * Phase D, package D2: every /v1/staff/* route carries the `staff` middleware (EnsureStaff) — the structural guard. Each
 * controller and the bus keep checking their own permission; this guard makes "a customer reached a staff endpoint because
 * the controller forgot to ask" impossible by construction, and refuses a member of staff who holds no staff permission at all
 * (the console role `none`) before any controller runs.
 *
 * The sweep walks EVERY staff route and method with identifiers that exist nowhere: a customer who owns an organization (with
 * a fresh step-up, so no later gate can be the one that refused) and a member of staff without a single permission both get
 * the guard's own 403 `staff_only` — never a 404 that tells which identifiers exist, never a validation answer that shows the
 * endpoint's shape, never a 2xx.
 */

/**
 * Staff routes a member of staff WITHOUT any staff permission may still reach. Each entry needs its reason; the list only
 * shrinks. It is empty: no staff endpoint serves a person who holds no staff permission (the console shows such a person the
 * role `none` from the boot object, which is no /v1/staff route).
 *
 * @var array<string, string> "METHOD uri" => reason
 */
const STAFF_SWEEP_NO_PERMISSION_ALLOW_LIST = [];

/** @return list<array{0: string, 1: string, 2: array<string, string>}> every staff route as [method, uri, wheres], HEAD left out (it is GET) */
function staffSweepRoutes(): array
{
    $out = [];
    /** @var RoutingRoute $route */
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'v1/staff/') && $route->uri() !== 'v1/staff') {
            continue;
        }
        foreach ($route->methods() as $method) {
            if ($method !== 'HEAD') {
                $out[] = [$method, $route->uri(), $route->wheres];
            }
        }
    }
    usort($out, fn (array $a, array $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);

    return $out;
}

/**
 * A concrete URL for a route: every placeholder gets an identifier that exists nowhere, one its `where` pattern accepts (a route
 * whose pattern refuses the value would answer the router's own 404 and the guard would never be asked).
 *
 * @param  array<string, string>  $wheres
 */
function staffSweepUrl(string $uri, array $wheres = []): string
{
    return '/'.preg_replace_callback('~\{([^}?]+)\??\}~', function (array $m) use ($wheres) {
        foreach (['01JSTAFFSWEEP0000000000000', '999999999'] as $candidate) {
            if (! isset($wheres[$m[1]]) || preg_match('~^(?:'.$wheres[$m[1]].')$~', $candidate) === 1) {
                return $candidate;
            }
        }
        throw new LogicException("No sweep value fits {$m[1]} in {$uri}");
    }, $uri);
}

/** @return array<string, int> "METHOD uri" => status, for every staff route the person is not refused by the guard on */
function staffSweepNotRefused(TestCase $test, User $user): array
{
    $leaks = [];
    foreach (staffSweepRoutes() as [$method, $uri, $wheres]) {
        app(Authorizer::class)->flush();
        $response = $test->actingAs($user, 'sanctum')->json($method, staffSweepUrl($uri, $wheres), [], ['Idempotency-Key' => 'sweep-'.md5($method.$uri)]);
        if ($response->status() !== 403 || $response->json('error') !== 'staff_only') {
            $leaks["{$method} {$uri}"] = $response->status();
        }
    }

    return $leaks;
}

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(); // nothing may reach a panel from here
    $this->withoutMiddleware(ThrottleRequests::class); // the sweep sends hundreds of requests as one person
});

it('puts the staff guard on every /v1/staff route', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn (RoutingRoute $r) => str_starts_with($r->uri(), 'v1/staff/') || $r->uri() === 'v1/staff');
    expect($routes->count())->toBeGreaterThan(200); // the sweep below walks the real list, not an empty one

    $unguarded = $routes->reject(fn (RoutingRoute $r) => in_array('staff', $r->gatherMiddleware(), true) || in_array(EnsureStaff::class, $r->gatherMiddleware(), true))
        ->map(fn (RoutingRoute $r) => implode('|', $r->methods()).' '.$r->uri())->values()->all();
    expect($unguarded)->toBe([]);
});

it('refuses a customer who owns an organization on every staff route and method', function () {
    [$owner] = $this->customerWithOrganization();
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1'); // fresh: the guard, not the step-up gate, must be what refuses

    expect(staffSweepNotRefused($this, $owner))->toBe([]);
});

it('refuses a member of staff without any staff permission on every staff route except the allow-list', function () {
    $staff = User::factory()->staff()->create(); // staff account, no role at all: the console role `none`
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');

    $leaks = staffSweepNotRefused($this, $staff);
    expect(array_keys($leaks))->toBe(array_keys(STAFF_SWEEP_NO_PERMISSION_ALLOW_LIST));
});

it('refuses a member of staff whose only global role carries customer keys alone', function () {
    $staff = $this->staff('owner'); // a customer role bound globally is no staff permission
    $this->actingAs($staff, 'sanctum')->getJson('/v1/staff/customers')->assertForbidden()->assertJsonPath('error', 'staff_only');
});

it('lets a member of staff with a staff permission past the guard', function () {
    $this->actingAs($this->staff('support_l1'), 'sanctum')->getJson('/v1/staff/tickets')->assertOk();
});

it('keeps the allow-list to routes that exist', function () {
    $routes = array_map(fn (array $r) => "{$r[0]} {$r[1]}", staffSweepRoutes());
    expect(array_diff(array_keys(STAFF_SWEEP_NO_PERMISSION_ALLOW_LIST), $routes))->toBe([]);
});

it('answers 401 to a visitor on a staff route', function () {
    $this->getJson('/v1/staff/customers')->assertUnauthorized();
});

it('signs staff into a customer panel by POST only: GET is no longer a route (405)', function () {
    $this->actingAs($this->steppedUpStaff(), 'sanctum')->getJson('/v1/staff/services/01JSTAFFSWEEP0000000000000/panel-login')
        ->assertStatus(405)->assertJsonPath('error', 'method_not_allowed');
});
