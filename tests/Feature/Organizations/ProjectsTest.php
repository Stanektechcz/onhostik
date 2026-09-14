<?php

declare(strict_types=1);

use Database\Seeders\TaxRuleSeeder;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Billing\Models\Subscription;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Organizations\Models\ProjectMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;

/*
 * Partitioning and management: services are assigned to projects, spend is reported per project (gross, monthly), and a
 * member's project role applies to that project's services only — a viewer of the organization who is a developer of
 * one project deploys there and nowhere else. Archiving needs the project empty; the last active project stays.
 */

it('assigns services to projects, reports spend per project and scopes project roles to the project', function () {
    $this->seed(TaxRuleSeeder::class);
    [$owner, $org] = $this->customerWithOrganization();
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    $this->actingAs($owner, 'sanctum');

    $shop = $this->postJson("/v1/organizations/{$org->id}/projects", ['name' => 'E-shop'])->assertCreated()->json('project.id');
    $blog = $this->postJson("/v1/organizations/{$org->id}/projects", ['name' => 'Blog'])->assertCreated()->json('project.id');
    $a = featureWebService($org, 'aapanel');
    $b = Service::query()->create(array_merge($a->only(['organization_id', 'product_key', 'family', 'name', 'state', 'region_code', 'provider_instance_id', 'node_id', 'desired_spec', 'entitlements', 'sla_class', 'activated_at', 'tags', 'health']), ['hostname' => 'blog.cz']));
    $subscription = fn (Service $s, int $net, string $period) => Subscription::query()->create(['organization_id' => $org->id, 'service_id' => $s->id, 'currency' => 'CZK', 'period' => $period, 'amount_minor' => $net, 'state' => 'active', 'auto_renew' => true, 'current_period_start' => now(), 'current_period_end' => now()->addMonth(), 'next_renewal_at' => now()->addMonth()]);
    $subscription($a, 100000, 'month'); // 1 000 Kč net → 1 210 Kč gross a month
    $subscription($b, 60000, 'year');   // 600 Kč net a year → 726 Kč gross → 60,50 Kč a month

    // assignment: the service lists filter by project, a service belongs to one project at a time
    $this->postJson("/v1/services/{$a->id}/project", ['project_id' => $shop])->assertOk()->assertJsonPath('service.project_id', $shop);
    $this->postJson("/v1/services/{$b->id}/project", ['project_id' => $blog])->assertOk();
    $this->getJson("/v1/services?project_id={$shop}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a->id);
    $this->postJson("/v1/services/{$a->id}/project", ['project_id' => 'prj_missing'])->assertNotFound();

    // spend per project (gross, monthly; yearly plans spread over twelve months) and the share of the total
    $list = $this->getJson("/v1/organizations/{$org->id}/projects")->assertOk()->json();
    $rows = collect($list['data'])->keyBy('id');
    expect($rows[$shop]['monthly']['minor'])->toBe(121000)->and($rows[$shop]['services'])->toBe(1)->and($rows[$shop]['share'])->toBe(95.2)
        ->and($rows[$blog]['monthly']['minor'])->toBe(6050)->and($rows[$blog]['share'])->toBe(4.8)
        ->and($list['spend']['monthly_total']['minor'])->toBe(127050)->and($list['spend']['unassigned']['services'])->toBe(0)
        ->and($rows->has('prj_default') || $rows->count() === 3)->toBeTrue(); // the default project created with the organization

    // members: an organization member gains a role inside one project; strangers are refused
    $dev = $this->customer();
    app(OrganizationService::class)->attachMember($org, $dev, 'viewer', CommandContext::system('test'), true);
    $stranger = $this->customer();
    $this->postJson("/v1/organizations/{$org->id}/projects/{$shop}/members", ['user_id' => $stranger->id, 'role' => 'developer'])->assertStatus(422)->assertJsonPath('error', 'not_a_member');
    $this->postJson("/v1/organizations/{$org->id}/projects/{$shop}/members", ['email' => $dev->email, 'role' => 'developer'])->assertCreated()->assertJsonPath('membership.role_key', 'developer');
    $this->postJson("/v1/organizations/{$org->id}/projects/{$shop}/members", ['user_id' => $dev->id, 'role' => 'owner'])->assertStatus(422)->assertJsonPath('error', 'invalid_role');
    expect(PolicyBinding::query()->where('principal_id', $dev->id)->where('scope_type', 'project')->where('scope_id', $shop)->where('role_key', 'developer')->exists())->toBeTrue();
    $detail = $this->getJson("/v1/organizations/{$org->id}/projects/{$shop}")->assertOk()->json('data');
    expect($detail['members'])->toHaveCount(1)->and($detail['members'][0]['email'])->toBe($dev->email)->and($detail['services'][0]['id'])->toBe($a->id)->and($detail['spend']['monthly']['minor'])->toBe(121000);

    // the project role is scoped: the developer manages the e-shop service, not the blog
    $this->actingAs($dev, 'sanctum');
    $this->postJson("/v1/services/{$b->id}/actions", ['action' => 'https.force', 'params' => ['enabled' => true]])->assertForbidden();
    $this->postJson("/v1/services/{$a->id}/actions", ['action' => 'https.force', 'params' => ['enabled' => true]])->assertStatus(202);
    $this->getJson('/v1/services')->assertOk()->assertJsonCount(2, 'data'); // reading stays organization-wide for a viewer
    $this->patchJson("/v1/organizations/{$org->id}/projects/{$shop}", ['name' => 'Shop'])->assertForbidden(); // project.manage is an organization permission

    // archiving needs an empty project; the last active project stays; archived projects take no services
    $this->actingAs($owner, 'sanctum');
    $this->postJson("/v1/organizations/{$org->id}/projects/{$shop}/archive")->assertStatus(409)->assertJsonPath('error', 'project_has_services');
    $this->postJson("/v1/services/{$a->id}/project", ['project_id' => null])->assertOk()->assertJsonPath('service.project_id', null);
    // repeated calls carry a fresh Idempotency-Key: the same actor, route and body would otherwise replay the earlier result
    $this->withHeader('Idempotency-Key', 'archive-2')->postJson("/v1/organizations/{$org->id}/projects/{$shop}/archive")->assertOk()->assertJsonPath('project.state', 'archived');
    $this->flushHeaders()->withHeader('Idempotency-Key', 'assign-archived')->postJson("/v1/services/{$a->id}/project", ['project_id' => $shop])->assertStatus(409)->assertJsonPath('error', 'project_archived');
    $this->flushHeaders();
    $this->postJson("/v1/organizations/{$org->id}/projects/{$shop}/restore")->assertOk()->assertJsonPath('project.state', 'active');
    expect($this->getJson("/v1/organizations/{$org->id}/projects")->json('spend.unassigned.services'))->toBe(1);

    // edits and removal
    $this->patchJson("/v1/organizations/{$org->id}/projects/{$blog}", ['name' => 'Blog & média', 'cost_center' => 'MKT', 'tags' => ['web', 'marketing', 'web']])->assertOk()
        ->assertJsonPath('project.name', 'Blog & média')->assertJsonPath('project.cost_center', 'MKT')->assertJsonPath('project.tags', ['web', 'marketing']);
    $this->deleteJson("/v1/organizations/{$org->id}/projects/{$shop}/members/{$dev->id}")->assertOk()->assertJsonPath('removed', true);
    expect(ProjectMembership::query()->where('project_id', $shop)->count())->toBe(0)
        ->and(PolicyBinding::query()->where('principal_id', $dev->id)->where('scope_type', 'project')->exists())->toBeFalse();
    $this->actingAs($dev, 'sanctum');
    $this->postJson("/v1/services/{$a->id}/actions", ['action' => 'https.force', 'params' => ['enabled' => true]])->assertForbidden();
});
