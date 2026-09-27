<?php

declare(strict_types=1);

namespace Onhost\Providers\AaPanel;

use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\NodeShell;
use Onhost\Providers\Shell\Q;

/**
 * Lists an archive on the node before anything unpacks it as root (TASK-0034, permission program IF-7, exploit PA-02).
 *
 * aaPanel's `UnZip` — and the restore's `unzip` — run as root. An archive is the customer's own content, so an entry
 * that is a symlink to /etc with a file beneath it, a hardlink, a device, an absolute name or a `..` wrote wherever
 * it pointed: another customer's site on the same node, the node's cron, the panel's keys. The platform only ever
 * checked the NAME of the archive.
 *
 * The listing is judged on the node and only a short report comes back (a site of 100 000 files would not fit the
 * shell's 1 MB answer): per entry type a count, the first bad names, every symlink with its target, and the first
 * entry that lies beneath a symlink of the same archive. Refused are hardlinks, devices, fifos, absolute names, `..`,
 * anything beneath a symlink of the archive, symlinks in a zip (zipinfo does not show where they point), and symlinks
 * whose target leaves the site. A symlink that stays inside the site is what real sites carry (Laravel's
 * `public/storage` points at its own absolute path, a release switch at `releases/<n>`) and is kept: refusing every
 * link would have refused the restore of such a site, which is legitimate behaviour of existing customers.
 *
 * An archive that lies in the site is copied out and judged as the copy (stage(), review round 1): the tenant can no
 * longer swap it or point it at a neighbour's file between the check and the unpack.
 *
 * What this does NOT cover: a symlink already standing in the site on disk that the panel's root unpack writes
 * through. That race is not engineered around on an open node (program D11); on a node several customers share and
 * the operator closed (`operator:aapanel:tenancy`), unpacks go in as the site user instead (AaPanelSiteUnpack).
 */
final class AaPanelArchivePreflight
{
    /** More links than this in one archive is not a site, it is a probe; the beneath-check is O(names x links). */
    private const MAX_LINKS = 200;

    /** Entry types a site archive may carry: regular file, directory, symlink (the last one judged by its target). */
    private const ALLOWED_TYPES = ['-', 'd', 'l'];

    /** What the copy step (stage()) reports, in the customer's words; nothing of the file itself is echoed. */
    private const STAGE_REFUSALS = [
        'root' => 'the site folder was not found on the server',
        'target' => 'the folder it would be unpacked into leads out of the site',
        'missing' => 'it could not be opened',
        'outside' => 'it does not lie inside the site (a link to somewhere else)',
        'kind' => 'it is not an ordinary file',
        'links' => 'it is a second name of another file (hardlink)',
    ];

    public function __construct(private readonly NodeShell $shell) {}

    /**
     * @param  string  $archive  absolute path of the archive on the node
     * @param  string  $extractRoot  absolute folder the entries are unpacked into
     * @param  string  $relativeBound  a relative link target, resolved from where its entry lands, must stay inside this
     * @param  string  $siteRoot  an absolute link target must stay inside this (the site as it will serve)
     */
    public function assertSafe(string $archive, string $extractRoot, string $relativeBound, string $siteRoot): void
    {
        $this->judge(! self::isTar($archive), 'A='.Q::arg($archive).'; ', $extractRoot, $relativeBound, $siteRoot);
    }

