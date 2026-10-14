<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Advisories\AdvisoryCases;
use Onhost\Domain\Advisories\AdvisoryIngest;
use Onhost\Domain\Advisories\AdvisoryMatcher;
use Onhost\Domain\Advisories\Models\AdvisoryCase;
use Onhost\Domain\Advisories\Models\AdvisoryMatch;
use Onhost\Domain\Advisories\Models\InventoryItem;
use Onhost\Domain\Content\Models\Post;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderBinding;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Platform\Outbox\OutboxMessage;
use Onhost\Platform\Outbox\OutboxPublisher;

/*
 * SN: security news for technical support. An advisory from a feed that hits software on a customer's site opens a case; staff
 * approve the fix plan (step-up, four eyes over the limit or with risky fixes), fixes run in waves through the ordinary service
 * actions and stop at the first failure, customers hear what, where and why, and a draft blog post is written (never published).
 */

beforeEach(function () {
    Queue::fake(); // the fix operations belong to the service actions (tested there); here they stay queued and the test settles them
    Http::fake(fn () => Http::response(['status' => true, 'msg' => 'ok']));
    config(['onhost.advisories.canary' => 1, 'onhost.advisories.wave_percent' => 10, 'onhost.advisories.single_approver_max' => 25]);
});

/** A second active site of the organization on the same aaPanel instance as $first. */
function snSecondSite(Organization $org, Service $first, string $host): Service
{
    $service = Service::query()->create(['organization_id' => $org->id, 'product_key' => 'web-hosting', 'family' => 'web', 'name' => 'Webhosting Standard', 'hostname' => $host, 'state' => ServiceStateMachine::ACTIVE, 'region_code' => 'cz1', 'provider_instance_id' => $first->provider_instance_id, 'node_id' => $first->node_id, 'entitlements' => $first->entitlements, 'desired_spec' => ['executor' => 'aapanel', 'family' => 'web', 'domain' => $host, 'php_version' => '8.3'], 'sla_class' => 'standard', 'activated_at' => now()]);
    ProviderBinding::query()->create(['service_id' => $service->id, 'provider_instance_id' => $first->provider_instance_id, 'remote_type' => 'site', 'remote_id' => (string) random_int(100, 999), 'remote_node' => 'aapanel-managed01', 'meta' => ['name' => $host, 'path' => '/www/wwwroot/'.$host], 'ownership' => ['managed_by' => 'onhost'], 'idempotency_key' => "sn-test:{$service->id}", 'adapter_version' => '1.0.0']);

    return $service;
}

function snInstalled(Service $service, string $ecosystem, string $product, string $version): InventoryItem
{
    return InventoryItem::query()->create(['subject_type' => InventoryItem::SERVICE, 'subject_id' => $service->id, 'organization_id' => $service->organization_id, 'ecosystem' => $ecosystem, 'product' => $product, 'version' => $version, 'label' => $service->hostname.' · '.$product, 'seen_at' => now()]);
}

/** An advisory as a feed brings it, matched against the inventory: the case it opened. */
function snAdvisory(string $product = 'contact-form-7', ?string $fixed = '5.9.8', bool $kev = false, string $ecosystem = 'wordpress-plugin'): AdvisoryCase
{
    app(AdvisoryIngest::class)->upsert([
        'key' => 'CVE-2026-4444', 'source' => 'wordfence', 'external_id' => 'f1', 'aliases' => ['CVE-2026-4444'], 'title' => 'Contact Form 7 <= 5.9.7 - Stored XSS', 'summary' => 'Stored cross-site scripting.',
        'severity' => 'high', 'cvss' => 7.2, 'kev' => $kev, 'kev_added_on' => null, 'affected' => [array_filter(['ecosystem' => $ecosystem, 'product' => $product, 'last_affected' => '5.9.7', 'fixed' => $fixed])],
        'references' => ['https://www.wordfence.com/threat-intel/f1'], 'published_at' => '2026-10-12T08:00:00Z', 'modified_at' => '2026-10-13T08:00:00Z',
    ]);
    app(AdvisoryMatcher::class)->run();

    return AdvisoryCase::query()->firstOrFail();
}

