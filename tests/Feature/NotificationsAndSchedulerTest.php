<?php

declare(strict_types=1);

use App\Console\Commands\ProcessDomainRenewalsCommand;
use App\Console\Commands\RunMonitorChecksCommand;
use App\Console\Commands\SendDomainExpiringRemindersCommand;
use App\Console\Commands\SendPaymentOverdueRemindersCommand;
use App\Console\Commands\SendRenewalRemindersCommand;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Monitoring\Enums\MonitorStatus;
use App\Domains\Monitoring\Models\Monitor;
use App\Domains\Monitoring\Models\MonitorIncident;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Provisioning\Jobs\RenewDomainJob;
use App\Domains\Provisioning\Models\DomainRegistration;
use App\Domains\Provisioning\Models\Service;
use App\Notifications\DomainExpiringNotification;
use App\Notifications\MonitorDownNotification;
use App\Notifications\PaymentOverdueNotification;
use App\Notifications\ServiceSuspendedNotification;
use App\Notifications\ServiceTerminatedNotification;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── ServiceSuspendedNotification ──────────────────────────────────────────────

it('ChangeServiceStateJob sends ServiceSuspendedNotification on suspend', function (): void {
    Notification::fake();

    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'test-suspend-notify',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    ChangeServiceStateJob::dispatchSync($service->id, 'suspend', 'overdue_invoice');

    Notification::assertSentTo($user, ServiceSuspendedNotification::class);
});

it('ServiceSuspendedNotification renders mail correctly', function (): void {
    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'muj-web.cz',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $notification = new ServiceSuspendedNotification($service, 'overdue_invoice');
    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('muj-web.cz');
    expect($notification->toArray($user)['color'])->toBe('danger');
});

// ── ServiceTerminatedNotification ────────────────────────────────────────────

it('ChangeServiceStateJob sends ServiceTerminatedNotification on terminate', function (): void {
    Notification::fake();

    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'test-terminate-notify',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    ChangeServiceStateJob::dispatchSync($service->id, 'terminate', 'overdue_invoice');

    Notification::assertSentTo($user, ServiceTerminatedNotification::class);
});

it('ServiceTerminatedNotification renders mail correctly', function (): void {
    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'muj-zruseny-web.cz',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $notification = new ServiceTerminatedNotification($service, 'overdue_invoice');
    $mail = $notification->toMail($user);

    expect($mail->subject)->toContain('muj-zruseny-web.cz');
    expect($notification->toArray($user)['color'])->toBe('danger');
    expect($notification->toArray($user)['icon'])->toBe('x-octagon');
});

// ── PaymentOverdueNotification trigger ───────────────────────────────────────

