<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Web;

use Onhost\Platform\Errors\DomainError;

/**
 * The customer terminal: non-interactive commands in the site's document root as the site user. The operating
 * system enforces what the user may touch; this guard keeps the panel from becoming a launcher for daemons and
 * privilege changes (no sudo/su, no background jobs, no scheduler edits, a fixed set of tools).
 */
final class CommandRunner
{
    public const ALLOWED = [
        'wp', 'php', 'composer', 'git', 'npm', 'npx', 'node', 'yarn', 'pnpm', 'ls', 'cat', 'head', 'tail', 'grep', 'find', 'du', 'df', 'pwd', 'echo', 'printf', 'env', 'mkdir', 'rm', 'rmdir', 'cp', 'mv', 'tar', 'zip', 'unzip', 'gzip', 'gunzip',
        'mysql', 'mysqldump', 'curl', 'wget', 'sed', 'awk', 'sort', 'uniq', 'wc', 'chmod', 'touch', 'ln', 'date', 'which', 'whoami', 'id', 'uname', 'stat', 'tree', 'diff', 'md5sum', 'sha256sum', 'base64', 'xargs', 'true', 'false', 'test', 'cd', 'export', 'rsync', 'artisan',
    ];

    public const FORBIDDEN = ['sudo', 'su', 'chattr', 'crontab', 'nohup', 'screen', 'tmux', 'setsid', 'systemctl', 'service', 'nc', 'ncat', 'socat', 'telnet', 'ssh', 'scp', 'sftp', 'mount', 'umount', 'kill', 'pkill', 'killall', 'reboot', 'shutdown', 'passwd', 'useradd', 'usermod', 'chown', 'iptables', 'nft', 'docker'];

    /** Returns the command unchanged when every segment starts with an allowed tool; throws otherwise. */
    public static function guard(string $command): string
    {
        $command = trim($command);
        if ($command === '' || strlen($command) > 2000 || str_contains($command, "\n")) {
            throw new DomainError('command_invalid', 'One command line up to 2000 characters.', 422, ['field' => 'command']);
        }
        if (preg_match('/(^|[^&])&\s*$/', $command) || str_contains($command, '$(') || str_contains($command, '`')) {
            throw new DomainError('command_invalid', 'Background jobs and command substitution are not available in the terminal.', 422, ['field' => 'command']);
        }
        foreach (preg_split('/\s*(?:\|\||&&|;|\|)\s*/', $command) ?: [] as $segment) {
            $segment = ltrim($segment, '( ');
            if ($segment === '') {
                continue;
            }
            $tokens = preg_split('/\s+/', $segment) ?: [];
            $first = strtolower(basename((string) ($tokens[0] ?? '')));
            while (preg_match('/^[A-Z_][A-Z0-9_]*=/', (string) ($tokens[0] ?? '')) && count($tokens) > 1) { // ENV=value prefix
                array_shift($tokens);
                $first = strtolower(basename((string) $tokens[0]));
            }
            if (in_array($first, self::FORBIDDEN, true)) {
                throw new DomainError('command_forbidden', "`{$first}` is not available in the terminal.", 422, ['field' => 'command']);
            }
            if (! in_array($first, self::ALLOWED, true) && ! str_starts_with((string) $tokens[0], './') && ! str_ends_with($first, '.phar')) { // ./script.sh and vendor binaries belong to the site
                throw new DomainError('command_forbidden', "`{$first}` is not one of the terminal tools (".implode(', ', array_slice(self::ALLOWED, 0, 12)).', …).', 422, ['field' => 'command']);
            }
        }

        return $command;
    }
}
