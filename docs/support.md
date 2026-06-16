# Support tickets

Fully functional (local module, no external helpdesk).

## Data model

- `support_tickets` — uuid, customer, status (open/pending/answered/closed),
  priority (low/normal/high/urgent), optional department, optional morph
  link (`related`) to a service/domain/order/invoice, assignee placeholder.
- `support_ticket_messages` — thread (customer + staff flag).
- `support_ticket_events` — created/replied/status_changed/priority_changed.

## Flow

All transitions go through `App\Domains\Support\Services\TicketService`
(controllers stay thin) and write BOTH a ticket event row and a Spatie
audit entry (`support.*`).

- Customer (`/panel/podpora`): create, list, view, reply. Customer replies
  reopen the ticket (status → open).
- Admin (`/admin/podpora`): list + status filter, view, staff reply
  (status → answered), change status/priority. Assignee management is a
  placeholder.

## Authorization

`SupportTicketPolicy` — customers see only their own tickets;
admin + support roles bypass via `before()`.
