<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Secrets\DbSecretStore;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/**
 * The generated secrets of one Penpot instance (TASK-0123) in the platform's secret vault (`db://penpot/<service id>`,
 * encrypted at rest by DbSecretStore): `secret_key` (PENPOT_SECRET_KEY, 64+ URL-safe characters as the official docs generate
 * with `secrets.token_urlsafe(64)`) and `db_password` (the stack's PostgreSQL). Generated once; a retried step or a rebuilt
 * stack reads the same values. Never in an operation, a log, an event or a command line — the adapter writes them to the
 * stack's `.env` (0600) over SFTP. The owner's Penpot password is not kept anywhere: the customer sets it.
 */
final class PenpotSecrets
{
    public function __construct(private readonly SecretStore $secrets) {}

    public static function ref(Service|string $service): SecretRef
    {
        $id = $service instanceof Service ? $service->id : $service;

        return SecretRef::parse(rtrim((string) config('penpot.secret_ref_prefix', 'db://penpot/'), '/').'/'.preg_replace('/[^A-Za-z0-9_]/', '', $id));
    }

    /** @return array{secret_key:string, db_password:string} the stored values, generated and stored first when missing */
    public function ensure(Service $service): array
    {
        $ref = self::ref($service);
        $current = $this->secrets->exists($ref) ? $this->secrets->read($ref) : [];
        if (strlen((string) ($current['secret_key'] ?? '')) >= 64 && strlen((string) ($current['db_password'] ?? '')) >= 24) {
            return ['secret_key' => (string) $current['secret_key'], 'db_password' => (string) $current['db_password']];
        }
        $values = ['secret_key' => self::urlSafe(64), 'db_password' => bin2hex(random_bytes(20))];
        $this->secrets->write($ref, $values);

        return $values;
    }

    public function exists(Service $service): bool
    {
        return $this->secrets->exists(self::ref($service));
    }

    /** After the instance is gone (purge): nothing is left to decrypt. */
    public function forget(Service $service): void
    {
        if ($this->secrets instanceof DbSecretStore) {
            $this->secrets->delete(self::ref($service));
        }
    }

    /** A random password nobody sees (the first profile needs one; the customer sets their own). */
    public static function throwaway(): string
    {
        return self::urlSafe(32);
    }

    private static function urlSafe(int $length): string
    {
        return substr(rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '='), 0, $length);
    }
}
