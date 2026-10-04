<?php

declare(strict_types=1);

namespace Onhost\Domain\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Models\ServiceAccount;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Support\Models\Ticket;
use Onhost\Platform\Commands\CommandScope;

/**
 * Who of a customer organization reads (or answers) which of its tickets (permission program S1-09, D19; TASK-0043).
 *
 * The portal asked `support.ticket.read` at the organization and then showed every ticket: a member of one project read the
 * tickets about another project's services, anybody with the key read the billing tickets, and somebody whose role was given in
 * one project only (a guest of the organization with a project developer role) read none — not even the tickets about the
 * services they look after. Now a ticket is read at the scope of what it concerns:
 *
 *  - a ticket about a service: `support.ticket.read` and `service.read` at that service (an organization, project or shared-service
 *    binding covers it, as for every other read of the service);
 *  - a ticket about no service (a domain — domains are organization-wide, O7 — or the organization at large): the key at the
 *    organization;
 *  - a billing ticket (a topic of the billing queue, Triage): in addition `billing.invoice.read` at the organization — what it
 *    discusses are the invoices;
 *  - its author reads it while they read any ticket of the organization at all (a support contact keeps their own billing ticket).
 *
 * Whoever reads tickets and services organization-wide (owner, organization admin, billing admin, every organization preset with
 * the read keys) sees exactly what they saw; only the billing tickets of others leave a role without `billing.invoice.read` (the
 * support contact). The same rule answers a reply, a work offer and the assistant's listing (`support.ticket.write` for writes).
 */
final class TicketVisibility
{
    public const READ = 'support.ticket.read';

    public const WRITE = 'support.ticket.write';

    public function __construct(private readonly Authorizer $authorizer) {}

    /** The Triage topics of the billing queue: their tickets discuss invoices. @return list<string> */
    public static function billingCategories(): array
    {
        return array_keys(array_filter(Triage::TOPICS, fn (array $topic) => $topic['queue'] === 'billing'));
    }

    /** Whether the principal holds `$permission` anywhere in the organization — at it, a project of it or a service of it. */
    public function holdsAnywhere(Authenticatable|ServiceAccount $principal, string $organizationId, string $permission = self::READ): bool
    {
        return $this->authorizer->can($principal, $permission, CommandScope::organization($organizationId))
            || $this->authorizer->projectIdsWhere($principal, $permission, $organizationId) !== []
            || $this->authorizer->resourceIdsWhere($principal, $permission, $organizationId) !== [];
    }

    /** Whether the principal may read (or, with WRITE, answer) this ticket. */
    public function may(Authenticatable|ServiceAccount $principal, Ticket $ticket, string $permission = self::READ): bool
    {
        $organizationId = (string) $ticket->organization_id;
        if ($organizationId === '' || ! $this->holdsAnywhere($principal, $organizationId, $permission)) {
            return false;
        }
        if ($ticket->user_id !== null && (string) $ticket->user_id === (string) $principal->getAuthIdentifier()) {
            return true; // the author
        }
        $organization = CommandScope::organization($organizationId);
        if (in_array($ticket->category, self::billingCategories(), true) && ! $this->authorizer->can($principal, 'billing.invoice.read', $organization)) {
            return false;
        }
        $service = $ticket->service_id !== null ? Service::query()->withTrashed()->where('organization_id', $organizationId)->find($ticket->service_id) : null;
        if ($service === null) {
            return $this->authorizer->can($principal, $permission, $organization);
        }
        $scope = CommandScope::resource($service->id, $organizationId, $service->project_id);

        return $this->authorizer->can($principal, $permission, $scope) && $this->authorizer->can($principal, 'service.read', $scope);
    }

    /**
     * The organization's tickets the principal may read (or answer) — may() as one query. Whoever holds the key and `service.read`
     * organization-wide gets the organization's tickets unfiltered by service (a ticket of a service since removed stays theirs).
     *
     * @return Builder<Ticket>
     */
    public function query(Authenticatable|ServiceAccount $principal, string $organizationId, string $permission = self::READ): Builder
    {
        $organization = CommandScope::organization($organizationId);
        $ticketsHere = $this->authorizer->can($principal, $permission, $organization);
        $servicesHere = $this->authorizer->can($principal, 'service.read', $organization);
        $billing = $this->authorizer->can($principal, 'billing.invoice.read', $organization);
        $author = (string) $principal->getAuthIdentifier();

        $visible = function (Builder $query) use ($principal, $organizationId, $permission, $ticketsHere, $servicesHere, $billing): void {
            if (! ($ticketsHere && $servicesHere)) {
                $services = Service::query()->withTrashed()->where('organization_id', $organizationId)->select('id');
                $this->narrow($services, $principal, $organizationId, $permission, $ticketsHere);
                $this->narrow($services, $principal, $organizationId, 'service.read', $servicesHere);
                $query->where(fn (Builder $q) => $ticketsHere
                    ? $q->whereNull('service_id')->orWhereIn('service_id', $services)
                    : $q->whereIn('service_id', $services));
            }
            if (! $billing) {
                $query->where(fn (Builder $q) => $q->whereNull('category')->orWhereNotIn('category', self::billingCategories()));
            }
        };

        $tickets = Ticket::query()->where('organization_id', $organizationId);
        if ($ticketsHere && $servicesHere && $billing) {
            // nothing to narrow — and an empty nested where would vanish from the SQL, leaving only "the author's" behind
            return $tickets;
        }

        return $tickets->where(fn (Builder $q) => $q->where(fn (Builder $inner) => $visible($inner))->orWhere('user_id', $author));
    }

    /** Services of the organization where `$permission` is held by a project or a shared-service binding (unless held organization-wide). @param Builder<Service> $services */
    private function narrow(Builder $services, Authenticatable|ServiceAccount $principal, string $organizationId, string $permission, bool $organizationWide): void
    {
        if ($organizationWide) {
            return;
        }
        $projects = $this->authorizer->projectIdsWhere($principal, $permission, $organizationId);
        $resources = $this->authorizer->resourceIdsWhere($principal, $permission, $organizationId);
        $services->where(fn (Builder $q) => $q->whereIn('project_id', $projects)->orWhereIn('id', $resources));
    }
}
