<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\CreditTransactionType;
use App\Domains\Billing\Models\CreditExpiryReminder;
use App\Domains\Billing\Models\CreditTransaction;
use App\Domains\Billing\Services\CreditLedger;
use App\Notifications\CreditExpiryReminderNotification;
use Brick\Money\Money;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Migration columns ──────────────────────────────────────────────────────────

it('credit_transactions has expires_at column', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $tx     = $ledger->deposit($customer, Money::of(100, 'CZK'), 'Vklad s expirací');

    DB::table('credit_transactions')->where('id', $tx->id)->update(['expires_at' => now()->addDays(30)]);

    expect(CreditTransaction::find($tx->id)?->expires_at)->not->toBeNull();
});

it('credit_expiry_reminders table exists and has correct columns', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $tx     = $ledger->deposit($customer, Money::of(50, 'CZK'), 'Vklad');

    $reminder = CreditExpiryReminder::create([
        'credit_transaction_id' => $tx->id,
        'days_before'           => 7,
        'sent_at'               => now(),
    ]);

    expect($reminder->id)->toBeGreaterThan(0)
        ->and($reminder->days_before)->toBe(7)
        ->and($reminder->sent_at)->not->toBeNull();
});

// ── Enum ──────────────────────────────────────────────────────────────────────

it('CreditTransactionType Expiry has correct label, color and sign', function (): void {
    $type = CreditTransactionType::Expiry;

    expect($type->label())->toBe('Vypršení')
        ->and($type->color())->toBe('secondary')
        ->and($type->sign())->toBe(-1);
});

// ── CreditLedger::expire ──────────────────────────────────────────────────────

it('expire() creates a negative Expiry ledger row', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    $ledger   = app(CreditLedger::class);

    $deposit = $ledger->deposit($customer, Money::of(200, 'CZK'), 'Vklad');
    $expiry  = $ledger->expire($customer, Money::of(200, 'CZK'), 'Vypršení vkladu', $deposit);

    expect($expiry->type)->toBe(CreditTransactionType::Expiry)
        ->and($expiry->amount->getMinorAmount()->toInt())->toBeLessThan(0);
});

it('expiryDeductions relationship returns matching Expiry rows', function (): void {
    $user     = customerUser();
    $customer = $user->customer;
    $ledger   = app(CreditLedger::class);

    $deposit = $ledger->deposit($customer, Money::of(100, 'CZK'), 'Vklad');
    $ledger->expire($customer, Money::of(100, 'CZK'), 'Vypršení', $deposit);

    expect($deposit->expiryDeductions()->count())->toBe(1);
});

// ── SendCreditExpiryRemindersCommand ──────────────────────────────────────────

it('send-credit-expiry-reminders command sends 30d reminder', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $tx     = $ledger->deposit($customer, Money::of(150, 'CZK'), 'Vklad s expirací');
    DB::table('credit_transactions')->where('id', $tx->id)
        ->update(['expires_at' => now()->addDays(30)->toDateString()]);

    $this->artisan('billing:send-credit-expiry-reminders')->assertExitCode(0);

    Notification::assertSentTo($user, CreditExpiryReminderNotification::class);
    expect(CreditExpiryReminder::where('credit_transaction_id', $tx->id)->where('days_before', 30)->exists())->toBeTrue();
});

it('send-credit-expiry-reminders command sends 7d reminder', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $tx     = $ledger->deposit($customer, Money::of(80, 'CZK'), 'Vklad 7d');
    DB::table('credit_transactions')->where('id', $tx->id)
        ->update(['expires_at' => now()->addDays(7)->toDateString()]);

    $this->artisan('billing:send-credit-expiry-reminders')->assertExitCode(0);

    Notification::assertSentTo($user, CreditExpiryReminderNotification::class);
    expect(CreditExpiryReminder::where('credit_transaction_id', $tx->id)->where('days_before', 7)->exists())->toBeTrue();
});

