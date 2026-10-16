<?php

declare(strict_types=1);

namespace Onhost\Domain\Services;

use Illuminate\Support\Facades\Storage;
use Onhost\Domain\Services\Models\Backup;
use Throwable;

/**
 * "Finished" is not "whole". A set of a web service is files plus every database the service owned when the set was
 * taken; a set whose database dump is absent or empty, or whose archive cannot be read back, restores a site without
 * its data — and used to count as the latest good backup all the same.
 *
 * `inspect()` reads the set from the backup disk against its own manifest (which lists the databases the service owned
 * at that moment) and writes the verdict onto the row as `meta.incomplete = {missing, problems, checked_at}`; a set
 * that is whole has the mark removed again. Read-only toward the nodes: only the platform's backup disk is touched.
 * Health and the "latest good backup" readers use `Backup::query()->whereNull('meta->incomplete')`.
 */
final class BackupCompleteness
{
    /** Gap text of a copy that is partial by design (the one taken before a database is overwritten). */
    public const PARTIAL_GAP = 'only the database being overwritten is in this copy';

    /** @return array{missing:list<string>, problems:list<string>} empty arrays when the set is whole (or is not a web set) */
    public function inspect(Backup $backup): array
    {
        $verdict = ['missing' => [], 'problems' => []];
        if (! FinalArchive::isSet($backup) || ! in_array((string) data_get($backup->meta, 'family'), ['web', 'managed'], true)) {
            return $verdict;
        }
        $gaps = array_map('strval', (array) data_get($backup->meta, 'gaps', []));
        if (in_array(self::PARTIAL_GAP, $gaps, true)) {
            return $verdict; // partial by design, and never a candidate for "latest backup" in the first place
        }
        try {
            $verdict = $this->look($backup, $gaps);
        } catch (Throwable $e) {
            $verdict['problems'][] = 'the set cannot be inspected: '.mb_substr($e->getMessage(), 0, 120);
        }
        $meta = (array) $backup->meta;
        if ($verdict['missing'] === [] && $verdict['problems'] === []) {
            if (array_key_exists('incomplete', $meta)) {
                unset($meta['incomplete']);
                $backup->forceFill(['meta' => $meta])->save();
            }

            return $verdict;
        }
        $meta['incomplete'] = $verdict + ['checked_at' => now()->toIso8601String()];
        $backup->forceFill(['meta' => $meta])->save();

        return $verdict;
    }

    /** The databases a set lacks, for the sentence "chybí DB x". @return list<string> */
    public static function missingOf(Backup $backup): array
    {
        return array_values(array_map('strval', (array) data_get($backup->meta, 'incomplete.missing', [])));
    }

    /** @param  list<string>  $gaps  @return array{missing:list<string>, problems:list<string>} */
    private function look(Backup $backup, array $gaps): array
    {
        $set = (string) data_get($backup->meta, 'set');
        $storage = Storage::disk((string) config('onhost.platform_backup.disk', 'local'));
        $missing = [];
        $problems = [];
        if (! $storage->exists($set.'/manifest.json')) {
            return ['missing' => [], 'problems' => ['the manifest is missing']];
        }
        $manifest = json_decode((string) $storage->get($set.'/manifest.json'), true);
        $manifest = is_array($manifest) ? $manifest : [];
        $parts = (array) ($manifest['parts'] ?? []);
        $expected = array_values(array_unique(array_map('strval', (array) ($manifest['databases'] ?? []))));

        foreach ($expected as $slug) {
            $name = 'database-'.$slug.'.sql';
            if (! array_key_exists($name, $parts)) {
                $missing[] = $slug;

                continue;
            }
            if (($problem = $this->unreadable($storage, $set, $name, (array) $parts[$name])) !== null) {
                $missing[] = $slug;
                $problems[] = $problem;
            }
        }
        $filesExpected = ! array_filter($gaps, fn (string $gap) => str_contains($gap, 'no longer exists') || str_contains($gap, 'offers no file or database export'));
        if ($filesExpected && array_key_exists('databases', $manifest)) { // sets of the web families written since the manifest names its databases
            $files = array_values(array_filter(array_keys($parts), fn ($name) => str_starts_with((string) $name, 'site-files')));
            if ($files === []) {
                $problems[] = 'the site files are not in the set';
            }
            foreach ($files as $name) {
                if (($problem = $this->unreadable($storage, $set, (string) $name, (array) $parts[$name])) !== null) {
                    $problems[] = $problem;
                }
            }
        }

        return ['missing' => $missing, 'problems' => $problems];
    }

    /** @param  array<string,mixed>  $part  @return string|null what is wrong with this archive */
    private function unreadable(object $storage, string $set, string $name, array $part): ?string
    {
        $path = $set.'/'.$name;
        if (! $storage->exists($path)) {
            return "{$name} is not on the backup disk";
        }
        $size = (int) $storage->size($path);
        if ($size <= 0 || (int) ($part['bytes'] ?? 0) <= 0) {
            return "{$name} is empty";
        }
        if (isset($part['bytes']) && (int) $part['bytes'] !== $size) {
            return "{$name} has {$size} bytes, the manifest says {$part['bytes']}";
        }
        $stream = $storage->readStream($path);
        if (! is_resource($stream)) {
            return "{$name} cannot be read";
        }
        try {
            $magic = (string) fread($stream, 2);
            rewind($stream);
            if ($magic !== "\x1f\x8b") {
                return null; // plain text dump: existing, non-empty and of the recorded size is what can be said
            }
            // a gzip stream is read to its footer: a truncated upload fails the CRC / end-of-stream check
            $context = inflate_init(ZLIB_ENCODING_GZIP);
            $ended = false;
            while (! feof($stream)) {
                $chunk = (string) fread($stream, 65536);
                if ($chunk === '') {
                    break;
                }
                @inflate_add($context, $chunk);
                if (inflate_get_status($context) === ZLIB_STREAM_END) {
                    $ended = true;
                    break;
                }
            }

            return $ended ? null : "{$name} is a truncated gzip archive";
        } catch (Throwable) {
            return "{$name} is a damaged gzip archive";
        } finally {
            fclose($stream);
        }
    }
}
