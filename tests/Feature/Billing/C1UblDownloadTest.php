<?php

declare(strict_types=1);

use App\Console\Commands\Doctor;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LegalEntitySeeder;
use Database\Seeders\TaxRuleSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Invoicing\Models\Invoice;
use Onhost\Domain\Orders\CheckoutService;
use Onhost\Domain\Orders\QuoteService;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationMembership;
use Onhost\Platform\Audit\AuditEvent;
use Onhost\Platform\Commands\CommandContext;

/*
 * C1 (decision 2026-10-15): the customer downloads the UBL 2.1 (EN 16931 / Peppol BIS 3.0) form of an issued document; a read
 * (LOW), audited, tenant-scoped. The doctor warns when the SK eFaktúra mandatory date approaches without a Peppol access point.
 */

beforeEach(function () {
    $this->seed([CatalogSeeder::class, TaxRuleSeeder::class, LegalEntitySeeder::class]);
    Http::fake();
    $this->withoutMiddleware(ThrottleRequests::class);
});

function c1IssuedInvoice(User $owner, Organization $org): Invoice
{
    $ctx = new CommandContext('user', $owner->id, $org->id, null, '127.0.0.1', 'pest', 'c1-session');
    $consents = ['terms' => ['version' => '4.0'], 'privacy' => ['version' => '4.0'], 'withdrawal_waiver' => ['version' => '4.0'], 'dpa' => ['version' => '4.0'], 'sla' => ['version' => '4.0']];
    $quote = app(QuoteService::class)->quote([['product_key' => 'web-hosting', 'plan_key' => 'standard']], 'CZK', ['country' => 'CZ', 'customer_class' => 'b2c'], 12, null, $org);
    $order = app(CheckoutService::class)->placeOrder($quote, $org, $owner, $consents, ['mode' => 'bank'], 'c1:q1', $ctx)['order'];

    return Invoice::query()->findOrFail($order->invoice_id);
}

/** @return array<string, mixed> */
function c1DoctorRow(): array
{
    Artisan::call('onhost:doctor', ['--json' => true]);

    return (array) collect(json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR)['checks'])->firstWhere('check', 'e-invoice (SK eFaktúra / Peppol) delivery');
}

it('lets the owner download the UBL form of an issued invoice and audits the download', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $invoice = c1IssuedInvoice($owner, $org);
    $this->actingAs($owner, 'sanctum');

    $response = $this->get('/v1/invoices/'.$invoice->id.'/ubl')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    expect((string) $response->headers->get('Content-Disposition'))->toContain('attachment')->toContain('.xml')
        ->and($response->getContent())->toContain('urn:fdc:peppol.eu:2017:poacc:billing:3.0')->toContain((string) $invoice->number);
    expect(AuditEvent::query()->where('action', 'invoice.download')->where('resource_id',$invoice->id)->exists())->toBeTrue();
});

it('answers 403 to a member of the organization without the invoice permission and 404 to another tenant', function () {
    [$owner, $org] = $this->customerWithOrganization();
    $invoice = c1IssuedInvoice($owner, $org);
    $guest = User::query()->create(['email' => 'c1-guest@ubl.test', 'name' => 'Host', 'password' => 'Correct-Horse-Battery-9', 'state' => 'active']);
    OrganizationMembership::query()->create(['organization_id' => $org->id, 'user_id' => $guest->id, 'state' => 'active', 'role_key' => 'guest', 'joined_at' => now()]);
    PolicyBinding::query()->create(['principal_type' => 'user', 'principal_id' => $guest->id, 'role_key' => 'guest', 'scope_type' => 'organization', 'scope_id' => $org->id, 'organization_id' => $org->id]);
    [$stranger] = $this->customerWithOrganization();

    $this->actingAs($guest, 'sanctum');
    $this->getJson('/v1/invoices/'.$invoice->id.'/ubl')->assertForbidden();
    app('auth')->forgetGuards();

    $this->actingAs($stranger, 'sanctum');
    $this->getJson('/v1/invoices/'.$invoice->id.'/ubl')->assertNotFound(); // no oracle for a sequential number (TASK-0098)
    $this->getJson('/v1/invoices/'.$invoice->number.'/ubl')->assertNotFound();
});

it('warns in the doctor when the SK eFaktúra date approaches without Peppol, never blocking', function () {
    config(['onhost.einvoice.sk_mandatory_from' => now()->addDays(Doctor::EINVOICE_WARN_DAYS - 10)->toDateString(), 'onhost.einvoice.peppol.base_url' => null, 'onhost.einvoice.peppol.sender_id' => null]);
    $row = c1DoctorRow();
    expect($row['status'])->toBe('WARN')->and($row['detail'])->toContain('no Peppol access point')->and($row['remedy'])->toContain('PEPPOL_BASE_URL');
});

it('reports OK when Peppol is configured or the SK date is still far away', function () {
    config(['onhost.einvoice.sk_mandatory_from' => now()->addDays(Doctor::EINVOICE_WARN_DAYS + 30)->toDateString(), 'onhost.einvoice.peppol.base_url' => null, 'onhost.einvoice.peppol.sender_id' => null]);
    expect(c1DoctorRow()['status'])->toBe('OK');

    config(['onhost.einvoice.sk_mandatory_from' => now()->subDay()->toDateString(), 'onhost.einvoice.peppol.base_url' => 'https://ap.example.test', 'onhost.einvoice.peppol.sender_id' => '9946:SK2020000000']);
    $row = c1DoctorRow();
    expect($row['status'])->toBe('OK')->and($row['detail'])->not->toContain('ap.example.test')->not->toContain('SK2020000000');
});
