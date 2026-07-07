<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeCalendarService(array $attrs = []): array
{
    $user    = customerUser();
    $service = Service::factory()->create(array_merge([
        'customer_id'   => $user->customer->id,
        'status'        => ServiceStatus::Active,
        'label'         => 'Test Hosting',
        'next_due_date' => Carbon::parse('2027-01-15'),
    ], $attrs));
    return compact('user', 'service');
}

// ── Response format ───────────────────────────────────────────────────────────

it('calendar endpoint returns iCal content-type', function (): void {
    ['user' => $user] = makeCalendarService();

    $response = $this->actingAs($user)
                     ->get(route('panel.services.calendar'));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/calendar');
});

it('calendar response contains VCALENDAR wrapper', function (): void {
    ['user' => $user] = makeCalendarService();

    $response = $this->actingAs($user)
                     ->get(route('panel.services.calendar'));

    $content = $response->getContent();
    expect($content)->toContain('BEGIN:VCALENDAR')
                    ->toContain('END:VCALENDAR');
});

it('calendar response contains VEVENT for service with next_due_date', function (): void {
    ['user' => $user, 'service' => $service] = makeCalendarService([
        'label'         => 'Moje Hosting CZ',
        'next_due_date' => Carbon::parse('2027-03-10'),
    ]);

    $content = $this->actingAs($user)
                    ->get(route('panel.services.calendar'))
                    ->getContent();

    expect($content)->toContain('BEGIN:VEVENT')
                    ->toContain('END:VEVENT')
                    ->toContain('Moje Hosting CZ')
                    ->toContain('DTSTART;VALUE=DATE:20270310');
});

it('calendar excludes terminated services', function (): void {
    ['user' => $user] = makeCalendarService([
        'label'          => 'Terminated Service',
        'next_due_date'  => Carbon::parse('2027-05-01'),
        'terminated_at'  => now()->subDay(),
    ]);

    $content = $this->actingAs($user)
                    ->get(route('panel.services.calendar'))
                    ->getContent();

    expect($content)->not->toContain('Terminated Service');
});

it('calendar includes services without next_due_date in iCal but not as events', function (): void {
    $user    = customerUser();
    Service::factory()->create([
        'customer_id'   => $user->customer->id,
        'status'        => ServiceStatus::Active,
        'label'         => 'No Due Date Service',
        'next_due_date' => null,
    ]);

    $content = $this->actingAs($user)
                    ->get(route('panel.services.calendar'))
                    ->getContent();

    expect($content)->not->toContain('No Due Date Service');
});

it('calendar endpoint requires authentication', function (): void {
    $this->get(route('panel.services.calendar'))
         ->assertRedirect(route('login'));
});

it('services index shows export calendar link', function (): void {
    ['user' => $user] = makeCalendarService();

    $this->actingAs($user)
         ->get(route('panel.services.index'))
         ->assertOk()
         ->assertSee('Export do kalendáře');
});
