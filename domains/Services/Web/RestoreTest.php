<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Onhost\Domain\Services\FinalArchive;
use Onhost\Domain\Services\Models\Backup;
use Onhost\Domain\Services\Models\BackupPolicy;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\ServiceStateMachine;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\ServiceService;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\ResourceRef;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Contracts\WebToolsProvider;
use Throwable;

/**
 * A backup nobody has ever restored is not a backup.
 *
 * The `backup-7` and `backup-30` add-ons are sold with `restore_test: monthly`, the price list says "Test obnovy
 * měsíčně", the plan writes it into `backup_policies.restore_test` — and **no code ever tested a restore**. The only
 * other mention in the whole platform is a loyalty mission asking the CUSTOMER to try one. The promise was sold and
 * kept by nobody.
 *
 * What a test may not do is decide the question by overwriting the live database (H458): a test that destroys what it
 * is testing is worse than no test. So the dump is restored into a database of its own, made for this run, and the
 * result is judged by a round trip — the restored database is exported again and the tables that come back are
 * compared with the tables the dump carried. That uses only what both panels already do (create, import, export,
 * delete), needs no database connection of our own, and proves the thing that matters: the archive on the backup disk
 * really becomes a database again.
 *
 * The test database is removed whatever happens, so neither the customer's disk nor their database count keeps it.
 */
final class RestoreTest
{
    /** The record lives on the service; the panel and the doctor read it. */
    public const TAG = 'restore_test';

    public const CADENCE_DAYS = ['weekly' => 7, 'monthly' => 30, 'quarterly' => 91];

    /** A test that finds nothing to compare is not a pass: a dump with no tables in it is reported as a problem. */
    public const MIN_TABLES = 1;

    public function __construct(
        private readonly ServiceService $services,
        private readonly FinalArchive $archives,
        private readonly OutboxPublisher $outbox,
    ) {}

    /**
     * Ask for a test on every service whose plan promises one and has not had one lately.
     *
     * @return array{checked:int, started:int, skipped:int, errors:int}
     */
    public function tick(int $limit = 20): array
    {
        $stats = ['checked' => 0, 'started' => 0, 'skipped' => 0, 'errors' => 0];
        $context = CommandContext::system('restore test');
        $services = Service::query()->whereIn('family', ['web', 'managed'])
            ->whereIn('state', [ServiceStateMachine::ACTIVE, ServiceStateMachine::DEGRADED])
            ->whereNotNull('provider_instance_id')->orderBy('id')->limit($limit)->get();
        foreach ($services as $service) {
            $stats['checked']++;
            try {
                $backup = $this->due($service);
                if ($backup === null) {
                    $stats['skipped']++;

                    continue;
                }
                $this->services->requestAction($service, 'restore.test', $context, 'restore-test:'.$service->id.':'.now()->format('Ymd'), ['backup_id' => $backup->id]);
                $stats['started']++;
            } catch (DomainError) {
                $stats['skipped']++; // another operation is on the service, or the panel says no: the next run asks again
            } catch (Throwable $e) {
                $stats['errors']++;
                report($e);
            }
        }

        return $stats;
    }

    /** How often the plan promises a test, or null when it promises none. */
    public function cadenceDays(Service $service): ?int
    {
        $policy = BackupPolicy::query()->where('service_id', $service->id)->first();
        $cadence = (string) ($policy?->restore_test['cadence'] ?? '');

        return array_key_exists($cadence, self::CADENCE_DAYS) ? (int) self::CADENCE_DAYS[$cadence] : null;
    }

    /** The set this service should have tested by now, or null when none is owed. */
    public function due(Service $service): ?Backup
    {
        $days = $this->cadenceDays($service);
        if ($days === null) {
            return null;
        }
        $last = (string) data_get($service->tags, self::TAG.'.last_at', '');
        if ($last !== '' && Carbon::parse($last)->greaterThan(now()->subDays($days))) {
            return null;
        }
        $backup = Backup::query()->where('service_id', $service->id)->where('state', 'completed')->whereNull('remote_id')
            ->orderByDesc('started_at')->orderByDesc('id')->first();

        return $backup !== null && FinalArchive::isSet($backup) ? $backup : null;
    }

    /**
     * Restore one set into databases of its own and compare what came back.
     *
     * @return array{outcome:string, databases:list<array<string,mixed>>, problems:list<string>}
     */
    public function run(Service $service, object $adapter, ResourceRef $ref, Backup $backup): array
    {
        if (! $adapter instanceof WebToolsProvider || ! $adapter instanceof WebHostingProvider) {
            throw new DomainError('restore_test_unsupported', 'Tento panel neumí databázi založit, naplnit a zase odstranit; test obnovy na něm nelze provést.', 422);
        }
        $parts = $this->archives->restorable($backup);
        if ($parts['databases'] === []) {
            throw new DomainError('restore_test_no_database', 'Tato záloha neobsahuje žádnou databázi, nemá se tedy co obnovovat.', 409);
        }
        $work = storage_path('app/onhost-restore-test-'.Str::random(8));
        if (! is_dir($work) && ! mkdir($work, 0700, true) && ! is_dir($work)) {
            throw new DomainError('restore_test_workdir', 'Pracovní adresář pro test obnovy nelze vytvořit.', 500);
        }
        $databases = [];
        $problems = [];
        try {
            foreach ($parts['databases'] as $slug => $path) {
                [$row, $problem] = $this->one($adapter, $ref, $service, $work, (string) $slug, (string) $path);
                $databases[] = $row;
                if ($problem !== null) {
                    $problems[] = $problem;
                }
            }
        } finally {
            $this->sweep($work);
        }

        return ['outcome' => $problems === [] ? 'ok' : 'failed', 'databases' => $databases, 'problems' => $problems];
    }

