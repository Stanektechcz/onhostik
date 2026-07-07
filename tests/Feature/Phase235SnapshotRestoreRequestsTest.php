<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('panel user can view snapshot restore requests', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.snapshot-restore-requests.index'))
        ->assertOk()
        ->assertViewHas('requests');
});

it('panel user can submit a restore request', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->post(route('panel.snapshot-restore-requests.store'), [
            'service_id'    => $service->id,
            'snapshot_id'   => 'snap-abc-123',
            'restore_point' => '2026-01-01 00:00:00',
            'customer_note' => 'Please restore',
        ])
        ->assertRedirect();

    expect(\App\Models\SnapshotRestoreRequest::count())->toBe(1);
});

it('restore request is created with pending status', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->post(route('panel.snapshot-restore-requests.store'), [
            'service_id'    => $service->id,
            'snapshot_id'   => 'snap-abc-123',
            'restore_point' => '2026-01-01 00:00:00',
            'customer_note' => 'Please restore',
        ]);

    expect(\App\Models\SnapshotRestoreRequest::first()->status)->toBe('pending');
});

it('guest cannot access restore requests', function (): void {
    $this->get(route('panel.snapshot-restore-requests.index'))
        ->assertRedirect();
});

it('restore request captures the requesting user id', function (): void {
    $user    = customerUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create(['customer_id' => $user->customer->id]);

    $this->actingAs($user)
        ->post(route('panel.snapshot-restore-requests.store'), [
            'service_id'    => $service->id,
            'snapshot_id'   => 'snap-abc-123',
            'restore_point' => '2026-01-01 00:00:00',
            'customer_note' => 'Please restore',
        ]);

    expect(\App\Models\SnapshotRestoreRequest::first()->user_id)->toBe($user->id);
});
