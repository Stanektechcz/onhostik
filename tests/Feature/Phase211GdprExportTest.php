<?php

use App\Jobs\GenerateGdprExport;
use App\Models\GdprExportRequest;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('customer can view the GDPR export page', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.gdpr.export.index'))
        ->assertOk()
        ->assertViewHas('exports');
});

it('customer can request a GDPR export and job is dispatched', function (): void {
    Queue::fake();

    $user = customerUser();

    $this->actingAs($user)
        ->post(route('panel.gdpr.export.store'))
        ->assertRedirect();

    Queue::assertPushed(GenerateGdprExport::class);
    expect(GdprExportRequest::where('user_id', $user->id)->where('status', 'queued')->exists())->toBeTrue();
});

it('customer cannot request while another export is pending', function (): void {
    Queue::fake();

    $user = customerUser();

    GdprExportRequest::create([
        'user_id' => $user->id,
        'status'  => 'processing',
    ]);

    $this->actingAs($user)
        ->post(route('panel.gdpr.export.store'))
        ->assertSessionHasErrors('export');
});

it('download with invalid token returns redirect with error', function (): void {
    $user = customerUser();

    $this->actingAs($user)
        ->get(route('panel.gdpr.export.download', 'invalid-token-xyz'))
        ->assertRedirect(route('panel.gdpr.export.index'));
});

it('unauthenticated user cannot access GDPR export page', function (): void {
    $this->get(route('panel.gdpr.export.index'))
        ->assertRedirect();
});