    /**
     * @return array{0:array<string,mixed>, 1:?string}
     */
    private function one(WebHostingProvider&WebToolsProvider $adapter, ResourceRef $ref, Service $service, string $work, string $slug, string $path): array
    {
        $local = $this->pull($path, $work);
        $wanted = self::tablesIn($local);
        $name = ServiceFeatures::scopedName($service, 'rt'.Str::lower(Str::random(6)));
        $password = Str::password(20, true, true, false);
        $created = $adapter->createDatabase($ref, ['name' => $name, 'user' => $name, 'password' => $password, 'charset' => 'utf8mb4']);
        $createdRef = $created->ref; // every adapter names what it made; one that does not gets no database dropped on it
        $remoteId = $createdRef instanceof ResourceRef ? (string) $createdRef->remoteId : '';
        if ($remoteId === '') {
            return [['database' => $slug, 'tables' => count($wanted), 'restored' => 0, 'verified' => false], "databáze {$slug}: zkušební databázi se nepodařilo založit"];
        }
        try {
            $adapter->importDatabase($ref, $remoteId, $local, ['name' => $name, 'user' => $name, 'password' => $password]);
            $back = $work.'/back-'.substr(sha1($slug), 0, 8).'.sql';
            $adapter->exportDatabase($ref, $remoteId, $back, ['name' => $name, 'user' => $name, 'password' => $password]);
            $got = self::tablesIn($back);
            $missing = array_values(array_diff($wanted, $got));
            $row = ['database' => $slug, 'tables' => count($wanted), 'restored' => count($got), 'verified' => $missing === [] && count($wanted) >= self::MIN_TABLES];
            if (count($wanted) < self::MIN_TABLES) {
                return [$row, "databáze {$slug}: v záloze není žádná tabulka, není co ověřit"];
            }

            return [$row, $missing === [] ? null : "databáze {$slug}: po obnově chybí ".count($missing).' tabulek ('.implode(', ', array_slice($missing, 0, 3)).')'];
        } catch (Throwable $e) {
            return [['database' => $slug, 'tables' => count($wanted), 'restored' => 0, 'verified' => false], "databáze {$slug}: ".mb_substr($e->getMessage(), 0, 120)];
        } finally {
            try {
                $adapter->deleteDatabase($ref, $remoteId); // the test database never outlives the test, whatever happened
            } catch (Throwable) {
                // it stays in the panel and the customer can see it; the run itself is not failed for that
            }
        }
    }

    private function pull(string $path, string $work): string
    {
        $local = $work.'/'.basename($path);
        $stream = $this->archives->disk()->readStream($path);
        $out = is_resource($stream) ? fopen($local, 'wb') : false;
        if (! is_resource($stream) || ! is_resource($out)) {
            throw new DomainError('backup_missing', 'Část zálohy '.basename($path).' nelze ze záložního disku načíst.', 503);
        }
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        return $local;
    }

    /**
     * The tables a dump carries. A part named `.sql` may hold gzip bytes — aaPanel hands out `.sql.gz` and the set
     * keeps the name it was written under — so the first two bytes decide how it is read, not the extension.
     *
     * @return list<string>
     */
    public static function tablesIn(string $file): array
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }
        $magic = (string) fread($handle, 2);
        fclose($handle);
        $reader = $magic === "\x1f\x8b" ? @gzopen($file, 'rb') : @fopen($file, 'rb');
        if ($reader === false) {
            return [];
        }
        $tables = [];
        while (($line = $magic === "\x1f\x8b" ? gzgets($reader) : fgets($reader)) !== false) {
            if (preg_match('/^\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"\[]?([A-Za-z0-9_$\x80-\xff-]+)/i', (string) $line, $m) === 1) {
                $tables[] = strtolower($m[1]);
            }
        }
        $magic === "\x1f\x8b" ? gzclose($reader) : fclose($reader);

        return array_values(array_unique($tables));
    }

    /** Write down what happened and tell somebody when a backup did not come back. */
    public function record(Service $service, Backup $backup, array $result): void
    {
        $tags = (array) $service->tags;
        $tags[self::TAG] = ['last_at' => now()->toIso8601String(), 'backup_id' => $backup->id, 'outcome' => $result['outcome'],
            'databases' => $result['databases'], 'problems' => array_slice($result['problems'], 0, 5)];
        $service->forceFill(['tags' => $tags])->save();
        if ($result['outcome'] !== 'ok') {
            // a passing test is read in the panel; a failing one is the customer's data not coming back, so it is said out loud
            $this->outbox->publish(GenericEvent::of('service.restore_test.failed', 'service', $service->id, [
                'backup_id' => $backup->id, 'problem' => mb_substr((string) ($result['problems'][0] ?? ''), 0, 160),
                'label' => $service->label ?: ($service->hostname ?: $service->name),
            ], $service->organization_id));
        }
    }

    /** What the last test found (the panel and the doctor read it). @return array<string,mixed> */
    public static function health(Service $service): array
    {
        return (array) data_get($service->tags, self::TAG, []);
    }

    private function sweep(string $work): void
    {
        foreach ((array) glob($work.'/*') as $file) {
            @unlink((string) $file);
        }
        @rmdir($work);
    }
}
