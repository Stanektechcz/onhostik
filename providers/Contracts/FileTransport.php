<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * Files of one site, addressed relative to the site root (the implementation jails every path). aaPanel is backed
 * by the panel's file API plus the node shell, ISPConfig by SFTP as the site's agent user. Large transfers stream
 * through local temporary files, never through memory.
 */
interface FileTransport
{
    /** @return array{path:string, entries:list<array{name:string, type:string, size:?int, modified:?string, mode:?string}>} */
    public function list(string $path): array;

    public function read(string $path, int $maxBytes = 20971520): string;

    public function write(string $path, string $content): void;

    /** Upload a local file (stream) to the site. */
    public function upload(string $path, string $localFile): void;

    /** Download a site file into a local file (stream). */
    public function download(string $path, string $localFile): void;

    public function delete(string $path, bool $directory = false): void;

    public function mkdir(string $path): void;

    public function rename(string $from, string $to): void;

    public function copy(string $from, string $to): void;

    public function chmod(string $path, int $mode): void;

    /** @param list<string> $paths site-relative paths packed into `$target` (zip or tar.gz by extension) */
    public function archive(array $paths, string $target): void;

    public function extract(string $archive, string $targetDir): void;

    public function exists(string $path): bool;

    /** Absolute site root on the node (for shell commands). */
    public function root(): string;
}
