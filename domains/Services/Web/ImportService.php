<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\Models\SiteImport;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;
use Onhost\Providers\Contracts\Naming;
use Onhost\Providers\Contracts\WebHostingProvider;
use Onhost\Providers\Shell\Q;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

/**
 * Site migration into a hosting service from a cPanel or Plesk backup, an archive URL or an uploaded archive:
 * the archive is unpacked on the control plane, the document root and SQL dumps are located, the files go to
 * the site through the transport and every dump becomes a database of the site (WordPress is re-pointed).
 */
final class ImportService
{
    private const DOCROOT_NAMES = ['public_html', 'httpdocs', 'htdocs', 'www', 'public', 'web', 'html', 'wwwroot'];

    public function __construct(private readonly WebFileStore $files, private readonly OutboxPublisher $outbox, private readonly ServiceFeatures $features) {}

    /** @return list<array<string,mixed>> */
    public function list(Service $service): array
    {
        return SiteImport::query()->where('service_id', $service->id)->orderByDesc('created_at')->limit(20)->get()->map(fn (SiteImport $i) => [
            'id' => $i->id, 'kind' => $i->kind, 'source' => $i->kind === 'url' ? $i->source : ($i->stats['original_name'] ?? $i->source), 'size_bytes' => $i->size_bytes, 'state' => $i->state, 'stats' => (array) $i->stats, 'log' => $i->log,
            'started_at' => $i->started_at?->toIso8601String(), 'finished_at' => $i->finished_at?->toIso8601String(), 'operation_id' => $i->operation_id,
        ])->all();
    }

    public function open(Service $service, Operation $operation, array $desired): SiteImport
    {
        $import = SiteImport::query()->firstOrCreate(['operation_id' => $operation->id], [
            'service_id' => $service->id, 'organization_id' => $service->organization_id, 'kind' => (string) ($desired['kind'] ?? 'upload'), 'source' => (string) ($desired['source'] ?? ''), 'state' => 'running', 'started_at' => now(), 'stats' => [], 'log' => '',
        ]);
        $this->outbox->publish(GenericEvent::of('import.started', 'service', $service->id, ['import_id' => $import->id, 'kind' => $import->kind], $service->organization_id));

        return $import;
    }

