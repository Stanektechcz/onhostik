<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\CreateOrderAction;
use App\Domains\Billing\Actions\IssueProformaInvoiceAction;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Customer\Models\Customer;
use App\Domains\Products\Models\PricingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Shared helpers (Phase 2 vertical-slice tests)
|--------------------------------------------------------------------------
*/

/** Creates a user with an attached Czech B2C customer profile. */
function customerUser(array $customerAttributes = []): User
{
    $user = User::factory()->create();
    Customer::factory()->for($user)->create($customerAttributes);

    return $user->load('customer');
}

/** Creates a user holding the admin role (access-admin gate). */
function adminUser(): User
{
    Role::findOrCreate('admin', 'web');

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

/**
 * Creates an order (+ proforma invoice) for the first seeded plan.
 * Catalog and mock server must already be seeded.
 *
 * @param  array<string, mixed>  $config
 * @return array{order: Order, invoice: Invoice}
 */
function placeOrder(User $user, array $config = []): array
{
    $customer = $user->customer ?? throw new RuntimeException('User has no customer profile.');
    $plan     = PricingPlan::query()->orderBy('sort_order')->firstOrFail();

    $order   = app(CreateOrderAction::class)->execute($customer, $plan, $config);
    $invoice = app(IssueProformaInvoiceAction::class)->execute($order);

    return ['order' => $order, 'invoice' => $invoice];
}