/** The fix operations of the case settle as given, and the case takes its next step. */
function snSettle(AdvisoryCase $case, string $state): AdvisoryCase
{
    foreach (AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::RUNNING)->get() as $match) {
        Operation::query()->whereKey($match->operation_id)->update(['state' => $state]);
    }

    return app(AdvisoryCases::class)->advance($case->refresh());
}

it('opens a case when an advisory hits a plugin on a customer site, tells staff and leaves the platform and untouched versions out', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    snInstalled($site, 'wordpress-plugin', 'contact-form-7', '5.9.7');
    snInstalled(snSecondSite($org, $site, 'blog.cz'), 'wordpress-plugin', 'contact-form-7', '5.9.8'); // already fixed
    $case = snAdvisory(kev: true);

    expect($case->state)->toBe(AdvisoryCase::NEW)->and($case->matches_count)->toBe(1)->and($case->kev)->toBeTrue();
    $match = AdvisoryMatch::query()->where('case_id', $case->id)->firstOrFail();
    expect($match->class)->toBe('A')->and($match->subject_id)->toBe($site->id)->and($match->fixed_in)->toBe('5.9.8')->and($match->action)->toBe(['action' => 'wp.plugin', 'params' => ['slug' => 'contact-form-7', 'op' => 'update']]);
    $event = OutboxMessage::query()->where('name', 'security.advisory.matched')->firstOrFail();
    expect($event->payload['urgent'])->toBeTrue()->and($event->payload['classes'])->toBe(['A' => 1]);
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('event', 'security.advisory.matched')->value('severity'))->toBe('hot');

    // the plugin got updated by somebody: the match is gone and the case closes itself
    InventoryItem::query()->where('subject_id', $site->id)->update(['version' => '5.9.8']);
    app(AdvisoryMatcher::class)->run();
    expect($case->refresh()->state)->toBe(AdvisoryCase::RESOLVED)->and($match->refresh()->state)->toBe(AdvisoryMatch::GONE);
});

it('runs an approved plan in waves after a step-up, tells the customers whose sites were fixed and drafts a blog post', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $second = snSecondSite($org, $site, 'blog.cz');
    $third = snSecondSite($org, $site, 'eshop.cz');
    foreach ([$site, $second, $third] as $s) {
        snInstalled($s, 'wordpress-plugin', 'contact-form-7', '5.9.7');
    }
    $case = snAdvisory();

    $this->actingAs($this->staff('security_soc'), 'sanctum');
    $this->getJson("/v1/staff/security/advisory-cases/{$case->id}")->assertOk()->assertJsonPath('data.targets.safe', 3)->assertJsonPath('data.matches.0.label', fn ($l) => in_array($l, ['shop.cz', 'blog.cz', 'eshop.cz'], true));
    $this->postJson("/v1/staff/security/advisory-cases/{$case->id}/approve", ['plan_revision' => 1])->assertForbidden()->assertJsonPath('error', 'step_up_required');

    $this->actingAs($this->steppedUpStaff('security_soc'), 'sanctum');
    $this->postJson("/v1/staff/security/advisory-cases/{$case->id}/approve", ['plan_revision' => 7])->assertStatus(409)->assertJsonPath('error', 'advisory_plan_changed');
    $this->postJson("/v1/staff/security/advisory-cases/{$case->id}/approve", ['plan_revision' => 1, 'note' => 'bezpečná aktualizace pluginu'])->assertOk()->assertJsonPath('state', 'approved')->assertJsonPath('wave', 1);

    // wave 1 is the canary: one site, through the ordinary service action
    $running = AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::RUNNING)->get();
    expect($running)->toHaveCount(1)->and(Operation::query()->findOrFail($running[0]->operation_id)->desired['action'])->toBe('wp.plugin')
        ->and(AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::QUEUED)->count())->toBe(2);
    $case = snSettle($case, Operation::SUCCEEDED);
    expect($case->wave)->toBe(2)->and(AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::RUNNING)->count())->toBe(1);
    $case = snSettle($case, Operation::SUCCEEDED);
    $case = snSettle($case, Operation::SUCCEEDED);
    $case = app(AdvisoryCases::class)->advance($case);

    expect($case->state)->toBe(AdvisoryCase::RESOLVED)->and(AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::FIXED)->count())->toBe(3)
        ->and(OutboxMessage::query()->where('name', 'security.advisory.service_fixed')->count())->toBe(3)
        ->and(OutboxMessage::query()->where('name', 'security.remediation.finished')->exists())->toBeTrue();
    app(OutboxPublisher::class)->relayPending();
    $mail = MailOutbox::query()->where('template_key', 'security-advisory-fixed')->firstOrFail();
    expect($mail->vars['software'])->toBe('plugin contact-form-7 5.9.7')->and($mail->vars['chyba'])->toBe('CVE-2026-4444');

    // the blog draft names the software and the fix, never a customer or a site
    $post = Post::query()->findOrFail($case->post_id);
    expect($post->state)->toBe('draft')->and($post->category['cs'] ?? null)->toBe('Bezpečnost')
        ->and(json_encode($post->body, JSON_UNESCAPED_UNICODE))->toContain('5.9.8')->not->toContain('shop.cz')->not->toContain('blog.cz')->not->toContain($org->name);
});

