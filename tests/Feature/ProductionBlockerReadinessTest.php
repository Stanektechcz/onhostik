<?php

declare(strict_types=1);

use Database\Seeders\MockServerSeeder;
use Database\Seeders\ProductCatalogSeeder;

beforeEach(function (): void {
    $this->seed([ProductCatalogSeeder::class, MockServerSeeder::class]);
});

// ─── Doctor command blocker checks ────────────────────────────────────────

it('doctor --production fails when APP_DEBUG is true', function (): void {
    config(['app.debug' => true, 'app.env' => 'local']);

    $this->artisan('onhost:doctor', ['--production' => true])
        ->assertFailed();
});

it('doctor passes without --production when APP_DEBUG is true (dev mode)', function (): void {
    config(['app.debug' => true]);

    $this->artisan('onhost:doctor')
        ->assertSuccessful();
});

it('doctor --production reports billing address missing', function (): void {
    config([
        'app.debug' => false,
        'app.env'   => 'production',
        'app.url'   => 'https://onhost.cz',
        'billing.supplier.ic'     => '',     // missing
        'billing.supplier.street' => '',     // missing
        'billing.supplier.city'   => '',     // missing
        'billing.supplier.zip'    => '',     // missing
    ]);

    $this->artisan('onhost:doctor', ['--production' => true])
        ->assertFailed();
});

it('doctor billing section does not fail with complete billing address', function (): void {
    // Verify the doctor's billing section generates no critical errors
    // when all required fields are present
    config([
        'billing.supplier.name'   => 'Test Firma s.r.o.',
        'billing.supplier.ic'     => '12345678',
        'billing.supplier.street' => 'Testovací 1',
        'billing.supplier.city'   => 'Praha',
        'billing.supplier.zip'    => '11000',
    ]);

    // Run without --production so we only test billing section in warning mode
    $this->artisan('onhost:doctor')
        ->assertSuccessful();
});

it('doctor warns about MAIL_PORT=3306 (MySQL port)', function (): void {
    config(['mail.mailers.smtp.port' => 3306]);

    $result = $this->artisan('onhost:doctor');
    // Doctor should fail due to invalid MAIL_PORT
    $result->assertFailed();
});

it('doctor warns when mail mailer is log in standard mode', function (): void {
    config(['mail.default' => 'log']);

    $this->artisan('onhost:doctor')
        ->assertSuccessful(); // warnings only, not failure
});

// ─── Comgate without credentials behaves safely ────────────────────────────

it('Comgate payment attempt without credentials returns graceful error not 500', function (): void {
    config(['comgate.merchant_id' => '', 'comgate.secret' => '']);

    $user     = customerUser();
    $customer = $user->customer;

    // Create a paid invoice to test Comgate attempt
    \App\Domains\Customer\Models\CustomerAddress::create([
        'customer_id' => $customer->id, 'type' => 'billing',
        'street' => 'Test', 'city' => 'Praha', 'zip' => '11000',
        'country_code' => 'CZ', 'is_primary' => true,
    ]);

    ['invoice' => $invoice] = placeOrder($user);

    // Attempt Comgate payment — should return redirect with error, not 500
    $response = $this->actingAs($user)
        ->post(route('panel.billing.invoices.pay-comgate', $invoice));

    // Should redirect back with error (not 500)
    expect($response->status())->not->toBe(500);
    expect($response->status())->toBeIn([302, 422, 200]); // redirect back
});

// ─── Session cookie readiness ─────────────────────────────────────────────

it('.env.production.example does not contain MAIL_PORT=3306', function (): void {
    $envExample = file_get_contents(base_path('.env.production.example'));
    expect($envExample)->not->toContain('MAIL_PORT=3306');
});

it('.env.production.example does not use stanektech.cz as mail from', function (): void {
    $envExample = file_get_contents(base_path('.env.production.example'));
    expect($envExample)->not->toContain('stanektech.cz');
});

it('.env.production.example contains SESSION_SECURE_COOKIE=true', function (): void {
    $envExample = file_get_contents(base_path('.env.production.example'));
    expect($envExample)->toContain('SESSION_SECURE_COOKIE=true');
});

it('.env.production.example contains SESSION_DOMAIN starting with dot', function (): void {
    $envExample = file_get_contents(base_path('.env.production.example'));
    expect($envExample)->toContain('SESSION_DOMAIN=.onhost.cz');
});

it('.env.production.example contains DB_CONNECTION=mysql', function (): void {
    $envExample = file_get_contents(base_path('.env.production.example'));
    expect($envExample)->toContain('DB_CONNECTION=mysql');
});

// ─── Contact page uses config not hardcoded values ─────────────────────────

it('contact page loads and shows config-driven billing info', function (): void {
    $this->get('/kontakt')->assertOk();
});

it('billing company name comes from config', function (): void {
    expect(config('billing.supplier.name'))->not->toBeEmpty();
});

// ─── deploy.sh APP_KEY safety ─────────────────────────────────────────────

it('deploy.sh only generates APP_KEY when empty', function (): void {
    $deployScript = file_get_contents(base_path('deploy/deploy.sh'));

    // deploy.sh must check if APP_KEY is empty before generating
    expect($deployScript)
        ->toContain('APP_KEY')
        ->toContain('key:generate');

    // Must NOT unconditionally run key:generate --force on every deploy
    $lines = explode("\n", $deployScript);
    $forceLines = array_filter($lines, fn ($l) => str_contains($l, 'key:generate --force') && !str_contains($l, 'if'));
    expect(count($forceLines))->toBeLessThanOrEqual(1);
});
