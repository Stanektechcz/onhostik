<?php

declare(strict_types=1);

use App\Domains\Customer\Models\Customer;
use App\Models\User;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function csvFile(string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv') . '.csv';
    file_put_contents($path, $content);
    return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
}

// ── Access ────────────────────────────────────────────────────────────────────

it('admin can view the import form', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.customers.import'))
         ->assertOk()
         ->assertSee('Nahrát CSV soubor');
});

it('customer cannot access the import form', function (): void {
    $user = customerUser();

    $this->actingAs($user)
         ->get(route('admin.customers.import'))
         ->assertForbidden();
});

// ── Successful import ─────────────────────────────────────────────────────────

it('admin can import customers from CSV', function (): void {
    $admin = adminUser();

    $csv = csvFile("name,email,company_name,country_code,type\nTest Import,import@example.com,,CZ,person\n");

    $this->actingAs($admin)
         ->post(route('admin.customers.import.store'), ['file' => $csv])
         ->assertOk()
         ->assertSee('Import dokončen');

    $user = User::where('email', 'import@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Test Import');

    $customer = Customer::where('email', 'import@example.com')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->country_code)->toBe('CZ');
});

it('import creates a customer with company_name for company type', function (): void {
    $admin = adminUser();

    $csv = csvFile("name,email,company_name,country_code,type\nAcme Support,acme@example.com,Acme s.r.o.,SK,company\n");

    $this->actingAs($admin)
         ->post(route('admin.customers.import.store'), ['file' => $csv])
         ->assertOk();

    $customer = Customer::where('email', 'acme@example.com')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->company_name)->toBe('Acme s.r.o.')
        ->and($customer->type)->toBe('company')
        ->and($customer->country_code)->toBe('SK');
});

it('import skips existing email addresses', function (): void {
    $admin    = adminUser();
    $existing = customerUser();

    $csv = csvFile("name,email\nDuplicate," . $existing->email . "\n");

    $response = $this->actingAs($admin)
         ->post(route('admin.customers.import.store'), ['file' => $csv])
         ->assertOk();

    $response->assertSee('přeskočeno');

    expect(User::where('email', $existing->email)->count())->toBe(1);
});

it('import handles multiple rows', function (): void {
    $admin = adminUser();

    $csv = csvFile(
        "name,email\n" .
        "User One,userone@example.com\n" .
        "User Two,usertwo@example.com\n" .
        "User Three,userthree@example.com\n"
    );

    $this->actingAs($admin)
         ->post(route('admin.customers.import.store'), ['file' => $csv])
         ->assertOk()
         ->assertSee('3 importováno');
});

// ── Validation ────────────────────────────────────────────────────────────────

it('import rejects missing file', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->post(route('admin.customers.import.store'), [])
         ->assertSessionHasErrors('file');
});

it('import rejects CSV missing name/email headers', function (): void {
    $admin = adminUser();

    $csv = csvFile("foo,bar\nvalue1,value2\n");

    $this->actingAs($admin)
         ->post(route('admin.customers.import.store'), ['file' => $csv])
         ->assertRedirect()
         ->assertSessionHasErrors('file');
});

it('import skips rows with invalid email', function (): void {
    $admin = adminUser();

    $csv = csvFile("name,email\nBad User,not-an-email\n");

    $this->actingAs($admin)
         ->post(route('admin.customers.import.store'), ['file' => $csv])
         ->assertOk()
         ->assertSee('0 importováno');
});

it('import skips rows with empty name or email', function (): void {
    $admin = adminUser();

    $csv = csvFile("name,email\n,missing@example.com\nNo Email,\n");

    $this->actingAs($admin)
         ->post(route('admin.customers.import.store'), ['file' => $csv])
         ->assertOk()
         ->assertSee('0 importováno');
});
