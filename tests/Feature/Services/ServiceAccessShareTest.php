<?php

declare(strict_types=1);

use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Organizations\OrganizationService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Access\ServiceAccessService;
use Onhost\Domain\Services\Models\ServiceAccessGrant;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\TestCase;

/*
 * One service handed to another person — the customer's freelancer or agency — with named capabilities and, if wanted,
 * until a date. The person sees that service and nothing else of the organization, may do exactly what was ticked, and
 * loses it the moment the owner takes it back or the date comes.
 */

beforeEach(function () {
    $this->seed(NotificationTemplateSeeder::class);
    Http::preventStrayRequests();
    Queue::fake(); // what an action does on a panel is not the subject here
});

/** Signs the owner in with a fresh step-up and shares; returns the test response. */
function shareService(TestCase $test, User $owner, Organization $org, string $serviceId, array $body, string $key)
{
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');

    return $test->actingAs($owner, 'sanctum')->postJson("/v1/services/{$serviceId}/access", $body, ['X-Organization' => $org->id, 'Idempotency-Key' => $key]);
}

it('shares one service with somebody outside the organization: they accept, see only that service and do only what was ticked', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $shop = featureWebService($org, 'aapanel');
    $other = featureMailService($org, 'posta-firma.cz');
    $freelancer = $this->customer(['email' => 'koder@example.cz', 'name' => 'Petr Kodér']);

    // sharing lets somebody new in: it takes the right that manages members and a fresh proof of identity
    $this->actingAs($owner, 'sanctum')->postJson("/v1/services/{$shop->id}/access", ['email' => 'koder@example.cz', 'capabilities' => ['manage']], ['X-Organization' => $org->id, 'Idempotency-Key' => 'sh-0'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    $grant = shareService($this, $owner, $org, $shop->id, ['email' => 'Koder@Example.cz', 'capabilities' => ['manage', 'backups'], 'note' => 'redesign do konce října'], 'sh-1')->assertCreated()->json('grant');
    expect($grant['state'])->toBe('pending')->and($grant['capabilities'])->toBe(['view', 'manage', 'backups'])->and($grant['email'])->toBe('koder@example.cz');

    // nothing works before the invitation is accepted — the link in the mail proves the mailbox
    $this->actingAs($freelancer, 'sanctum')->getJson("/v1/services/{$shop->id}")->assertForbidden();
    $mail = MailOutbox::query()->where('template_key', 'service-shared')->where('to', 'koder@example.cz')->firstOrFail();
    expect((string) json_encode($mail->vars, JSON_UNESCAPED_UNICODE))->toContain('shop.cz')->toContain('správa a nastavení');
    preg_match('/pozvanka=([^&"]+)/', (string) json_encode($mail->vars, JSON_UNESCAPED_SLASHES), $m);
    $accepted = $this->postJson('/v1/organizations/invitations/accept', ['token' => rawurldecode($m[1])])->assertOk()->json('data');
    expect($accepted)->toMatchArray(['organization_id' => $org->id, 'role' => 'guest', 'shared_services' => 1]);

    // the guest sees that one service …
    $h = ['X-Organization' => $org->id];
    $listed = $this->getJson('/v1/services', $h)->assertOk()->json('data');
    expect(array_column($listed, 'id'))->toBe([$shop->id]);
    $this->getJson("/v1/services/{$shop->id}", $h)->assertOk();
    expect($this->getJson('/v1/me/shared-services')->assertOk()->json('data.0'))->toMatchArray(['service_id' => $shop->id, 'capabilities' => ['view', 'manage', 'backups']]);
    // … and nothing else of the organization
    $this->getJson("/v1/services/{$other->id}", $h)->assertForbidden();
    $this->getJson('/v1/invoices', $h)->assertForbidden();
    $this->getJson('/v1/wallet', $h)->assertForbidden();
    $this->getJson("/v1/organizations/{$org->id}", $h)->assertForbidden();
    $this->getJson("/v1/services/{$shop->id}/access", $h)->assertForbidden(); // who else has access is the owner's business
    // what was ticked works, what was not does not
    $this->postJson("/v1/services/{$shop->id}/actions", ['action' => 'php.set', 'params' => ['version' => '8.4']], $h + ['Idempotency-Key' => 'g-1'])->assertAccepted();
    Operation::query()->where('service_id', $shop->id)->delete();
    $this->postJson("/v1/services/{$shop->id}/actions", ['action' => 'restore', 'params' => ['backup_id' => 'bkp_x']], $h + ['Idempotency-Key' => 'g-2'])->assertForbidden();
    $this->postJson("/v1/services/{$shop->id}/actions", ['action' => 'terminate'], $h + ['Idempotency-Key' => 'g-3'])->assertForbidden();
    $this->postJson("/v1/services/{$other->id}/actions", ['action' => 'backup'], $h + ['Idempotency-Key' => 'g-4'])->assertForbidden();
    // a guest cannot pass the service on
    app(StepUpService::class)->grant($freelancer, 'totp', null, '127.0.0.1');
    $this->postJson("/v1/services/{$shop->id}/access", ['email' => 'dalsi@example.cz', 'capabilities' => ['view']], $h + ['Idempotency-Key' => 'g-5'])->assertForbidden();

    // the owner sees who has what, and takes it back: the access ends at once and the guest leaves the organization
    $list = $this->actingAs($owner, 'sanctum')->getJson("/v1/services/{$shop->id}/access", $h)->assertOk();
    expect($list->json('data.0'))->toMatchArray(['email' => 'koder@example.cz', 'state' => 'active', 'name' => 'Petr Kodér', 'note' => 'redesign do konce října'])->and(array_column($list->json('capabilities'), 'key'))->toContain('view', 'manage', 'console', 'backups', 'restore', 'assistant');
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->deleteJson("/v1/services/{$shop->id}/access/{$grant['id']}", [], $h + ['Idempotency-Key' => 'sh-2'])->assertOk()->assertJsonPath('grant.state', 'revoked');
    app(Authorizer::class)->forget($freelancer);
    expect(PolicyBinding::query()->where('principal_id', $freelancer->id)->count())->toBe(0)
        ->and(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $freelancer->id)->exists())->toBeFalse();
    $this->actingAs($freelancer, 'sanctum')->getJson("/v1/services/{$shop->id}")->assertForbidden();
    app(OutboxPublisher::class)->relayPending();
    expect(Notification::query()->where('organization_id', $org->id)->where('title', 'like', 'Sdílení služby ukončeno%')->exists())->toBeTrue();
});

