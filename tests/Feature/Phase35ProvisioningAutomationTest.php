<?php

declare(strict_types=1);

use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Jobs\ProvisionHostingServiceJob;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Domains\Provisioning\Services\DriverResolver;
use App\Jobs\DeliverWebhookJob;
use App\Models\OutgoingWebhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookDispatcher;
use Database\Seeders\IntegrationSeeder;
use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class, IntegrationSeeder::class]);
});

// ── Auto-retry on failure ──────────────────────────────────────────────────────

it('dispatches delayed retry job when provisioning fails but attempts remain', function (): void {
    Queue::fake();

    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'status' => ServiceStatus::Pending,
    ]);
    ProvisioningTask::create([
        'service_id'   => $service->id,
        'operation'    => 'create',
        'status'       => TaskStatus::Pending,
        'attempts'     => 0,
        'max_attempts' => 3,
        'payload'      => ['simulate_failure' => true],
    ]);

    $job = new ProvisionHostingServiceJob($service->id);
    $job->handle(app(DriverResolver::class), app(WebhookDispatcher::class));

    $task = ProvisioningTask::where('service_id', $service->id)->latest('id')->first();

    expect($task->status)->toBe(TaskStatus::Retrying);

    Queue::assertPushed(ProvisionHostingServiceJob::class);
});

it('sets task to Failed when attempts are exhausted and max_attempts reached', function (): void {
    Queue::fake();

    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'status' => ServiceStatus::Pending,
    ]);
    // Already at max attempts
    ProvisioningTask::create([
        'service_id'   => $service->id,
        'operation'    => 'create',
        'status'       => TaskStatus::Pending,
        'attempts'     => 2,
        'max_attempts' => 3,
        'payload'      => ['simulate_failure' => true],
    ]);

    $job = new ProvisionHostingServiceJob($service->id);
    $job->handle(app(DriverResolver::class), app(WebhookDispatcher::class));

    $task = ProvisioningTask::where('service_id', $service->id)->latest('id')->first();

    // 3rd attempt fails — no more retries → will park to ManualReview on next resolveTask
    expect($task->status)->toBe(TaskStatus::Failed);
    // No additional retry job dispatched
    Queue::assertNotPushed(ProvisionHostingServiceJob::class);
});

it('parks task to ManualReview when attempts exhausted', function (): void {
    Queue::fake();

    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'status' => ServiceStatus::Pending,
    ]);
    // Already failed at max_attempts
    ProvisioningTask::create([
        'service_id'   => $service->id,
        'operation'    => 'create',
        'status'       => TaskStatus::Failed,
        'attempts'     => 3,
        'max_attempts' => 3,
        'payload'      => [],
    ]);

    // Running the job again triggers resolveTask() which parks to ManualReview
    $job = new ProvisionHostingServiceJob($service->id);
    $job->handle(app(DriverResolver::class), app(WebhookDispatcher::class));

    $task = ProvisioningTask::where('service_id', $service->id)->latest('id')->first();

    expect($task->status)->toBe(TaskStatus::ManualReview);
});

// ── Provisioning failed notification ──────────────────────────────────────────

it('sends ProvisioningFailedNotification to admins when task reaches ManualReview', function (): void {
    Notification::fake();
    Queue::fake();

    $admin = adminUser();

    $service = \App\Domains\Provisioning\Models\Service::factory()->create([
        'status' => ServiceStatus::Pending,
    ]);
    // Exhausted — next run will park to ManualReview and fire notification
    ProvisioningTask::create([
        'service_id'   => $service->id,
        'operation'    => 'create',
        'status'       => TaskStatus::Failed,
        'attempts'     => 3,
        'max_attempts' => 3,
        'payload'      => [],
    ]);

    $job = new ProvisionHostingServiceJob($service->id);
    $job->handle(app(DriverResolver::class), app(WebhookDispatcher::class));

    Notification::assertSentTo($admin, \App\Notifications\ProvisioningFailedNotification::class);
});

// ── OutgoingWebhook model ──────────────────────────────────────────────────────

it('OutgoingWebhook subscribesTo returns true for matching event', function (): void {
    $webhook = OutgoingWebhook::create([
        'name'      => 'Test',
        'url'       => 'https://example.com/hook',
        'secret'    => 'abc',
        'events'    => ['service.provisioned', 'invoice.paid'],
        'is_active' => true,
    ]);

    expect($webhook->subscribesTo('service.provisioned'))->toBeTrue()
        ->and($webhook->subscribesTo('invoice.paid'))->toBeTrue()
        ->and($webhook->subscribesTo('ticket.created'))->toBeFalse();
});

it('OutgoingWebhook with wildcard subscribes to any event', function (): void {
    $webhook = OutgoingWebhook::create([
        'name'      => 'Wildcard',
        'url'       => 'https://example.com/hook',
        'secret'    => '',
        'events'    => ['*'],
        'is_active' => true,
    ]);

    expect($webhook->subscribesTo('anything.at.all'))->toBeTrue();
});

// ── WebhookDispatcher ─────────────────────────────────────────────────────────

