<?php

declare(strict_types=1);

use App\Http\Controllers\Panel\ServiceUsageAlertController;
use App\Notifications\ServiceUsageAlertNotification;

test('service usage alert controller exists', function (): void {
    expect(class_exists(ServiceUsageAlertController::class))->toBeTrue();
});

test('service usage alert notification exists', function (): void {
    expect(class_exists(ServiceUsageAlertNotification::class))->toBeTrue();
});

test('service usage alert route exists', function (): void {
    expect(Route::has('panel.services.usage-alert.update'))->toBeTrue();
});

test('service usage alert migration adds threshold column', function (): void {
    expect(\Schema::hasColumn('services', 'usage_alert_threshold'))->toBeTrue();
});

test('service usage alert notification has correct properties', function (): void {
    $service = \App\Domains\Provisioning\Models\Service::factory()->create();
    $notification = new ServiceUsageAlertNotification($service, 85);
    expect($notification->service->id)->toBe($service->id);
    expect($notification->usagePct)->toBe(85);
});
