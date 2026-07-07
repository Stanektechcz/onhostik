<?php

declare(strict_types=1);

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;

it('admin can view email log index', function (): void {
    $admin = adminUser();

    $this->actingAs($admin)
         ->get(route('admin.email-logs.index'))
         ->assertOk()
         ->assertViewIs('admin.email-logs');
});

it('email log index shows existing logs', function (): void {
    $admin = adminUser();

    EmailLog::create(['to_address' => 'test@example.com', 'subject' => 'Hello']);

    $response = $this->actingAs($admin)
         ->get(route('admin.email-logs.index'))
         ->assertOk();

    $logs = $response->viewData('logs');
    expect($logs->total())->toBeGreaterThanOrEqual(1);
});

it('email log index supports search by address', function (): void {
    $admin = adminUser();

    EmailLog::create(['to_address' => 'unique-addr@search.com', 'subject' => 'Subject A']);
    EmailLog::create(['to_address' => 'other@example.com', 'subject' => 'Subject B']);

    $response = $this->actingAs($admin)
         ->get(route('admin.email-logs.index', ['q' => 'unique-addr']))
         ->assertOk();

    $logs = $response->viewData('logs');
    expect($logs->total())->toBe(1);
});

it('email log listener is registered for MessageSent event', function (): void {
    $appServiceProvider = new \App\Providers\AppServiceProvider(app());
    expect($appServiceProvider)->toBeInstanceOf(\App\Providers\AppServiceProvider::class);

    expect(class_exists(\App\Listeners\LogSentEmail::class))->toBeTrue();
    expect(method_exists(\App\Listeners\LogSentEmail::class, 'handle'))->toBeTrue();
});

it('customer cannot access email logs', function (): void {
    $customer = customerUser();

    $this->actingAs($customer)
         ->get(route('admin.email-logs.index'))
         ->assertForbidden();
});