it('gives a colleague who is already in the organization the access at once, and ends it by itself on its date', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $shop = featureWebService($org, 'aapanel');
    $colleague = $this->customer(['email' => 'ucetni@example.cz']);
    app(OrganizationService::class)->attachMember($org, $colleague, 'billing_admin', CommandContext::system('test'), true);

    $until = now()->addDays(3);
    $grant = shareService($this, $owner, $org, $shop->id, ['email' => 'ucetni@example.cz', 'capabilities' => ['console'], 'access_until' => $until->toIso8601String()], 'col-1')->assertCreated()->json('grant');
    expect($grant['state'])->toBe('active')->and($grant['capabilities'])->toBe(['view', 'manage', 'console']); // a shell is more than managing, never less (H334)
    $scope = CommandScope::resource($shop->id, $org->id, $shop->project_id);
    expect(app(Authorizer::class)->can($colleague, 'service.console', $scope))->toBeTrue()->and(app(Authorizer::class)->can($colleague, 'backup.download', $scope))->toBeFalse();
    // sharing again changes what the person may do instead of adding a second record
    shareService($this, $owner, $org, $shop->id, ['email' => 'ucetni@example.cz', 'capabilities' => ['manage'], 'access_until' => $until->toIso8601String()], 'col-2')->assertCreated();
    app(Authorizer::class)->forget($colleague);
    expect(ServiceAccessGrant::query()->where('service_id', $shop->id)->count())->toBe(1)
        ->and(app(Authorizer::class)->can($colleague, 'service.manage', $scope))->toBeTrue()->and(app(Authorizer::class)->can($colleague, 'service.console', $scope))->toBeFalse(); // the console was taken back, managing stays

    // the date comes: the permission stops at that second, the sweep closes the record — and a member with a role of their own stays a member
    $this->travelTo($until->copy()->addMinute());
    app(Authorizer::class)->forget($colleague);
    expect(app(Authorizer::class)->can($colleague, 'service.manage', $scope))->toBeFalse();
    expect(app(ServiceAccessService::class)->expire())->toBe(1)->and(ServiceAccessGrant::query()->findOrFail($grant['id'])->state)->toBe('expired');
    expect(OrganizationMembership::query()->where('organization_id', $org->id)->where('user_id', $colleague->id)->value('role_key'))->toBe('billing_admin');
});

