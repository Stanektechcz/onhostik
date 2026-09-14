<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Secrets\DbSecretStore;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/**
 * Stores one value of a platform secret that is not a provider instance (the pager's routing key, the Discord bot
 * token, the AI API key, the Peppol access point, the OIDC client secret, the Cloudflare CDN token): the `.env` names
 * the reference (`db://oncall/pager`), this command fills its keys from a hidden prompt (or STDIN), and only key names
 * are ever printed or audited. Provider credentials keep their own command (`onhost:integrations:secret`).
 */
final class StoreSecret extends Command
{
    protected $signature = 'onhost:secrets:set {ref : Secret reference from .env, e.g. db://oncall/pager} {key : Key inside the secret, e.g. routing_key} {--stdin : Read the value from standard input} {--remove : Remove the key instead}';

    protected $description = 'Store a key of a db:// platform secret (on-call, Discord, AI, Peppol, OIDC, CDN) with a hidden prompt — never an argument';

    public function handle(SecretStore $secrets, AuditRecorder $audit): int
    {
        $ref = SecretRef::parse((string) $this->argument('ref'));
        if ($ref->scheme !== 'db') {
            $this->error("Only db:// references are written here ({$ref} is {$ref->scheme}://); env:// values belong to the environment file, bao:// to OpenBao.");

            return self::FAILURE;
        }
        $key = strtolower(trim((string) $this->argument('key')));
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1) {
            $this->error('The key must look like routing_key.');

            return self::FAILURE;
        }
        $current = [];
        try {
            $current = $secrets->read($ref);
        } catch (\Throwable) {
            // nothing stored yet
        }
        if ($this->option('remove')) {
            unset($current[$key]);
        } else {
            $value = $this->option('stdin') ? trim((string) stream_get_contents(STDIN)) : (string) $this->secret("Value for {$key}");
            if ($value === '') {
                $this->warn('Nothing to store.');

                return self::SUCCESS;
            }
            $current[$key] = $value;
        }
        $secrets instanceof DbSecretStore ? $secrets->write($ref, $current, 'cli') : $secrets->write($ref, $current);
        $audit->record(CommandContext::system('cli:secrets:set'), 'secret.set', 'succeeded', ['ref' => (string) $ref, 'key' => $key, 'removed' => (bool) $this->option('remove')], 'secret', $ref->path);
        $this->info(($this->option('remove') ? "Removed {$key}" : "Stored {$key}")." in {$ref}; keys now: ".(implode(', ', array_keys($current)) ?: '—'));

        return self::SUCCESS;
    }
}
