<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Services\InvoicePdfService;
use App\Domains\Customer\Models\Customer;
use App\Domains\Reseller\Models\ResellerProfile;
use App\Models\User;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** @return array{user: User, profile: ResellerProfile} */
function resellerSetup(array $branding = []): array
{
    Role::findOrCreate('admin', 'web');
    Permission::findOrCreate('access-reseller', 'web');

    $user    = User::factory()->create();
    $profile = ResellerProfile::factory()->active()->create([
        'user_id'  => $user->id,
        'branding' => $branding ?: null,
    ]);

    $user->givePermissionTo('access-reseller');

    return ['user' => $user, 'profile' => $profile];
}

// ── Branding update (invoice fields) ─────────────────────────────────────────

it('reseller can save invoice branding fields', function (): void {
    ['user' => $user, 'profile' => $profile] = resellerSetup();

    $this->actingAs($user)
        ->put(route('reseller.branding.update'), [
            'company_name'         => 'MůjHosting s.r.o.',
            'primary_color'        => '#FF5500',
            'invoice_company_name' => 'MůjHosting Fakturace s.r.o.',
            'invoice_email'        => 'billing@mujhosting.cz',
            'invoice_website'      => 'mujhosting.cz',
            'invoice_street'       => 'Technická 1',
            'invoice_zip'          => '160 00',
            'invoice_city'         => 'Praha 6',
            'invoice_ic'           => '12345678',
            'invoice_dic'          => 'CZ12345678',
            'invoice_bank_account' => '123456789/0800',
            'invoice_footer_note'  => 'Faktura je splatná do 14 dnů.',
        ])
        ->assertRedirect();

    $saved = $profile->fresh()->branding;

    expect($saved['invoice_company_name'])->toBe('MůjHosting Fakturace s.r.o.')
        ->and($saved['invoice_email'])->toBe('billing@mujhosting.cz')
        ->and($saved['invoice_website'])->toBe('mujhosting.cz')
        ->and($saved['invoice_street'])->toBe('Technická 1')
        ->and($saved['invoice_zip'])->toBe('160 00')
        ->and($saved['invoice_city'])->toBe('Praha 6')
        ->and($saved['invoice_ic'])->toBe('12345678')
        ->and($saved['invoice_dic'])->toBe('CZ12345678')
        ->and($saved['invoice_bank_account'])->toBe('123456789/0800')
        ->and($saved['invoice_footer_note'])->toBe('Faktura je splatná do 14 dnů.');
});

it('invoice branding update validates email format', function (): void {
    ['user' => $user] = resellerSetup();

    $this->actingAs($user)
        ->put(route('reseller.branding.update'), [
            'invoice_email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('invoice_email');
});

it('invoice branding update validates primary_color hex format', function (): void {
    ['user' => $user] = resellerSetup();

    $this->actingAs($user)
        ->put(route('reseller.branding.update'), [
            'primary_color' => 'red',
        ])
        ->assertSessionHasErrors('primary_color');
});

// ── InvoicePdfService brand resolution ───────────────────────────────────────

it('InvoicePdfService resolves empty brand for customer without reseller', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    ['order' => $order] = placeOrder($user);
    $invoice = Invoice::where('order_id', $order->id)->first();

    // Use reflection to test the private resolveBrand method
    $service = new InvoicePdfService();
    $reflect = new ReflectionClass($service);
    $method  = $reflect->getMethod('resolveBrand');
    $method->setAccessible(true);

    $invoice->load(['items', 'customer.reseller']);
    $brand = $method->invoke($service, $invoice);

    expect($brand)->toBe([]);
});

it('InvoicePdfService resolves brand from reseller when customer has reseller_id', function (): void {
    ['profile' => $profile] = resellerSetup([
        'invoice_company_name' => 'Reseller Corp',
        'invoice_email'        => 'invoice@reseller.cz',
        'invoice_ic'           => '99887766',
        'primary_color'        => '#FF0000',
    ]);

    // Create a customer linked to this reseller
    $subUser    = customerUser();
    $customer   = $subUser->customer;
    $customer->update(['reseller_id' => $profile->id]);

    ['order' => $order] = placeOrder($subUser);
    $invoice = Invoice::where('order_id', $order->id)->first();

    $service = new InvoicePdfService();
    $reflect = new ReflectionClass($service);
    $method  = $reflect->getMethod('resolveBrand');
    $method->setAccessible(true);

    $invoice->load(['items', 'customer.reseller']);
    $brand = $method->invoke($service, $invoice);

    expect($brand['name'])->toBe('Reseller Corp')
        ->and($brand['email'])->toBe('invoice@reseller.cz')
        ->and($brand['ic'])->toBe('99887766')
        ->and($brand['primary_color'])->toBe('#FF0000');
});

it('InvoicePdfService brand falls back to business_name when invoice_company_name not set', function (): void {
    ['profile' => $profile] = resellerSetup([
        'company_name' => 'MůjHosting',
        // no invoice_company_name
    ]);

    $subUser  = customerUser();
    $customer = $subUser->customer;
    $customer->update(['reseller_id' => $profile->id]);

    ['order' => $order] = placeOrder($subUser);
    $invoice = Invoice::where('order_id', $order->id)->first();
    $invoice->load(['items', 'customer.reseller']);

    $service = new InvoicePdfService();
    $reflect = new ReflectionClass($service);
    $method  = $reflect->getMethod('resolveBrand');
    $method->setAccessible(true);

    $brand = $method->invoke($service, $invoice);

    expect($brand['name'])->toBe('MůjHosting');
});

// ── Branding form view ─────────────────────────────────────────────────────────

it('branding form shows invoice fields', function (): void {
    ['user' => $user, 'profile' => $profile] = resellerSetup([
        'invoice_bank_account' => '111222333/0100',
        'invoice_footer_note'  => 'Test patička',
    ]);

    $this->actingAs($user)
        ->get(route('reseller.branding.show'))
        ->assertOk()
        ->assertSee('invoice_bank_account')
        ->assertSee('111222333/0100')
        ->assertSee('invoice_footer_note')
        ->assertSee('Test patička');
});

it('non-reseller cannot access branding page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('reseller.branding.show'))
        ->assertStatus(403);
});

// ── Branding page renders saved fields ────────────────────────────────────────

it('reseller can update only some invoice fields without losing others', function (): void {
    ['user' => $user, 'profile' => $profile] = resellerSetup([
        'invoice_email' => 'existing@host.cz',
        'invoice_ic'    => '11111111',
    ]);

    $this->actingAs($user)
        ->put(route('reseller.branding.update'), [
            'invoice_city' => 'Brno',
        ])
        ->assertRedirect();

    $saved = $profile->fresh()->branding;

    expect($saved['invoice_email'])->toBe('existing@host.cz')
        ->and($saved['invoice_ic'])->toBe('11111111')
        ->and($saved['invoice_city'])->toBe('Brno');
});