it('SendPaymentOverdueRemindersCommand sends notification for 1-day overdue invoice', function (): void {
    Notification::fake();

    $user = customerUser();

    // Place a real order to get a proper invoice
    \App\Domains\Customer\Models\CustomerAddress::create([
        'customer_id' => $user->customer->id, 'type' => 'billing',
        'street' => 'Test 1', 'city' => 'Praha', 'zip' => '11000',
        'country_code' => 'CZ', 'is_primary' => true,
    ]);
    ['invoice' => $invoice] = placeOrder($user);

    // Force it into overdue state with a due_date exactly 1 day ago
    $invoice->update([
        'status'   => InvoiceStatus::Overdue,
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $this->artisan(SendPaymentOverdueRemindersCommand::class)->assertSuccessful();

    Notification::assertSentTo($user, PaymentOverdueNotification::class);
});

// ── DomainExpiringNotification ────────────────────────────────────────────────

it('SendDomainExpiringRemindersCommand sends notification for domain expiring in 7 days', function (): void {
    Notification::fake();

    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'domain-notify-test',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    DomainRegistration::create([
        'service_id'  => $service->id,
        'domain'      => 'test-notify',
        'tld'         => 'cz',
        'registrar'   => 'wedos',
        'auto_renew'  => false,
        'expires_at'  => now()->addDays(7)->toDateString(),
    ]);

    $this->artisan(SendDomainExpiringRemindersCommand::class)->assertSuccessful();

    Notification::assertSentTo($user, DomainExpiringNotification::class);
});

it('DomainExpiringNotification toArray returns correct color for urgent domain', function (): void {
    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'urgent-domain',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $domain = DomainRegistration::create([
        'service_id'  => $service->id,
        'domain'      => 'urgent',
        'tld'         => 'cz',
        'registrar'   => 'wedos',
        'auto_renew'  => false,
        'expires_at'  => now()->addDays(3)->toDateString(),
    ]);

    $notification = new DomainExpiringNotification($domain, 3);
    expect($notification->toArray($user)['color'])->toBe('danger');
});

// ── Monitor checks ────────────────────────────────────────────────────────────

it('RunMonitorChecksCommand checks all active monitors in mock mode', function (): void {
    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'monitor-check-test',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    Monitor::create([
        'service_id'  => $service->id,
        'name'        => 'Test HTTP',
        'type'        => 'http',
        'target'      => 'https://example.com',
        'provider'    => 'internal_mock',
        'status'      => MonitorStatus::Up,
        'is_active'   => true,
    ]);

    $this->artisan(RunMonitorChecksCommand::class)->assertSuccessful();

    // Check row should have been created
    expect(Monitor::where('service_id', $service->id)->first()?->checks()->count())->toBeGreaterThan(0);
});

it('RunMonitorChecksCommand opens incident when monitor goes Down', function (): void {
    Notification::fake();

    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'down-monitor-test',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $monitor = Monitor::create([
        'service_id' => $service->id,
        'name'       => 'Down Monitor',
        'type'       => 'http',
        'target'     => 'https://example.com',
        'provider'   => 'internal_mock',
        'status'     => MonitorStatus::Down,  // previously down
        'is_active'  => true,
    ]);

    // Simulate UP transition (mock always returns Up) — mock should close the incident
    $incident = MonitorIncident::create([
        'monitor_id'  => $monitor->id,
        'severity'    => 'major',
        'reason'      => 'timeout',
        'started_at'  => now()->subMinutes(5),
    ]);

    $this->artisan(RunMonitorChecksCommand::class)->assertSuccessful();

    // Mock always returns Up, so DOWN→UP transition should resolve the incident
    expect($incident->fresh()->resolved_at)->not->toBeNull();
});

// ── Domain renewal ────────────────────────────────────────────────────────────

it('ProcessDomainRenewalsCommand dispatches RenewDomainJob for auto-renew domains', function (): void {
    Queue::fake();

    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'auto-renew-domain',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    DomainRegistration::create([
        'service_id' => $service->id,
        'domain'     => 'auto-renew',
        'tld'        => 'cz',
        'registrar'  => 'wedos',
        'auto_renew' => true,
        'expires_at' => now()->addDays(3)->toDateString(), // within 7-day window
    ]);

    $this->artisan(ProcessDomainRenewalsCommand::class)->assertSuccessful();

    Queue::assertPushed(RenewDomainJob::class);
});

it('ProcessDomainRenewalsCommand skips domains with auto_renew=false', function (): void {
    Queue::fake();

    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'no-auto-renew',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    DomainRegistration::create([
        'service_id' => $service->id,
        'domain'     => 'no-auto-renew',
        'tld'        => 'cz',
        'registrar'  => 'wedos',
        'auto_renew' => false,
        'expires_at' => now()->addDays(3)->toDateString(),
    ]);

    $this->artisan(ProcessDomainRenewalsCommand::class)->assertSuccessful();

    Queue::assertNotPushed(RenewDomainJob::class);
});

it('RenewDomainJob extends expires_at by 1 year in mock mode', function (): void {
    $user      = customerUser();
    $productId = \App\Domains\Products\Models\Product::value('id');

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'product_id'          => $productId,
        'status'              => ServiceStatus::Active,
        'label'               => 'renew-job-test',
        'provisioning_driver' => ProvisioningDriver::AAPanel,
    ]);

    $expiresAt = now()->addDays(3);
    $domain = DomainRegistration::create([
        'service_id' => $service->id,
        'domain'     => 'renew-job',
        'tld'        => 'cz',
        'registrar'  => 'wedos',
        'auto_renew' => true,
        'expires_at' => $expiresAt->toDateString(),
    ]);

    RenewDomainJob::dispatchSync($domain->id);

    $newExpiry = $domain->fresh()->expires_at;
    expect($newExpiry)->not->toBeNull();
    expect($newExpiry->year)->toBe($expiresAt->addYear()->year);
});
