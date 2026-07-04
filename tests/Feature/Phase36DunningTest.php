<?php

declare(strict_types=1);

use App\Console\Commands\SendPaymentOverdueRemindersCommand;
use App\Console\Commands\SuspendOverdueServicesCommand;
use App\Console\Commands\TerminateOverdueServicesCommand;
use App\Domains\Billing\Actions\PauseDunningAction;
use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Enums\VatScenario;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Domains\Provisioning\Jobs\ChangeServiceStateJob;
use App\Domains\Shared\Enums\Currency;
use App\Notifications\PaymentOverdueNotification;
use Brick\Money\Money;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── isDunningPaused() ─────────────────────────────────────────────────────

it('isDunningPaused returns false when paused_until is null', function (): void {
    $invoice = Invoice::factory()->overdue()->create();
    expect($invoice->isDunningPaused())->toBeFalse();
});

it('isDunningPaused returns true when paused_until is in the future', function (): void {
    $invoice = Invoice::factory()->overdue()->create(['dunning_paused_until' => now()->addDays(7)]);
    expect($invoice->isDunningPaused())->toBeTrue();
});

it('isDunningPaused returns false when paused_until is in the past', function (): void {
    $invoice = Invoice::factory()->overdue()->create(['dunning_paused_until' => now()->subDays(1)]);
    expect($invoice->isDunningPaused())->toBeFalse();
});

// ── PauseDunningAction ────────────────────────────────────────────────────

it('PauseDunningAction::pause sets dunning_paused_until', function (): void {
    $invoice = Invoice::factory()->overdue()->create();
    $admin   = adminUser();

    $this->actingAs($admin);
    app(PauseDunningAction::class)->pause($invoice, 14);

    expect($invoice->fresh()->dunning_paused_until)->not->toBeNull()
        ->and($invoice->fresh()->isDunningPaused())->toBeTrue();

    expect(Activity::where('log_name', 'invoice')->where('description', 'invoice.dunning_paused')->exists())
        ->toBeTrue();
});

it('PauseDunningAction::resume clears dunning_paused_until', function (): void {
    $invoice = Invoice::factory()->overdue()->create(['dunning_paused_until' => now()->addDays(7)]);
    $admin   = adminUser();

    $this->actingAs($admin);
    app(PauseDunningAction::class)->resume($invoice);

    expect($invoice->fresh()->dunning_paused_until)->toBeNull()
        ->and($invoice->fresh()->isDunningPaused())->toBeFalse();

    expect(Activity::where('log_name', 'invoice')->where('description', 'invoice.dunning_resumed')->exists())
        ->toBeTrue();
});

// ── SendPaymentOverdueRemindersCommand ────────────────────────────────────

it('send-overdue-reminders sends notification for 1d overdue invoice', function (): void {
    Notification::fake();

    $user    = customerUser();
    $invoice = Invoice::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => InvoiceStatus::Overdue,
        'due_date'    => now()->subDays(1)->toDateString(),
    ]);

    $this->artisan(SendPaymentOverdueRemindersCommand::class)->assertExitCode(0);

    Notification::assertSentTo($user, PaymentOverdueNotification::class);
    expect($invoice->fresh()->reminder_1d_sent_at)->not->toBeNull();
});

it('send-overdue-reminders sets milestone timestamps without double-sending', function (): void {
    Notification::fake();

    $user    = customerUser();
    $invoice = Invoice::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => InvoiceStatus::Overdue,
        'due_date'    => now()->subDays(3)->toDateString(),
    ]);

    // First run — hits 1d and 3d milestones (due_date = 3 days ago)
    $this->artisan(SendPaymentOverdueRemindersCommand::class)->assertExitCode(0);

    expect($invoice->fresh()->reminder_1d_sent_at)->not->toBeNull()
        ->and($invoice->fresh()->reminder_3d_sent_at)->not->toBeNull()
        ->and($invoice->fresh()->reminder_7d_sent_at)->toBeNull();

    // Second run — milestones already set, nothing re-sent
    $this->artisan(SendPaymentOverdueRemindersCommand::class)->assertExitCode(0);
    Notification::assertSentToTimes($user, PaymentOverdueNotification::class, 2); // 1d + 3d, once each
});

