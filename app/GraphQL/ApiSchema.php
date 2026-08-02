<?php

declare(strict_types=1);

namespace App\GraphQL;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use App\Domains\Loyalty\Services\LoyaltyPointsService;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\TicketService;
use App\Models\User;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * Read-only GraphQL schema over the customer-facing domain (services, invoices,
 * domains, credit). It mirrors the shape and scoping of the v1 REST endpoints
 * one-for-one — every query is scoped to the authenticated user's customer, so
 * a token can only ever see its own account. Authentication is handled upstream
 * by the `auth:sanctum` guard; the resolved User arrives here as the query
 * context.
 *
 * Deliberately query-only: writes stay on the REST API where idempotency keys,
 * per-token abilities and audit logging already live.
 */
final class ApiSchema
{
    public function __construct(
        private readonly CreditLedger $ledger,
        private readonly TicketService $tickets,
        private readonly LoyaltyPointsService $loyaltyPoints,
    ) {}

    public function make(): Schema
    {
        $customer = new ObjectType([
            'name'        => 'Customer',
            'description' => 'The billing account the viewer belongs to.',
            'fields'      => [
                'type'              => ['type' => Type::string()],
                'companyName'       => ['type' => Type::string()],
                'preferredCurrency' => ['type' => Type::string()],
                'countryCode'       => ['type' => Type::string()],
            ],
        ]);

        $viewer = new ObjectType([
            'name'        => 'Viewer',
            'description' => 'The authenticated user behind the API token.',
            'fields'      => [
                'id'            => ['type' => Type::int()],
                'name'          => ['type' => Type::string()],
                'email'         => ['type' => Type::string()],
                'locale'        => ['type' => Type::string()],
                'loyaltyPoints' => ['type' => Type::int()],
                'customer'      => ['type' => $customer],
            ],
        ]);

        $service = new ObjectType([
            'name'   => 'Service',
            'fields' => [
                'id'          => ['type' => Type::int()],
                'uuid'        => ['type' => Type::string()],
                'label'       => ['type' => Type::string()],
                'status'      => ['type' => Type::string()],
                'product'     => ['type' => Type::string()],
                'nextDueDate' => ['type' => Type::string()],
                'createdAt'   => ['type' => Type::string()],
            ],
        ]);

        $invoice = new ObjectType([
            'name'   => 'Invoice',
            'fields' => [
                'id'        => ['type' => Type::int()],
                'number'    => ['type' => Type::string()],
                'status'    => ['type' => Type::string()],
                'total'     => ['type' => Type::string()],
                'dueDate'   => ['type' => Type::string()],
                'paidAt'    => ['type' => Type::string()],
                'createdAt' => ['type' => Type::string()],
            ],
        ]);

        $domain = new ObjectType([
            'name'   => 'Domain',
            'fields' => [
                'id'           => ['type' => Type::int()],
                'domain'       => ['type' => Type::string()],
                'registrar'    => ['type' => Type::string()],
                'registeredAt' => ['type' => Type::string()],
                'expiresAt'    => ['type' => Type::string()],
                'autoRenew'    => ['type' => Type::boolean()],
                'nameservers'  => ['type' => Type::listOf(Type::string())],
            ],
        ]);

        $credit = new ObjectType([
            'name'   => 'CreditBalance',
            'fields' => [
                'amount'    => ['type' => Type::int()],
                'currency'  => ['type' => Type::string()],
                'formatted' => ['type' => Type::string()],
            ],
        ]);

        $ticket = new ObjectType([
            'name'   => 'Ticket',
            'fields' => [
                'id'        => ['type' => Type::int()],
                'subject'   => ['type' => Type::string()],
                'status'    => ['type' => Type::string()],
                'createdAt' => ['type' => Type::string()],
            ],
        ]);

        $order = new ObjectType([
            'name'   => 'Order',
            'fields' => [
                'id'        => ['type' => Type::int()],
                'status'    => ['type' => Type::string()],
                'total'     => ['type' => Type::string()],
                'createdAt' => ['type' => Type::string()],
            ],
        ]);

        $query = new ObjectType([
            'name'   => 'Query',
            'fields' => [
                'viewer' => [
                    'type'    => $viewer,
                    'resolve' => fn ($root, array $args, $ctx) => $this->viewer($this->user($ctx)),
                ],
                'services' => [
                    'type'    => Type::listOf($service),
                    'resolve' => fn ($root, array $args, $ctx) => $this->services($this->user($ctx)),
                ],
                'service' => [
                    'type'    => $service,
                    'args'    => ['id' => ['type' => Type::nonNull(Type::int())]],
                    'resolve' => fn ($root, array $args, $ctx) => $this->service($this->user($ctx), (int) $args['id']),
                ],
                'invoices' => [
                    'type'    => Type::listOf($invoice),
                    'resolve' => fn ($root, array $args, $ctx) => $this->invoices($this->user($ctx)),
                ],
                'domains' => [
                    'type'    => Type::listOf($domain),
                    'resolve' => fn ($root, array $args, $ctx) => $this->domains($this->user($ctx)),
                ],
                'creditBalance' => [
                    'type'    => $credit,
                    'resolve' => fn ($root, array $args, $ctx) => $this->creditBalance($this->user($ctx)),
                ],
                'tickets' => [
                    'type'    => Type::listOf($ticket),
                    'resolve' => fn ($root, array $args, $ctx) => $this->tickets($this->user($ctx)),
                ],
                'orders' => [
                    'type'    => Type::listOf($order),
                    'resolve' => fn ($root, array $args, $ctx) => $this->orders($this->user($ctx)),
                ],
            ],
        ]);

        $mutation = new ObjectType([
            'name'   => 'Mutation',
            'fields' => [
                'createTicket' => [
                    'type' => $ticket,
                    'args' => [
                        'subject'  => ['type' => Type::nonNull(Type::string())],
                        'message'  => ['type' => Type::nonNull(Type::string())],
                        'priority' => ['type' => Type::string()],
                    ],
                    'resolve' => fn ($root, array $args, $ctx) => $this->createTicket($this->user($ctx), $args),
                ],
                'replyTicket' => [
                    'type' => $ticket,
                    'args' => [
                        'ticketId' => ['type' => Type::nonNull(Type::int())],
                        'message'  => ['type' => Type::nonNull(Type::string())],
                    ],
                    'resolve' => fn ($root, array $args, $ctx) => $this->replyTicket($this->user($ctx), (int) $args['ticketId'], (string) $args['message']),
                ],
            ],
        ]);

        return new Schema(['query' => $query, 'mutation' => $mutation]);
    }

