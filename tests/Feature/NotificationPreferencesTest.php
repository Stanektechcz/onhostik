<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceSeries;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\InvoiceType;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Customer\Models\Customer;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\DomainExpiringNotification;
use App\Notifications\InvoiceIssuedNotification;
use App\Notifications\InvoicePaidNotification;
use App\Notifications\MonitorDownNotification;
use App\Notifications\PaymentOverdueNotification;
use App\Notifications\RenewalReminderNotification;
use App\Notifications\TicketRepliedNotification;
use Brick\Money\Money;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

/** Creates a minimal paid invoice for notification tests. */
function notifTestInvoice(Customer $customer): Invoice
{
    static $seq = 0;
    $seq++;

    return Invoice::create([
        'customer_id'                  => $customer->id,
        'type'                         => InvoiceType::Invoice,
        'purpose'                      => 'order',
        'series'                       => InvoiceSeries::Czech->value,
        'number'                       => "CZ-TEST-{$seq}",
        'status'                       => InvoiceStatus::Paid,
        'vat_scenario'                 => VatScenario::CzechB2C,
        'currency'                     => 'CZK',
        'subtotal'                     => Money::ofMinor(8264, 'CZK'),
        'tax_amount'                   => Money::ofMinor(1736, 'CZK'),
        'total'                        => Money::ofMinor(10000, 'CZK'),
        'variable_symbol'              => "20260{$seq}",
        'issue_date'                   => now()->toDateString(),
        'taxable_supply_date'          => now()->toDateString(),
        'due_date'                     => now()->toDateString(),
        'paid_at'                      => now(),
        'snapshot_name'                => 'Test',
        'snapshot_company'             => 'Test s.r.o.',
        'snapshot_street'              => 'Testovací 1',
        'snapshot_city'                => 'Praha',
        'snapshot_zip'                 => '11000',
        'snapshot_country_code'        => 'CZ',
        'snapshot_registration_number' => '12345678',
    ]);
}

// ── User::wantsNotification() ─────────────────────────────────────────────────

it('wantsNotification returns true when no preferences set', function (): void {
    $user = User::factory()->create(['notification_preferences' => null]);

    expect($user->wantsNotification('invoice', 'mail'))->toBeTrue()
        ->and($user->wantsNotification('payment', 'database'))->toBeTrue();
});

it('wantsNotification returns false when key is in opt-out list for channel', function (): void {
    $user = User::factory()->create([
        'notification_preferences' => ['mail' => ['invoice', 'renewal']],
    ]);

    expect($user->wantsNotification('invoice', 'mail'))->toBeFalse()
        ->and($user->wantsNotification('renewal', 'mail'))->toBeFalse()
        ->and($user->wantsNotification('payment', 'mail'))->toBeTrue();
});

it('wantsNotification is channel-independent — opt-out of mail does not affect database', function (): void {
    $user = User::factory()->create([
        'notification_preferences' => ['mail' => ['support']],
    ]);

    expect($user->wantsNotification('support', 'mail'))->toBeFalse()
        ->and($user->wantsNotification('support', 'database'))->toBeTrue();
});

it('wantsNotification defaults to checking mail channel', function (): void {
    $user = User::factory()->create([
        'notification_preferences' => ['mail' => ['monitor']],
    ]);

    expect($user->wantsNotification('monitor'))->toBeFalse()
        ->and($user->wantsNotification('invoice'))->toBeTrue();
});

// ── Notification via() respects wantsNotification ────────────────────────────

it('InvoiceIssuedNotification via() skips mail when user opts out of invoice mail', function (): void {
    $user     = User::factory()->create(['notification_preferences' => ['mail' => ['invoice']]]);
    $customer = Customer::factory()->for($user)->create();

    $notif = new InvoiceIssuedNotification(notifTestInvoice($customer));
    $via   = $notif->via($user);

    expect($via)->not->toContain('mail')
        ->and($via)->toContain('database');
});

it('InvoiceIssuedNotification via() includes both channels when no preferences set', function (): void {
    $user     = User::factory()->create(['notification_preferences' => null]);
    $customer = Customer::factory()->for($user)->create();

    $notif = new InvoiceIssuedNotification(notifTestInvoice($customer));
    $via   = $notif->via($user);

    expect($via)->toContain('mail')
        ->and($via)->toContain('database');
});

it('InvoicePaidNotification via() skips database when user opts out', function (): void {
    $user     = User::factory()->create(['notification_preferences' => ['database' => ['payment']]]);
    $customer = Customer::factory()->for($user)->create();

    $notif = new InvoicePaidNotification(notifTestInvoice($customer));
    $via   = $notif->via($user);

    expect($via)->not->toContain('database')
        ->and($via)->toContain('mail');
});

it('PaymentOverdueNotification via() skips mail when user opts out of payment mail', function (): void {
    $user     = User::factory()->create(['notification_preferences' => ['mail' => ['payment']]]);
    $customer = Customer::factory()->for($user)->create();

    $notif = new PaymentOverdueNotification(notifTestInvoice($customer), 5);
    $via   = $notif->via($user);

    expect($via)->not->toContain('mail');
});

