<?php

declare(strict_types=1);

use Onhost\Domain\Identity\Authorization\TokenScopes;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Commands\CreateOrganizationCommand;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Support\Assistant\AssistantService;
use Onhost\Domain\Support\Commands\TicketCustomerCommand;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/*
 * G7 (TASK-0115): the bus decided a command on the person's role bindings alone. The token's scopes were asked only by the
 * HTTP layer (ApiController::dispatch → ApiContext::assertTokenScope), so a command dispatched from inside the platform on a
 * token's context — the assistant handing a conversation over to a person opens a ticket through the bus, not through a
 * controller — ran with every right of the person behind the token. A token without `tickets:write` opened a support ticket.
 * The bus now asks the token too: the scope the command's permission needs (TokenScopes, the one map), deny by default.
 */

/** A personal token of `$user` for `$org` with exactly `$scopes`, and the context the API builds for a request made with it. */
function tokenCommandContext(User $user, Organization $org, array $scopes): CommandContext
{
    $token = $user->createToken('g7-token-command', array_merge($scopes, ['org:'.$org->id]));
    $token->accessToken->forceFill(['organization_id' => $org->id])->save();

    return new CommandContext('user', $user->id, $org->id, null, '127.0.0.1', 'pest', 'token:'.$token->accessToken->getKey());
}

function tokenCommandTicket(Organization $org, string $key): TicketCustomerCommand
{
    return new TicketCustomerCommand($org->id, $key, ['op' => 'create', 'subject' => 'Token ticket', 'body' => 'Opened through the bus.']);
}

it('refuses a ticket to a token without tickets:write, whatever the person behind it may do', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $context = tokenCommandContext($owner, $org, [TokenScopes::SERVICES_READ]);

    expect(fn () => app(CommandBus::class)->dispatch(tokenCommandTicket($org, 'g7-ticket-1'), $context))
        ->toThrow(fn (DomainError $e) => expect($e->status)->toBe(403)->and($e->getMessage())->toContain(TokenScopes::TICKETS_WRITE));
    expect(Ticket::query()->where('organization_id', $org->id)->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'ticket.customer.create')->where('result', 'denied')->count())->toBe(1);
});

it('lets a token with tickets:write open the same ticket', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $context = tokenCommandContext($owner, $org, [TokenScopes::TICKETS_WRITE]);

    $result = (array) app(CommandBus::class)->dispatch(tokenCommandTicket($org, 'g7-ticket-2'), $context);

    expect(Ticket::query()->whereKey((string) $result['ticket_id'])->where('organization_id', $org->id)->exists())->toBeTrue();
});

it('does not let the assistant hand a token conversation over to a person when the token cannot write tickets', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $context = tokenCommandContext($owner, $org, [TokenScopes::SERVICES_READ]);

    $answer = app(AssistantService::class)->chat('Chci mluvit s člověkem', $org, $owner, 'g7-chat', $context, 'cs');

    expect($answer['handoff'])->toBeNull()
        ->and(Ticket::query()->where('organization_id', $org->id)->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'ticket.customer.handoff')->where('result', 'succeeded')->count())->toBe(0);
});

it('refuses a command that asks for no permission to a token: such a command is the portal\'s, never a token\'s', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $context = tokenCommandContext($owner, $org, TokenScopes::ALL);

    expect(fn () => app(CommandBus::class)->dispatch(new CreateOrganizationCommand('g7-org-1', ['name' => 'Token Org s.r.o.', 'type' => 'company', 'country' => 'CZ', 'currency' => 'CZK']), $context))
        ->toThrow(DomainError::class);
    expect(Organization::query()->where('name', 'Token Org s.r.o.')->exists())->toBeFalse();
});

it('keeps the portal session and the system untouched by the token rule', function () {
    [$owner, $org] = $this->customerWithOrganization();

    $result = (array) app(CommandBus::class)->dispatch(tokenCommandTicket($org, 'g7-ticket-3'), $this->contextFor($owner, $org));

    expect(Ticket::query()->whereKey((string) $result['ticket_id'])->exists())->toBeTrue();
});

it('refuses a revoked token on the bus as an unauthenticated principal', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $context = tokenCommandContext($owner, $org, [TokenScopes::TICKETS_WRITE]);
    $owner->tokens()->update(['revoked_at' => now()]);

    expect(fn () => app(CommandBus::class)->dispatch(tokenCommandTicket($org, 'g7-ticket-4'), $context))->toThrow(DomainError::class);
    expect(Ticket::query()->where('organization_id', $org->id)->count())->toBe(0);
});