    private function user(mixed $ctx): ?User
    {
        return $ctx instanceof User ? $ctx : null;
    }

    private function customer(?User $user): ?Customer
    {
        return $user?->customer;
    }

    /** @return array<string, mixed>|null */
    private function viewer(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $customer = $user->customer;

        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'locale'        => $user->locale,
            'loyaltyPoints' => $customer === null ? 0 : $this->loyaltyPoints->balance($customer),
            'customer'      => $customer === null ? null : [
                'type'              => $customer->type,
                'companyName'       => $customer->company_name,
                'preferredCurrency' => $customer->preferred_currency->value,
                'countryCode'       => $customer->country_code,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function tickets(?User $user): array
    {
        $customer = $this->customer($user);

        if ($customer === null) {
            return [];
        }

        return SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (SupportTicket $t): array => $this->ticketRow($t))
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function orders(?User $user): array
    {
        $customer = $this->customer($user);

        if ($customer === null) {
            return [];
        }

        return Order::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Order $o): array => [
                'id'        => $o->id,
                'status'    => $o->status->value,
                'total'     => MoneyFormatter::format($o->total),
                'createdAt' => $o->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function services(?User $user): array
    {
        $customer = $this->customer($user);

        if ($customer === null) {
            return [];
        }

        return Service::query()
            ->where('customer_id', $customer->id)
            ->with('product')
            ->get()
            ->map(fn (Service $s): array => [
                'id'          => $s->id,
                'uuid'        => $s->uuid,
                'label'       => $s->label,
                'status'      => $s->status->value,
                'product'     => $s->product?->name,
                'nextDueDate' => $s->next_due_date?->toDateString(),
                'createdAt'   => $s->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function service(?User $user, int $id): ?array
    {
        $customer = $this->customer($user);

        if ($customer === null) {
            return null;
        }

        $service = Service::query()
            ->where('customer_id', $customer->id)
            ->where('id', $id)
            ->with('product')
            ->first();

        if ($service === null) {
            return null;
        }

        return [
            'id'          => $service->id,
            'uuid'        => $service->uuid,
            'label'       => $service->label,
            'status'      => $service->status->value,
            'product'     => $service->product?->name,
            'nextDueDate' => $service->next_due_date?->toDateString(),
            'createdAt'   => $service->created_at?->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function invoices(?User $user): array
    {
        $customer = $this->customer($user);

        if ($customer === null) {
            return [];
        }

        return Invoice::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Invoice $i): array => [
                'id'        => $i->id,
                'number'    => $i->number,
                'status'    => $i->status->value,
                'total'     => MoneyFormatter::format($i->total),
                'dueDate'   => $i->due_date?->toDateString(),
                'paidAt'    => $i->paid_at?->toIso8601String(),
                'createdAt' => $i->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function domains(?User $user): array
    {
        $customer = $this->customer($user);

        if ($customer === null) {
            return [];
        }

        return DomainRegistration::query()
            ->whereHas('service', fn ($q) => $q->where('customer_id', $customer->id))
            ->get()
            ->map(fn (DomainRegistration $d): array => [
                'id'           => $d->id,
                'domain'       => $d->fqdn(),
                'registrar'    => $d->registrar,
                'registeredAt' => $d->registered_at?->toDateString(),
                'expiresAt'    => $d->expires_at?->toDateString(),
                'autoRenew'    => $d->auto_renew,
                'nameservers'  => $d->nameservers,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function creditBalance(?User $user): ?array
    {
        $customer = $this->customer($user);

        if ($customer === null) {
            return null;
        }

        $balance = $this->ledger->getBalance($customer);

        return [
            'amount'    => $balance->getMinorAmount()->toInt(),
            'currency'  => $balance->getCurrency()->getCurrencyCode(),
            'formatted' => MoneyFormatter::format($balance),
        ];
    }

    // ── mutations ────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function createTicket(?User $user, array $args): array
    {
        $customer = $this->requireWriteTickets($user);

        $ticket = $this->tickets->open(
            $customer,
            $user,
            subject: (string) $args['subject'],
            message: (string) $args['message'],
            priority: TicketPriority::tryFrom((string) ($args['priority'] ?? '')) ?? TicketPriority::Normal,
        );

        return $this->ticketRow($ticket);
    }

    /** @return array<string, mixed> */
    private function replyTicket(?User $user, int $ticketId, string $message): array
    {
        $customer = $this->requireWriteTickets($user);

        $ticket = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->where('id', $ticketId)
            ->first();

        if ($ticket === null) {
            throw new Error('Tiket nebyl nalezen.');
        }

        $this->tickets->reply($ticket, $user, $message, isStaff: false);

        return $this->ticketRow($ticket->refresh());
    }

    /**
     * Authorise a write mutation: a valid token with the write:tickets ability
     * and a customer account. Throws a client-visible GraphQL error otherwise.
     */
    private function requireWriteTickets(?User $user): Customer
    {
        if ($user === null || ! $user->tokenCan('write:tickets')) {
            throw new Error('Token nemá oprávnění write:tickets.');
        }

        $customer = $user->customer;

        if ($customer === null) {
            throw new Error('Účet nemá zákaznický profil.');
        }

        return $customer;
    }

    /** @return array<string, mixed> */
    private function ticketRow(SupportTicket $ticket): array
    {
        return [
            'id'        => $ticket->id,
            'subject'   => $ticket->subject,
            'status'    => $ticket->status->value,
            'createdAt' => $ticket->created_at?->toIso8601String(),
        ];
    }
}
