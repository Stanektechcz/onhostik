<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Identity\StepUp\StepUpService;
use Onhost\Domain\Notifications\Models\MailOutbox;
use Onhost\Domain\Notifications\Models\Notification;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\TicketService;
use Onhost\Domain\Support\TicketStateMachine;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Outbox\OutboxPublisher;
use Tests\TestCase;

/*
 * TASK-0039 — permission program P0-14 (IF-16, audit SS-4/PA-06): the staff single sign-on into a customer's hosting panel is a
 * bus command, `PanelLoginCommand`, and it is bound to what the catalogue always claimed ("ticket-bound").
 *
 * It was a GET that asked for `staff.console` globally and a fresh step-up, and took an optional reason: any support engineer
 * could open any customer's panel — every site, database and mailbox of the client — without a ticket, a reason, or the
 * customer ever learning of it. Now it takes an OPEN ticket the customer opened about THAT service in the portal (never a
 * staff-opened or e-mailed one: the classic social-engineering vector, program D7), a reason of at least ten characters, the
 * console of the service's family (StaffActor::CONSOLE_FAMILIES until S2-01), and — without the customer's consent recorded on
 * the ticket — a second person, or the sole approver's time lock. The customer is told at once (`service.staff_panel_login`).
 */

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $function = (string) parse_url($request->url(), PHP_URL_QUERY);
        $ok = fn ($response) => Http::response(['code' => 'ok', 'message' => '', 'response' => $response]);

        return match ($function) {
            'login' => $ok('SESSION-spl-4c2b'),
            'client_get' => $ok(['client_id' => 3, 'username' => 'client3']),
            'client_login_get' => $ok('https://isp.test:8080/login/?otp=SPL-ONE-TIME-7a1c'),
            default => $ok([]),
        };
    });
});

/** A ticket about the service, as the customer opens it in the portal — or as `$o` says otherwise. @param array{staff?:bool, channel?:string, service?:?Service, state?:string, consent?:?User} $o */
function splTicket(Organization $organization, User $author, Service $service, array $o = []): Ticket
{
    $ticket = app(TicketService::class)->create(['subject' => 'Web nejde', 'body' => 'Po aktualizaci pluginu padá web na chybu 500.', 'service_id' => array_key_exists('service', $o) ? $o['service']?->id : $service->id, 'channel' => $o['channel'] ?? 'portal'],
        new CommandContext('user', $author->id, $organization->id), $organization, $author, (bool) ($o['staff'] ?? false));
    $patch = isset($o['state']) ? ['state' => $o['state']] : [];
    if (isset($o['consent'])) {
        $patch['meta'] = (array) $ticket->meta + ['support_access' => ['level' => 'console', 'granted_by' => $o['consent']->id, 'granted_at' => now()->toIso8601String(), 'until' => now()->addDays(3)->toIso8601String()]];
    }
    if ($patch !== []) {
        $ticket->forceFill($patch)->save();
    }

    return $ticket->fresh();
}

function splLogin(TestCase $test, Service $service, array $body): TestResponse
{
    return $test->postJson("/v1/staff/services/{$service->id}/panel-login", $body);
}

it('refuses the sign-on without an open ticket the customer opened in the portal about this very service', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $other = featureWebService($org, 'aapanel');
    $staff = $this->steppedUpStaff('shared_hosting_admin');
    $reason = 'Zákazník hlásí chybu 500 po aktualizaci, kontrola logů v panelu.';
    $refused = fn (?Ticket $ticket) => expect(splLogin($this->actingAs($staff, 'sanctum'), $service, ['reason' => $reason] + ($ticket ? ['ticket_id' => $ticket->number] : []))->assertStatus(422)->json('error'))->toBe('support_ticket_required');

    $refused(null);
    $refused(splTicket($org, $owner, $service, ['service' => $other]));                 // about another service
    $refused(splTicket($org, $owner, $service, ['service' => null]));                   // about no service
    $refused(splTicket($org, $staff, $service, ['staff' => true]));                     // opened by staff: self-satisfiable
    $refused(splTicket($org, $owner, $service, ['channel' => 'email']));                // an e-mail claim is not the portal
    $refused(splTicket($org, $owner, $service, ['state' => TicketStateMachine::RESOLVED])); // no longer open

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'client_login_get')); // nothing was asked of the panel
});

