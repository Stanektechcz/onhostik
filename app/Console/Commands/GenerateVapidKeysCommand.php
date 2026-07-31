<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Integrations\Models\IntegrationSetting;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Generates a VAPID keypair for web push and stores it in the admin credential
 * vault (provider "web_push"), turning push on. The private key is stored
 * encrypted and never printed — only the public key, which is safe to share.
 */
final class GenerateVapidKeysCommand extends Command
{
    protected $signature = 'webpush:vapid {--subject= : mailto: or https: contact URL for the VAPID JWT}';

    protected $description = 'Generate and store a VAPID keypair for web push notifications';

    public function handle(): int
    {
        if (IntegrationSetting::credentialsFor('web_push')['public_key'] ?? false) {
            if (! $this->confirm('Web push already has VAPID keys. Replace them? Existing subscriptions will stop working.', false)) {
                $this->info('Aborted — keys unchanged.');

                return self::SUCCESS;
            }
        }

        /** @var array{publicKey: string, privateKey: string} $keys */
        $keys = VAPID::createVapidKeys();

        $subject = (string) ($this->option('subject')
            ?: config('webpush.vapid.subject')
            ?: 'mailto:admin@onhost.cz');

        IntegrationSetting::firstOrNew(['provider' => 'web_push'])->fill([
            'label'       => 'Web push (VAPID)',
            'credentials' => [
                'subject'     => $subject,
                'public_key'  => $keys['publicKey'],
                'private_key' => $keys['privateKey'],
            ],
            'is_active' => true,
            'mock_mode' => false,
            'dry_run'   => false,
            'meta'      => ['category' => 'notifications'],
        ])->save();

        $this->info('VAPID keypair generated and stored in the admin credential vault (web_push). Push is now enabled.');
        $this->line('Public key (safe to share): ' . $keys['publicKey']);
        $this->line('Private key stored encrypted — not printed.');

        return self::SUCCESS;
    }
}
