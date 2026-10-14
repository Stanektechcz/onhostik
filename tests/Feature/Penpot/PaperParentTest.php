<?php

declare(strict_types=1);

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\Penpot\PaperParentCommand;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Errors\DomainError;

/*
 * J2 (TASK-0154): the "paper parent" of the staging Penpot E2E. Penpot is ordered for a running service of the same
 * organisation, and staging has no web panel to build one. The owner's document used to create the row with tinker; it is now
 * `onhost:penpot:paper-parent`: a bus command (audited), only from the command line, never in production, only for a sandbox
 * organisation, a web/managed product, no provider instance, node, binding, order or subscription — nothing is written to a
 * panel and nothing is billed. `--end` closes it, but only while it carries no Penpot that still runs.
 */

beforeEach(function () {
    Http::preventStrayRequests();
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
});

function paperParentOrg(bool $sandbox = true): Organization
{
    $org = Organization::query()->create(['name' => 'Penpot E2E '.bin2hex(random_bytes(3)), 'slug' => 'penpot-e2e-'.bin2hex(random_bytes(3)), 'owner_user_id' => 'usr_paper', 'country' => 'CZ', 'customer_class' => 'b2b']);
    $org->forceFill(['feature_flags' => ['sandbox' => $sandbox]])->save();

    return $org->fresh();
}

it('creates one audited paper parent for a sandbox organisation, with no resource behind it, and finds it again', function () {
    $org = paperParentOrg();

    $this->artisan('onhost:penpot:paper-parent', ['organization' => $org->slug, '--reason' => 'staging Penpot E2E'])->assertExitCode(0);

    $parent = Service::query()->where('organization_id', $org->id)->sole();
    expect($parent->state)->toBe(ServiceStateMachine::ACTIVE)
        ->and($parent->family)->toBe('web')
        ->and($parent->label)->toBe(PaperParentCommand::LABEL)
        ->and(data_get($parent->tags, PaperParentCommand::TAG))->toBeTrue()
        ->and($parent->provider_instance_id)->toBeNull()
        ->and($parent->subscription_id)->toBeNull()
        ->and($parent->bindings()->count())->toBe(0)
        ->and(Operation::query()->where('service_id', $parent->id)->count())->toBe(0);
    expect(AuditEvent::query()->where('action', 'penpot.paper_parent.create')->where('result', 'succeeded')->count())->toBe(1);

    // a second run returns the same row
    $this->artisan('onhost:penpot:paper-parent', ['organization' => $org->slug])->assertExitCode(0);
    expect(Service::query()->where('organization_id', $org->id)->count())->toBe(1);
});

it('refuses an organisation that is not a sandbox, a product that is not web hosting and production', function () {
    $plain = paperParentOrg(false);
    $this->artisan('onhost:penpot:paper-parent', ['organization' => $plain->slug])->assertExitCode(1);
    expect(Service::query()->where('organization_id', $plain->id)->exists())->toBeFalse();

    $org = paperParentOrg();
    $this->artisan('onhost:penpot:paper-parent', ['organization' => $org->slug, '--product' => 'vps', '--plan' => 'compute-2'])->assertExitCode(1);
    expect(Service::query()->where('organization_id', $org->id)->exists())->toBeFalse();

    $this->app['env'] = 'production';
    $this->artisan('onhost:penpot:paper-parent', ['organization' => $org->slug])->assertExitCode(1);
    expect(Service::query()->where('organization_id', $org->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'penpot.paper_parent.create')->where('result', 'failed')->count())->toBe(3);
});

it('is a command-line tool only: a person dispatching it through the bus is refused', function () {
    $org = paperParentOrg();
    [$user, $own] = $this->customerWithOrganization();

    expect(fn () => app(CommandBus::class)->dispatch(new PaperParentCommand('pp-user', ['op' => 'create', 'organization_id' => $org->id, 'product_key' => 'web-hosting', 'plan' => 'start']), $this->contextFor($user, $own)))
        ->toThrow(DomainError::class);
    expect(Service::query()->where('organization_id', $org->id)->exists())->toBeFalse();
});

it('ends the paper parent only when no Penpot of it still runs, and never ends another service', function () {
    $org = paperParentOrg();
    $this->artisan('onhost:penpot:paper-parent', ['organization' => $org->slug])->assertExitCode(0);
    $parent = Service::query()->where('organization_id', $org->id)->sole();
    $penpot = Service::query()->create([
        'organization_id' => $org->id, 'product_key' => 'penpot', 'family' => 'penpot', 'name' => 'Penpot', 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1',
        'entitlements' => [], 'sla_class' => 'standard', 'activated_at' => now(), 'tags' => ['parent_service_id' => $parent->id], 'desired_spec' => ['parent_service_id' => $parent->id],
    ]);

    $this->artisan('onhost:penpot:paper-parent', ['organization' => $org->slug, '--end' => $parent->id])->assertExitCode(1);
    expect($parent->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);

    // an ordinary service is never ended by this tool
    $this->artisan('onhost:penpot:paper-parent', ['organization' => $org->slug, '--end' => $penpot->id])->assertExitCode(1);
    expect($penpot->fresh()->state)->toBe(ServiceStateMachine::ACTIVE);

    $penpot->forceFill(['state' => ServiceStateMachine::TERMINATED])->save();
    $this->artisan('onhost:penpot:paper-parent', ['organization' => $org->slug, '--end' => $parent->id])->assertExitCode(0);
    expect($parent->fresh()->state)->toBe(ServiceStateMachine::TERMINATED)
        ->and($parent->fresh()->terminated_at)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'penpot.paper_parent.end')->where('result', 'succeeded')->count())->toBe(1)
        ->and(Operation::query()->where('service_id', $parent->id)->count())->toBe(0);
});
