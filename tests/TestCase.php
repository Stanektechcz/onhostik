<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\BaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Http\HostResolver;

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

    protected function contextFor(User $user, ?Organization $organization = null, ?string $stepUp = null): CommandContext
    {
        return new CommandContext('user', $user->id, $organization?->id, null, '127.0.0.1', 'pest', 'test-session', stepUpMethod: $stepUp);
    }
}
