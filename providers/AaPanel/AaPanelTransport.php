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

    /**
     * A site's transport gets `$sharedNode` and `$siteWriter`; a transport of a panel folder (backups, database dumps)
     * gets neither — nothing a tenant can plant a link in.
     *
     * @param  Closure(string, array<string,mixed>, string, bool, array<string,mixed>): mixed  $post  the adapter's signed request (path, params, action, critical, files)
     * @param  (Closure(): ?bool)|null  $sharedNode  whether the operator closed the node as shared (AaPanelTenancyGate::isClosed; null = the row could not be read), asked at every call
     * @param  (Closure(): string)|null  $siteWriter  the site's own shell user, made ready (AaPanelTools::readyAgent): who writes into the site on a closed node
     */
    public function __construct(private readonly Closure $post, private readonly NodeShell $shell, private readonly string $root, private readonly string $siteUser = 'www', private readonly ?Closure $sharedNode = null, private readonly ?Closure $siteWriter = null) {}

    /**
     * On a node the operator closed as shared the panel's root file API is not used inside the site at all — not only
     * refused where a customer asks (ServiceFeatures), but here, where every caller ends up: an operation queued or
     * retrying from before `--apply`, a deployment, a final archive (TASK-0034 review round 2). Reads are refused too:
     * the panel reads as root and follows a planted link just as it writes through one.
     */
    private function assertOpenNode(): void
    {
        if ($this->sharedNode !== null) {
            AaPanelTenancyGate::refuseUnlessOpen(($this->sharedNode)());
        }
    }

    /** Closed, or not known to be open: the site-user paths are the safe ones either way. */
    private function closedNode(): bool
    {
        return $this->sharedNode !== null && ($this->sharedNode)() !== false;
    }

    /** Who writes into the site on a closed node: the site's own shell user — `www` itself may not run a binary there. */
    private function writer(): string
    {
        return $this->siteWriter !== null ? ($this->siteWriter)() : $this->siteUser;
    }

    public function list(string $path): array
    {
        $this->assertOpenNode();
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
        $this->assertOpenNode();
        $result = ($this->post)('/files?action=GetFileBody', ['path' => $this->abs($path)], 'files.body', false, []);
        $content = is_array($result) ? (string) ($result['data'] ?? '') : (string) $result;
        if (strlen($content) > $maxBytes) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The file is larger than the read limit; download it instead');
        }

        return $content;
    }

    public function write(string $path, string $content): void
    {
        $this->assertOpenNode();
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
        if ($this->closedNode()) {
            $this->uploadAsSiteUser($absolute, $localFile, $size);

            return;
        }
        $this->sendChunks(dirname($absolute), basename($absolute), $localFile, $size);
        if ($size === 0) {
            $this->write($path, '');
        }
    }

    /** The panel appends by `f_start`: one request per chunk, as root, into `$dir/$name`. */
    private function sendChunks(string $dir, string $name, string $localFile, int $size): void
    {
        $handle = fopen($localFile, 'rb');
        if ($handle === false) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The local file to upload cannot be read');
        }
        try {
            $offset = 0;
            do {
                $chunk = (string) fread($handle, self::UPLOAD_CHUNK);
                ($this->post)('/files?action=upload', ['f_path' => $dir, 'f_name' => $name, 'f_size' => $size, 'f_start' => $offset], 'files.upload', true, ['blob' => ['contents' => $chunk, 'filename' => $name]]);
                $offset += strlen($chunk);
            } while ($offset < $size && $chunk !== '');
        } finally {
            fclose($handle);
        }
    }

    /**
     * A closed shared node (TASK-0034 review round 2): the panel's upload appends chunk after chunk as root, and after the
     * first chunk the file stands in the site where the tenant can swap it for a link — the next chunk then lands
     * wherever that link points. Restores, archive restores and migrations upload into the site, so on a closed node the
     * chunks go into STAGE_DIR (root-only) and the site's own shell user writes the file into the site from there.
     */
    private function uploadAsSiteUser(string $absolute, string $localFile, int $size): void
    {
        $name = 'up-'.bin2hex(random_bytes(6));
        $staged = AaPanelShell::STAGE_DIR.'/'.$name;
        $writer = $this->writer();
        $prepare = $this->shell->run(AaPanelShell::stageDir(), ['timeout' => 30]);
        if (! $prepare->ok()) {
            throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'The upload could not be prepared on the server: '.mb_substr($prepare->output(), 0, 200));
        }
        try {
            $this->sendChunks(AaPanelShell::STAGE_DIR, $name, $localFile, $size);
            $run = $this->shell->run('F='.Q::arg($staged).'; [ -f "$F" ] || : > "$F"; '.AaPanelSiteUnpack::writeFileAsSiteUser('"$F"', $absolute, $writer), ['timeout' => 900]);
            if (! $run->ok()) {
                throw new ProviderException('aapanel', $run->timedOut ? ProviderErrorCode::TRANSIENT : ProviderErrorCode::VALIDATION, 'The file could not be written into the site: '.mb_substr($run->output(), 0, 300));
            }
        } finally {
            $this->shell->run('rm -f '.Q::arg($staged), ['timeout' => 30]);
        }
    }

    /**
     * Root used to read the file by its name (`stat`, then `tail -c` per chunk). The name is the tenant's: between the
     * platform packing an archive into the site and reading it back — a final archive, a staging copy — the site's PHP
     * or cron can swap it for a link to /root/.my.cnf, the panel's keys or a neighbour's wp-config.php, and those bytes
     * came back as the tenant's own backup (TASK-0034 review round 2, the read side of PA-02). So, as when an archive is
     * unpacked (AaPanelArchivePreflight::stage): the file is opened once, what was opened must lie inside the root, be a
     * regular file and have one name, exactly those bytes are copied into STAGE_DIR, and the chunks come from the copy.
     */
    public function download(string $path, string $localFile): void
    {
        $absolute = $this->abs($path);
        $copy = AaPanelShell::STAGE_DIR.'/get-'.bin2hex(random_bytes(6));
        try {
            $size = $this->copyForDownload($absolute, $copy);
            $this->readChunks($copy, $size, $localFile);
        } finally {
            $this->shell->run('rm -f '.Q::arg($copy), ['timeout' => 30]);
        }
    }

    /** @return int the size of the copy */
    private function copyForDownload(string $absolute, string $copy): int
    {
        // a panel folder's transport (root '') reads node files the platform itself names: no site boundary to hold
        $bound = $this->root === '' ? '/' : $this->root;
        $run = $this->shell->run(AaPanelShell::stageDir().' || exit 1; '
            .'S='.Q::arg($absolute).'; C='.Q::arg($copy).'; '
            .'RR=$(realpath -e -- '.Q::arg($bound).' 2>/dev/null); [ -n "$RR" ] || { echo "S root"; exit 0; }; '
            .'{ exec 3< "$S"; } 2>/dev/null || { echo "S missing"; exit 0; }; '
            .'if [ "$RR" != / ]; then case "$(readlink "/proc/self/fd/3")" in "$RR"/*) ;; *) echo "S outside"; exit 0;; esac; fi; '
            .'[ -f "/proc/self/fd/3" ] || { echo "S kind"; exit 0; }; '
            // a second name of a file the site's users could not read themselves (root's, another service user's); a file
            // of the sites' own users (`www`, a site's shell user) with two names is the tenant's own and still comes back
            .'[ "$(stat -L -c %h "/proc/self/fd/3")" = 1 ] || case "$(stat -L -c %U "/proc/self/fd/3")" in www|oh*ag) ;; *) echo "S links"; exit 0;; esac; '
            .'head -c '.(self::DOWNLOAD_MAX + 1).' <&3 > "$C" || { echo ERR; exit 0; }; exec 3<&-; '
            .'echo "SIZE $(stat -c %s "$C")"', ['timeout' => 900]);
        if ($run->timedOut || ! $run->ok()) {
            throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'Reading the file on the node failed: '.mb_substr($run->output(), 0, 200));
        }
        foreach (preg_split('/\r?\n/', $run->stdout) ?: [] as $line) {
            if (preg_match('/^SIZE (\d+)$/', trim($line), $m)) {
                if ((int) $m[1] > self::DOWNLOAD_MAX) {
                    throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Files over 2 GB are transferred by the backup download link');
                }

                return (int) $m[1];
            }
            if (str_starts_with($line, 'S ')) {
                throw match (trim(substr($line, 2))) {
                    'missing' => new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The file does not exist on the site'),
                    'root' => new ProviderException('aapanel', ProviderErrorCode::NOT_FOUND, 'The site folder was not found on the server'),
                    'outside' => new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The file was not read: it does not lie inside the site (a link to somewhere else).'),
                    'kind' => new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The file was not read: it is not an ordinary file.'),
                    'links' => new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The file was not read: it is a second name of another file (hardlink).'),
                    default => new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'The file could not be read on the node.'),
                };
            }
        }

        throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'The file could not be read on the node.');
    }

    private function readChunks(string $copy, int $size, string $localFile): void
    {
        $target = fopen($localFile, 'wb');
        if ($target === false) {
            throw new ProviderException('aapanel', ProviderErrorCode::UNKNOWN, 'The local download file cannot be written');
        }
        try {
            $offset = 0;
            while ($offset < $size) {
                // the chunk is a piece of the customer's file: staged where only root reads, not in /tmp where every site's
                // PHP on a shared node could read it (TASK-0034, IF-7)
                $temp = AaPanelShell::PRIVATE_DIR.'/dl-'.bin2hex(random_bytes(6)).'.b64';
                $cmd = AaPanelShell::privateDir().' && '.sprintf('tail -c +%d %s | head -c %d | base64 -w0 > %s', $offset + 1, Q::arg($copy), self::DOWNLOAD_CHUNK, $temp);
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
        if ($this->closedNode()) {
            // the panel deletes as root and a folder link on the way leads it into a neighbour's site (review round 2)
            $run = $this->shell->run('su -s /bin/bash '.Q::arg($this->writer()).' -c '.Q::arg('rm -'.($directory ? 'rf' : 'f').' -- '.Q::arg($absolute)), ['timeout' => 300]);
            if (! $run->ok()) {
                throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The file could not be deleted: '.mb_substr($run->output(), 0, 200));
            }

            return;
        }
        ($this->post)($directory ? '/files?action=DeleteDir' : '/files?action=DeleteFile', ['path' => $absolute], 'files.delete', true, []);
    }

    public function mkdir(string $path): void
    {
        $this->assertOpenNode();
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
        $this->assertOpenNode();
        ($this->post)('/files?action=MvFile', ['sfile' => $this->abs($from), 'dfile' => $this->abs($to)], 'files.move', true, []);
    }

    public function copy(string $from, string $to): void
    {
        $this->assertOpenNode();
        $source = $this->abs($from);
        $target = $this->abs($to);
        $run = $this->shell->run('cp -a '.Q::arg($source).' '.Q::arg($target).' && chown -R '.Q::arg($this->siteUser).':'.Q::arg($this->siteUser).' '.Q::arg($target), ['timeout' => 600]);
        if (! $run->ok()) {
            throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'Copy failed: '.$run->output());
        }
    }

    public function chmod(string $path, int $mode): void
    {
        $this->assertOpenNode();
        ($this->post)('/files?action=SetFileAccess', ['filename' => $this->abs($path), 'user' => $this->siteUser, 'access' => sprintf('%o', $mode & 0777), 'all' => 'False'], 'files.chmod', true, []);
    }

    public function archive(array $paths, string $target): void
    {
        // a closed node: the panel packs as root and writes the archive into the site; a final archive falls back to the
        // panel's own backup, which it makes in its root-only folder (review round 2)
        $this->assertOpenNode();
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

    /**
     * The panel unpacks as root. Its entries are listed on the node first and the archive is refused when one of them
     * would land outside the site — a link out with a file beneath it, a hardlink, an absolute name, `..` (TASK-0034,
     * permission program IF-7, exploit PA-02). Applies to every unpack: the customer's own archive, an import, a restore.
     *
     * The archive lies in the site, which the tenant can still change: it is copied out first and only the copy is judged
     * and unpacked (AaPanelArchivePreflight::stage, review round 1). On a node the operator closed as shared, the copy is
     * unpacked as the site user instead of by the panel's root UnZip (AaPanelSiteUnpack; since review round 3 the user
     * does the whole unpack, root only hands the copy over).
     */
    public function extract(string $archive, string $targetDir): void
    {
        $target = $this->abs($targetDir);
        $tar = AaPanelArchivePreflight::isTar($archive);
        $copy = (new AaPanelArchivePreflight($this->shell))->stage($this->abs($archive), $this->root, $target);
        try {
            if ($this->closedNode()) {
                $this->unpackAsSiteUser($copy, $tar, $target);

                return;
            }
            ($this->post)('/files?action=UnZip', ['sfile' => $copy, 'dfile' => $target, 'type' => $tar ? 'tar.gz' : 'zip', 'coding' => 'utf-8', 'password' => ''], 'files.unzip', true, []);
        } finally {
            $this->shell->run('rm -f '.Q::arg($copy), ['timeout' => 30]);
        }
    }

    /** Root only hands the judged copy over; the site user unpacks it (review round 3: no root `tar -x`, not even into STAGE_DIR). */
    private function unpackAsSiteUser(string $copy, bool $tar, string $target): void
    {
        $run = $this->shell->run(AaPanelSiteUnpack::unpackAsSiteUser($tar, Q::arg($copy), $this->root, $target, $this->writer(), $target === $this->root), ['timeout' => 900]);
        if (! $run->ok()) {
            throw new ProviderException('aapanel', $run->timedOut ? ProviderErrorCode::TRANSIENT : ProviderErrorCode::VALIDATION, 'The archive could not be unpacked: '.mb_substr($run->output(), 0, 300));
        }
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
