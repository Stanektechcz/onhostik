<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\AutoApplyCreditToInvoiceAction;
use App\Domains\Billing\Actions\CancelSubscriptionAction;
use App\Domains\Billing\Actions\PauseSubscriptionAction;
use App\Domains\Billing\Actions\ResumeSubscriptionAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Services\CreditLedger;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Brick\Money\Money;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Service model helpers ─────────────────────────────────────────────────────

it('Service isPaused returns true when paused_at set and status Suspended', function (): void {
    $service = new Service([
        'status'    => ServiceStatus::Suspended,
        'paused_at' => now(),
    ]);
    expect($service->isPaused())->toBeTrue();
});

it('Service isPaused returns false when status is Active', function (): void {
    $service = new Service([
        'status'    => ServiceStatus::Active,
        'paused_at' => now(),
    ]);
    expect($service->isPaused())->toBeFalse();
});

it('Service isPaused returns false when paused_at is null', function (): void {
    $service = new Service([
        'status'    => ServiceStatus::Suspended,
        'paused_at' => null,
    ]);
    expect($service->isPaused())->toBeFalse();
});

it('Service isCancelledAtPeriodEnd returns true when flag set', function (): void {
    $service = new Service(['cancel_at_period_end' => true]);
    expect($service->isCancelledAtPeriodEnd())->toBeTrue();
});

it('Service isCancelledAtPeriodEnd returns false by default', function (): void {
    $service = new Service(['cancel_at_period_end' => false]);
    expect($service->isCancelledAtPeriodEnd())->toBeFalse();
});

// ── CancelSubscriptionAction ──────────────────────────────────────────────────

it('CancelSubscriptionAction marks service cancel_at_period_end', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'  => $user->customer->id,
        'status'       => ServiceStatus::Active,
        'next_due_date' => now()->addDays(20)->toDateString(),
    ]);

    app(CancelSubscriptionAction::class)->execute($service, 'Nechci dále platit');

    $service->refresh();
    expect($service->cancel_at_period_end)->toBeTrue();
    expect($service->cancellation_reason)->toBe('Nechci dále platit');
    expect($service->status)->toBe(ServiceStatus::Active); // still active until period end
});

it('CancelSubscriptionAction stores null reason when empty', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create(['customer_id' => $user->customer->id]);

    app(CancelSubscriptionAction::class)->execute($service);

    $service->refresh();
    expect($service->cancel_at_period_end)->toBeTrue();
    expect($service->cancellation_reason)->toBeNull();
});

// ── PauseSubscriptionAction ───────────────────────────────────────────────────

it('PauseSubscriptionAction pauses an active service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    app(PauseSubscriptionAction::class)->execute($service, 30, 'Dovolená');

    $service->refresh();
    expect($service->status)->toBe(ServiceStatus::Suspended);
    expect($service->paused_at)->not()->toBeNull();
    expect($service->paused_until)->not()->toBeNull();
    expect($service->suspension_reason)->toBe('Dovolená');
    expect($service->isPaused())->toBeTrue();
});

it('PauseSubscriptionAction sets paused_until to correct date', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    app(PauseSubscriptionAction::class)->execute($service, 14);

    $service->refresh();
    $expected = now()->addDays(14)->toDateString();
    expect($service->paused_until->toDateString())->toBe($expected);
});

it('PauseSubscriptionAction throws for non-active service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->suspended()->create(['customer_id' => $user->customer->id]);

    expect(fn () => app(PauseSubscriptionAction::class)->execute($service, 7))
        ->toThrow(InvalidArgumentException::class);
});

it('PauseSubscriptionAction throws for invalid days', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    expect(fn () => app(PauseSubscriptionAction::class)->execute($service, 91))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => app(PauseSubscriptionAction::class)->execute($service, 0))
        ->toThrow(InvalidArgumentException::class);
});

// ── ResumeSubscriptionAction ──────────────────────────────────────────────────

it('ResumeSubscriptionAction resumes a paused service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'       => $user->customer->id,
        'status'            => ServiceStatus::Suspended,
        'suspended_at'      => now(),
        'paused_at'         => now(),
        'paused_until'      => now()->addDays(14)->toDateString(),
        'suspension_reason' => 'Dovolená',
    ]);

    app(ResumeSubscriptionAction::class)->execute($service);

    $service->refresh();
    expect($service->status)->toBe(ServiceStatus::Active);
    expect($service->paused_at)->toBeNull();
    expect($service->paused_until)->toBeNull();
    expect($service->suspended_at)->toBeNull();
    expect($service->suspension_reason)->toBeNull();
});

it('ResumeSubscriptionAction throws when service is not paused', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
        'paused_at'   => null,
    ]);

    expect(fn () => app(ResumeSubscriptionAction::class)->execute($service))
        ->toThrow(InvalidArgumentException::class);
});

// ── AutoApplyCreditToInvoiceAction ────────────────────────────────────────────

