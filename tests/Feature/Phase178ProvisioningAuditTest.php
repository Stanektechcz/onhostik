<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ProvisioningAuditController;
use App\Models\ProvisioningAuditLog;

test('provisioning audit controller exists', function (): void {
    expect(class_exists(ProvisioningAuditController::class))->toBeTrue();
});

test('provisioning audit model exists', function (): void {
    expect(class_exists(ProvisioningAuditLog::class))->toBeTrue();
});

test('provisioning audit table exists', function (): void {
    expect(\Schema::hasTable('provisioning_audit_logs'))->toBeTrue();
});

test('provisioning audit route exists', function (): void {
    expect(Route::has('admin.services.provisioning-audit.index'))->toBeTrue();
});

test('provisioning audit log can be created', function (): void {
    $service = \App\Domains\Provisioning\Models\Service::factory()->create();
    $log = ProvisioningAuditLog::create([
        'service_id' => $service->id,
        'action'     => 'create',
        'driver'     => 'aapanel',
        'success'    => true,
    ]);
    expect($log->id)->toBeInt();
});
