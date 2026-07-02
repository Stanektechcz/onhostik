<?php

declare(strict_types=1);

use App\Domains\Backups\Enums\BackupJobStatus;
use App\Domains\Backups\Jobs\RunBackupJob;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Models\Service;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

it('RunBackupJob fails safely (no uncaught exception) when no real provider is configured', function (): void {
    config(['provisioning.mock_mode' => false]);

    $user = customerUser();
    ['order' => $order] = placeOrder($user);
    $orderItem = $order->items()->with('pricingPlan')->first();

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'order_item_id'       => $orderItem->id,
        'product_id'          => $orderItem->pricingPlan->product_id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'backup-safety.onhost.cz',
    ]);

    $job = BackupJob::create([
        'service_id' => $service->id,
        'type'       => 'manual',
        'status'     => BackupJobStatus::Pending,
    ]);

    // Must not throw — this call would previously bubble an uncaught
    // ProvisioningException straight out of handle().
    (new RunBackupJob($job->id))->handle();

    $job->refresh();
    expect($job->status)->toBe(BackupJobStatus::Failed)
        ->and($job->error_message)->not->toBeNull()
        ->and($job->started_at)->not->toBeNull()
        ->and($job->finished_at)->not->toBeNull();

    expect(Activity::where('log_name', 'backup')
        ->where('description', 'backup.failed')
        ->where('subject_id', $job->id)
        ->exists())->toBeTrue();
});

it('RunBackupJob still completes normally in mock mode', function (): void {
    config(['provisioning.mock_mode' => true]);

    $user = customerUser();
    ['order' => $order] = placeOrder($user);
    $orderItem = $order->items()->with('pricingPlan')->first();

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'order_item_id'       => $orderItem->id,
        'product_id'          => $orderItem->pricingPlan->product_id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'backup-mock-ok.onhost.cz',
    ]);

    $job = BackupJob::create([
        'service_id' => $service->id,
        'type'       => 'manual',
        'status'     => BackupJobStatus::Pending,
    ]);

    (new RunBackupJob($job->id))->handle();

    expect($job->refresh()->status)->toBe(BackupJobStatus::Success);
});

it('admin monitoring page labels mock data as mock, not real SLA', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.monitoring.index'))
        ->assertOk()
        ->assertSee(__('panel.admin.monitoring_mock_note'));
});

it('customer service page labels mock monitoring data when a monitor exists', function (): void {
    $user = customerUser();
    ['order' => $order] = placeOrder($user);
    $orderItem = $order->items()->with('pricingPlan')->first();

    $service = Service::create([
        'customer_id'         => $user->customer->id,
        'order_item_id'       => $orderItem->id,
        'product_id'          => $orderItem->pricingPlan->product_id,
        'provisioning_driver' => ProvisioningDriver::AAPanel,
        'status'              => ServiceStatus::Active,
        'label'               => 'monitor-mock-label.onhost.cz',
    ]);

    app(\App\Domains\Provisioning\Services\ServiceActivationHooks::class)->handle($service);

    $this->actingAs($user)
        ->get(route('panel.services.show', $service))
        ->assertOk()
        ->assertSee(__('panel.services.monitoring_mock_note'));
});