it('AutoApplyCreditToInvoiceAction pays invoice when balance is sufficient', function (): void {
    $user    = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $ledger->deposit($customer, Money::of(500, 'CZK'), 'Test deposit');

    $invoice = \App\Domains\Billing\Models\Invoice::factory()->create([
        'customer_id' => $customer->id,
        'status'      => InvoiceStatus::Sent,
        'total'       => Money::of(100, 'CZK'),
        'subtotal'    => Money::of(100, 'CZK'),
        'tax_amount'  => Money::of(0, 'CZK'),
        'currency'    => 'CZK',
    ]);

    $result = app(AutoApplyCreditToInvoiceAction::class)->execute($invoice);

    expect($result)->toBeTrue();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('AutoApplyCreditToInvoiceAction returns false when balance is insufficient', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    // No credit deposited — balance = 0

    $invoice = \App\Domains\Billing\Models\Invoice::factory()->create([
        'customer_id' => $customer->id,
        'status'      => InvoiceStatus::Sent,
        'total'       => Money::of(999, 'CZK'),
        'subtotal'    => Money::of(999, 'CZK'),
        'tax_amount'  => Money::of(0, 'CZK'),
        'currency'    => 'CZK',
    ]);

    $result = app(AutoApplyCreditToInvoiceAction::class)->execute($invoice);

    expect($result)->toBeFalse();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('AutoApplyCreditToInvoiceAction returns false for already-paid invoice', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $ledger->deposit($customer, Money::of(1000, 'CZK'), 'Test deposit');

    $invoice = \App\Domains\Billing\Models\Invoice::factory()->create([
        'customer_id' => $customer->id,
        'status'      => InvoiceStatus::Paid,
        'total'       => Money::of(100, 'CZK'),
        'subtotal'    => Money::of(100, 'CZK'),
        'tax_amount'  => Money::of(0, 'CZK'),
        'currency'    => 'CZK',
        'paid_at'     => now(),
    ]);

    $result = app(AutoApplyCreditToInvoiceAction::class)->execute($invoice);

    expect($result)->toBeFalse();
});

// ── Panel route — pause ───────────────────────────────────────────────────────

it('customer can pause their active service via panel', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.pause', $service), [
            'paused_days' => 30,
            'reason'      => 'Na dovolenou',
        ])
        ->assertRedirect();

    $service->refresh();
    expect($service->status)->toBe(ServiceStatus::Suspended);
    expect($service->isPaused())->toBeTrue();
});

it('panel pause rejects non-active service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->suspended()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->post(route('panel.services.pause', $service), ['paused_days' => 14])
        ->assertSessionHasErrors('pause');
});

it('panel pause validates paused_days range', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.pause', $service), ['paused_days' => 0])
        ->assertSessionHasErrors('paused_days');

    $this->actingAs($user)
        ->post(route('panel.services.pause', $service), ['paused_days' => 91])
        ->assertSessionHasErrors('paused_days');
});

it('customer cannot pause another customer service', function (): void {
    $owner    = customerUser();
    $intruder = customerUser();

    $service = Service::factory()->create([
        'customer_id' => $owner->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($intruder)
        ->post(route('panel.services.pause', $service), ['paused_days' => 7])
        ->assertForbidden();
});

// ── Panel route — resume ──────────────────────────────────────────────────────

it('customer can resume their paused service via panel', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'       => $user->customer->id,
        'status'            => ServiceStatus::Suspended,
        'suspended_at'      => now(),
        'paused_at'         => now(),
        'paused_until'      => now()->addDays(30)->toDateString(),
        'suspension_reason' => 'Dovolená',
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.resume', $service))
        ->assertRedirect();

    $service->refresh();
    expect($service->status)->toBe(ServiceStatus::Active);
    expect($service->isPaused())->toBeFalse();
});

it('panel resume rejects non-paused service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
        'paused_at'   => null,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.resume', $service))
        ->assertSessionHasErrors('resume');
});

// ── Panel route — cancel at period end ───────────────────────────────────────

it('customer can schedule cancellation at period end', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'  => $user->customer->id,
        'status'       => ServiceStatus::Active,
        'next_due_date' => now()->addDays(25)->toDateString(),
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.cancel-at-period-end', $service), [
            'reason' => 'Přecházím jinam',
        ])
        ->assertRedirect();

    $service->refresh();
    expect($service->isCancelledAtPeriodEnd())->toBeTrue();
    expect($service->cancellation_reason)->toBe('Přecházím jinam');
    expect($service->status)->toBe(ServiceStatus::Active);
});

it('panel cancel-at-period-end is idempotent (second call returns without error)', function (): void {
    $user    = customerUser();
    $service = Service::factory()->create([
        'customer_id'          => $user->customer->id,
        'status'               => ServiceStatus::Active,
        'cancel_at_period_end' => true,
    ]);

    $this->actingAs($user)
        ->post(route('panel.services.cancel-at-period-end', $service))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('panel cancel-at-period-end rejected for terminated service', function (): void {
    $user    = customerUser();
    $service = Service::factory()->terminated()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->post(route('panel.services.cancel-at-period-end', $service))
        ->assertSessionHasErrors('cancel');
});

// ── SendPaymentOverdueRemindersCommand ────────────────────────────────────────

it('SendPaymentOverdueRemindersCommand sends 1d reminder for overdue invoice', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;

    $invoice = \App\Domains\Billing\Models\Invoice::factory()->create([
        'customer_id'       => $customer->id,
        'status'            => InvoiceStatus::Overdue,
        'due_date'          => now()->subDays(2)->toDateString(),
        'reminder_1d_sent_at' => null,
        'reminder_3d_sent_at' => null,
        'reminder_7d_sent_at' => null,
        'dunning_paused_until' => null,
    ]);

    $this->artisan('billing:send-overdue-reminders')->assertExitCode(0);

    expect($invoice->fresh()->reminder_1d_sent_at)->not()->toBeNull();
    Notification::assertSentTo($user, \App\Notifications\PaymentOverdueNotification::class);
});

it('SendPaymentOverdueRemindersCommand skips dunning-paused invoices', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;

    \App\Domains\Billing\Models\Invoice::factory()->create([
        'customer_id'          => $customer->id,
        'status'               => InvoiceStatus::Overdue,
        'due_date'             => now()->subDays(5)->toDateString(),
        'reminder_1d_sent_at'  => null,
        'dunning_paused_until' => now()->addDays(7),
    ]);

    $this->artisan('billing:send-overdue-reminders')->assertExitCode(0);

    Notification::assertNothingSentTo($user);
});
