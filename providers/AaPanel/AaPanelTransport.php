<?php

declare(strict_types=1);

namespace Onhost\Providers\AaPanel;

use Closure;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\FileTransport;
use Onhost\Providers\Contracts\NodeShell;
use Onhost\Providers\Shell\Q;

/**
 * Files of one aaPanel site through the panel's file API (`files?action=GetDir|GetFileBody|SaveFileBody|upload|MvFile|
 * CopyFile|SetFileAccess|Zip|UnZip|DeleteFile|DeleteDir`) with the node shell for what the API lacks (downloads of
 * arbitrary size, directory copies, existence checks). Every path is jailed to the site root; uploads are chunked
 * (the panel appends by `f_start`), downloads come back base64-encoded in chunks so nothing large sits in memory.
 */
final class AaPanelTransport implements FileTransport
{
    private const UPLOAD_CHUNK = 4194304;

    private const DOWNLOAD_CHUNK = 8388608;

    private const DOWNLOAD_MAX = 2147483648;

    /** @param Closure(string, array<string,mixed>, string, bool, array<string,mixed>): mixed $post the adapter's signed request (path, params, action, critical, files) */
    public function __construct(private readonly Closure $post, private readonly NodeShell $shell, private readonly string $root, private readonly string $siteUser = 'www') {}

    public function list(string $path): array
    {
        $absolute = $this->abs($path);
        $result = ($this->post)('/files?action=GetDir', ['path' => $absolute, 'p' => 1, 'showRow' => 1000], 'files.list', false, []);
        if (isset($result['PATH']) && rtrim((string) $result['PATH'], '/') !== rtrim($absolute, '/')) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The folder does not exist'); // the panel answers a missing folder with its default listing
        }
        $entries = [];
        foreach ([['DIR', 'dir'], ['FILES', 'file']] as [$key, $type]) {
            foreach ((array) ($result[$key] ?? []) as $line) {
                $parts = explode(';', (string) $line);
                if (($parts[0] ?? '') === '') {
                    continue;
                }
                $entries[] = [
                    'name' => $parts[0], 'type' => $type,
                    'size' => $type === 'file' && isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : null,
                    'modified' => isset($parts[2]) && is_numeric($parts[2]) ? date(DATE_ATOM, (int) $parts[2]) : null,
                    'mode' => isset($parts[3]) && preg_match('/^[0-7]{3,4}$/', $parts[3]) ? $parts[3] : null,
                    'owner' => $parts[4] ?? null,
                ];
            }
        }
        usort($entries, fn ($a, $b) => [$a['type'] !== 'dir', $a['name']] <=> [$b['type'] !== 'dir', $b['name']]);

