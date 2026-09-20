<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Illuminate\Support\Str;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\Service;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;
use Throwable;

/**
 * A backup of a web or managed service is made by the platform itself: the site files and a dump of every database,
 * pulled off the node onto the backup disk and checksummed — the same set the final archive is (`FinalArchive`).
 *
 * Why not the panel's own backup. ISPConfig archives sites on its nightly schedule and its remote API cannot be asked
 * for one now; the step used to "complete" with the newest archive it found — last night's — under today's date.
 * aaPanel packs the site on request, but the files only: a WordPress backup without its database restores nothing.
 * And an archive that sits on the node it protects is gone together with the node.
 *
 * So the rule is one for both panels: what somebody asked for now is fresh and whole, or the operation fails and says why.
 * Game servers, virtual machines and databases keep the backups of their own panels (`BackupCapable`).
 */
final class ServiceBackups
{
    public function __construct(private readonly FinalArchive $archives) {}

    /** Whether the backups of this service are archive sets made by the platform (and not archives living on the panel). */
    public static function platformMade(Service $service, ?object $adapter): bool
    {
        return in_array($service->family, ['web', 'managed'], true) && $adapter instanceof WebToolsProvider;
    }

    /**
     * How many backups the customer may keep at once (taken by hand or by a hook; the scheduled ones have the plan's
     * generations). They live on our disk: without a ceiling one impatient afternoon fills it.
     */
    public static function manualLimit(Service $service): int
    {
        return max(1, (int) (data_get($service->entitlements, 'manual_backups') ?? config('onhost.backups.manual_max', 5)));
    }

    /** Refuses another manual backup when the service already holds as many as it may. */
    public static function assertRoomForManual(Service $service): void
    {
        $held = Backup::query()->where('service_id', $service->id)->where('kind', 'manual')->whereIn('state', ['running', 'completed'])->whereNotNull('meta->set')->count();
        if ($held >= self::manualLimit($service)) {
            throw new DomainError('backup_limit_reached', 'Služba už má nejvyšší počet ručních záloh ('.self::manualLimit($service).'). Smažte některou starší a zálohu spusťte znovu.', 409, ['limit' => self::manualLimit($service), 'held' => $held]);
        }
    }

    /**
     * Takes the backup now. Throws when any part the panel offers cannot be stored — the caller fails its step, the
     * row stays `failed` with the reason and what was tried.
     */
    public function take(Service $service, object $adapter, ResourceRef $ref, CommandContext $context, string $operationId, string $kind, int $retentionDays, bool $protected = false): Backup
    {
        $done = Backup::query()->where('operation_id', $operationId)->where('kind', $kind)->where('state', 'completed')->first();
        if ($done !== null) {
            return $done; // the step ran again after the archive was written (a worker died between the two writes)
        }

        return $this->archives->create($service, $adapter, $ref, $context, $operationId, null, [
            'kind' => $kind, 'retention_days' => $retentionDays, 'protected' => $protected, 'reason' => $kind, 'fresh_only' => true,
        ])['backup'];
    }

    /**
     * Puts a set back onto the service it was taken from: the files over the site root, every dump into the database of
     * the same name. Files that were added after the backup stay where they are (the panels' own restores do the same);
     * nothing is touched until every dump has a database to go into.
     *
     * @return array{files:?string, databases:list<array{dump:string, database:string}>, mode:string}
     */
    public function restoreInPlace(Service $service, WebToolsProvider $adapter, ResourceRef $ref, Backup $backup): array
    {
        if ($backup->service_id !== $service->id || ! FinalArchive::isSet($backup) || $backup->state !== 'completed') {
            throw new DomainError('backup_not_restorable', 'Tuto zálohu nelze na službu obnovit.', 409);
        }
        $parts = $this->archives->restorable($backup);
        if ($parts['files'] === null && $parts['databases'] === []) {
            throw new DomainError('backup_missing', 'Archivní sada této zálohy už na záložním disku není.', 410);
        }
        $targets = [];
        if ($parts['databases'] !== []) {
            $current = $adapter instanceof WebHostingProvider ? $adapter->listDatabases($ref) : [];
            foreach (array_keys($parts['databases']) as $slug) {
                $match = collect($current)->first(fn (array $db) => FinalArchive::databaseSlug((string) ($db['name'] ?? $db['remote_id'] ?? '')) === $slug);
                if (! is_array($match) || (string) ($match['remote_id'] ?? '') === '') {
                    throw new DomainError('restore_database_missing', "Databáze „{$slug}“ ze zálohy už na službě není. Založte ji znovu pod stejným názvem a obnovu zopakujte; do té doby se nic nezměnilo.", 409, ['database' => $slug]);
                }
                $targets[$slug] = $match;
            }
        }
        $disk = $this->archives->disk();
        $work = storage_path('app/onhost-restore-'.Str::random(8));
        if (! is_dir($work) && ! mkdir($work, 0700, true) && ! is_dir($work)) {
            throw new DomainError('restore_workdir', 'Pracovní adresář pro obnovu nelze vytvořit.', 500);
        }
        $result = ['files' => null, 'databases' => [], 'mode' => 'overlay'];
        try {
            $pull = function (string $path) use ($disk, $work): string {
                $local = $work.'/'.basename($path);
                $stream = $disk->readStream($path);
                $out = is_resource($stream) ? fopen($local, 'wb') : false;
                if (! is_resource($stream) || ! is_resource($out)) {
                    throw new DomainError('backup_missing', 'Část zálohy '.basename($path).' nelze ze záložního disku načíst.', 503);
                }
                stream_copy_to_stream($stream, $out);
                fclose($out);
                fclose($stream);

                return $local;
            };
            // databases first: a dump that does not import leaves the files as they were, and the site still matches its data
            foreach ($parts['databases'] as $slug => $path) {
                $local = $pull($path);
                $adapter->importDatabase($ref, (string) $targets[$slug]['remote_id'], $local);
                $result['databases'][] = ['dump' => basename($path), 'database' => (string) ($targets[$slug]['name'] ?? $targets[$slug]['remote_id'])];
                @unlink($local);
            }
            if ($parts['files'] !== null) {
                $local = $pull($parts['files']);
                $name = 'onhost-restore-'.Str::lower(Str::random(6)).(str_ends_with($parts['files'], '.zip') ? '.zip' : '.tar.gz');
                $transport = $adapter->transport($ref);
                $transport->upload($name, $local);
                try {
                    $transport->extract($name, '.');
                } finally {
                    try {
                        $transport->delete($name);
                    } catch (Throwable) {
                        // the uploaded archive stays in the site root; the customer sees it and may delete it
                    }
                }
                $result['files'] = basename($parts['files']);
            }
        } finally {
            foreach (glob($work.'/*') ?: [] as $left) {
                @unlink($left);
            }
            @rmdir($work);
        }

        return $result;
    }
}
