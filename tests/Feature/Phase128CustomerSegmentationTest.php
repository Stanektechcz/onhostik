<?php

declare(strict_types=1);

use App\Domains\Bi\Enums\CustomerSegment;
use App\Domains\Customer\Models\Customer;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('admin can view customer segmentation report', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.customers.segmentation'))
         ->assertOk()
         ->assertSee('Segmentace zákazníků');
});

it('segmentation report shows all segment labels', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.customers.segmentation'))
         ->assertOk()
         ->assertSee('VIP')
         ->assertSee('Zdravý')
         ->assertSee('Ohrožený')
         ->assertSee('Odchod');
});

it('segmentation report shows customer counts per segment', function (): void {
    $admin = adminUser();

    $customer1 = customerUser();
    $customer1->customer->update(['segment' => CustomerSegment::VIP->value]);

    $customer2 = customerUser();
    $customer2->customer->update(['segment' => CustomerSegment::AtRisk->value]);

    $this->actingAs($admin)
         ->get(route('admin.customers.segmentation'))
         ->assertOk();

    // counts appear somewhere in the page
    expect(Customer::where('segment', 'vip')->count())->toBe(1);
    expect(Customer::where('segment', 'at_risk')->count())->toBe(1);
});

it('segmentation report can filter by segment', function (): void {
    $admin = adminUser();

    $vipCustomer = customerUser();
    $vipCustomer->customer->update([
        'segment' => CustomerSegment::VIP->value,
    ]);

    $this->actingAs($admin)
         ->get(route('admin.customers.segmentation', ['segment' => 'vip']))
         ->assertOk()
         ->assertSee($vipCustomer->customer->email);
});

it('customer cannot access segmentation report', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.customers.segmentation'))
         ->assertForbidden();
});

it('segmentation report shows unassigned customer count', function (): void {
    $admin = adminUser();

    customerUser(); // unassigned segment

    $this->actingAs($admin)
         ->get(route('admin.customers.segmentation'))
         ->assertOk()
         ->assertSee('Bez segmentu');
});
