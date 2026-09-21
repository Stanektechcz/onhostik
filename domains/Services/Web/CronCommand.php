<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Platform\Errors\DomainError;

/**
 * What a customer may schedule on a shared web node.
 *
 * A cron job is a command somebody else's computer runs, unattended, for as long as the service lives — the most
 * valuable field on the whole panel to anyone who gets to write into it. Two separate things guard it:
 *
 *  · WHO runs it — the adapters confine the job to the site's own user (aaPanel wraps the body, ISPConfig writes a
 *    jailed cron of the site's client). That is the confinement, and it is what actually limits the damage.
 *  · WHAT it may say — this class. A command that changes who you are, or takes the host apart, has no business in a
 *    site's cron even when it would fail: a site that is allowed to TRY is a site the node has to withstand, every
 *    minute, for years.
 *
 * Judged statement by statement, exactly as `CustomDirectives` is. A deny-list anchored at the start of the whole
 * string is one `;` away from useless (`php cron.php; sudo …`), so the text is cut at every place a shell starts a
 * new command — `; & | && || newline $( ) ` {} ` — and every token of every piece is looked at with its directory
 * stripped, which makes `/usr/bin/sudo`, `env sudo`, `$(sudo …)` and a backtick the same word.
 *
 * The list holds programs that change identity, the host's accounts, its schedule, its kernel, its network rules or
 * its containers, plus the panels' own command-line tools — and everything the interactive terminal already refuses
 * (`CommandRunner::FORBIDDEN`), because a command nobody may type by hand is not one to run unattended every minute
 * for years. Ordinary hosting work — php, composer, wp, git, curl, mysqldump, tar, find, rm inside the site — is
 * untouched: unlike the terminal this is a deny-list, since a cron legitimately calls the site's own scripts.
 */
final class CronCommand
{
    public const MAX = 500;

    private const REFUSED = [
        // becoming somebody else
        'su', 'sudo', 'doas', 'pkexec', 'runuser', 'setpriv', 'newgrp', 'chroot', 'unshare', 'nsenter', 'setcap',
        // the host's accounts
        'useradd', 'adduser', 'usermod', 'userdel', 'groupadd', 'groupmod', 'groupdel', 'passwd', 'chpasswd', 'visudo', 'vipw',
        // the host's own schedule and services
        'crontab', 'at', 'batch', 'anacron', 'systemctl', 'systemd-run', 'service', 'initctl', 'supervisorctl', 'rc-service',
        // the kernel and the machine
        'insmod', 'modprobe', 'rmmod', 'sysctl', 'mount', 'umount', 'swapon', 'swapoff', 'mkfs', 'fdisk', 'parted', 'dd',
        'shutdown', 'reboot', 'halt', 'poweroff', 'init', 'telinit', 'kexec',
        // the network's rules
        'iptables', 'ip6tables', 'nft', 'ufw', 'firewall-cmd', 'tc', 'ifconfig',
        // containers and hypervisors
        'docker', 'podman', 'lxc', 'lxc-attach', 'virsh', 'qm', 'pct',
        // listeners and tunnels: a cron that opens a way in is the classic way to stay in
        'nc', 'ncat', 'netcat', 'socat', 'sshd', 'telnetd', 'ngrok', 'frpc',
        // the panels' own command lines
        'bt', 'ispconfig_update.sh', 'pterodactyl', 'wings', 'pvesh', 'pveum',
    ];

    /** A shell starts a new command at each of these, so each piece is judged on its own. */
    private const SPLIT = '/[\n;&|`(){}]+|\$\(/';

    /** @return list<string> */
    public static function refused(): array
    {
        return array_values(array_unique(array_merge(self::REFUSED, CommandRunner::FORBIDDEN)));
    }

    /**
     * @return string|null the first word that may not be scheduled, or null when the command may be written
     */
    public static function firstRefused(string $command): ?string
    {
        $refused = self::refused();
        if (preg_match('~/dev/(tcp|udp)/~i', $command) === 1) {
            return '/dev/tcp'; // bash opens a socket by opening this path: a reverse shell with no program name to name
        }
        foreach (preg_split(self::SPLIT, $command) ?: [] as $statement) {
            foreach (preg_split('/\s+/', trim($statement)) ?: [] as $token) {
                $token = trim($token, "'\"");
                if ($token === '') {
                    continue;
                }
                $word = strtolower(basename(str_replace('\\', '/', $token)));
                if (in_array($word, $refused, true)) {
                    return $word;
                }
            }
        }

        return null;
    }

    /**
     * The command as it will be scheduled, or a 422 saying why it will not be.
     *
     * @throws DomainError
     */
    public static function assert(string $action, mixed $value): string
    {
        $command = trim((string) $value);
        $fail = fn (string $why) => throw new DomainError('action_param_invalid', "{$action}: {$why}", 422, ['field' => 'command']);
        if ($command === '' || strlen($command) > self::MAX) {
            $fail('command is required (one line, max '.self::MAX.' characters).');
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $command) === 1) {
            $fail('command must be a single line without control characters.');
        }
        $refused = self::firstRefused($command);
        if ($refused !== null) {
            $fail("a scheduled command may not run `{$refused}` — it runs as your site's own user and may not change the server itself, its accounts, its network or its schedule.");
        }

        return $command;
    }
}
