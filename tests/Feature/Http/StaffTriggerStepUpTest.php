<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Billing\Models\DunningCase;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;

/*
 * Audit §4 "Step-up is not enforced on non-bus staff triggers" (TASK-0030 WP-B): the bus asks for a fresh step-up before
 * anything under a HIGH permission runs, but a few staff endpoints do their work without the bus and only asked
 * `ApiContext::authorize()` — which checks the permission and never its risk. A stolen staff session could run dunning
 * by hand (suspensions, terminations), the capacity pass (vendor orders) or open a customer's panel, no second factor.
 * Those writes now go through `ApiContext::authorizeAction()`, the same step-up the bus asks for, with the same answer
 * the console's step-up dialog already understands.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

function staffTriggerGrant(User $user): void
{
    app(StepUpService::class)->grant($user, 'totp', null, '127.0.0.1');
}

/** A case the next dunning pass will look at (due today: no notice yet, only the next look is moved to tomorrow). */
function staffTriggerDunningCase(string $organizationId): DunningCase
{
    return DunningCase::query()->create(['organization_id' => $organizationId, 'invoice_id' => null, 'service_id' => null, 'state' => DunningCase::DUE, 'due_at' => now(), 'next_action_at' => now()->subMinute(), 'notices_sent' => []]);
}

it('running dunning by hand takes a fresh step-up', function () {
    [, $org] = $this->customerWithOrganization();
    $case = staffTriggerDunningCase($org->id);
    $before = $case->next_action_at->toIso8601String();
    $operator = $this->staff('billing_operator');
    $this->actingAs($operator, 'sanctum');

    $this->postJson('/v1/staff/dunning/run', [], ['Idempotency-Key' => 'staff-trigger-dunning'])->assertForbidden()
        ->assertJsonPath('error', 'step_up_required')->assertJsonPath('requirement', 'step_up')->assertJsonPath('help', '/v1/auth/step-up');
    expect($case->fresh()->next_action_at->toIso8601String())->toBe($before); // the pass did not run

    // the console's dialog steps up and sends the very same request again: a refusal is never replayed
    staffTriggerGrant($operator);
    $run = $this->postJson('/v1/staff/dunning/run', [], ['Idempotency-Key' => 'staff-trigger-dunning'])->assertOk()->assertHeaderMissing('Idempotent-Replayed')->json('data');
    expect($run['cases'])->toBe(1)->and($case->fresh()->next_action_at->toIso8601String())->not->toBe($before);
});

it('running the capacity pass by hand takes a fresh step-up', function () {
    $admin = $this->staff('infrastructure_admin');
    $this->actingAs($admin, 'sanctum');
    $planned = 0; // the planner always reads the ordered capacity requests first: no such query, no pass
    DB::listen(function ($query) use (&$planned) {
        if (str_contains($query->sql, 'capacity_requests')) {
            $planned++;
        }
    });

    $this->postJson('/v1/staff/capacity/forecast/run', [], ['Idempotency-Key' => 'staff-trigger-capacity'])->assertForbidden()->assertJsonPath('error', 'step_up_required');
    expect($planned)->toBe(0);

    staffTriggerGrant($admin);
    $run = $this->postJson('/v1/staff/capacity/forecast/run', [], ['Idempotency-Key' => 'staff-trigger-capacity'])->assertOk()->json('data');
    expect($run)->toHaveKeys(['warned', 'plan', 'forecast'])->and($planned)->toBeGreaterThan(0);
});

it('without the permission the answer is the permission, not a step-up dialog', function () {
    $this->actingAs($this->staff('support_agent'), 'sanctum');

    $dunning = $this->postJson('/v1/staff/dunning/run', [], ['Idempotency-Key' => 'staff-trigger-agent-1'])->assertForbidden();
    expect($dunning->json('message'))->toBe('Missing permission billing.dunning.manage')->and($dunning->json('error'))->not->toBe('step_up_required');
    $capacity = $this->postJson('/v1/staff/capacity/forecast/run', [], ['Idempotency-Key' => 'staff-trigger-agent-2'])->assertForbidden();
    expect($capacity->json('message'))->toBe('Missing permission capacity.manage')->and($capacity->json('error'))->not->toBe('step_up_required');
});

it('reading under a HIGH permission needs no step-up', function () {
    $this->actingAs($this->staff('billing_operator'), 'sanctum');
    $this->getJson('/v1/staff/dunning')->assertOk();

    [$owner] = $this->customerWithOrganization();
    $this->actingAs($owner, 'sanctum');
    $this->getJson('/v1/tokens')->assertOk();

    $this->actingAs($this->staff('security_soc'), 'sanctum');
    $this->getJson('/v1/staff/security/incidents')->assertOk();
});

