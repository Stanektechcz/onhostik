<?php

declare(strict_types=1);

namespace Onhost\Platform\Secrets;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * `db://<name>` secrets encrypted at rest with the application key (AES-256-GCM via the Laravel encrypter),
 * for credentials that operators register through the admin console. Every other scheme (`env://`,
 * `file://`, `bao://`) is delegated to the wrapped store, so OpenBao remains the preferred backend.
 * Values are never logged; the `keys` column lists key names only so the UI can show what is configured.
 */
final class DbSecretStore implements SecretStore
{
    public function __construct(private readonly SecretStore $fallback, private readonly Encrypter $encrypter) {}

    public function read(SecretRef $ref): array
    {
        if ($ref->scheme !== 'db') {
            return $this->fallback->read($ref);
        }
        $row = DB::table('secrets')->where('name', $ref->path)->first();
        if ($row === null) {
            throw new RuntimeException("No stored secret for {$ref}");
        }
        $values = json_decode($this->encrypter->decryptString((string) $row->payload), true);

        return is_array($values) ? $values : [];
    }

    public function write(SecretRef $ref, array $values, ?string $actor = null): void
    {
        if ($ref->scheme !== 'db') {
            $this->fallback->write($ref, $values);

            return;
        }
        $clean = array_filter($values, fn ($v) => $v !== null && $v !== '');
        DB::table('secrets')->updateOrInsert(['name' => $ref->path], [
            'payload' => $this->encrypter->encryptString(json_encode($clean, JSON_THROW_ON_ERROR)),
            'keys' => json_encode(array_keys($clean)),
            'rotated_by' => $actor,
            'rotated_at' => now(),
            'updated_at' => now(),
            'created_at' => DB::table('secrets')->where('name', $ref->path)->value('created_at') ?? now(),
        ]);
    }

    /** Merge new values over the stored ones (blank fields in a form keep the previous value). */
    public function merge(SecretRef $ref, array $values, ?string $actor = null): void
    {
        $current = $this->exists($ref) ? $this->read($ref) : [];
        $this->write($ref, array_merge($current, array_filter($values, fn ($v) => $v !== null && $v !== '')), $actor);
    }

    public function delete(SecretRef $ref): void
    {
        if ($ref->scheme === 'db') {
            DB::table('secrets')->where('name', $ref->path)->delete();
        }
    }

    /** Key names of a stored secret (never values). */
    public function keys(SecretRef $ref): array
    {
        if ($ref->scheme !== 'db') {
            return $this->exists($ref) ? array_keys($this->fallback->read($ref)) : [];
        }
        $keys = DB::table('secrets')->where('name', $ref->path)->value('keys');

        return is_string($keys) ? (array) json_decode($keys, true) : [];
    }

    public function exists(SecretRef $ref): bool
    {
        if ($ref->scheme !== 'db') {
            return $this->fallback->exists($ref);
        }

        return DB::table('secrets')->where('name', $ref->path)->exists();
    }

    public function health(): SecretStoreHealth
    {
        return $this->fallback->health();
    }
}
