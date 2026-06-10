<?php

declare(strict_types=1);

use App\Domains\Billing\Models\PaymentWebhookLog;
use App\Domains\Customer\Models\Customer;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('serves public pages', function (string $uri): void {
    $this->get($uri)->assertOk();
})->with([
    '/',
    '/webhosting',
    '/gamehosting',
    '/vps',
    '/domeny',
    '/kontakt',
    '/o-nas',
    '/faq',
    '/obchodni-podminky',
    '/gdpr',
    '/cookies',
    '/sla',
    '/refundace',
    '/login',
    '/register',
]);

it('serves the health check endpoint', function (): void {
    $this->get('/up')->assertOk();
});

it('serves customer panel pages to authenticated customers', function (string $uri): void {
    Role::findOrCreate('customer', 'web');
    $user = User::factory()->create();
    $user->assignRole('customer');
    Customer::factory()->for($user)->create();

    $this->actingAs($user)->get($uri)->assertOk();
})->with([
    '/panel',
    '/panel/sluzby',
    '/panel/domeny',
    '/panel/objednavky',
    '/panel/objednavky/nova',
    '/panel/fakturace/faktury',
    '/panel/fakturace/platby',
    '/panel/fakturace/kredit',
    '/panel/ucet/profil',
    '/panel/ucet/fakturacni-udaje',
    '/panel/ucet/zabezpeceni',
    '/panel/podpora',
    '/panel/ai',
]);

it('redirects guests away from panel pages', function (string $uri): void {
    $this->get($uri)->assertRedirect('/login');
})->with([
    '/panel',
    '/panel/sluzby',
    '/panel/fakturace/kredit',
]);

it('serves admin pages to admins', function (string $uri): void {
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get($uri)->assertOk();
})->with([
    '/admin',
    '/admin/zakaznici',
    '/admin/objednavky',
    '/admin/faktury',
    '/admin/platby',
    '/admin/produkty',
    '/admin/sluzby',
    '/admin/servery',
    '/admin/provisioning',
    '/admin/audit',
    '/admin/nastaveni',
]);

it('forbids admin pages to customers', function (string $uri): void {
    Role::findOrCreate('customer', 'web');
    $user = User::factory()->create();
    $user->assignRole('customer');
    Customer::factory()->for($user)->create();

    $this->actingAs($user)->get($uri)->assertForbidden();
})->with([
    '/admin',
    '/admin/zakaznici',
    '/admin/nastaveni',
]);

it('accepts and logs comgate webhooks without processing them', function (): void {
    $response = $this->post('/api/webhooks/comgate', [
        'transId' => 'TEST-123',
        'secret'  => 'should-be-stripped',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('payment_webhook_logs', [
        'provider' => 'comgate',
        'event_id' => 'TEST-123',
    ]);

    $log = PaymentWebhookLog::firstOrFail();
    expect($log->payload)->not->toHaveKey('secret');
});
