<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Secrets\SecretRef;
use Onhost\Platform\Secrets\SecretStore;

/**
 * Database credentials the platform created for a service (database.create, dbuser.create, dbuser.password) live in
 * the secret store — encrypted at rest, never in service specs or logs — so dumps, imports and staging copies can run
 * without asking the customer. A database the platform never saw a password for stays unknown until a password reset.
 */
final class DatabaseCredentials
{
    public function __construct(private readonly SecretStore $secrets) {}

    /** @param array{name?:string,user?:string,password?:string,host?:string} $values */
    public function remember(Service $service, string $databaseRemoteId, array $values): void
    {
        $current = $this->read($service, $databaseRemoteId) ?? [];
        $merged = array_filter(array_merge($current, $values), fn ($v) => $v !== null && $v !== '');
        $this->secrets->write($this->ref($service, $databaseRemoteId), $merged + ['updated_at' => now()->toIso8601String()]);
    }

    /** @return array{name?:string,user?:string,password?:string,host?:string}|null */
    public function read(Service $service, string $databaseRemoteId): ?array
    {
        $ref = $this->ref($service, $databaseRemoteId);
        try {
            if (! $this->secrets->exists($ref)) {
                return null;
            }
            $values = $this->secrets->read($ref);
        } catch (\Throwable) {
            return null;
        }

        return ($values['password'] ?? '') !== '' ? $values : null;
    }

    public function forget(Service $service, string $databaseRemoteId): void
    {
        $ref = $this->ref($service, $databaseRemoteId);
        try {
            if (method_exists($this->secrets, 'delete')) {
                $this->secrets->delete($ref);
            } else {
                $this->secrets->write($ref, []);
            }
        } catch (\Throwable) {
            // nothing to forget
        }
    }

    /** Credentials for a database user the panel created for a user (remembered under the user's databases). */
    public function rememberForUser(Service $service, string $user, string $password): void
    {
        $this->secrets->write($this->ref($service, 'user:'.$user), ['user' => $user, 'password' => $password, 'updated_at' => now()->toIso8601String()]);
    }

    private function ref(Service $service, string $key): SecretRef
    {
        return SecretRef::parse('db://services/'.$service->id.'/database/'.preg_replace('/[^A-Za-z0-9_.-]/', '_', $key)); // secret paths allow letters, digits, _ . - only
    }
}
