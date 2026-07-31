<?php

declare(strict_types=1);

namespace App\GraphQL;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Customer\Models\Customer;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Shared\Support\MoneyFormatter;
use App\Models\User;
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
    public function __construct(private readonly CreditLedger $ledger) {}

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
                'id'       => ['type' => Type::int()],
                'name'     => ['type' => Type::string()],
                'email'    => ['type' => Type::string()],
                'locale'   => ['type' => Type::string()],
                'customer' => ['type' => $customer],
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
            ],
        ]);

        return new Schema(['query' => $query]);
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
            'id'       => $user->id,
            'name'     => $user->name,
            'email'    => $user->email,
            'locale'   => $user->locale,
            'customer' => $customer === null ? null : [
                'type'              => $customer->type,
                'companyName'       => $customer->company_name,
                'preferredCurrency' => $customer->preferred_currency->value,
                'countryCode'       => $customer->country_code,
            ],
        ];
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
}
