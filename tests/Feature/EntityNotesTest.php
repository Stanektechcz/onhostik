<?php

declare(strict_types=1);

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Order;
use App\Models\EntityNote;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

/**
 * Audit 75 — one polymorphic internal-notes mechanism reused across entities.
 * Orders and invoices had no notes; now both adopt the same trait, component
 * and controller.
 */

it('attaches a note to an order and reads it back through the trait', function (): void {
    $order = placeOrder(customerUser())['order'];
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.entity-notes.store', ['order', $order->getRouteKey()]), ['body' => 'Zavolat zákazníkovi'])
        ->assertRedirect();

    expect($order->entityNotes()->count())->toBe(1)
        ->and($order->entityNotes->first()->body)->toBe('Zavolat zákazníkovi')
        ->and($order->entityNotes->first()->author_id)->toBe($admin->id);
});

it('works for invoices through the same endpoint', function (): void {
    $invoice = Invoice::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('admin.entity-notes.store', ['invoice', $invoice->getRouteKey()]), ['body' => 'Sporná položka'])
        ->assertRedirect();

    expect($invoice->entityNotes()->count())->toBe(1);
});

it('orders pinned notes first, then newest', function (): void {
    $order = placeOrder(customerUser())['order'];

    // Auto-timestamps overwrite a created_at passed to create(), so set the
    // ordering explicitly after the fact.
    $mk = function (string $body, bool $pinned, $when) use ($order): void {
        $note = EntityNote::create([
            'notable_type' => $order->getMorphClass(),
            'notable_id'   => $order->id,
            'body'         => $body,
            'is_pinned'    => $pinned,
        ]);
        $note->forceFill(['created_at' => $when])->save();
    };

    $mk('old', false, now()->subDay());
    $mk('newest', false, now());
    $mk('pinned', true, now()->subWeek());

    expect($order->entityNotes->pluck('body')->all())->toBe(['pinned', 'newest', 'old']);
});

it('toggles a note’s pinned state', function (): void {
    $order = placeOrder(customerUser())['order'];
    $note  = EntityNote::create(['notable_type' => $order->getMorphClass(), 'notable_id' => $order->id, 'body' => 'x']);

    $this->actingAs(adminUser())
        ->post(route('admin.entity-notes.pin', ['order', $order->getRouteKey(), $note]))
        ->assertRedirect();

    expect($note->fresh()->is_pinned)->toBeTrue();
});

it('deletes a note', function (): void {
    $order = placeOrder(customerUser())['order'];
    $note  = EntityNote::create(['notable_type' => $order->getMorphClass(), 'notable_id' => $order->id, 'body' => 'x']);

    $this->actingAs(adminUser())
        ->delete(route('admin.entity-notes.destroy', ['order', $order->getRouteKey(), $note]))
        ->assertRedirect();

    expect(EntityNote::find($note->id))->toBeNull();
});

it('rejects an unknown notable type', function (): void {
    // Only whitelisted slugs may be addressed — no arbitrary morph target.
    $this->actingAs(adminUser())
        ->post(route('admin.entity-notes.store', ['user', 1]), ['body' => 'x'])
        ->assertNotFound();
});

it('refuses to act on a note through the wrong parent', function (): void {
    $orderA = placeOrder(customerUser())['order'];
    $orderB = placeOrder(customerUser())['order'];
    $note   = EntityNote::create(['notable_type' => $orderA->getMorphClass(), 'notable_id' => $orderA->id, 'body' => 'x']);

    // The note belongs to A; deleting it via B's URL must not work.
    $this->actingAs(adminUser())
        ->delete(route('admin.entity-notes.destroy', ['order', $orderB->getRouteKey(), $note]))
        ->assertNotFound();

    expect(EntityNote::find($note->id))->not->toBeNull();
});

it('forbids a non-admin from adding notes', function (): void {
    $order = placeOrder(customerUser())['order'];

    $this->actingAs(customerUser())
        ->post(route('admin.entity-notes.store', ['order', $order->getRouteKey()]), ['body' => 'x'])
        ->assertForbidden();
});

it('shows the notes card on the order detail', function (): void {
    $order = placeOrder(customerUser())['order'];
    EntityNote::create(['notable_type' => $order->getMorphClass(), 'notable_id' => $order->id, 'body' => 'Poznámka na kartě']);

    $this->actingAs(adminUser())
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Interní poznámky')
        ->assertSee('Poznámka na kartě');
});
