<?php

declare(strict_types=1);

use App\Domains\Billing\Actions\ProcessMockPaymentAction;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Payment;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

function completedPaymentForRefundTest(): Payment
{
    $user = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    return app(ProcessMockPaymentAction::class)->execute($invoice);
}

it('admin can record a manual refund with a reason', function (): void {
    $admin   = adminUser();
    $payment = completedPaymentForRefundTest();

    $this->actingAs($admin)
        ->post(route('admin.payments.refund', $payment), ['reason' => 'Customer requested cancellation'])
        ->assertRedirect();

    expect($payment->fresh()->status)->toBe(PaymentStatus::ManualRefund);
});

it('refund requires a reason', function (): void {
    $admin   = adminUser();
    $payment = completedPaymentForRefundTest();

    $this->actingAs($admin)
        ->post(route('admin.payments.refund', $payment), [])
        ->assertSessionHasErrors('reason');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Completed);
});

it('refund writes an audit log entry with who, when and why', function (): void {
    $admin   = adminUser();
    $payment = completedPaymentForRefundTest();

    $this->actingAs($admin)
        ->post(route('admin.payments.refund', $payment), ['reason' => 'Duplicate charge']);

    $entry = Activity::where('log_name', 'payment')
        ->where('description', 'payment.manual_refund_recorded')
        ->where('subject_id', $payment->id)
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->causer_id)->toBe($admin->id)
        ->and($entry->getExtraProperty('reason'))->toBe('Duplicate charge')
        ->and($entry->getExtraProperty('refund_type'))->toBe('manual')
        ->and($entry->getExtraProperty('gateway_call'))->toBeFalse();
});

it('never marks a refund as PaymentStatus::Refunded — only the manual status', function (): void {
    $admin   = adminUser();
    $payment = completedPaymentForRefundTest();

    $this->actingAs($admin)
        ->post(route('admin.payments.refund', $payment), ['reason' => 'Test reason']);

    // ::Refunded is reserved for a future real gateway-confirmed refund —
    // today's flow must never claim that confidence level.
    expect($payment->fresh()->status)
        ->toBe(PaymentStatus::ManualRefund)
        ->not->toBe(PaymentStatus::Refunded);
});

it('refuses to refund a payment that is not completed', function (): void {
    $admin = adminUser();
    $user  = customerUser();
    ['invoice' => $invoice] = placeOrder($user);

    $payment = app(ProcessMockPaymentAction::class)->execute($invoice, simulateFailure: true);

    $this->actingAs($admin)
        ->post(route('admin.payments.refund', $payment), ['reason' => 'Test reason'])
        ->assertSessionHasErrors('payment');

    expect($payment->fresh()->status)->not->toBe(PaymentStatus::ManualRefund);
});

it('shows the manual-refund warning text on the admin payments page', function (): void {
    $admin = adminUser();
    completedPaymentForRefundTest();

    $this->actingAs($admin)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertSee(__('panel.admin.refund_warning'));
});