it('TicketRepliedNotification via() falls back to database when user opts out of support mail', function (): void {
    $user     = User::factory()->create(['notification_preferences' => ['mail' => ['support']]]);
    $customer = Customer::factory()->for($user)->create();

    $ticket = SupportTicket::factory()->for($customer, 'customer')->create();
    $msg    = SupportTicketMessage::create([
        'support_ticket_id' => $ticket->id,
        'user_id'           => null,
        'is_staff'          => true,
        'is_internal'       => false,
        'message'           => 'Staff reply',
    ]);

    $notif = new TicketRepliedNotification($ticket, $msg);
    $via   = $notif->via($user);

    expect($via)->not->toContain('mail')
        ->and($via)->toContain('database');
});

it('MonitorDownNotification via() skips mail when user opts out of monitor mail', function (): void {
    $user    = User::factory()->create(['notification_preferences' => ['mail' => ['monitor']]]);
    $monitor = Monitor::factory()->create();

    $notif = new MonitorDownNotification($monitor);
    $via   = $notif->via($user);

    expect($via)->not->toContain('mail')
        ->and($via)->toContain('database');
});

it('RenewalReminderNotification via() skips database when user opts out of renewal database', function (): void {
    $user     = User::factory()->create(['notification_preferences' => ['database' => ['renewal']]]);
    $customer = Customer::factory()->for($user)->create();
    $service  = Service::factory()->create();

    $notif = new RenewalReminderNotification($service, notifTestInvoice($customer), 7);
    $via   = $notif->via($user);

    expect($via)->not->toContain('database')
        ->and($via)->toContain('mail');
});

it('DomainExpiringNotification via() skips mail when user opts out of renewal', function (): void {
    $user    = User::factory()->create(['notification_preferences' => ['mail' => ['renewal']]]);
    $service = Service::factory()->create();
    $domain  = DomainRegistration::create([
        'service_id' => $service->id,
        'domain'     => 'testdomain',
        'tld'        => 'cz',
        'registrar'  => 'wedos',
        'auto_renew' => false,
        'expires_at' => now()->addDays(14),
    ]);

    $notif = new DomainExpiringNotification($domain, 14);
    $via   = $notif->via($user);

    expect($via)->not->toContain('mail')
        ->and($via)->toContain('database');
});

// ── Panel HTTP endpoints ───────────────────────────────────────────────────────

it('GET /panel/ucet/notifikace returns 200 for authenticated customer', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.account.notification-preferences'))
        ->assertOk()
        ->assertViewIs('panel.account.notification-preferences');
});

it('GET /panel/ucet/notifikace redirects guests', function (): void {
    $this->get(route('panel.account.notification-preferences'))
        ->assertRedirect(route('login'));
});

it('PUT /panel/ucet/notifikace saves notification preferences', function (): void {
    $user = customerUser();

    // Submitting mail=['invoice','payment'] means those checkboxes are CHECKED (user wants them ON)
    // The controller stores the OPT-OUT list = types that were NOT submitted
    // So opt-out for mail = ['renewal','support','backup','monitor']
    $this->actingAs($user)
        ->put(route('panel.account.notification-preferences.update'), [
            'mail'     => ['invoice', 'payment'],
            'database' => ['support'],
        ])
        ->assertRedirect();

    $user->refresh();

    // mail opt-out contains types not checked (renewal, support, backup, monitor)
    expect($user->notification_preferences['mail'])->not->toContain('invoice')
        ->and($user->notification_preferences['mail'])->not->toContain('payment')
        ->and($user->notification_preferences['mail'])->toContain('renewal')
        // database opt-out: everything except 'support'
        ->and($user->notification_preferences['database'])->not->toContain('support')
        ->and($user->notification_preferences['database'])->toContain('invoice');
});

it('PUT /panel/ucet/notifikace with empty body opts out of all notification types', function (): void {
    $user = customerUser();

    // No checkboxes submitted → ALL types become opt-out
    $this->actingAs($user)
        ->put(route('panel.account.notification-preferences.update'), [])
        ->assertRedirect();

    $user->refresh();

    $allTypes = ['renewal', 'invoice', 'payment', 'support', 'backup', 'monitor'];

    expect($user->notification_preferences['mail'])->toBe($allTypes)
        ->and($user->notification_preferences['database'])->toBe($allTypes);
});

it('wantsNotification reflects updated preferences after PUT', function (): void {
    $user = customerUser();

    // Submit mail=['payment'] (only payment checked for mail)
    // → mail opt-out = ['renewal','invoice','support','backup','monitor']
    // → invoice is now opted OUT → wantsNotification('invoice','mail') = false
    // → payment is checked (not opted out) → wantsNotification('payment','mail') = true
    $this->actingAs($user)
        ->put(route('panel.account.notification-preferences.update'), [
            'mail' => ['payment'],
        ]);

    $user->refresh();

    expect($user->wantsNotification('invoice', 'mail'))->toBeFalse()
        ->and($user->wantsNotification('payment', 'mail'))->toBeTrue();
});
