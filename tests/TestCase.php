<?php

declare(strict_types=1);

namespace Tests;

use Closure;
use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\Authorization\ApprovalService;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Http\HostResolver;
use Onhost\Platform\Outbox\OutboxPublisher;

abstract class TestCase extends BaseTestCase
{
    /** RefreshDatabase seeds the authorization catalog once per process (in-memory SQLite). */
    protected bool $seed = true;

    protected string $seeder = BaseSeeder::class;

    protected function customer(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // the array store outlives the refreshed database: settings, ledgers and probes must not leak between tests
        FakeHostResolver::$hosts = [];
        $this->app->bind(HostResolver::class, FakeHostResolver::class); // customer-named destinations are resolved without touching the network
    }

    protected function tearDown(): void
    {
        if (WidthGuard::enabled() && $this->app !== null) {
            WidthGuard::measure(static::class.'::'.$this->name());
        }
        parent::tearDown();
    }

    /** A customer with an organization they own (owner role binding). @return array{0:User,1:Organization} */
    protected function customerWithOrganization(array $userAttributes = [], array $orgAttributes = []): array
    {
        $user = $this->customer($userAttributes);
        $organization = app(OrganizationService::class)->create($user, array_merge([
            'name' => 'Test s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK',
        ], $orgAttributes), CommandContext::system('test'));

        return [$user, $organization];
    }

    protected function staff(string $role = 'platform_owner', array $attributes = []): User
    {
        $user = User::factory()->staff()->create($attributes);
        PolicyBinding::query()->create([
            'principal_type' => 'user', 'principal_id' => $user->id, 'role_key' => $role,
            'scope_type' => 'global', 'scope_id' => null, 'organization_id' => null,
        ]);

        return $user;
    }

    /**
     * A member of staff with a fresh step-up (TOTP, no session: good for the portal session of the test, never for a token).
     * TASK-0037: the risk of an operation never goes below its permission's, so work under a HIGH staff permission — freezing,
     * provider instances, incidents on the status page, partners, abuse cases — takes the step-up the console asks for.
     */
    protected function steppedUpStaff(string $role = 'platform_owner', array $attributes = []): User
    {
        $user = $this->staff($role, $attributes);
        app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');

        return $user;
    }

    /**
     * The only approver's own critical request with ONHOST_FOUR_EYES=false (TASK-0037, permission program IF-10, D8): the first
     * attempt opens a time lock and is refused; the repeat after the delay, with a fresh step-up, runs it. What it published is
     * delivered and the clock is put back afterwards, so a test making several such changes reads as before.
     *
     * @param  Closure(): TestResponse  $send
     */
    protected function soloAfterTimeLock(User $solo, Closure $send): TestResponse
    {
        $send()->assertForbidden()->assertJsonPath('error', 'approval_required');
        $minutes = ApprovalService::timeLockHours() * 60 + 1;
        $this->travel($minutes)->minutes();
        app(StepUpService::class)->grant($solo, 'totp', null, '127.0.0.1');
        $response = $send();
        app(OutboxPublisher::class)->relayPending(); // what the action published a day later is delivered before the clock goes back
        $this->travel(-$minutes)->minutes();

        return $response;
    }

    protected function contextFor(User $user, ?Organization $organization = null, ?string $stepUp = null): CommandContext
    {
        return new CommandContext('user', $user->id, $organization?->id, null, '127.0.0.1', 'pest', 'test-session', stepUpMethod: $stepUp);
    }

    // ── TASK-0039 ──
    /**
     * A member of staff on a /v1/staff/* route (permission program P0-08, IF-8): staff mode. `contextFor()` of a member of staff is
     * that person on a customer route — a customer of the organization, whatever `is_staff` says (StaffActor).
     */
    protected function staffContextFor(User $user, ?Organization $organization = null, ?string $stepUp = null): CommandContext
    {
        return new CommandContext('user', $user->id, $organization?->id, null, '127.0.0.1', 'pest', 'test-session', stepUpMethod: $stepUp, staffMode: true);
    }
    // ── end TASK-0039 ──
}
