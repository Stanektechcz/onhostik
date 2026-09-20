<?php

declare(strict_types=1);

namespace Onhost\Providers\Shell;

use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\FileTransport;
use phpseclib3\Net\SFTP;

/**
 * Files of one site over SFTP as the site's own (jailed) user — ISPConfig nodes, where the panel has no file API.
 * Copies, archives and extraction run through the same SSH session with the node's coreutils; every path is jailed
 * to the site root, which for a jailed user is also the filesystem the user can see.
 */
final class SftpTransport implements FileTransport
{
    public function __construct(private readonly SshShell $shell, private readonly string $root, private readonly string $provider = 'ispconfig') {}

    public function list(string $path): array
    {
        $absolute = $this->abs($path);
        $raw = $this->sftp()->rawlist($absolute);
        if ($raw === false) {
            throw new ProviderException($this->provider, ProviderErrorCode::NOT_FOUND, 'The folder does not exist on the site');
        }
        $entries = [];
        foreach ($raw as $name => $attrs) {
            if (! is_array($attrs) || $name === '.' || $name === '..') {
                continue;
            }
            $type = (int) ($attrs['type'] ?? 1) === 2 ? 'dir' : 'file';
            $entries[] = [
                'name' => (string) $name, 'type' => $type, 'size' => $type === 'file' ? (int) ($attrs['size'] ?? 0) : null,
                'modified' => isset($attrs['mtime']) ? date(DATE_ATOM, (int) $attrs['mtime']) : null,
                'mode' => isset($attrs['mode']) ? sprintf('%o', ((int) $attrs['mode']) & 0777) : null, 'owner' => isset($attrs['uid']) ? (string) $attrs['uid'] : null,
            ];
        }
        usort($entries, fn ($a, $b) => [$a['type'] !== 'dir', $a['name']] <=> [$b['type'] !== 'dir', $b['name']]);

        return ['path' => '/'.trim(str_replace('\\', '/', $path), '/'), 'entries' => $entries];
    }

    public function read(string $path, int $maxBytes = 20971520): string
    {
        $absolute = $this->abs($path);
        $sftp = $this->sftp();
        $size = $sftp->filesize($absolute);
        if ($size === false) {
            throw new ProviderException($this->provider, ProviderErrorCode::NOT_FOUND, 'The file does not exist on the site');
        }
        if ($size > $maxBytes) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'The file is larger than the read limit; download it instead');
        }
        $content = $sftp->get($absolute);

        return is_string($content) ? $content : '';
    }

    public function write(string $path, string $content): void
    {
        if (! $this->sftp()->put($this->abs($path), $content)) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'The file could not be written: '.$this->lastError());
        }
    }

    public function upload(string $path, string $localFile): void
    {
        if (! $this->sftp()->put($this->abs($path), $localFile, SFTP::SOURCE_LOCAL_FILE)) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'The upload failed: '.$this->lastError());
        }
    }

    public function download(string $path, string $localFile): void
    {
        if ($this->sftp()->get($this->abs($path), $localFile) === false) {
            throw new ProviderException($this->provider, ProviderErrorCode::NOT_FOUND, 'The download failed: '.$this->lastError());
        }
    }

    public function delete(string $path, bool $directory = false): void
    {
        $absolute = $this->abs($path);
        if ($absolute === $this->root) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'The site root cannot be deleted');
        }
        if (! $this->sftp()->delete($absolute, $directory)) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'The delete failed: '.$this->lastError());
        }
    }

    public function mkdir(string $path): void
    {
        $absolute = $this->abs($path);
        if (! $this->sftp()->mkdir($absolute, -1, true) && ! $this->sftp()->is_dir($absolute)) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'The folder could not be created: '.$this->lastError());
        }
    }

    public function rename(string $from, string $to): void
    {
        if (! $this->sftp()->rename($this->abs($from), $this->abs($to))) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'The rename failed: '.$this->lastError());
        }
    }

    public function copy(string $from, string $to): void
    {
        $run = $this->shell->run('cp -a '.Q::arg($this->abs($from)).' '.Q::arg($this->abs($to)), ['timeout' => 600]);
        if (! $run->ok()) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'Copy failed: '.$run->output());
        }
    }

    public function chmod(string $path, int $mode): void
    {
        if ($this->sftp()->chmod($mode & 0777, $this->abs($path)) === false) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'Changing permissions failed: '.$this->lastError());
        }
    }

    public function archive(array $paths, string $target): void
    {
        $tar = str_ends_with(strtolower($target), '.tar.gz') || str_ends_with(strtolower($target), '.tgz');
        $sources = implode(' ', array_map(fn (string $p) => Q::arg($this->rel($p) === '' ? '.' : $this->rel($p)), $paths)); // '.' is the whole site
        // the archive is written into the tree it packs: it never packs itself
        $cmd = $tar
            ? 'tar czf '.Q::arg($this->abs($target)).' --exclude='.Q::arg('./'.$this->rel($target)).' '.$sources
            : 'zip -qr '.Q::arg($this->abs($target)).' '.$sources.' -x '.Q::arg($this->rel($target));
        $run = $this->shell->run($cmd, ['cwd' => $this->root, 'timeout' => 900]);
        // tar exits 1 when a file changed or vanished while it was being read — a live site does that all day (caches, logs,
        // sessions). The archive is complete and usable; calling that a failure made the backup of every busy site fail.
        $usable = $tar && $run->exitCode === 1 && ! $run->timedOut && $this->exists($target);
        if (! $run->ok() && ! $usable) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'Packing failed: '.$run->output());
        }
    }

    public function extract(string $archive, string $targetDir): void
    {
        $this->mkdir($targetDir);
        $tar = str_ends_with(strtolower($archive), '.tar.gz') || str_ends_with(strtolower($archive), '.tgz');
        $cmd = $tar ? 'tar xzf '.Q::arg($this->abs($archive)).' -C '.Q::arg($this->abs($targetDir)) : 'unzip -oq '.Q::arg($this->abs($archive)).' -d '.Q::arg($this->abs($targetDir));
        $run = $this->shell->run($cmd, ['timeout' => 900]);
        if (! $run->ok()) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'Unpacking failed: '.$run->output());
        }
    }

    public function exists(string $path): bool
    {
        return $this->sftp()->stat($this->abs($path)) !== false;
    }

    public function root(): string
    {
        return $this->root;
    }

    public function abs(string $path): string
    {
        $relative = $this->rel($path);

        return $relative === '' ? $this->root : rtrim($this->root, '/').'/'.$relative;
    }

    private function rel(string $path): string
    {
        $relative = trim(str_replace('\\', '/', $path), '/');
        if ($relative === '.') {
            return ''; // the site root itself ("pack everything", "unpack here") — the guard below took it for a way out of the root
        }
        if ($relative !== '' && (str_contains($relative, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $relative))) {
            throw new ProviderException($this->provider, ProviderErrorCode::VALIDATION, 'Path must stay inside the site root');
        }

        return $relative;
    }

    private function sftp(): SFTP
    {
        return $this->shell->sftp();
    }

    private function lastError(): string
    {
        $errors = $this->sftp()->getSFTPErrors();

        return is_array($errors) && $errors !== [] ? (string) end($errors) : 'unknown error';
    }
}
