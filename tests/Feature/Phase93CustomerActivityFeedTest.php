<?php

declare(strict_types=1);

use App\Domains\Billing\Enums\InvoiceStatus;
use App\Domains\Billing\Enums\OrderStatus;
use App\Domains\Billing\Enums\PaymentStatus;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function insertPayment(int $customerId, string $status = 'completed', int $amount = 50000): int
{
    return DB::table('payments')->insertGetId([
        'uuid'        => Str::uuid(),
        'customer_id' => $customerId,
        'method'      => 'credit',
        'status'      => $status,
        'currency'    => 'CZK',
        'amount'      => $amount,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
}

function insertOrder(int $customerId, string $status = 'active', int $total = 100000): int
{
    return DB::table('orders')->insertGetId([
        'uuid'         => Str::uuid(),
        'customer_id'  => $customerId,
        'status'       => $status,
        'currency'     => 'CZK',
        'subtotal'     => $total,
        'tax_amount'   => 0,
        'total'        => $total,
        'vat_scenario' => 'b2c_cz',
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);
}

// ── Access control ─────────────────────────────────────────────────────────────

it('guest cannot access activity feed', function (): void {
    $this->get(route('panel.activity-feed'))
        ->assertRedirect(route('login'));
});

it('customer can view activity feed', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee('Historie aktivit');
});

it('activity feed shows empty state when no events', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee('Žádné aktivity k zobrazení');
});

// ── Invoice events ─────────────────────────────────────────────────────────────

it('activity feed shows invoice created event', function (): void {
    $user    = customerUser();
    $invoice = Invoice::factory()->create([
        'customer_id' => $user->customer->id,
        'number'      => 'INV-2024-001',
        'status'      => InvoiceStatus::Sent,
    ]);

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee("Faktura #{$invoice->number} vystavena");
});

it('activity feed shows invoice paid event when paid_at is set', function (): void {
    $user    = customerUser();
    $invoice = Invoice::factory()->create([
        'customer_id' => $user->customer->id,
        'number'      => 'INV-2024-002',
        'status'      => InvoiceStatus::Paid,
        'paid_at'     => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk();

    $response->assertSee("Faktura #{$invoice->number} vystavena");
    $response->assertSee("Faktura #{$invoice->number} zaplacena");
});

// ── Payment events ─────────────────────────────────────────────────────────────

it('activity feed shows completed payment event', function (): void {
    $user = customerUser();
    insertPayment($user->customer->id, 'completed');

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee('Platba přijata');
});

it('activity feed shows failed payment event', function (): void {
    $user = customerUser();
    insertPayment($user->customer->id, 'failed');

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee('Platba selhala');
});

// ── Order events ──────────────────────────────────────────────────────────────

it('activity feed shows order created event', function (): void {
    $user    = customerUser();
    $orderId = insertOrder($user->customer->id, 'active');

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee("Objednávka #{$orderId} vytvořena");
});

// ── Ticket events ─────────────────────────────────────────────────────────────

it('activity feed shows ticket opened event', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'subject'     => 'Problém s platbou',
        'status'      => TicketStatus::Open,
    ]);

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee("Ticket #{$ticket->id} otevřen");
});

it('activity feed shows ticket closed event when closed_at is set', function (): void {
    $user   = customerUser();
    $ticket = SupportTicket::factory()->create([
        'customer_id' => $user->customer->id,
        'subject'     => 'Vyřešeno',
        'status'      => TicketStatus::Closed,
        'closed_at'   => now(),
    ]);

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee("Ticket #{$ticket->id} uzavřen");
});

// ── Service events ────────────────────────────────────────────────────────────

it('activity feed shows service created event', function (): void {
    $user = customerUser();
    Service::factory()->create([
        'customer_id' => $user->customer->id,
        'status'      => ServiceStatus::Active,
    ]);

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee('Služba zřízena');
});

// ── Filters ───────────────────────────────────────────────────────────────────

it('filter=invoices shows only invoice events', function (): void {
    $user = customerUser();
    $invoice = Invoice::factory()->create([
        'customer_id' => $user->customer->id,
        'number'      => 'INV-F-001',
        'status'      => InvoiceStatus::Sent,
    ]);
    insertPayment($user->customer->id, 'completed');

    $response = $this->actingAs($user)
        ->get(route('panel.activity-feed', ['filter' => 'invoices']))
        ->assertOk();

    $response->assertSee('Faktura #INV-F-001 vystavena');
    $response->assertDontSee('Platba přijata');
});

it('filter=payments shows only payment events', function (): void {
    $user = customerUser();
    Invoice::factory()->create([
        'customer_id' => $user->customer->id,
        'number'      => 'INV-P-001',
        'status'      => InvoiceStatus::Sent,
    ]);
    insertPayment($user->customer->id, 'completed');

    $response = $this->actingAs($user)
        ->get(route('panel.activity-feed', ['filter' => 'payments']))
        ->assertOk();

    $response->assertSee('Platba přijata');
    $response->assertDontSee('Faktura #INV-P-001 vystavena');
});

it('filter buttons are rendered', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertSee('Faktury')
        ->assertSee('Platby')
        ->assertSee('Objednávky')
        ->assertSee('Tikety')
        ->assertSee('Služby')
        ->assertSee('Kredit');
});

it('customer only sees their own events', function (): void {
    $user1 = customerUser();
    $user2 = customerUser();
    Invoice::factory()->create([
        'customer_id' => $user2->customer->id,
        'number'      => 'PRIVATE-001',
        'status'      => InvoiceStatus::Sent,
    ]);

    $this->actingAs($user1)
        ->get(route('panel.activity-feed'))
        ->assertOk()
        ->assertDontSee('Faktura #PRIVATE-001');
});