it('lets nobody hand out what they do not hold, share with themselves, or give a capability as an organization role', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $shop = featureWebService($org, 'aapanel');
    $developer = $this->customer(['email' => 'dev@example.cz']);
    app(OrganizationService::class)->attachMember($org, $developer, 'developer', CommandContext::system('test'), true);
    $access = app(ServiceAccessService::class);

    // a developer manages the service; who else may is not theirs to decide
    app(StepUpService::class)->grant($developer, 'totp', null, '127.0.0.1');
    $this->actingAs($developer, 'sanctum')->postJson("/v1/services/{$shop->id}/access", ['email' => 'kamarad@example.cz', 'capabilities' => ['view']], ['X-Organization' => $org->id, 'Idempotency-Key' => 'dev-1'])->assertForbidden();
    // and even with that right nobody hands out more than they hold on that service (a developer cannot restore)
    expect(fn () => $access->share($org, $shop, 'kamarad@example.cz', ['restore'], $this->contextFor($developer, $org, 'totp')))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('capability_above_own'));
    expect(fn () => $access->share($org, $shop, $owner->email, ['manage'], $this->contextFor($owner, $org, 'totp')))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('cannot_share_with_self'));
    expect(fn () => $access->share($org, $shop, 'dev@example.cz', ['manage'], $this->contextFor($owner, $org, 'totp')))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('already_has_access'));
    expect(fn () => $access->share($org, $shop, 'x@example.cz', ['delete'], $this->contextFor($owner, $org, 'totp')))->toThrow(fn (DomainError $e) => expect($e->error)->toBe('capabilities_invalid'));
    [, $elsewhere] = $this->customerWithOrganization(['email' => 'jinde@example.cz']);
    expect(fn () => $access->share($elsewhere, $shop, 'x@example.cz', ['view'], CommandContext::system('test')))->toThrow(DomainError::class); // somebody else's service

    // a capability on one service is not a role in the organization or in a project
    app(StepUpService::class)->grant($owner, 'totp', null, '127.0.0.1');
    $this->actingAs($owner, 'sanctum')->postJson("/v1/organizations/{$org->id}/invitations", ['email' => 'y@example.cz', 'role' => 'svc_manage'], ['Idempotency-Key' => 'role-1'])->assertStatus(422);

    // an API token that may restart services must not be able to let somebody in
    $token = $owner->createToken('ci', ['services:read', 'services:power'])->plainTextToken;
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->postJson("/v1/services/{$shop->id}/access", ['email' => 'z@example.cz', 'capabilities' => ['view']], ['X-Organization' => $org->id, 'Idempotency-Key' => 'tok-1'])->assertForbidden();
    $this->withToken($token)->getJson("/v1/services/{$shop->id}/access", ['X-Organization' => $org->id])->assertForbidden();
});

it('closes what was shared with somebody who leaves the organization', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $shop = featureWebService($org, 'aapanel');
    $colleague = $this->customer(['email' => 'kolega@example.cz']);
    app(OrganizationService::class)->attachMember($org, $colleague, 'viewer', CommandContext::system('test'), true);
    $grant = app(ServiceAccessService::class)->share($org, $shop, 'kolega@example.cz', ['manage'], $this->contextFor($owner, $org, 'totp'));
    expect($grant->state)->toBe('active');

    app(OrganizationService::class)->removeMember($org, $colleague, $this->contextFor($owner, $org, 'totp'));
    app(OutboxPublisher::class)->relayPending();
    expect($grant->fresh()->state)->toBe('revoked')->and(PolicyBinding::query()->where('principal_id', $colleague->id)->count())->toBe(0);
});
