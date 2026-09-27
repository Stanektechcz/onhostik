<?php

declare(strict_types=1);

namespace Onhost\Providers\AaPanel;

use Onhost\Providers\Shell\Q;

/**
 * Shell words for unpacking an archive into a site without a root write inside the site (TASK-0034 review round 1,
 * permission program §3 "remaining writes run as the site user", exploit PA-02).
 *
 * The panel's `UnZip` and the restore's `rsync`/`cp -a` write into the live site as root. A link the tenant planted in
 * its own site after the archive was judged — a folder `wp-content` pointing at a neighbour's site or at /etc — is then
 * followed by root. On a node several customers share (closed by `operator:aapanel:tenancy`), the archive is therefore
 * unpacked as root only into a fresh folder in STAGE_DIR that no tenant can enter (its entries were judged, nothing on
 * disk there is theirs), and streamed from there into the site by a `tar` that runs as the site user: whatever link it
 * meets, it writes with the rights the tenant already has, never more.
 *
 * On a node nobody closed this is not used: the panel's own unpack stays (a change of what existing customers get —
 * owner of restored files, a mirror restore that deletes what the backup does not hold — needs the per-node switch).
 */
final class AaPanelSiteUnpack
{
    /** Unpacks `$archive` (shell word) into `$into` (shell word) as the calling (root) shell. */
    public static function unpackCommand(bool $tar, string $archive, string $into): string
    {
        return $tar
            ? 'tar --force-local --no-same-owner -xzf '.$archive.' -C '.$into
            : 'unzip -oq '.$archive.' -d '.$into;
    }

    /**
     * Streams the content of `$from` (shell word, root-readable) into `$to` (absolute path in the site) as `$user`.
     * The site's `.user.ini` is the panel's (root-owned, often immutable, it carries open_basedir): never part of it,
     * exactly as the root restore already left it out. A brace group: its status is the whole pipe's, whatever precedes
     * it with `&&`.
     */
    public static function copyAsSiteUser(string $from, string $to, string $user, bool $skipUserIni): string
    {
        $receive = 'mkdir -p -- '.Q::arg($to).' && tar -C '.Q::arg($to).' -xf - --no-same-owner --no-overwrite-dir';

        return '{ set -o pipefail; tar -C '.$from.' -cf - '.($skipUserIni ? '--exclude=./.user.ini ' : '').'. | su -s /bin/bash '.Q::arg($user).' -c '.Q::arg($receive).'; }';
    }
}