it('asks for a real reason and for the console of the service\'s family', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $ticket = splTicket($org, $owner, $service);

    $staff = $this->steppedUpStaff('shared_hosting_admin');
    splLogin($this->actingAs($staff, 'sanctum'), $service, ['ticket_id' => $ticket->id, 'reason' => 'kontrola'])->assertStatus(422)->assertJsonPath('error', 'reason_required');

    $game = $this->steppedUpStaff('game_admin'); // holds staff.console — for game servers
    splLogin($this->actingAs($game, 'sanctum'), $service, ['ticket_id' => $ticket->id, 'reason' => 'Kontrola logů po chybě 500 z tiketu.'])->assertForbidden()->assertJsonPath('error', 'staff_family_mismatch');

    $l1 = $this->steppedUpStaff('support_l1'); // no console at all
    splLogin($this->actingAs($l1, 'sanctum'), $service, ['ticket_id' => $ticket->id, 'reason' => 'Kontrola logů po chybě 500 z tiketu.'])->assertForbidden();

    $this->actingAs($owner, 'sanctum'); // and never the customer, whatever they hold
    splLogin($this, $service, ['ticket_id' => $ticket->id, 'reason' => 'Kontrola logů po chybě 500 z tiketu.'])->assertForbidden();
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'client_login_get'));
});

it('takes a second person without the customer\'s consent, and tells the customer at once', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $ticket = splTicket($org, $owner, $service);
    $staff = $this->steppedUpStaff('shared_hosting_admin', ['name' => 'Jana Podpora']);
    $body = ['ticket_id' => $ticket->number, 'reason' => 'Zákazník hlásí chybu 500 po aktualizaci, kontrola logů v panelu.'];

    $approval = (string) splLogin($this->actingAs($staff, 'sanctum'), $service, $body)->assertForbidden()->assertJsonPath('error', 'approval_required')->json('approval_id');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'client_login_get'));

    $signOn = splLogin($this, $service, $body + ['approval_ids' => [secondPersonApproves($approval)]])->assertOk();
    expect($signOn->json('data.url'))->toBe('https://isp.test:8080/login/?otp=SPL-ONE-TIME-7a1c')->and($signOn->json('data.consented'))->toBeFalse()
        ->and($signOn->json('data.ticket_number'))->toBe($ticket->number);
    // the one-time link is for the person who asked, never for a record
    expect(json_encode([DB::table('audit_events')->get(), DB::table('outbox_messages')->get(), DB::table('idempotency_keys')->get()]))->not->toContain('SPL-ONE-TIME-7a1c');

    app(OutboxPublisher::class)->relayPending();
    $notice = Notification::query()->where('audience', 'customer')->where('organization_id', $org->id)->where('event', 'service.staff_panel_login')->first();
    expect($notice)->not->toBeNull()->and($notice->title)->toContain('shop.cz')->and((string) $notice->body)->toContain($ticket->number)->toContain('Jana Podpora');
    expect(MailOutbox::query()->where('organization_id', $org->id)->where('template_key', 'staff-panel-login')->exists())->toBeTrue();
    expect(DB::table('audit_events')->where('action', 'staff.panel_login')->where('resource_id', $service->id)->where('result', 'succeeded')->exists())->toBeTrue();
});

it('lets the sole approver sign on only after the time lock', function () {
    config(['onhost.identity.four_eyes' => false]);
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $ticket = splTicket($org, $owner, $service);
    $solo = $this->steppedUpStaff('platform_owner');
    $this->actingAs($solo, 'sanctum');

    $signOn = $this->soloAfterTimeLock($solo, fn () => splLogin($this, $service, ['ticket_id' => $ticket->id, 'reason' => 'Zákazník hlásí chybu 500 po aktualizaci, kontrola logů.']));
    $signOn->assertOk()->assertJsonPath('data.url', 'https://isp.test:8080/login/?otp=SPL-ONE-TIME-7a1c');
});

it('needs only the step-up when the customer consented on the ticket, and never answers a token', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'ispconfig');
    $ticket = splTicket($org, $owner, $service, ['consent' => $owner]);
    $staff = $this->staff('shared_hosting_admin');
    $body = ['ticket_id' => $ticket->id, 'reason' => 'Zákazník hlásí chybu 500 po aktualizaci, kontrola logů v panelu.'];

    splLogin($this->actingAs($staff, 'sanctum'), $service, $body)->assertForbidden()->assertJsonPath('error', 'step_up_required');
    app(StepUpService::class)->grant($staff, 'totp', null, '127.0.0.1');
    splLogin($this, $service, $body)->assertOk()->assertJsonPath('data.consented', true);

    // a consent written by somebody who is not (or no longer) a member counts for nothing
    $stranger = User::factory()->create();
    $forged = splTicket($org, $owner, $service, ['consent' => $stranger]);
    splLogin($this, $service, ['ticket_id' => $forged->id] + $body)->assertForbidden()->assertJsonPath('error', 'approval_required');

    // staff routes are not for tokens
    app('auth')->forgetGuards();
    $token = $staff->createToken('spl', TokenScopes::ALL);
    $this->withToken($token->plainTextToken)->postJson("/v1/staff/services/{$service->id}/panel-login", $body)->assertForbidden();
});