    public function workDir(SiteImport $import): string
    {
        $dir = storage_path('app/onhost/web-tools/'.$import->service_id.'/imports/'.$import->id);
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /** Bring the archive to the control plane; returns its local path. */
    public function fetch(SiteImport $import, Service $service): string
    {
        $dir = $this->workDir($import);
        $max = (int) config('onhost.web_tools.upload_max_bytes', 2 * 1024 * 1024 * 1024);
        if ($import->kind === 'url') {
            $target = $dir.'/source.bin';
            $response = Http::withOptions(['sink' => $target])->timeout(900)->withUserAgent('ONhost-Importer/1.0')->get($import->source);
            if (! $response->successful()) {
                throw new DomainError('import_fetch_failed', 'Download failed with HTTP '.$response->status().'.', 502);
            }
            $size = (int) @filesize($target);
            if ($size > $max) {
                @unlink($target);
                throw new DomainError('import_too_large', 'The archive is larger than the allowed '.round($max / 1073741824, 1).' GB.', 422);
            }
            $import->forceFill(['size_bytes' => $size, 'stats' => array_merge((array) $import->stats, ['original_name' => basename((string) parse_url($import->source, PHP_URL_PATH)) ?: 'archive'])])->save();

            return $target;
        }
        $id = (string) preg_replace('/\..*$/', '', $import->source);
        $path = $this->files->uploadPath($service, $id);
        if (! is_file($path)) {
            throw new DomainError('import_source_missing', 'The uploaded archive is no longer available; upload it again.', 410);
        }
        $meta = $this->files->uploadMeta($service, $id) ?? [];
        $import->forceFill(['size_bytes' => (int) filesize($path), 'stats' => array_merge((array) $import->stats, ['original_name' => (string) ($meta['name'] ?? $import->source)])])->save();

        return $path;
    }

    /** Unpack zip / tar / tar.gz into the work dir; returns the number of entries. */
    public function unpack(string $archive, string $dir): int
    {
        $extract = $dir.'/extract';
        File::ensureDirectoryExists($extract);
        $head = (string) file_get_contents($archive, false, null, 0, 300);
        if (str_starts_with($head, "PK\x03\x04")) {
            return $this->unzip($archive, $extract);
        }
        $ext = str_starts_with($head, "\x1f\x8b") ? '.tar.gz' : (substr($head, 257, 5) === 'ustar' ? '.tar' : null);
        if ($ext === null) {
            throw new DomainError('import_format', 'The archive must be a .zip, .tar or .tar.gz file.', 422);
        }
        $named = $dir.'/source'.$ext;
        if (! is_file($named)) {
            copy($archive, $named);
        }
        $tar = new PharData($named);
        $count = 0;
        foreach (new RecursiveIteratorIterator($tar) as $entry) {
            $count++;
        }
        $tar->extractTo($extract, null, true);

        return $count;
    }

    /**
     * Where the site lives inside the unpacked tree and which dumps to import.
     *
     * @return array{docroot:string, docroot_rel:string, databases:list<array{path:string,name:string}>, files:int, bytes:int, wordpress:bool, layout:string}
     */
    public function analyze(string $dir): array
    {
        $extract = $dir.'/extract';
        $docroot = $this->findDocroot($extract);
        $databases = [];
        foreach ($this->walk($extract, 6) as $file) {
            $name = $file->getFilename();
            if (! preg_match('/\.sql(\.gz)?$/i', $name) || $file->getSize() === 0 || in_array(strtolower($name), ['mysql.sql', 'grants.sql', 'roundcube.sql'], true)) {
                continue;
            }
            if (str_starts_with($file->getPathname(), $docroot.DIRECTORY_SEPARATOR)) {
                continue; // dumps left inside the web root are not the site's live database
            }
            $databases[] = ['path' => $file->getPathname(), 'name' => (string) preg_replace('/\.sql(\.gz)?$/i', '', $name)];
        }
        [$files, $bytes] = $this->measure($docroot);
        $rel = trim(str_replace('\\', '/', substr($docroot, strlen($extract))), '/');
        $layout = match (true) {
            str_contains($rel, 'homedir/public_html') || is_dir($extract.'/'.explode('/', $rel)[0].'/mysql') => 'cpanel',
            str_contains($rel, 'httpdocs') => 'plesk',
            default => 'generic',
        };

        return ['docroot' => $docroot, 'docroot_rel' => $rel, 'databases' => $databases, 'files' => $files, 'bytes' => $bytes, 'wordpress' => is_file($docroot.'/wp-config.php'), 'layout' => $layout];
    }

    /**
     * Pack the document root and unpack it in the site (optionally in a sub-folder).
     *
     * @return array{files:int, bytes:int}
     */
    public function pushFiles(Service $service, string $docroot, string $subdir, string $workDir): array
    {
        [$tools, $ref] = $this->features->toolsFor($service);
        $transport = $tools->transport($ref);
        $tarPath = $workDir.'/site.tar';
        @unlink($tarPath);
        @unlink($tarPath.'.gz');
        $tar = new PharData($tarPath);
        $tar->buildFromDirectory($docroot);
        $tar->compress(Phar::GZ);
        unset($tar);
        @unlink($tarPath);
        $remoteDir = trim($subdir, '/');
        if ($remoteDir !== '') {
            $transport->mkdir($remoteDir);
        }
        $remoteArchive = ($remoteDir !== '' ? $remoteDir.'/' : '').'.onhost-import.tar.gz';
        $transport->upload($remoteArchive, $tarPath.'.gz');
        $transport->extract($remoteArchive, $remoteDir === '' ? '/' : $remoteDir);
        try {
            $transport->delete($remoteArchive, false);
        } catch (\Throwable) {
            // best effort
        }
        @unlink($tarPath.'.gz');
        [$files, $bytes] = $this->measure($docroot);

        return ['files' => $files, 'bytes' => $bytes];
    }

    /**
     * One database of the site per dump; credentials are remembered for the tools.
     *
     * @param  list<array{path:string,name:string}>  $dumps
     * @return list<array{name:string,user:string,remote_id:string,file:string,bytes:int}>
     */
    public function importDatabases(Service $service, array $dumps): array
    {
        $adapter = $this->features->adapterFor($service);
        if (! $adapter instanceof WebHostingProvider) {
            throw new DomainError('feature_unavailable', 'Databases cannot be created on this panel.', 422);
        }
        [$tools, $ref] = $this->features->toolsFor($service);
        $out = [];
        foreach (array_slice($dumps, 0, 20) as $i => $dump) {
            $suffix = substr((string) preg_replace('/^[a-z0-9]{1,8}_/', '', strtolower($dump['name'])), 0, 20) ?: 'import'.($i + 1);
            $name = Naming::scoped($service->id, $suffix, 32);
            $user = Naming::scoped($service->id, substr($suffix, 0, 8), 16);
            $password = Str::password(24, symbols: false);
            $result = $adapter->createDatabase($ref, ['name' => $name, 'user' => $user, 'password' => $password, 'charset' => 'utf8mb4']);
            $remoteId = (string) ($result->ref?->remoteId ?? $name);
            app(DatabaseCredentials::class)->remember($service, $remoteId, ['name' => $name, 'user' => $user, 'password' => $password]);
            $sql = $dump['path'];
            $tmp = null;
            if (preg_match('/\.gz$/i', $sql)) {
                $tmp = $sql.'.plain.sql';
                $in = gzopen($sql, 'rb');
                $outFile = fopen($tmp, 'wb');
                if ($in && $outFile) {
                    while (! gzeof($in)) {
                        fwrite($outFile, (string) gzread($in, 1 << 20));
                    }
                }
                if ($in) {
                    gzclose($in);
                }
                if ($outFile) {
                    fclose($outFile);
                }
                $sql = $tmp;
            }
            $tools->importDatabase($ref, $remoteId, $sql, ['name' => $name, 'user' => $user, 'password' => $password]);
            $out[] = ['name' => $name, 'user' => $user, 'remote_id' => $remoteId, 'file' => basename($dump['path']), 'bytes' => (int) @filesize($sql)];
            if ($tmp !== null) {
                @unlink($tmp);
            }
        }

        return $out;
    }

    /** Point an imported WordPress at its new database and domain. */
    public function fixWordPress(Service $service, array $databases, string $subdir): string
    {
        [$tools, $ref] = $this->features->toolsFor($service);
        $transport = $tools->transport($ref);
        $rel = trim($subdir, '/');
        $configPath = ($rel !== '' ? $rel.'/' : '').'wp-config.php';
        if (! $transport->exists($configPath)) {
            return '';
        }
        $root = rtrim($transport->root(), '/').($rel !== '' ? '/'.$rel : '');
        $wp = $tools->wpCommand($ref).' --path='.Q::arg($root).' --skip-plugins --skip-themes';
        $run = fn (string $args, int $t = 300) => $tools->shell($ref)->run($wp.' '.$args, ['timeout' => $t, 'user' => $tools->siteUser($ref)]);
        $log = '';
        if (count($databases) === 1) {
            $db = $databases[0];
            foreach (['DB_NAME' => $db['name'], 'DB_USER' => $db['user'], 'DB_PASSWORD' => app(DatabaseCredentials::class)->read($service, $db['remote_id'])['password'] ?? '', 'DB_HOST' => 'localhost'] as $k => $v) {
                $run('config set '.$k.' '.Q::arg((string) $v).' --type=constant --quiet', 60);
            }
            $log .= "wordpress: wp-config.php points at {$db['name']}\n";
        }
        $domain = (string) $service->spec('domain', $service->hostname);
        $current = trim($run('option get siteurl', 60)->stdout);
        $host = strtolower((string) parse_url($current, PHP_URL_HOST));
        if ($host !== '' && $host !== strtolower($domain)) {
            foreach (['https://', 'http://'] as $scheme) {
                $r = $run('search-replace '.Q::arg($scheme.$host).' '.Q::arg('https://'.$domain).' --all-tables --skip-columns=guid --report-changed-only --format=count', 600);
                $log .= 'wordpress: '.$scheme.$host.' → https://'.$domain.': '.trim($r->stdout ?: '0')." replacements\n";
            }
            $run('option update home '.Q::arg('https://'.$domain).' --quiet', 60);
            $run('option update siteurl '.Q::arg('https://'.$domain).' --quiet', 60);
        }
        $run('cache flush --quiet', 60);

        return $log;
    }

    public function finish(SiteImport $import, bool $ok, array $stats, string $log): void
    {
        $import->forceFill(['state' => $ok ? 'succeeded' : 'failed', 'stats' => array_merge((array) $import->stats, $stats), 'log' => mb_substr($log, -20000), 'finished_at' => now()])->save();
        $this->outbox->publish(GenericEvent::of($ok ? 'import.succeeded' : 'import.failed', 'service', $import->service_id, ['import_id' => $import->id, 'kind' => $import->kind, 'stats' => $stats, 'error' => $ok ? null : mb_substr(trim(substr($log, -300)), 0, 300)], $import->organization_id));
    }

    public function cleanup(SiteImport $import, Service $service): void
    {
        File::deleteDirectory(storage_path('app/onhost/web-tools/'.$import->service_id.'/imports/'.$import->id));
        if ($import->kind !== 'url') {
            $id = (string) preg_replace('/\..*$/', '', $import->source);
            @unlink($this->files->uploadPath($service, $id));
        }
    }

    // ── helpers ──────────────────────────────────────────────────────────────────────────────────────────

    private function unzip(string $archive, string $target): int
    {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new DomainError('import_format', 'The zip archive cannot be opened.', 422);
        }
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, ':')) {
                continue;
            }
            $names[] = $name;
        }
        $zip->extractTo($target, $names);
        $zip->close();

        return count($names);
    }

    private function findDocroot(string $extract): string
    {
        $best = null;
        $bestDepth = PHP_INT_MAX;
        foreach ($this->walk($extract, 5, true) as $entry) {
            if (! $entry->isDir()) {
                continue;
            }
            $depth = substr_count(str_replace('\\', '/', substr($entry->getPathname(), strlen($extract))), '/');
            if (in_array(strtolower($entry->getFilename()), self::DOCROOT_NAMES, true) && $depth < $bestDepth) {
                $best = $entry->getPathname();
                $bestDepth = $depth;
            }
        }
        if ($best !== null) {
            return $best;
        }
        foreach (['wp-config.php', 'index.php', 'index.html'] as $marker) {
            foreach ($this->walk($extract, 5) as $file) {
                if (strtolower($file->getFilename()) === $marker) {
                    return dirname($file->getPathname());
                }
            }
        }
        $top = array_values(array_filter(scandir($extract) ?: [], fn ($n) => ! in_array($n, ['.', '..', '__MACOSX'], true)));
        if (count($top) === 1 && is_dir($extract.'/'.$top[0])) {
            return $extract.'/'.$top[0];
        }

        return $extract;
    }

    /** @return iterable<SplFileInfo> */
    private function walk(string $dir, int $maxDepth, bool $dirs = false): iterable
    {
        if (! is_dir($dir)) {
            return [];
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), $dirs ? RecursiveIteratorIterator::SELF_FIRST : RecursiveIteratorIterator::LEAVES_ONLY);
        $it->setMaxDepth($maxDepth);
        foreach ($it as $entry) {
            /** @var SplFileInfo $entry */
            if ($dirs || $entry->isFile()) {
                yield $entry;
            }
        }
    }

    /** @return array{0:int,1:int} files and bytes under a directory */
    private function measure(string $dir): array
    {
        $files = 0;
        $bytes = 0;
        foreach ($this->walk($dir, 64) as $file) {
            $files++;
            $bytes += (int) $file->getSize();
        }

        return [$files, $bytes];
    }
}