        return ['path' => '/'.trim(str_replace('\\', '/', $path), '/'), 'entries' => $entries];
    }

    public function read(string $path, int $maxBytes = 20971520): string
    {
        $result = ($this->post)('/files?action=GetFileBody', ['path' => $this->abs($path)], 'files.body', false, []);
        $content = is_array($result) ? (string) ($result['data'] ?? '') : (string) $result;
        if (strlen($content) > $maxBytes) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The file is larger than the read limit; download it instead');
        }

        return $content;
    }

    public function write(string $path, string $content): void
    {
        $absolute = $this->abs($path);
        try {
            ($this->post)('/files?action=SaveFileBody', ['path' => $absolute, 'data' => $content, 'encoding' => 'utf-8'], 'files.save', true, []);
        } catch (ProviderException $e) {
            if (! str_contains(mb_strtolower($e->getMessage()), 'not exist')) {
                throw $e;
            }
            ($this->post)('/files?action=CreateFile', ['path' => $absolute], 'files.create', true, []);
            ($this->post)('/files?action=SaveFileBody', ['path' => $absolute, 'data' => $content, 'encoding' => 'utf-8'], 'files.save', true, []);
        }
    }

    public function upload(string $path, string $localFile): void
    {
        $absolute = $this->abs($path);
        $size = filesize($localFile);
        if ($size === false) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The local file to upload does not exist');
        }
        $handle = fopen($localFile, 'rb');
        if ($handle === false) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The local file to upload cannot be read');
        }
        try {
            $offset = 0;
            do {
                $chunk = (string) fread($handle, self::UPLOAD_CHUNK);
                ($this->post)('/files?action=upload', ['f_path' => dirname($absolute), 'f_name' => basename($absolute), 'f_size' => $size, 'f_start' => $offset], 'files.upload', true, ['blob' => ['contents' => $chunk, 'filename' => basename($absolute)]]);
                $offset += strlen($chunk);
            } while ($offset < $size && $chunk !== '');
        } finally {
            fclose($handle);
        }
        if ($size === 0) {
            $this->write($path, '');
        }
    }

    public function download(string $path, string $localFile): void
    {
        $absolute = $this->abs($path);
        $stat = $this->shell->run('stat -c %s '.Q::arg($absolute), ['timeout' => 30]);
        $size = (int) trim($stat->stdout);
        if (! $stat->ok()) {
            throw new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The file does not exist on the site');
        }
        if ($size > self::DOWNLOAD_MAX) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Files over 2 GB are transferred by the backup download link');
        }
        $target = fopen($localFile, 'wb');
        if ($target === false) {
            throw new ProviderException('aapanel', ProviderErrorCode::UNKNOWN, 'The local download file cannot be written');
        }
        try {
            $offset = 0;
            while ($offset < $size) {
                $temp = '/tmp/onhost-dl-'.bin2hex(random_bytes(6)).'.b64';
                $cmd = sprintf('tail -c +%d %s | head -c %d | base64 -w0 > %s', $offset + 1, Q::arg($absolute), self::DOWNLOAD_CHUNK, $temp);
                $run = $this->shell->run($cmd, ['timeout' => 300]);
                if (! $run->ok()) {
                    throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'Reading the file on the node failed: '.$run->output());
                }
                $body = ($this->post)('/files?action=GetFileBody', ['path' => $temp], 'files.chunk', false, []);
                $this->shell->run('rm -f '.Q::arg($temp), ['timeout' => 15]);
                $encoded = is_array($body) ? (string) ($body['data'] ?? '') : (string) $body;
                $decoded = base64_decode(trim($encoded), true);
                if ($decoded === false) {
                    throw new ProviderException('aapanel', ProviderErrorCode::PROVIDER_BUG, 'The node returned an unreadable file chunk');
                }
                fwrite($target, $decoded);
                $offset += strlen($decoded);
                if ($decoded === '') {
                    break;
                }
            }
        } finally {
            fclose($target);
        }
    }

    public function delete(string $path, bool $directory = false): void
    {
        $absolute = $this->abs($path);
        if ($absolute === $this->root) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The site root cannot be deleted');
        }
        ($this->post)($directory ? '/files?action=DeleteDir' : '/files?action=DeleteFile', ['path' => $absolute], 'files.delete', true, []);
    }

    public function mkdir(string $path): void
    {
        try {
            ($this->post)('/files?action=CreateDir', ['path' => $this->abs($path)], 'files.mkdir', true, []);
        } catch (ProviderException $e) {
            if ($e->errorCode !== ProviderErrorCode::CONFLICT) {
                throw $e; // an existing folder is the desired state
            }
        }
    }

    public function rename(string $from, string $to): void
    {
        ($this->post)('/files?action=MvFile', ['sfile' => $this->abs($from), 'dfile' => $this->abs($to)], 'files.move', true, []);
    }

    public function copy(string $from, string $to): void
    {
        $source = $this->abs($from);
        $target = $this->abs($to);
        $run = $this->shell->run('cp -a '.Q::arg($source).' '.Q::arg($target).' && chown -R '.Q::arg($this->siteUser).':'.Q::arg($this->siteUser).' '.Q::arg($target), ['timeout' => 600]);
        if (! $run->ok()) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Copy failed: '.$run->output());
        }
    }

    public function chmod(string $path, int $mode): void
    {
        ($this->post)('/files?action=SetFileAccess', ['filename' => $this->abs($path), 'user' => $this->siteUser, 'access' => sprintf('%o', $mode & 0777), 'all' => 'False'], 'files.chmod', true, []);
    }

    public function archive(array $paths, string $target): void
    {
        $type = str_ends_with(strtolower($target), '.tar.gz') || str_ends_with(strtolower($target), '.tgz') ? 'tar.gz' : 'zip';
        // the panel joins `path` with every name in `sfile`: names are relative to the site root, the archive absolute
        // … and expects the list to end with a comma, like its own file manager sends it (verified live on 8.0.6)
        $names = [];
        foreach ($paths as $p) {
            $relative = trim(substr($this->abs($p), strlen($this->root)), '/');
            if ($relative !== '') {
                $names[] = $relative;

                continue;
            }
            foreach ($this->list('')['entries'] as $entry) { // the whole site is what stands in its root — without the archive that is being written
                if ($entry['name'] !== trim(str_replace('\\', '/', $target), '/')) {
                    $names[] = $entry['name'];
                }
            }
        }
        if ($names === []) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'There is nothing to pack');
        }
        $sources = implode(',', array_values(array_unique($names))).',';
        ($this->post)('/files?action=Zip', ['sfile' => $sources, 'dfile' => $this->abs($target), 'z_type' => $type, 'path' => $this->root], 'files.zip', true, []);
    }

    public function extract(string $archive, string $targetDir): void
    {
        $type = str_ends_with(strtolower($archive), '.tar.gz') || str_ends_with(strtolower($archive), '.tgz') ? 'tar.gz' : 'zip';
        ($this->post)('/files?action=UnZip', ['sfile' => $this->abs($archive), 'dfile' => $this->abs($targetDir), 'type' => $type, 'coding' => 'utf-8', 'password' => ''], 'files.unzip', true, []);
    }

    public function exists(string $path): bool
    {
        return $this->shell->run('test -e '.Q::arg($this->abs($path)), ['timeout' => 15])->ok();
    }

    public function root(): string
    {
        return $this->root;
    }

    /** Absolute path inside the site root; anything escaping it is refused before it reaches the panel. */
    public function abs(string $path): string
    {
        $relative = trim(str_replace('\\', '/', $path), '/');
        if ($relative === '.') {
            $relative = ''; // the site root itself ("pack everything", "unpack here") — the guard below took it for a way out of the root
        }
        if ($relative !== '' && (str_contains($relative, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $relative))) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Path must stay inside the site root');
        }

        return $relative === '' ? $this->root : $this->root.'/'.$relative;
    }
}