it('send-overdue-reminders skips dunning-paused invoices', function (): void {
    Notification::fake();

    $user    = customerUser();
    Invoice::factory()->create([
        'customer_id'          => $user->customer->id,
        'status'               => InvoiceStatus::Overdue,
        'due_date'             => now()->subDays(1)->toDateString(),
        'dunning_paused_until' => now()->addDays(7),
    ]);

    $this->artisan(SendPaymentOverdueRemindersCommand::class)->assertExitCode(0);

    Notification::assertNothingSent();
});

it('send-overdue-reminders respects expired pause (sends reminder)', function (): void {
    Notification::fake();

    $user    = customerUser();
    $invoice = Invoice::factory()->create([
        'customer_id'          => $user->customer->id,
        'status'               => InvoiceStatus::Overdue,
        'due_date'             => now()->subDays(1)->toDateString(),
        'dunning_paused_until' => now()->subDays(1), // pause expired yesterday
    ]);

    $this->artisan(SendPaymentOverdueRemindersCommand::class)->assertExitCode(0);

    Notification::assertSentTo($user, PaymentOverdueNotification::class);
    expect($invoice->fresh()->reminder_1d_sent_at)->not->toBeNull();
});

// ── SuspendOverdueServicesCommand ─────────────────────────────────────────

it('suspend-overdue skips invoices with active dunning pause', function (): void {
    Queue::fake();

    $user  = customerUser();
    $order = Order::create([
        'customer_id'  => $user->customer->id,
        'status'       => OrderStatus::Active,
        'currency'     => Currency::CZK,
        'subtotal'     => Money::of(826, 'CZK'),
        'tax_amount'   => Money::of(173, 'CZK'),
        'total'        => Money::of(999, 'CZK'),
        'vat_scenario' => VatScenario::CzechB2C,
    ]);

    Invoice::factory()->create([
        'customer_id'          => $user->customer->id,
        'order_id'             => $order->id,
        'status'               => InvoiceStatus::Overdue,
        'due_date'             => now()->subDays(10)->toDateString(),
        'dunning_paused_until' => now()->addDays(7),
    ]);

    $this->artisan(SuspendOverdueServicesCommand::class)->assertExitCode(0);

    Queue::assertNotPushed(ChangeServiceStateJob::class);
});

it('suspend-overdue processes non-paused overdue invoices', function (): void {
    Queue::fake();

    // Ensure the command exits cleanly with no-pause invoices present
    // (no real services linked — just verifies command runs without error)
    $user = customerUser();
    Invoice::factory()->create([
        'customer_id' => $user->customer->id,
        'order_id'    => null,
        'status'      => InvoiceStatus::Overdue,
        'due_date'    => now()->subDays(10)->toDateString(),
    ]);

    $this->artisan(SuspendOverdueServicesCommand::class)->assertExitCode(0);
});

// ── Admin Dunning Dashboard ───────────────────────────────────────────────

it('admin dunning index returns 200', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.dunning.index'))
        ->assertOk()
        ->assertViewIs('admin.dunning.index');
});

it('admin can pause dunning via POST', function (): void {
    $invoice = Invoice::factory()->overdue()->create();
    $admin   = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.dunning.pause', $invoice), ['days' => 7])
        ->assertRedirect();

    expect($invoice->fresh()->isDunningPaused())->toBeTrue();
});

it('admin can resume dunning via POST', function (): void {
    $invoice = Invoice::factory()->overdue()->create(['dunning_paused_until' => now()->addDays(7)]);
    $admin   = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.dunning.resume', $invoice))
        ->assertRedirect();

    expect($invoice->fresh()->isDunningPaused())->toBeFalse();
});

it('non-admin cannot access dunning dashboard', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.dunning.index'))
        ->assertForbidden();
});

it('dunning dashboard lists overdue invoices', function (): void {
    $user = customerUser();
    Invoice::factory()->overdue()->create(['customer_id' => $user->customer->id]);
    Invoice::factory()->overdue()->create(['customer_id' => $user->customer->id]);

    $admin = adminUser();
    $this->actingAs($admin)
        ->get(route('admin.dunning.index'))
        ->assertOk()
        ->assertViewHas('stats');
});

it('dunning pause validates days range', function (): void {
    $invoice = Invoice::factory()->overdue()->create();
    $admin   = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.dunning.pause', $invoice), ['days' => 0])
        ->assertSessionHasErrors('days');

    $this->actingAs($admin)
        ->post(route('admin.dunning.pause', $invoice), ['days' => 91])
        ->assertSessionHasErrors('days');
});
