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
 * link would have refused the restore of such a site, which is legitimate behaviour of existing customers. A link is
 * judged together with the other links of its archive, where it will land (linkStaysInside(), landing(); review round
 * 3): `y -> .` beside `x -> y/../other.cz` is the neighbour, not a name inside the site. That only holds when every name
 * is read as it lands, so a name with a `.` or empty part (`q/./y` lands as `q/y`) is refused outright (review round 4).
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

    /** Links followed while one target is resolved; more is a loop (Linux gives up at 40 as well: ELOOP). */
    private const MAX_HOPS = 40;

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
     * @param  string  $extractRoot  absolute folder the entries land in (where they will lie, not a folder they pass through)
     * @param  string  $relativeBound  a relative link target, resolved from where its entry lands, must stay inside this
     * @param  string  $siteRoot  an absolute link target must stay inside this (the site as it will serve)
     * @param  string|null  $folder  the archive's top folder that alone lands in `$extractRoot` when the archive has it as
     *                               a real folder (a panel backup packs `<site>/…`; AaPanelSiteUnpack moves it up)
     */
    public function assertSafe(string $archive, string $extractRoot, string $relativeBound, string $siteRoot, ?string $folder = null): void
    {
        $this->judge(! self::isTar($archive), 'A='.Q::arg($archive).'; ', $extractRoot, $relativeBound, $siteRoot, $folder);
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
    private function judge(bool $zip, string $prelude, string $extractRoot, string $relativeBound, string $siteRoot, ?string $folder = null): void
    {
        $run = $this->shell->run($zip ? self::zipScript($prelude) : self::tarScript($prelude, $folder), ['timeout' => 900]);
        if ($run->timedOut) {
            throw new ProviderException('aapanel', ProviderErrorCode::TRANSIENT, 'The archive could not be checked in time; it was not unpacked.');
        }
        if (! $run->ok()) {
            self::refuse('the node could not list it');
        }
        $types = [];
        $links = [];
        $counted = false;
        $inFolder = null;
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
                $tag === 'D ' => self::refuse('an entry name has an empty or "." part (it lands under another name than the one judged)', $rest),
                $tag === 'U ' => self::refuse('an entry lies beneath a link of the same archive', $rest),
                $tag === 'X ' => self::refuse('a link could not be read', $rest),
                $tag === 'L ' => $links[] = $rest,
                $tag === 'F ' => $inFolder = ctype_digit(trim($rest)) ? (int) trim($rest) : null,
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
        $landing = self::landing(self::linkMap($links), $folder, $inFolder);
        foreach ($landing as $name => $target) {
            $bound = str_starts_with($target, '/') ? $siteRoot : $relativeBound;
            if (! self::linkStaysInside((string) $name, $target, $landing, $extractRoot, $bound)) {
                self::refuse('a symlink points outside the site', (string) $name);
            }
        }
    }

    /**
     * @param  list<string>  $links  "<name>\t<target>" as the listing printed them
     * @return array<string, string> name (relative to where the archive lands) => target
     */
    private static function linkMap(array $links): array
    {
        $map = [];
        foreach ($links as $link) {
            [$name, $target] = array_pad(explode("\t", $link, 2), 2, '');
            // tar escapes what it cannot print (`\\`, `\t`, octal): the name or target read is then not the one on disk,
            // and a link can only be judged by what it really says (review round 3)
            if (str_contains($name, '\\') || str_contains($target, '\\')) {
                self::refuse('a symlink could not be judged (the listing had to escape its name or target)', $name);
            }
            $map[self::clean($name)] = $target;
        }

        return $map;
    }

    /**
     * A link's name as it will land: one leading `./` and one trailing `/` taken off, nothing else. A name with a `.` or
     * empty part left (`q/./y`, `q//y`, `.//shop.cz`, `././y`) is refused, as the node refuses it for every entry (`D`,
     * commonChecks()): the kernel lands `q/./y` as `q/y`, so a name kept as written was never met while another link was
     * resolved through it, and `.//shop.cz` was not the folder the unpack tests for (TASK-0034 review round 4). A link
     * named for the unpack folder itself (`.`, `./`) is refused too.
     */
    private static function clean(string $name): string
    {
        $clean = str_starts_with($name, './') ? substr($name, 2) : $name;
        $clean = str_ends_with($clean, '/') ? substr($clean, 0, -1) : $clean;
        foreach (explode('/', $clean) as $part) {
            if ($part === '' || $part === '.') {
                self::refuse('an entry name has an empty or "." part (it lands under another name than the one judged)', $name);
            }
        }

        return $clean;
    }

    /**
     * Where the links really land. With `$folder`, and the archive holding it as a real folder (entries beneath it, and
     * not a link of that name — exactly the test AaPanelSiteUnpack makes), only that folder moves in and its entries
     * land one level up; the rest of the archive never lands. A link judged where it was unpacked instead
     * (`<site>/up -> ../other.cz` stays inside the unpack folder) would leave the site once moved (review round 3).
     *
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    private static function landing(array $map, ?string $folder, ?int $inFolder): array
    {
        if ($folder === null || $map === []) {
            return $map;
        }
        if ($inFolder === null) {
            self::refuse('the node did not list it');
        }
        if ($inFolder === 0 || isset($map[$folder])) {
            return $map;
        }
        $moved = [];
        foreach ($map as $name => $target) {
            if (str_starts_with((string) $name, $folder.'/')) {
                $moved[substr((string) $name, strlen($folder) + 1)] = $target;
            }
        }

        return $moved;
    }

    public static function isTar(string $archive): bool
    {
        $lower = strtolower($archive);

        return str_ends_with($lower, '.tar.gz') || str_ends_with($lower, '.tgz');
    }

    /**
     * Two listings of the same archive, line for line in the same order: the verbose one for the type of each entry and
     * where a link points, the plain one for its name (GNU tar escapes both the same way). Nothing of the archive is
     * written anywhere but PRIVATE_DIR, and both listings are removed whatever happens. With `$folder`, also how many
     * entries lie beneath that top folder (`F <n>`; see landing()).
     */
    private static function tarScript(string $prelude, ?string $folder = null): string
    {
        [$v, $n] = self::scratch();

        return AaPanelShell::privateDir().' || exit 1; '
            .'V='.Q::arg($v).'; N='.Q::arg($n).'; trap \'rm -f "$V" "$N"\' EXIT; '.$prelude
            .'if ! tar --force-local --numeric-owner -tzvf "$A" > "$V" 2>/dev/null || ! tar --force-local -tzf "$A" > "$N" 2>/dev/null; then echo ERR; exit 0; fi; '
            .'echo "N $(wc -l < "$N") $(wc -l < "$V")"; '
            .self::commonChecks()
            .($folder !== null ? 'awk -v f='.Q::arg($folder).' \'{ n=$0; sub(/^\.\//, "", n); if (index(n, f "/")==1) c++ } END { print "F " c+0 }\' "$N"; ' : '')
            // every link: its name from the plain listing, its target from the verbose line after " <name> -> "
            .'awk \'NR==FNR { v[FNR]=$0; next } substr(v[FNR],1,1)=="l" { s=" " $0 " -> "; i=index(v[FNR], s); if (i==0) print "X " $0; else print "L " $0 "\t" substr(v[FNR], i+length(s)) }\' "$V" "$N" | head -n '.(self::MAX_LINKS + 1).'; '
            // the first entry that lies beneath a link of the same archive (unpacked, it would be written through the link)
            .'awk \'NR==FNR { if (substr($0,1,1)=="l") ln[FNR]=1; next } { n=$0; sub(/^\.\//, "", n); sub(/\/$/, "", n); name[FNR]=n; if (FNR in ln) link[n]=1 } END { for (i in name) for (x in link) if (name[i] != x && index(name[i], x "/")==1) { print "U " name[i]; exit } }\' "$V" "$N"';
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

    /**
     * The count of every entry type, the first names that are absolute or climb out with `..` (either slash), and the
     * first names with a `.` or empty part once one leading `./` and one trailing `/` are off (`D`; the unpack folder
     * itself, `./` or `.`, is the one exception): such a name lands under another name than the one every other check
     * reads (`q/./y` is `q/y`), so neither the link resolution nor the beneath-a-link check would meet it (review round 4).
     * Written for mawk as well (no anchor inside a group).
     */
    private static function commonChecks(): string
    {
        return 'awk \'{ c[substr($0,1,1)]++ } END { for (t in c) print "T " t " " c[t] }\' "$V"; '
            .'grep -E \'^[/\\\\]|(^|[/\\\\])\.\.([/\\\\]|$)\' "$N" | head -n 5 | sed \'s/^/B /\'; '
            .'awk \'{ n=$0; sub(/^\.\//, "", n); if (n == "" || n == ".") next; sub(/\/$/, "", n); '
            .'if (n == "" || n == "." || n ~ /^\.?\// || n ~ /\/\.?\// || n ~ /\/\.?$/) print "D " $0 }\' "$N" | head -n 5; ';
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

    /**
     * Resolved the way the kernel will, one name at a time, against the other links of the same archive: a name that is
     * a link of the archive is replaced by its target before the next `..` is taken. Judged on its own, `x -> y/../other.cz`
     * looked like a name inside the site; with `y -> .` beside it, it is the neighbour (review round 3). Every step must
     * stay inside `$bound` — a `..` above it is refused even when a later name would climb back, because what lies out
     * there on disk is not the archive's and cannot be judged. An absolute target (the first or one met on the way) must
     * name the bound literally. More than MAX_HOPS links on the way (a loop) is refused, as the kernel would (ELOOP).
     *
     * What is on disk inside the site already is not seen here (the archive is judged before it exists): a closed node
     * lets only the site user unpack, so a link planted there gives nothing the tenant does not have (AaPanelSiteUnpack).
     *
     * @param  array<string, string>  $links  every link of the archive where it lands: name => target
     */
    private static function linkStaysInside(string $name, string $target, array $links, string $extractRoot, string $bound): bool
    {
        $base = self::parts($extractRoot);
        $limit = self::parts($bound);
        if ($limit === [] || array_slice($base, 0, count($limit)) !== $limit) {
            return false;
        }
        $dir = dirname($name);
        $path = array_merge($base, $dir === '.' || $dir === '' ? [] : self::parts($dir));
        $queue = [];
        if (! self::enter($target, $limit, $path, $queue)) {
            return false;
        }
        $hops = 0;
        while ($queue !== []) {
            $part = array_shift($queue);
            if ($part === '..') {
                if (count($path) <= count($limit)) {
                    return false;
                }
                array_pop($path);

                continue;
            }
            $path[] = $part;
            $landed = count($path) > count($base) && array_slice($path, 0, count($base)) === $base;
            $relative = implode('/', array_slice($path, count($base)));
            if ($landed && isset($links[$relative])) {
                if (++$hops > self::MAX_HOPS) {
                    return false;
                }
                array_pop($path);
                if (! self::enter($links[$relative], $limit, $path, $queue)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Puts a link target in front of what is still to resolve; an absolute one starts again at the bound, which its
     * leading names must be exactly (no `..` among them).
     *
     * @param  list<string>  $limit
     * @param  list<string>  $path
     * @param  list<string>  $queue
     */
    private static function enter(string $target, array $limit, array &$path, array &$queue): bool
    {
        if ($target === '' || str_contains($target, "\0")) {
            return false;
        }
        $parts = self::parts($target);
        if (str_starts_with($target, '/')) {
            if (array_slice($parts, 0, count($limit)) !== $limit) {
                return false;
            }
            $path = $limit;
            $parts = array_slice($parts, count($limit));
        }
        $queue = array_merge($parts, $queue);

        return true;
    }

    /** @return list<string> the names of a path, without empty ones and `.` */
    private static function parts(string $path): array
    {
        return array_values(array_filter(explode('/', $path), fn (string $part) => $part !== '' && $part !== '.'));
    }

    private static function refuse(string $why, ?string $entry = null): never
    {
        throw new ProviderException('aapanel', ProviderErrorCode::VALIDATION, 'The archive was not unpacked: '.$why.($entry !== null ? ' ('.mb_substr($entry, 0, 120).')' : '').'.');
    }
}
