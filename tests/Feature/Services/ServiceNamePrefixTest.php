<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Providers\Contracts\Naming;

/*
 * What a service owns on a shared node is recognised by its name prefix — `oh` + the last six characters of its id, thirty
 * bits. Two services with one prefix list, change and drop each other's databases and FTP accounts. The prefix is unique
 * in the database, an id that would repeat one is replaced before anything exists, and a cancelled service keeps its
 * prefix (its databases stay on the node through the restore window).
 */

beforeEach(fn () => Http::preventStrayRequests());

/** A service row with an id of the caller's choice (the last six characters are what matters). */
function namePrefixService(string $organizationId, ?string $id = null): Service
{
    return Service::query()->create(array_filter(['id' => $id]) + [
        'organization_id' => $organizationId, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1',
        'desired_spec' => [], 'entitlements' => [], 'sla_class' => 'standard', 'tags' => [], 'health' => [],
    ]);
}

it('gives a service another id when its name prefix is taken — by a running service or by a cancelled one', function () {
    [, $org] = $this->customerWithOrganization();
    [, $other] = $this->customerWithOrganization();
    $first = namePrefixService($org->id, 'srv_01m1wf8e4c2pqbnw69b2abc123');
    expect($first->name_prefix)->toBe('ohabc123')->and(Naming::prefix($first->id))->toBe('ohabc123');

    // another customer's service whose id ends the same way: it would own the first one's databases on a shared panel
    $second = namePrefixService($other->id, 'srv_01m9zzzzzzzzzzzzzzzzzzabc123');
    expect($second->id)->not->toBe('srv_01m9zzzzzzzzzzzzzzzzzzabc123')->and($second->name_prefix)->not->toBe('ohabc123')->and($second->name_prefix)->toBe(Naming::prefix($second->id))
        ->and(Service::isValidPublicId($second->id))->toBeTrue();

    // a cancelled service keeps its prefix: its databases are still on the node
    $first->delete();
    $third = namePrefixService($other->id, 'srv_01m8yyyyyyyyyyyyyyyyyyabc123');
    expect($third->name_prefix)->not->toBe('ohabc123');

    // what the code would miss, the database refuses
    // (inside a savepoint: on PostgreSQL a refused statement aborts the transaction the test runs in)
    expect(fn () => DB::transaction(fn () => DB::table('services')->where('id', $third->id)->update(['name_prefix' => 'ohabc123'])))->toThrow(UniqueConstraintViolationException::class);

    // ordinary services simply get the prefix of their id
    $plain = namePrefixService($org->id);
    expect($plain->name_prefix)->toBe(Naming::prefix($plain->id));
});

it('names the services that share a prefix from before the rule', function () {
    [, $org] = $this->customerWithOrganization();
    $old = namePrefixService($org->id);
    DB::table('services')->where('id', $old->id)->update(['name_prefix' => null]); // what the migration leaves for the younger of two colliding services
    Artisan::call('onhost:doctor', ['--json' => true]);
    $check = collect(json_decode(trim(Artisan::output()), true)['checks'])->firstWhere('check', 'every service has a node name prefix of its own');
    expect($check['status'])->toBe('WARN')->and($check['detail'])->toContain($old->id);
});
