<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\IntegrationSetting;
use Throwable;

/**
 * Applies admin-managed SMTP credentials (/admin/integrace → "SMTP") to the
 * mailer at runtime, so an operator can point transactional mail at their own
 * server without editing .env — consistent with how the payment gateways read
 * the credential vault.
 *
 * Only overrides when the SMTP integration is active AND a host is set;
 * otherwise the .env mail config stands. Wrapped in a guard so it is a no-op
 * before migrations (fresh install) instead of throwing during boot.
 */
final class MailConfigurator
{
    public function apply(): void
    {
        try {
            $smtp = IntegrationSetting::query()
                ->where('provider', 'smtp')
                ->where('is_active', true)
                ->first();
        } catch (Throwable) {
            // integration_settings table not migrated yet, or no DB — leave .env config.
            return;
        }

        if ($smtp === null) {
            return;
        }

        $credentials = $smtp->credentials;
        $host        = $credentials['host'] ?? '';

        if ($host === '') {
            return;
        }

        $port = ($credentials['port'] ?? '') !== '' ? (int) $credentials['port'] : 587;

        config([
            'mail.default'               => 'smtp',
            'mail.mailers.smtp.host'     => $host,
            'mail.mailers.smtp.port'     => $port,
            'mail.mailers.smtp.username' => ($credentials['username'] ?? '') ?: null,
            'mail.mailers.smtp.password' => ($credentials['password'] ?? '') ?: null,
        ]);
    }
}