    /**
     * An archive that lies in the site is still the tenant's while it is checked: the site's own PHP (`www`) can swap it
     * for another file between the listing and the unpack, or leave a link there to a file only root can read — a
     * neighbour's backup, which root would then list and unpack into the tenant's site (TASK-0034 review round 1).
     *
     * So in one root step the file is opened ONCE and everything is judged on what was opened, not on the name: the path
     * the kernel reports for the open file must lie in the site (a link anywhere on the way out is caught, so is a
     * folder link), it must be a regular file with a single name (no hardlink to a file the tenant cannot read), and
     * exactly those bytes are copied into STAGE_DIR, which no tenant can enter. The copy is listed and judged, and only
     * the copy is ever unpacked. The folder it is unpacked into is resolved as well: a link out of the site there would
     * steer the root unpack past every entry check (that last look is still a race on disk; D11 closes shared nodes).
     *
     * @return string the copy (absolute, on the node) — the caller unpacks it and removes it
     */
    public function stage(string $archive, string $siteRoot, string $extractRoot): string
    {
        $tar = self::isTar($archive);
        $copy = AaPanelShell::STAGE_DIR.'/stage-'.bin2hex(random_bytes(6)).($tar ? '.tar.gz' : '.zip');
        $stage = AaPanelShell::stageDir().' || exit 1; '
            .'S='.Q::arg($archive).'; A='.Q::arg($copy).'; '
            .'RR=$(realpath -e -- '.Q::arg($siteRoot).' 2>/dev/null); if [ -z "$RR" ] || [ "$RR" = / ]; then echo "S root"; exit 0; fi; '
            .'TR=$(realpath -m -- '.Q::arg($extractRoot).' 2>/dev/null); case "$TR" in "$RR"|"$RR"/*) ;; *) echo "S target"; exit 0;; esac; '
            // /proc/self in each child is the same open file: fd 3 is inherited, so this holds in a subshell too
            .'{ exec 3< "$S"; } 2>/dev/null || { echo "S missing"; exit 0; }; '
            .'case "$(readlink "/proc/self/fd/3")" in "$RR"/*) ;; *) echo "S outside"; exit 0;; esac; '
            .'[ -f "/proc/self/fd/3" ] || { echo "S kind"; exit 0; }; '
            .'[ "$(stat -L -c %h "/proc/self/fd/3")" = 1 ] || { echo "S links"; exit 0; }; '
            .'cat <&3 > "$A" || { echo ERR; exit 0; }; exec 3<&-; ';
        try {
            $this->judge(! $tar, $stage, $extractRoot, $siteRoot, $siteRoot);
        } catch (\Throwable $e) {
            $this->shell->run('rm -f '.Q::arg($copy), ['timeout' => 30]);

            throw $e;
        }

        return $copy;
    }

