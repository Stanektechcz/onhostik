<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('admin can view scheduled tasks / failed jobs page', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.scheduled-tasks.index'))
         ->assertOk()
         ->assertViewIs('admin.scheduled-tasks');
});

it('failed jobs page shows failed count', function (): void {
    $admin = adminUser();

    $response = $this->actingAs($admin)
         ->get(route('admin.scheduled-tasks.index'))
         ->assertOk();

    $response->assertViewHas('failedCount')
             ->assertViewHas('failedJobs');
});

it('admin can delete a failed job', function (): void {
    $admin = adminUser();

    $id = DB::table('failed_jobs')->insertGetId([
        'uuid'       => \Illuminate\Support\Str::uuid(),
        'connection' => 'sync',
        'queue'      => 'default',
        'payload'    => '{}',
        'exception'  => 'SomeException',
        'failed_at'  => now(),
    ]);

    $this->actingAs($admin)
         ->delete(route('admin.scheduled-tasks.retry', $id))
         ->assertRedirect();

    expect(DB::table('failed_jobs')->where('id', $id)->exists())->toBeFalse();
});

it('admin can clear all failed jobs', function (): void {
    $admin = adminUser();

    DB::table('failed_jobs')->insert([
        'uuid'       => \Illuminate\Support\Str::uuid(),
        'connection' => 'sync',
        'queue'      => 'default',
        'payload'    => '{}',
        'exception'  => 'SomeException',
        'failed_at'  => now(),
    ]);

    $this->actingAs($admin)
         ->delete(route('admin.scheduled-tasks.clear'))
         ->assertRedirect();

    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('customer cannot access scheduled tasks page', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('admin.scheduled-tasks.index'))
         ->assertForbidden();
});
