<?php

declare(strict_types=1);

use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Integrations\Services\MailConfigurator;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Transactional mail can be pointed at an admin-managed SMTP server
 * (/admin/integrace), the same vault the payment gateways read — with .env as
 * the fallback.
 */

function activeSmtp(array $credentials): void
{
    IntegrationSetting::create([
        'provider'    => 'smtp',
        'label'       => 'SMTP',
        'credentials' => $credentials,
        'is_active'   => true,
        'mock_mode'   => false,
        'dry_run'     => false,
    ]);
}

it('applies active admin SMTP settings to the mailer', function (): void {
    activeSmtp(['host' => 'mail.firma.cz', 'port' => '2525', 'username' => 'user', 'password' => 'secret']);

    app(MailConfigurator::class)->apply();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('mail.firma.cz')
        ->and(config('mail.mailers.smtp.port'))->toBe(2525)
        ->and(config('mail.mailers.smtp.username'))->toBe('user');
});

it('leaves .env mail config untouched when there is no active SMTP integration', function (): void {
    config(['mail.default' => 'array', 'mail.mailers.smtp.host' => 'env.host']);

    app(MailConfigurator::class)->apply();

    expect(config('mail.default'))->toBe('array')
        ->and(config('mail.mailers.smtp.host'))->toBe('env.host');
});

it('does not override when the SMTP integration is inactive', function (): void {
    IntegrationSetting::create([
        'provider'    => 'smtp',
        'label'       => 'SMTP',
        'credentials' => ['host' => 'should.not.be.used'],
        'is_active'   => false,
        'mock_mode'   => true,
        'dry_run'     => true,
    ]);
    config(['mail.mailers.smtp.host' => 'env.host']);

    app(MailConfigurator::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe('env.host');
});

it('ignores an active SMTP integration that has no host', function (): void {
    activeSmtp(['username' => 'user']); // no host
    config(['mail.mailers.smtp.host' => 'env.host']);

    app(MailConfigurator::class)->apply();

    expect(config('mail.mailers.smtp.host'))->toBe('env.host');
});
