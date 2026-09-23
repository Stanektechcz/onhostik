<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Contracts\Filesystem\Filesystem;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Errors\DomainError;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;
use Throwable;

/**
 * What a web hosting needs before it can be carried to another node, and how its data is put down there.
 *
 * A site is not only its files: it is the databases those files open by **name, user and password**, written into
 * `wp-config.php` or `.env` where the platform cannot reach them. A migration that recreates a database under a new
 * name — or with a password the platform made up — hands the customer a site answering with a database error and a
 * backup somebody has to unpack by hand. So a database is carried only when the platform holds that database's own
 * credentials (`DatabaseCredentials`, written whenever it created one) and the whole migration is refused, by name,
 * when it does not: a site half moved is worse than a site not moved.
 *
 * The files and the dumps travel in the archive the platform already knows how to make (`FinalArchive`: files and
 * every database, fresh or the step fails). Nothing on the source is touched until the copy on the target is whole.
 */
final class WebMigration
{
    public function __construct(
        private readonly ServiceFeatures $features,
        private readonly DatabaseCredentials $credentials,
    ) {}

    /**
     * Whether this site can be carried, and with what.
     *
     * @return array{ok:bool, blockers:list<string>, databases:list<array{remote_id:string, name:string, user:string, password:string, charset:string}>}
     */
    public function readiness(Service $service): array
    {
        if (! in_array($service->family, ['web', 'managed'], true)) {
            return ['ok' => false, 'blockers' => ['stěhování webu je jen pro webhosting'], 'databases' => []];
        }
        $blockers = [];
        $databases = [];
        [$tools, $ref] = $this->features->toolsFor($service);
        if (! $tools instanceof WebToolsProvider) {
            $blockers[] = 'panel této služby neumí přenášet soubory';
        } elseif (! $tools->shellAvailable($ref)) {
            $blockers[] = 'k souborům webu se platforma nedostane (chybí agentní přístup na uzel)';
        }
        try {
            $listed = $this->features->resources($service, 'databases', true);
        } catch (Throwable) {
            $listed = null;
            $blockers[] = 'panel neodpověděl, jaké má web databáze';
        }
        foreach ((array) ($listed ?? []) as $database) {
            $remoteId = (string) ($database['remote_id'] ?? '');
            $name = (string) ($database['name'] ?? '');
            $stored = $remoteId === '' ? null : $this->credentials->read($service, $remoteId);
            if ($stored === null || (string) ($stored['password'] ?? '') === '') {
                // the site opens this database with a password the platform never saw: moving it would mean a new
                // password and a configuration file only the customer can change
                $blockers[] = 'databázi '.($name !== '' ? $name : $remoteId).' neumíme přenést: její heslo platforma nedrží';

                continue;
            }
            $databases[] = [
                'remote_id' => $remoteId,
                'name' => (string) ($stored['name'] ?? $name),
                'user' => (string) ($stored['user'] ?? ($database['user'] ?? $name)),
                'password' => (string) $stored['password'],
                'charset' => (string) ($database['charset'] ?? 'utf8mb4'),
            ];
        }

        return ['ok' => $blockers === [], 'blockers' => $blockers, 'databases' => $databases];
    }

    /** The dump of a database inside an archive set, as `FinalArchive` names it. */
    public static function dumpNameOf(string $databaseName): string
    {
        return 'database-'.FinalArchive::databaseSlug($databaseName).'.sql';
    }

    /**
     * Puts an archive set into a site that is already there: every database first — with its own name, user and
     * password, so the site's own configuration keeps working — and the files over the top of them.
     *
     * @param  list<array{remote_id:string, name:string, user:string, password:string, charset:string}>  $databases
     * @return array{files:?string, databases:list<array{name:string, remote_id:string}>}
     */
    public function restoreInto(Service $service, Filesystem $disk, string $set, object $target, ResourceRef $ref, array $databases, string $work): array
    {
        if (! $target instanceof WebToolsProvider || ! $target instanceof WebHostingProvider) {
            throw new DomainError('migration_target_panel', 'Cílový panel neumí přenést soubory ani databáze.', 422);
        }
        $out = ['files' => null, 'databases' => []];
        $byDump = [];
        foreach ($databases as $database) {
            $byDump[self::dumpNameOf($database['name'])] = $database;
        }
        foreach ($disk->files($set) as $file) {
            $name = basename((string) $file);
            $isFiles = str_starts_with($name, 'site-files');
            if (! $isFiles && ! isset($byDump[$name])) {
                continue; // service.json and the manifest describe the archive; they are not put on a node
            }
            $local = rtrim($work, '/').'/'.$name;
            $this->pull($disk, (string) $file, $local);
            try {
                if ($isFiles) {
                    $transport = $target->transport($ref);
                    $transport->upload($name, $local);
                    $transport->extract($name, '.');
                    try {
                        $transport->delete($name);
                    } catch (Throwable) {
                        // the archive stays in the site root: the customer sees it and may remove it
                    }
                    $out['files'] = $name;
                } else {
                    $database = $byDump[$name];
                    $created = $target->createDatabase($ref, ['name' => $database['name'], 'user' => $database['user'], 'password' => $database['password'], 'charset' => $database['charset']]);
                    $remoteId = (string) $created->ref?->remoteId;
                    if ($remoteId === '') {
                        throw new DomainError('migration_database', 'Databázi '.$database['name'].' se na cílovém uzlu nepodařilo vytvořit.', 502);
                    }
                    $target->importDatabase($ref, $remoteId, $local);
                    // the credentials follow the database to its new number, so the panel and the platform still agree
                    $this->credentials->remember($service, $remoteId, ['name' => $database['name'], 'user' => $database['user'], 'password' => $database['password']]);
                    $out['databases'][] = ['name' => $database['name'], 'remote_id' => $remoteId];
                }
            } finally {
                @unlink($local);
            }
        }

        return $out;
    }

    private function pull(Filesystem $disk, string $file, string $local): void
    {
        $stream = $disk->readStream($file);
        $out = is_resource($stream) ? fopen($local, 'wb') : false;
        if (! is_resource($stream) || ! is_resource($out)) {
            throw new DomainError('migration_archive_part', 'Část archivu '.basename($file).' nelze načíst.', 500);
        }
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);
    }
}
