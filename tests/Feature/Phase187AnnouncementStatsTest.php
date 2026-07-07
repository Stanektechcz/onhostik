<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AnnouncementStatsController;
use App\Domains\Communication\Models\SystemAnnouncement;

test('announcement stats controller exists', function (): void {
    expect(class_exists(AnnouncementStatsController::class))->toBeTrue();
});

test('announcement stats index route exists', function (): void {
    expect(Route::has('admin.announcement-stats.index'))->toBeTrue();
});

test('announcement stats show route exists', function (): void {
    expect(Route::has('admin.announcement-stats.show'))->toBeTrue();
});

test('announcement stats index loads for admin', function (): void {
    $admin = adminUser();
    $response = $this->actingAs($admin)->get(route('admin.announcement-stats.index'));
    $response->assertOk();
});

test('announcement stats show loads for admin', function (): void {
    $admin = adminUser();
    $announcement = SystemAnnouncement::create([
        'title'        => 'Test Announcement',
        'body'         => 'Test body',
        'type'         => 'info',
        'send_email'   => false,
        'is_published' => true,
        'created_by'   => $admin->id,
    ]);
    $response = $this->actingAs($admin)->get(route('admin.announcement-stats.show', $announcement));
    $response->assertOk();
});