it('send-credit-expiry-reminders does not resend already-sent reminder', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $tx     = $ledger->deposit($customer, Money::of(100, 'CZK'), 'Vklad dup');
    DB::table('credit_transactions')->where('id', $tx->id)
        ->update(['expires_at' => now()->addDays(30)->toDateString()]);

    CreditExpiryReminder::create([
        'credit_transaction_id' => $tx->id,
        'days_before'           => 30,
        'sent_at'               => now()->subHour(),
    ]);

    $this->artisan('billing:send-credit-expiry-reminders')->assertExitCode(0);

    Notification::assertNotSentTo($user, CreditExpiryReminderNotification::class);
});

it('send-credit-expiry-reminders ignores transactions without expires_at', function (): void {
    Notification::fake();

    $user     = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $ledger->deposit($customer, Money::of(100, 'CZK'), 'Vklad bez expiry');

    $this->artisan('billing:send-credit-expiry-reminders')->assertExitCode(0);

    Notification::assertNotSentTo($user, CreditExpiryReminderNotification::class);
});

// ── ExpireCreditCommand ───────────────────────────────────────────────────────

it('billing:expire-credit creates Expiry deduction for past expires_at', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $tx     = $ledger->deposit($customer, Money::of(300, 'CZK'), 'Starý vklad');
    DB::table('credit_transactions')->where('id', $tx->id)
        ->update(['expires_at' => now()->subDay()->toDateTimeString()]);

    $balanceBefore = $ledger->getBalance($customer)->getMinorAmount()->toInt();

    $this->artisan('billing:expire-credit')->assertExitCode(0);

    $balanceAfter = $ledger->getBalance($customer)->getMinorAmount()->toInt();
    expect($balanceAfter)->toBeLessThan($balanceBefore);

    $expiryCount = CreditTransaction::where('customer_id', $customer->id)
        ->where('type', CreditTransactionType::Expiry)
        ->count();
    expect($expiryCount)->toBe(1);
});

it('billing:expire-credit does not double-expire a transaction', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $tx     = $ledger->deposit($customer, Money::of(100, 'CZK'), 'Vklad');
    DB::table('credit_transactions')->where('id', $tx->id)
        ->update(['expires_at' => now()->subDay()->toDateTimeString()]);

    $this->artisan('billing:expire-credit')->assertExitCode(0);
    $this->artisan('billing:expire-credit')->assertExitCode(0);

    $expiryCount = CreditTransaction::where('customer_id', $customer->id)
        ->where('type', CreditTransactionType::Expiry)
        ->count();
    expect($expiryCount)->toBe(1);
});

it('billing:expire-credit skips transactions with future expires_at', function (): void {
    $user     = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $tx     = $ledger->deposit($customer, Money::of(200, 'CZK'), 'Budoucí vklad');
    DB::table('credit_transactions')->where('id', $tx->id)
        ->update(['expires_at' => now()->addDays(10)->toDateTimeString()]);

    $this->artisan('billing:expire-credit')->assertExitCode(0);

    $expiryCount = CreditTransaction::where('customer_id', $customer->id)
        ->where('type', CreditTransactionType::Expiry)
        ->count();
    expect($expiryCount)->toBe(0);
});

// ── Admin page ────────────────────────────────────────────────────────────────

it('admin can view expiring credits page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.expiring-credits.index'))
        ->assertOk()
        ->assertSee('Vypršení kreditů');
});

it('expiring credits page filters by days parameter', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.expiring-credits.index', ['days' => 7]))
        ->assertOk();
});

it('non-admin cannot view expiring credits page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('admin.expiring-credits.index'))
        ->assertStatus(403);
});

it('expiring credits page shows transactions expiring in selected window', function (): void {
    $admin    = adminUser();
    $user     = customerUser();
    $customer = $user->customer;

    $ledger = app(CreditLedger::class);
    $tx     = $ledger->deposit($customer, Money::of(99, 'CZK'), 'Krátká expiry');
    DB::table('credit_transactions')->where('id', $tx->id)
        ->update(['expires_at' => now()->addDays(5)->toDateTimeString()]);

    $this->actingAs($admin)
        ->get(route('admin.expiring-credits.index', ['days' => 7]))
        ->assertOk()
        ->assertSee($tx->customer?->user?->email ?? '');
});
