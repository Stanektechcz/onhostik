<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Commands;

use Illuminate\Support\Carbon;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Identity\Authorization\RiskAwareCommand;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Domain\Support\Models\TicketMessage;
use Onhost\Platform\Commands\CommandScope;
use Onhost\Platform\Commands\OrganizationCommand;
use Throwable;

/**
 * Staff single sign-on into the customer's hosting panel (TASK-0039, permission program P0-14, IF-16; audit SS-4, PA-06).
 * payload: service_id, ticket_id (id or number), reason.
 *
 * It was a GET under a global `staff.console` with an optional reason: any support engineer opened any customer's panel —
 * every site, database and mailbox of the client — with no ticket and nobody telling the customer, although the catalogue
 * calls the permission "ticket-bound". Now, besides `staff.console` (HIGH, a fresh step-up), the handler asks for:
 *  · an OPEN ticket about THIS service that a current member of the organization opened in the portal or through the API —
 *    never one staff opened, never an e-mail (program D7: a staff-opened ticket would satisfy itself, an e-mail or a phone call
 *    is the classic social-engineering vector);
 *  · a reason of at least REASON_MIN characters;
 *  · the console of the service's family (StaffActor::CONSOLE_FAMILIES until family-scoped staff roles, S2-01).
 * Without the customer's consent on the ticket (`meta.support_access`, level console, given by a member who holds the service's
 * console) it takes a second person — or, for the sole approver, the time lock (IdentityCommandAuthorizer, TASK-0037). The
 * customer is told at once (`service.staff_panel_login`).
 */
final class PanelLoginCommand extends OrganizationCommand implements RiskAwareCommand
{
    public const PERMISSION = 'staff.console';

    public const REASON_MIN = 10;

    /** The channels a portal-authenticated person opens a ticket through (program D7): never e-mail, chat hand-over, AI or incident. */
    public const CUSTOMER_CHANNELS = ['portal', 'api'];

    public function scope(): CommandScope
    {
        $serviceId = $this->get('service_id');

        return is_string($serviceId) && $serviceId !== '' ? CommandScope::resource($serviceId, $this->organizationId) : CommandScope::organization($this->organizationId);
    }

    public function permission(): string
    {
        return self::PERMISSION;
    }

    public function name(): string
    {
        return 'service.staff_panel_login';
    }

    public function riskLevel(): string
    {
        return PermissionCatalog::HIGH;
    }

    public function requiresStepUp(): bool
    {
        return true;
    }

    /** A second person (or the sole approver's time lock) unless the customer consented on the ticket. */
    public function requiresApproval(): bool
    {
        $service = $this->service();
        $ticket = $service === null ? null : self::ticketFor($service, (string) $this->get('ticket_id', ''));

        return $ticket === null || ! self::consented($ticket, $service);
    }

    public function service(): ?Service
    {
        $id = $this->get('service_id');

        return is_string($id) && $id !== '' ? Service::query()->where('organization_id', $this->organizationId)->find($id) : null;
    }

    /**
     * The open ticket about `$service` that a current member of its organization opened in the portal or through the API, or null.
     * `$reference` is the ticket id or its customer-facing number (TK-2026-0001).
     */
    public static function ticketFor(Service $service, string $reference): ?Ticket
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }
        $ticket = Ticket::query()->where('organization_id', $service->organization_id)->where('service_id', $service->id)
            ->where(fn ($q) => $q->where('id', $reference)->orWhere('number', $reference))->first();
        if ($ticket === null || ! $ticket->isOpen() || ! in_array($ticket->channel, self::CUSTOMER_CHANNELS, true) || $ticket->user_id === null) {
            return null;
        }
        $first = TicketMessage::query()->where('ticket_id', $ticket->id)->orderBy('created_at')->orderBy('id')->first();
        if ($first === null || $first->author_type !== 'customer' || (string) $first->author_id !== (string) $ticket->user_id) {
            return null;
        }

        return self::currentMember((string) $ticket->user_id, (string) $service->organization_id) ? $ticket : null;
    }

    /**
     * The customer let support into this service's console on the ticket: `meta.support_access` with level `console`, not
     * expired, given by a current member who holds `service.console` on the service themselves (nobody lends what they do not
     * hold). Staff cannot write it: nothing on the staff side writes a ticket's meta (S2-02 brings the customer's form).
     */
    public static function consented(Ticket $ticket, Service $service): bool
    {
        $grant = data_get($ticket->meta, 'support_access');
        if (! is_array($grant) || ($grant['level'] ?? null) !== 'console' || ! is_string($grant['granted_by'] ?? null)) {
            return false;
        }
        try {
            if (isset($grant['until']) && Carbon::parse((string) $grant['until'])->isPast()) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }
        $granter = User::query()->find($grant['granted_by']);
        if ($granter === null || ! self::currentMember($granter->id, (string) $service->organization_id)) {
            return false;
        }
        $held = app(Authorizer::class)->customerPermissionsAt($granter, CommandScope::resource($service->id, $service->organization_id, $service->project_id));

        return in_array('service.console', $held, true);
    }

    private static function currentMember(string $userId, string $organizationId): bool
    {
        return OrganizationMembership::query()->where('organization_id', $organizationId)->where('user_id', $userId)->current()->exists();
    }
}
