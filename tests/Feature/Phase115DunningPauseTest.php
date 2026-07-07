<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Models\Invoice;
use Database\Factories\InvoiceFactory;

// ── PauseDunningAction via controller ─────────────────────────────────────────

it('admin can pause dunning on an open invoice', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $invoice = InvoiceFactory::new()->create([
        'customer_id' => $user->customer->id,
        'status'      => InvoiceStatus::Sent,
    ]);

    $this->actingAs($admin)
         ->post(route('admin.invoices.pause-dunning', $invoice), ['days' => 14])
         ->assertRedirect()
         ->assertSessionHas('status');

    $invoice->refresh();
    expect($invoice->dunning_paused_until)->not->toBeNull()
        ->and($invoice->dunning_paused_until->isFuture())->toBeTrue()
        ->and($invoice->dunning_paused_until->gte(now()->addDays(13)))->toBeTrue();
});

it('admin can resume dunning on a paused invoice', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $invoice = InvoiceFactory::new()->create([
        'customer_id'          => $user->customer->id,
        'status'               => InvoiceStatus::Overdue,
        'dunning_paused_until' => now()->addDays(10),
    ]);

    $this->actingAs($admin)
         ->post(route('admin.invoices.resume-dunning', $invoice))
         ->assertRedirect()
         ->assertSessionHas('status');

    $invoice->refresh();
    expect($invoice->dunning_paused_until)->toBeNull();
});

it('pause dunning rejects invalid days value', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $invoice = InvoiceFactory::new()->create([
        'customer_id' => $user->customer->id,
        'status'      => InvoiceStatus::Sent,
    ]);

    $this->actingAs($admin)
         ->post(route('admin.invoices.pause-dunning', $invoice), ['days' => 99])
         ->assertSessionHasErrors('days');
});

it('pause dunning is blocked on a paid invoice', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $invoice = InvoiceFactory::new()->create([
        'customer_id' => $user->customer->id,
        'status'      => InvoiceStatus::Paid,
    ]);

    $this->actingAs($admin)
         ->post(route('admin.invoices.pause-dunning', $invoice), ['days' => 7])
         ->assertSessionHasErrors('invoice');
});

it('invoice show page renders dunning pause form for open invoice', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $invoice = InvoiceFactory::new()->create([
        'customer_id'          => $user->customer->id,
        'status'               => InvoiceStatus::Sent,
        'dunning_paused_until' => null,
    ]);

    $this->actingAs($admin)
         ->get(route('admin.invoices.show', $invoice))
         ->assertOk()
         ->assertSee('Pozastavit upomínání');
});

it('invoice show page renders resume dunning button when paused', function (): void {
    $admin   = adminUser();
    $user    = customerUser();
    $invoice = InvoiceFactory::new()->create([
        'customer_id'          => $user->customer->id,
        'status'               => InvoiceStatus::Overdue,
        'dunning_paused_until' => now()->addDays(7),
    ]);

    $this->actingAs($admin)
         ->get(route('admin.invoices.show', $invoice))
         ->assertOk()
         ->assertSee('Obnovit upomínání')
         ->assertSee('Upomínání do');
});
