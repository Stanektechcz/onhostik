<?php

declare(strict_types=1);

use App\Console\Commands\ApplyLateFeesCommand;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Notifications\LateFeeAppliedNotification;
use Database\Factories\InvoiceFactory;
use Illuminate\Support\Facades\Notification;

// ── Command: disabled ─────────────────────────────────────────────────────────

it('command skips when late fee is disabled', function (): void {
    Config::set('billing.late_fee_minor', 0);

    $this->artisan(ApplyLateFeesCommand::class)
        ->expectsOutput('Late fee is disabled (billing.late_fee_minor = 0). Skipping.')
        ->assertExitCode(0);
});

// ── Command: no eligible invoices ─────────────────────────────────────────────

it('command skips when no invoices are past grace period', function (): void {
    Config::set('billing.late_fee_minor', 20000);
    Config::set('billing.late_fee_days', 7);

    $user = customerUser();
    InvoiceFactory::new()->create([
        'customer_id' => $user->customer->id,
        'status'      => InvoiceStatus::Overdue,
        'due_date'    => now()->subDays(3),
    ]);

    $this->artisan(ApplyLateFeesCommand::class)
        ->expectsOutput('No overdue invoices eligible for late fee.')
        ->assertExitCode(0);
});

// ── Command: applies fee ──────────────────────────────────────────────────────

it('command applies late fee and notifies customer', function (): void {
    Notification::fake();
    Config::set('billing.late_fee_minor', 20000);
    Config::set('billing.late_fee_days', 7);

    $user    = customerUser();
    $invoice = InvoiceFactory::new()->create([
        'customer_id'        => $user->customer->id,
        'status'             => InvoiceStatus::Overdue,
        'due_date'           => now()->subDays(10),
        'late_fee_applied_at' => null,
    ]);

    $this->artisan(ApplyLateFeesCommand::class)->assertExitCode(0);

    $invoice->refresh();
    expect($invoice->late_fee_amount)->toBe(20000);
    expect($invoice->late_fee_applied_at)->not->toBeNull();

    Notification::assertSentTo(
        $user,
        LateFeeAppliedNotification::class,
        fn ($n) => $n->invoice->id === $invoice->id && $n->feeMinor === 20000,
    );
});

// ── Command: idempotent ───────────────────────────────────────────────────────

it('command does not double-charge already fined invoice', function (): void {
    Notification::fake();
    Config::set('billing.late_fee_minor', 20000);
    Config::set('billing.late_fee_days', 7);

    $user = customerUser();
    InvoiceFactory::new()->create([
        'customer_id'        => $user->customer->id,
        'status'             => InvoiceStatus::Overdue,
        'due_date'           => now()->subDays(10),
        'late_fee_applied_at' => now()->subDays(2),
        'late_fee_amount'    => 20000,
    ]);

    $this->artisan(ApplyLateFeesCommand::class)
        ->expectsOutput('No overdue invoices eligible for late fee.')
        ->assertExitCode(0);

    Notification::assertNothingSent();
});

// ── Command: non-overdue invoice is not affected ──────────────────────────────

it('command ignores unpaid invoices that are not overdue', function (): void {
    Notification::fake();
    Config::set('billing.late_fee_minor', 20000);
    Config::set('billing.late_fee_days', 7);

    $user = customerUser();
    InvoiceFactory::new()->create([
        'customer_id' => $user->customer->id,
        'status'      => InvoiceStatus::Sent,
        'due_date'    => now()->subDays(15),
    ]);

    $this->artisan(ApplyLateFeesCommand::class)
        ->expectsOutput('No overdue invoices eligible for late fee.')
        ->assertExitCode(0);
});

// ── Admin view ────────────────────────────────────────────────────────────────

it('admin invoice show renders late fee row', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $invoice = InvoiceFactory::new()->create([
        'customer_id'        => $user->customer->id,
        'status'             => InvoiceStatus::Overdue,
        'late_fee_amount'    => 20000,
        'late_fee_applied_at' => now()->subDays(1),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Upomínkový poplatek')
        ->assertSee('200');
});

// ── Panel view ────────────────────────────────────────────────────────────────

it('panel invoice show renders late fee row', function (): void {
    $user    = customerUser();
    $invoice = InvoiceFactory::new()->create([
        'customer_id'        => $user->customer->id,
        'status'             => InvoiceStatus::Overdue,
        'late_fee_amount'    => 20000,
        'late_fee_applied_at' => now()->subDays(1),
    ]);

    $this->actingAs($user)
        ->get(route('panel.billing.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Upomínkový poplatek')
        ->assertSee('200');
});

it('panel invoice show hides late fee section when none applied', function (): void {
    $user    = customerUser();
    $invoice = InvoiceFactory::new()->create([
        'customer_id'        => $user->customer->id,
        'status'             => InvoiceStatus::Sent,
        'late_fee_amount'    => null,
        'late_fee_applied_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('panel.billing.invoices.show', $invoice))
        ->assertOk()
        ->assertDontSee('Upomínkový poplatek');
});