    /** @param string $prelude shell words that leave the archive to list in `$A` (a copy made there, or a root-owned file) */
    private function judge(bool $zip, string $prelude, string $extractRoot, string $relativeBound, string $siteRoot): void
    {
        $run = $this->shell->run($zip ? self::zipScript($prelude) : self::tarScript($prelude), ['timeout' => 900]);
        if ($run->timedOut) {
            throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'The archive could not be checked in time; it was not unpacked.');
        }
        if (! $run->ok()) {
            self::refuse('the node could not list it');
        }
        $types = [];
        $links = [];
        $counted = false;
        foreach (preg_split('/\r?\n/', $run->stdout) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            $tag = substr($line, 0, 2);
            $rest = substr($line, 2);
            match (true) {
                $line === 'ERR' => self::refuse('the node could not read it'),
                $tag === 'S ' => self::refuse(self::STAGE_REFUSALS[trim($rest)] ?? 'it could not be copied out of the site'),
                $tag === 'N ' => $counted = self::counts($rest),
                $tag === 'T ' => $types[] = $rest,
                $tag === 'B ' => self::refuse('an entry leaves the folder it is unpacked into', $rest),
                $tag === 'U ' => self::refuse('an entry lies beneath a link of the same archive', $rest),
                $tag === 'X ' => self::refuse('a link could not be read', $rest),
                $tag === 'L ' => $links[] = $rest,
                default => null, // the panel's security banner and other chatter
            };
        }
        if (! $counted) {
            self::refuse('the node did not list it');
        }
        foreach ($types as $type) {
            $char = substr($type, 0, 1);
            if (! in_array($char, self::ALLOWED_TYPES, true) || ($zip && $char === 'l')) {
                self::refuse(match ($char) {
                    'h' => 'it carries a hardlink',
                    'l' => 'it carries a symlink (not accepted in a zip)',
                    default => 'it carries a special file (device, fifo or socket)',
                });
            }
        }
        if (count($links) > self::MAX_LINKS) {
            self::refuse('it carries more than '.self::MAX_LINKS.' symlinks');
        }
        foreach ($links as $link) {
            [$name, $target] = array_pad(explode("\t", $link, 2), 2, '');
            if (! self::linkStaysInside(self::clean($name), $target, $extractRoot, $relativeBound, $siteRoot)) {
                self::refuse('a symlink points outside the site', $name);
            }
        }
    }

    public static function isTar(string $archive): bool
    {
        $lower = strtolower($archive);

        return str_ends_with($lower, '.tar.gz') || str_ends_with($lower, '.tgz');
    }

    /**
     * Two listings of the same archive, line for line in the same order: the verbose one for the type of each entry and
     * where a link points, the plain one for its name (GNU tar escapes both the same way). Nothing of the archive is
     * written anywhere but PRIVATE_DIR, and both listings are removed whatever happens.
     */
    private static function tarScript(string $prelude): string
    {
        [$v, $n] = self::scratch();

        return AaPanelShell::privateDir().' || exit 1; '
            .'V='.Q::arg($v).'; N='.Q::arg($n).'; trap \'rm -f "$V" "$N"\' EXIT; '.$prelude
            .'if ! tar --force-local --numeric-owner -tzvf "$A" > "$V" 2>/dev/null || ! tar --force-local -tzf "$A" > "$N" 2>/dev/null; then echo ERR; exit 0; fi; '
            .'echo "N $(wc -l < "$N") $(wc -l < "$V")"; '
            .self::commonChecks()
            // every link: its name from the plain listing, its target from the verbose line after " <name> -> "
            .'awk \'NR==FNR { v[FNR]=$0; next } substr(v[FNR],1,1)=="l" { s=" " $0 " -> "; i=index(v[FNR], s); if (i==0) print "X " $0; else print "L " $0 "\t" substr(v[FNR], i+length(s)) }\' "$V" "$N" | head -n '.(self::MAX_LINKS + 1).'; '
            // the first entry that lies beneath a link of the same archive (unpacked, it would be written through the link)
            .'awk \'NR==FNR { if (substr($0,1,1)=="l") ln[FNR]=1; next } { n=$0; sub(/^(\.\/)+/, "", n); sub(/\/+$/, "", n); name[FNR]=n; if (FNR in ln) link[n]=1 } END { for (i in name) for (x in link) if (name[i] != x && index(name[i], x "/")==1) { print "U " name[i]; exit } }\' "$V" "$N"';
    }

    /** zipinfo's short lines are the entries whose second field is the zip version ("3.0"); header and totals are not. */
    private static function zipScript(string $prelude): string
    {
        [$v, $n] = self::scratch();

        return AaPanelShell::privateDir().' || exit 1; '
            .'V='.Q::arg($v).'; N='.Q::arg($n).'; trap \'rm -f "$V" "$N"\' EXIT; '.$prelude
            .'if ! unzip -Zs "$A" 2>/dev/null | awk \'$2 ~ /^[0-9]+\.[0-9]+$/\' > "$V" || ! unzip -Z1 "$A" > "$N" 2>/dev/null; then echo ERR; exit 0; fi; '
            .'echo "N $(wc -l < "$N") $(wc -l < "$V")"; '
            .self::commonChecks();
    }

    /** The count of every entry type, and the first names that are absolute or climb out with `..` (either slash). */
    private static function commonChecks(): string
    {
        return 'awk \'{ c[substr($0,1,1)]++ } END { for (t in c) print "T " t " " c[t] }\' "$V"; '
            .'grep -E \'^[/\\\\]|(^|[/\\\\])\.\.([/\\\\]|$)\' "$N" | head -n 5 | sed \'s/^/B /\'; ';
    }

    /** @return array{0:string,1:string} */
    private static function scratch(): array
    {
        $id = bin2hex(random_bytes(6));

        return [AaPanelShell::PRIVATE_DIR.'/pre-'.$id.'.v', AaPanelShell::PRIVATE_DIR.'/pre-'.$id.'.n'];
    }

    private static function counts(string $rest): bool
    {
        $parts = preg_split('/\s+/', trim($rest)) ?: [];
        if (count($parts) !== 2 || ! ctype_digit($parts[0]) || $parts[0] !== $parts[1]) {
            self::refuse('its two listings do not agree'); // a name with a line break, or a listing cut short: not judged, not unpacked
        }

        return true;
    }

    private static function clean(string $name): string
    {
        $name = preg_replace('~^(\./)+~', '', str_replace('\\', '/', $name)) ?? $name;

        return rtrim($name, '/');
    }

    /** Lexical: the archive's own links are judged before they exist, so there is nothing on disk to resolve yet. */
    private static function linkStaysInside(string $name, string $target, string $extractRoot, string $relativeBound, string $siteRoot): bool
    {
        if ($target === '' || str_contains($target, "\0")) {
            return false;
        }
        if (str_starts_with($target, '/')) {
            return self::inside(self::normalize($target), $siteRoot);
        }
        $dir = dirname($name);
        $base = rtrim($extractRoot, '/').($dir === '.' || $dir === '' ? '' : '/'.$dir);

        return self::inside(self::normalize($base.'/'.$target), $relativeBound);
    }

    private static function normalize(string $path): ?string
    {
        $out = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($out === []) {
                    return null; // above the file system's root: not a place a site can mean
                }
                array_pop($out);

                continue;
            }
            $out[] = $part;
        }

        return '/'.implode('/', $out);
    }

    private static function inside(?string $path, string $bound): bool
    {
        $bound = rtrim($bound, '/');
        if ($path === null || $bound === '') {
            return false;
        }

        return $path === $bound || str_starts_with($path, $bound.'/');
    }

    private static function refuse(string $why, ?string $entry = null): never
    {
        throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The archive was not unpacked: '.$why.($entry !== null ? ' ('.mb_substr($entry, 0, 120).')' : '').'.');
    }
}