it('halts the waves at the first failed fix and tells the customers that are left what to do themselves', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    $second = snSecondSite($org, $site, 'blog.cz');
    snInstalled($site, 'wordpress-plugin', 'contact-form-7', '5.9.7');
    snInstalled($second, 'wordpress-plugin', 'contact-form-7', '5.9.7');
    $case = snAdvisory();
    $this->actingAs($this->steppedUpStaff('security_soc'), 'sanctum');
    $this->postJson("/v1/staff/security/advisory-cases/{$case->id}/approve", ['plan_revision' => 1])->assertOk();

    $case = snSettle($case, Operation::FAILED);
    expect($case->state)->toBe(AdvisoryCase::HALTED)->and($case->halted_reason)->toContain('selhala')
        ->and(AdvisoryMatch::query()->where('case_id', $case->id)->where('state', AdvisoryMatch::QUEUED)->exists())->toBeFalse()
        ->and(Operation::query()->where('service_id', $second->id)->count() + Operation::query()->where('service_id', $site->id)->count())->toBe(1);
    $notices = OutboxMessage::query()->where('name', 'security.advisory.service_affected')->get();
    expect($notices)->toHaveCount(2)->and($notices->pluck('payload.outcome')->unique()->all())->toBe(['halted']);
    app(OutboxPublisher::class)->relayPending();
    expect(MailOutbox::query()->where('template_key', 'security-advisory')->count())->toBe(2)
        ->and(MailOutbox::query()->where('template_key', 'security-advisory')->value('vars')['co_udelat'])->toContain('zastavili');
});