it('staff SSO into a customer panel takes a fresh step-up', function () {
    $staff = $this->staff('shared_hosting_admin');
    $this->actingAs($staff, 'sanctum');
    $missing = (string) Str::uuid();

    $this->getJson("/v1/staff/services/{$missing}/panel-login")->assertForbidden()->assertJsonPath('error', 'step_up_required')->assertJsonPath('requirement', 'step_up');

    staffTriggerGrant($staff); // past the step-up the request reaches the service lookup
    $this->getJson("/v1/staff/services/{$missing}/panel-login")->assertNotFound();
});

it('every HIGH or CRITICAL authorize() outside the bus is classified', function () {
    // `File::permission` of every `->authorize($request, '<HIGH|CRITICAL>')` left in the controllers: each is a read, or a check in
    // front of a command whose bus asks for the step-up (and the second person) itself. A write that does its work without the
    // bus uses authorizeAction(). A new HIGH authorize() fails here until somebody decides which of the three it is.
    $classified = [
        'Api/V1/MeController.php::api_token.manage' => 'read: GET /v1/tokens (creating one is ApiTokenCommand)',
        'Api/V1/OrganizationController.php::organization.members.manage' => 'bus: resolve() in front of OrganizationCommand (invite, cancel, role, remove)',
        'Api/V1/ServiceController.php::service.delete' => 'bus: resolve() in front of WithdrawalCommand (the withdrawal)',
        'Api/V1/RewardsController.php::staff.customer.manage' => 'read: campaigns, rewards, the forecast POST that stores nothing',
        'Api/V1/ServiceAccessController.php::organization.members.manage' => 'read: index (store/destroy are ServiceAccessCommand)',
        'Api/V1/Staff/ComplianceController.php::abuse.case.manage' => 'read: abuse case lists',
        'Api/V1/Staff/ComplianceController.php::compliance.case.manage' => 'read: compliance case lists',
        'Api/V1/Staff/ComplianceController.php::security.incident.manage' => 'read: cyber incidents',
        'Api/V1/Staff/IncidentController.php::maintenance.manage' => 'read: maintenance list',
        'Api/V1/Staff/IncidentController.php::sla.credit.manage' => 'read: SLA credit preview',
        'Api/V1/Staff/MarketplaceController.php::partner.manage' => 'read: marketplace listings',
        'Api/V1/Staff/PartnerController.php::partner.manage' => 'read: partner lists',
        'Api/V1/Staff/PricingController.php::catalog.manage' => 'read: pricing views; the catalogue write is CatalogCommand',
        'Api/V1/Staff/RegistrarController.php::domain.registrar.manage' => 'read: connections; the connection write is RegistrarConnectionCommand',
        'Api/V1/Staff/ReportController.php::billing.dunning.manage' => 'read: GET /v1/staff/dunning (the run is authorizeAction)',
        'Api/V1/Staff/CustomerController.php::billing.limit_raise.waive' => 'bus: StaffCustomerCommand (CRITICAL, four-eyes)',
    ];
    $root = app_path('Http/Controllers');
    $found = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        // `->authorize(` itself and every `->resolve…(` helper that hands its literal to authorize() (round 1: a HIGH permission
        // behind a helper was never classified); the arguments are matched with their parentheses balanced
        preg_match_all('/(?:->authorize|->resolve[A-Za-z]*)(\((?:[^()]++|(?1))*\))/', (string) file_get_contents($file->getPathname()), $calls);
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        foreach ($calls[1] as $arguments) {
            preg_match_all("/'([a-z_]+(?:\\.[a-z_]+)+)'/", $arguments, $keys);
            foreach ($keys[1] as $permission) {
                if (in_array($permission, PermissionCatalog::keys(), true) && PermissionCatalog::risk($permission) !== PermissionCatalog::NORMAL) {
                    $found[$relative.'::'.$permission] = true;
                }
            }
        }
    }
    $found = array_keys($found);
    sort($found);
    $expected = array_keys($classified);
    sort($expected);
    expect($found)->toBe($expected);

    foreach (['Api/V1/Staff/ReportController.php', 'Api/V1/Staff/ProvisioningController.php', 'Api/V1/WebToolsController.php'] as $writer) {
        expect((string) file_get_contents($root.'/'.$writer))->toContain('authorizeAction(');
    }
});
