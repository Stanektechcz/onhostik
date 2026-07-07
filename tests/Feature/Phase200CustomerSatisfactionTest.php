<?php

use App\Domains\Customer\Models\Customer;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('admin can view CSAT dashboard', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.metrics.csat'))
        ->assertOk()
        ->assertViewIs('admin.csat.index');
});

it('CSAT page shows overall stats', function (): void {
    $customer = Customer::factory()->create();
    SupportTicket::factory()->create([
        'customer_id'   => $customer->id,
        'status'        => TicketStatus::Closed,
        'csat_score'    => 5,
        'csat_rated_at' => now(),
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.metrics.csat'))
        ->assertOk()
        ->assertViewHas('overall');
});

it('CSAT page passes byRating data', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.metrics.csat'))
        ->assertOk()
        ->assertViewHas('byRating');
});

it('CSAT page passes monthly data', function (): void {
    $this->actingAs(adminUser())
        ->get(route('admin.metrics.csat'))
        ->assertOk()
        ->assertViewHas('monthly');
});

it('CSAT page passes recent comments', function (): void {
    $customer = Customer::factory()->create();
    SupportTicket::factory()->create([
        'customer_id'   => $customer->id,
        'status'        => TicketStatus::Closed,
        'csat_score'    => 4,
        'csat_comment'  => 'Rychlé vyřízení.',
        'csat_rated_at' => now(),
    ]);

    $this->actingAs(adminUser())
        ->get(route('admin.metrics.csat'))
        ->assertOk()
        ->assertViewHas('recentComments');
});
