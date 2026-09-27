<?php

declare(strict_types=1);

namespace Onhost\Providers\AaPanel;

use Onhost\Providers\Shell\Q;

/**
 * Shell words for unpacking an archive into a site without a root write inside the site (TASK-0034 review rounds 1–3,
 * permission program §3 "remaining writes run as the site user", exploit PA-02).
 *
 * The panel's `UnZip` and the restore's `rsync`/`cp -a` write into the live site as root. A link the tenant planted in
 * its own site after the archive was judged — a folder `wp-content` pointing at a neighbour's site or at /etc — is then
 * followed by root. On a node several customers share (closed by `operator:aapanel:tenancy`), the archive is therefore
 * unpacked by the site user and nobody else: root only opens the (judged, root-only) archive and hands it over on the
 * user's stdin. Whatever the archive carries and whatever link it meets, it is written with the rights the tenant
 * already has, never more.
 *
 * Round 1–2 still unpacked as root into a folder of STAGE_DIR and only streamed the result in as the user; round 3 found
 * that a root `tar -x` is a privileged extraction all the same (owners, modes, set-id bits and links of the archive,
 * judged by nothing but the preflight's reading of a listing). Now no root `tar -x` or `unzip` runs for a closed node at
 * all: the user unpacks into a fresh folder of its own inside the site (`mktemp -d`, 0700, removed whatever happens) with
 * `--no-same-owner --no-same-permissions --no-overwrite-dir`, and copies from there into the target folder the same way.
 *
 * "The site user" is the site's own shell user (`<prefix>ag`, AaPanelTools::ensureAgent: a member of `www` with ACLs on
 * its site root), not `www` itself: the node's hardening stops `www` from running any binary (exit 126), so a `tar` as
 * `www` would have failed every restore on a closed node (found in review round 2; round 1 used `www`).
 *
 * On a node nobody closed this is not used: the panel's own unpack stays (a change of what existing customers get —
 * owner of restored files, a mirror restore that deletes what the backup does not hold — needs the per-node switch).
 */
final class AaPanelSiteUnpack
{
    /** What every `tar -x` of the site user is told: nobody's owner, the user's umask, existing folders left as they are. */
    private const TAR_SAFE = '--no-same-owner --no-same-permissions --no-overwrite-dir';

    /**
     * The whole unpack of `$archive` (shell word: a root-readable file, handed over on stdin — the user never opens it by
     * name) into `$to` (absolute path in the site), run as `$user`. The fresh folder lies in `$siteRoot`, the one place
     * the user may write and no other tenant may read (0700). `$folder`: the archive's own top folder that is moved in
     * instead of the whole archive when the archive has it as a real folder (a panel backup packs `<site>/…`); a link of
     * that name is not followed. The site's `.user.ini` is the panel's (root-owned, often immutable, it carries
     * open_basedir): never part of the copy into the site root, exactly as the root restore already left it out.
     */
    public static function unpackAsSiteUser(bool $tar, string $archive, string $siteRoot, string $to, string $user, bool $skipUserIni, ?string $folder = null): string
    {
        $script = 'set -e -o pipefail; umask 022; '
            .'D=$(mktemp -d '.Q::arg(rtrim($siteRoot, '/').'/.onhost-unpack.XXXXXXXX').'); trap \'rm -rf -- "$D"\' EXIT; mkdir -- "$D/x"; '
            .($tar
                ? 'tar --force-local -xzf - -C "$D/x" '.self::TAR_SAFE.'; '
                : 'cat > "$D/a.zip"; unzip -q "$D/a.zip" -d "$D/x"; rm -f -- "$D/a.zip"; ') // unzip needs a file it can seek in
            .'src="$D/x"; '
            .($folder !== null ? 'if [ -d "$src"/'.Q::arg($folder).' ] && [ ! -L "$src"/'.Q::arg($folder).' ]; then src="$src"/'.Q::arg($folder).'; fi; ' : '')
            .'mkdir -p -- '.Q::arg($to).'; '
            .'tar -C "$src" -cf - '.($skipUserIni ? '--exclude=./.user.ini ' : '').'. | tar -C '.Q::arg($to).' -xf - '.self::TAR_SAFE;

        return 'su -s /bin/bash '.Q::arg($user).' -c '.Q::arg($script).' < '.$archive;
    }

    /**
     * Writes the root-owned file `$from` (shell word) to `$to` (absolute path in the site) as `$user`, the folder made
     * first (review round 2: an upload on a closed node). Whatever link stands at `$to`, it is followed with the user's
     * rights only.
     */
    public static function writeFileAsSiteUser(string $from, string $to, string $user): string
    {
        return '{ su -s /bin/bash '.Q::arg($user).' -c '.Q::arg('mkdir -p -- '.Q::arg(dirname($to)).' && cat > '.Q::arg($to)).' < '.$from.'; }';
    }
}