it('takes a second person for a plan over the limit or with risky fixes, bound to what the console showed', function () {
    config(['onhost.advisories.single_approver_max' => 0]);
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    snInstalled($site, 'wordpress-plugin', 'contact-form-7', '4.2.0'); // the fix is a new major line: B
    $case = snAdvisory();
    expect(AdvisoryMatch::query()->where('case_id', $case->id)->value('class'))->toBe('B');
    $this->actingAs($this->steppedUpStaff('security_soc'), 'sanctum');

    $body = ['plan_revision' => 1, 'include_risky' => true];
    $approval = $this->postJson("/v1/staff/security/advisory-cases/{$case->id}/approve", $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    expect($case->refresh()->state)->toBe(AdvisoryCase::NEW)->and(Operation::query()->count())->toBe(0);
    $this->postJson("/v1/staff/security/advisory-cases/{$case->id}/approve", $body + ['approval_ids' => [secondPersonApproves($approval)]])->assertOk()->assertJsonPath('state', 'approved');

    // the customer of a B site is told before the update runs
    expect(OutboxMessage::query()->where('name', 'security.advisory.service_affected')->firstOrFail()->payload['outcome'])->toBe('scheduled')
        ->and(AdvisoryMatch::query()->where('case_id', $case->id)->value('state'))->toBe(AdvisoryMatch::RUNNING);
});

it('tells the customer what, where and why when there is nothing to fix automatically, and dismissing needs a note', function () {
    [, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    snInstalled($site, 'wordpress-plugin', 'contact-form-7', '5.9.7');
    $case = snAdvisory(fixed: null);
    expect(AdvisoryMatch::query()->where('case_id', $case->id)->value('class'))->toBe('D');

    $this->actingAs($this->staff('sre'), 'sanctum');
    $this->postJson("/v1/staff/security/advisory-cases/{$case->id}/triage", ['decision' => 'dismissed'])->assertStatus(422)->assertJsonPath('error', 'advisory_note_required');
    $this->postJson("/v1/staff/security/advisory-cases/{$case->id}/triage", ['decision' => 'relevant'])->assertOk()->assertJsonPath('state', 'awaiting_approval');
    $this->postJson("/v1/staff/security/advisory-cases/{$case->id}/notify", [])->assertForbidden(); // sre may triage, not tell customers

    $this->actingAs($this->steppedUpStaff('security_soc'), 'sanctum');
    $this->postJson("/v1/staff/security/advisory-cases/{$case->id}/notify", ['note' => 'oprava zatím nevyšla'])->assertOk()->assertJsonPath('state', 'partially_resolved')->assertJsonPath('post_id', fn ($id) => is_string($id) && $id !== '');
    expect(Operation::query()->count())->toBe(0);
    app(OutboxPublisher::class)->relayPending();
    $mail = MailOutbox::query()->where('template_key', 'security-advisory')->firstOrFail();
    expect($mail->vars['web'])->toBe('shop.cz')->and($mail->vars['co_udelat'])->toContain('Opravená verze zatím nevyšla')->and($mail->vars['zavaznost'])->toBe('vysoká')
        ->and(Notification::query()->where('event', 'security.advisory.service_affected')->where('audience', 'customer')->where('organization_id', $org->id)->exists())->toBeTrue();
    $first = $case->refresh()->post_id;
    expect(Post::query()->findOrFail($first)->state)->toBe('draft');
    $again = $this->postJson("/v1/staff/security/advisory-cases/{$case->id}/blog-draft")->assertCreated()->json('post_id');
    expect($again)->not->toBe($first)->and(Post::query()->whereIn('id', [$first, $again])->where('state', 'draft')->count())->toBe(2);
});

it('shows the page and the reads to whoever may read advisories, and nothing to a customer', function () {
    [$customer, $org] = $this->customerWithOrganization();
    $site = featureWebService($org, 'aapanel');
    snInstalled($site, 'wordpress-plugin', 'contact-form-7', '5.9.7');
    snAdvisory();

    $staff = $this->staff('shared_hosting_admin');
    $this->actingAs($staff)->get('/sprava/nastaveni/bezpecnost')->assertOk()->assertSee('Bezpečnostní novinky');
    $this->actingAs($staff, 'sanctum')->getJson('/v1/staff/security/advisory-cases')->assertOk()->assertJsonPath('data.0.advisory.key', 'CVE-2026-4444');
    $this->getJson('/v1/staff/security/advisories?q=4444')->assertOk()->assertJsonPath('data.0.title', 'Contact Form 7 <= 5.9.7 - Stored XSS');
    $this->getJson('/v1/staff/security/advisories/health')->assertOk()->assertJsonPath('data.inventory.0.ecosystem', 'wordpress-plugin');
    $this->postJson('/v1/staff/security/advisory-cases/'.AdvisoryCase::query()->value('id').'/triage', ['decision' => 'relevant'])->assertForbidden(); // read-only role

    $this->actingAs($customer, 'sanctum')->getJson('/v1/staff/security/advisory-cases')->assertForbidden();
    $this->actingAs($this->staff('billing_admin'))->get('/sprava/nastaveni/bezpecnost')->assertStatus(403);
});
