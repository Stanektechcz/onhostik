<?php

declare(strict_types=1);

use App\Console\Commands\OnhostDoctorCommand;

it('onhost:doctor exits 0 in the test environment', function (): void {
    // Standard mode (not --production): should pass even with dev defaults
    // because we only fail on hard critical misses (no APP_KEY, etc.)
    $this->artisan(OnhostDoctorCommand::class)->assertSuccessful();
});

it('onhost:doctor --production fails when APP_DEBUG is true', function (): void {
    config(['app.debug' => true]);

    $this->artisan(OnhostDoctorCommand::class, ['--production' => true])
        ->assertFailed();
});

it('onhost:doctor --production fails when queue driver is sync', function (): void {
    config([
        'app.debug' => false,
        'app.url'   => 'https://onhost.cz',
        'queue.default' => 'sync',
        'mail.default' => 'smtp',
    ]);

    $this->artisan(OnhostDoctorCommand::class, ['--production' => true])
        ->assertFailed();
});

it('onhost:doctor --production fails when Comgate merchant_id is missing', function (): void {
    config([
        'app.debug'            => false,
        'app.url'              => 'https://onhost.cz',
        'queue.default'        => 'database',
        'mail.default'         => 'smtp',
        'comgate.merchant_id'  => '',
        'comgate.secret'       => '',
    ]);

    $this->artisan(OnhostDoctorCommand::class, ['--production' => true])
        ->assertFailed();
});

it('onhost:doctor exits 0 in standard mode with production-like config (no critical errors)', function (): void {
    // In standard mode, infrastructure checks (symlink, mail server) are only warnings.
    // This test verifies that supplying all production credentials causes no critical failures.
    config([
        'app.debug'                              => false,
        'app.url'                                => 'https://onhost.cz',
        'app.key'                                => 'base64:' . base64_encode(random_bytes(32)),
        'queue.default'                          => 'database',
        'mail.default'                           => 'smtp',
        'mail.from.address'                      => 'noreply@onhost.cz',
        'comgate.merchant_id'                    => '123456',
        'comgate.secret'                         => 'secret-value',
        'comgate.test_mode'                      => false,
        'provisioning.mock_mode'                 => false,
        'provisioning.wedos.user'                => 'admin@example.cz',
        'provisioning.aapanel.allow_real_writes' => false,
        'provisioning.wedos.allow_real_writes'   => false,
    ]);

    // Standard mode (no --production): no critical failures expected
    $this->artisan(OnhostDoctorCommand::class)->assertSuccessful();
});