it('WebhookDispatcher dispatches DeliverWebhookJob for active subscribed webhook', function (): void {
    Queue::fake();

    OutgoingWebhook::create([
        'name'      => 'Active',
        'url'       => 'https://example.com/hook',
        'secret'    => '',
        'events'    => ['service.provisioned'],
        'is_active' => true,
    ]);

    app(WebhookDispatcher::class)->dispatch('service.provisioned', ['service_id' => 1]);

    Queue::assertPushed(DeliverWebhookJob::class, function ($job): bool {
        return $job->event === 'service.provisioned';
    });
});

it('WebhookDispatcher skips inactive webhooks', function (): void {
    Queue::fake();

    OutgoingWebhook::create([
        'name'      => 'Inactive',
        'url'       => 'https://example.com/hook',
        'secret'    => '',
        'events'    => ['service.provisioned'],
        'is_active' => false,
    ]);

    app(WebhookDispatcher::class)->dispatch('service.provisioned', []);

    Queue::assertNotPushed(DeliverWebhookJob::class);
});

it('WebhookDispatcher skips webhooks not subscribed to the event', function (): void {
    Queue::fake();

    OutgoingWebhook::create([
        'name'      => 'Other',
        'url'       => 'https://example.com/hook',
        'secret'    => '',
        'events'    => ['invoice.paid'],
        'is_active' => true,
    ]);

    app(WebhookDispatcher::class)->dispatch('service.provisioned', []);

    Queue::assertNotPushed(DeliverWebhookJob::class);
});

// ── DeliverWebhookJob ─────────────────────────────────────────────────────────

it('DeliverWebhookJob creates a delivered WebhookDelivery on HTTP 200', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    $webhook = OutgoingWebhook::create([
        'name'      => 'Receiver',
        'url'       => 'https://example.com/hook',
        'secret'    => 'mysecret',
        'events'    => ['*'],
        'is_active' => true,
    ]);

    (new DeliverWebhookJob($webhook->id, 'service.provisioned', ['service_id' => 42]))->handle();

    $delivery = WebhookDelivery::where('outgoing_webhook_id', $webhook->id)->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->status)->toBe('delivered')
        ->and($delivery->response_code)->toBe(200)
        ->and($delivery->event)->toBe('service.provisioned');
});

it('DeliverWebhookJob creates a failed WebhookDelivery on HTTP 500', function (): void {
    Http::fake(['*' => Http::response('server error', 500)]);

    $webhook = OutgoingWebhook::create([
        'name'      => 'Bad',
        'url'       => 'https://example.com/hook',
        'secret'    => '',
        'events'    => ['*'],
        'is_active' => true,
    ]);

    (new DeliverWebhookJob($webhook->id, 'service.failed', []))->handle();

    $delivery = WebhookDelivery::where('outgoing_webhook_id', $webhook->id)->first();

    expect($delivery->status)->toBe('failed')
        ->and($delivery->response_code)->toBe(500);
});

it('DeliverWebhookJob skips inactive webhook', function (): void {
    Http::fake();

    $webhook = OutgoingWebhook::create([
        'name'      => 'Off',
        'url'       => 'https://example.com/hook',
        'secret'    => '',
        'events'    => ['*'],
        'is_active' => false,
    ]);

    (new DeliverWebhookJob($webhook->id, 'test', []))->handle();

    Http::assertNothingSent();
    expect(WebhookDelivery::count())->toBe(0);
});

// ── Admin webhook CRUD ─────────────────────────────────────────────────────────

it('admin webhook index returns 200', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.outgoing-webhooks.index'))
        ->assertOk()
        ->assertViewIs('admin.webhooks.index');
});

it('admin can create a webhook', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
        ->post(route('admin.outgoing-webhooks.store'), [
            'name'      => 'CI System',
            'url'       => 'https://ci.example.com/hook',
            'secret'    => 'test_secret',
            'events'    => ['service.provisioned'],
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.outgoing-webhooks.index'));

    expect(OutgoingWebhook::where('name', 'CI System')->exists())->toBeTrue();
});

it('admin can delete a webhook', function (): void {
    $admin = adminUser();
    $webhook = OutgoingWebhook::create([
        'name'      => 'To Delete',
        'url'       => 'https://example.com',
        'secret'    => '',
        'events'    => ['*'],
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.outgoing-webhooks.destroy', $webhook))
        ->assertRedirect(route('admin.outgoing-webhooks.index'));

    expect(OutgoingWebhook::find($webhook->id))->toBeNull();
});

// ── Admin provisioning detail view ────────────────────────────────────────────

it('admin provisioning show returns 200', function (): void {
    $admin   = adminUser();
    $service = \App\Domains\Provisioning\Models\Service::factory()->create();
    $task    = ProvisioningTask::create([
        'service_id'   => $service->id,
        'operation'    => 'create',
        'status'       => TaskStatus::Success,
        'attempts'     => 1,
        'max_attempts' => 3,
        'payload'      => [],
        'finished_at'  => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.provisioning.show', $task))
        ->assertOk()
        ->assertViewIs('admin.provisioning-show')
        ->assertSee('create');
});
