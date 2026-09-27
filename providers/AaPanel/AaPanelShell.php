<?php

declare(strict_types=1);

namespace Onhost\Providers\AaPanel;

use Closure;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\NodeShell;
use Onhost\Providers\Contracts\ShellResult;
use Onhost\Providers\Shell\Q;
use Onhost\Providers\Shell\SshShell;

/**
 * Commands on an aaPanel node through the panel API. `files?action=ExecShell` only acknowledges ("Command sent") and
 * runs the command in the background as root, so every call is wrapped: `timeout … bash -c … > <private>/<id>.out 2>&1;
 * echo $? > <private>/<id>.exit`, the exit file is polled through `GetFileBody`, the output read the same way and both
 * removed afterwards. <private> is PRIVATE_DIR, a folder only root can enter: in /tmp every tenant's PHP on the node
 * could read what a command of another site printed (TASK-0034, permission program IF-7). Site-level work switches to the site user (`su -s /bin/bash www -c …`) — aaPanel runs every
 * site as `www`, so that is the same identity the customer's PHP already has.
 */
final class AaPanelShell implements NodeShell
{
    public const OUTPUT_CAP = 1048576;

    /**
     * Where the platform stages what it runs and reads on a node as root: the shell's output and exit files, download
     * chunks and archive listings (archives themselves: STAGE_DIR). It used to be /tmp, which every site's PHP (`www`)
     * can list and read on a node shared by several customers (TASK-0034, IF-7). /root is root's own and 0700; the
     * folder is made 0700 too.
     */
    public const PRIVATE_DIR = '/root/.onhost-shell';

    /** The shell words that make PRIVATE_DIR exist, root-only, before anything is written into it (chain with `&&`). */
    public static function privateDir(): string
    {
        return 'mkdir -p -m 700 '.self::PRIVATE_DIR.' && chmod 700 '.self::PRIVATE_DIR;
    }

    /**
     * Where archives are copied and unpacked as root before they reach a site (TASK-0034 review: an archive left in the
     * site could be swapped between the check and the unpack). A whole site does not belong on the root file system, so
     * this lives beside the sites under /www — which only root can write — and is 0700 like PRIVATE_DIR.
     */
    public const STAGE_DIR = '/www/.onhost-stage';

    /** The shell words that make STAGE_DIR exist, root-only (chain with `&&`). */
    public static function stageDir(): string
    {
        return 'mkdir -p -m 700 '.self::STAGE_DIR.' && chmod 700 '.self::STAGE_DIR;
    }

    /** Lines the panel's `www` shell wrapper prints on every `su`; not part of the command's output. */
    private const NOISE = ['Your request has been recorded. Tips from BT security !!!', 'Tips from BT security'];

    /** @param Closure(string, array<string,mixed>, string, bool): mixed $post the adapter's signed request */
    public function __construct(private readonly Closure $post, private readonly string $instanceKey, private readonly bool $configured = true) {}

    public function run(string $command, array $options = []): ShellResult
    {
        $timeout = min(900, max(1, (int) ($options['timeout'] ?? 120)));
        $id = 'onhost-'.bin2hex(random_bytes(6));
        $out = self::PRIVATE_DIR."/{$id}.out";
        $exit = self::PRIVATE_DIR."/{$id}.exit";
        $inner = SshShell::compose($command, $options);
        if (! empty($options['user'])) {
            $inner = 'su -s /bin/bash '.Q::arg((string) $options['user']).' -c '.Q::arg($inner);
        }
        // the redirections are the root shell's own: the command itself (maybe the site user) never needs the folder
        $wrapped = self::privateDir().' && { timeout '.$timeout.'s bash -c '.Q::arg($inner).' > '.$out.' 2>&1; echo $? > '.$exit.'; }';
        $started = hrtime(true);
        ($this->post)('/files?action=ExecShell', ['shell' => $wrapped, 'path' => '/tmp'], 'shell.exec', true);

        $code = null;
        $deadline = microtime(true) + $timeout + 15;
        $delay = 300_000;
        while (microtime(true) < $deadline) {
            usleep($delay);
            $delay = min(2_000_000, (int) ($delay * 1.5));
            try {
                $body = ($this->post)('/files?action=GetFileBody', ['path' => $exit], 'shell.exit', false);
                $text = trim(is_array($body) ? (string) ($body['data'] ?? '') : (string) $body);
                if ($text !== '' && is_numeric($text)) {
                    $code = (int) $text;
                    break;
                }
            } catch (ProviderException $e) {
                if ($e->errorCode !== ProviderErrorCode::NOT_FOUND && $e->errorCode !== ProviderErrorCode::VALIDATION) {
                    throw $e;
                }
            }
        }
        $stdout = '';
        try {
            $body = ($this->post)('/files?action=GetFileBody', ['path' => $out], 'shell.output', false);
            $stdout = is_array($body) ? (string) ($body['data'] ?? '') : (string) $body;
        } catch (ProviderException) {
            // no output file: the command produced nothing
        }
        try {
            ($this->post)('/files?action=ExecShell', ['shell' => 'rm -f '.$out.' '.$exit, 'path' => '/tmp'], 'shell.cleanup', false);
        } catch (ProviderException) {
            // temp files are also swept by the node's tmp cleaner
        }
        $ms = (int) ((hrtime(true) - $started) / 1_000_000);
        if ($code === null) {
            return new ShellResult(124, SshShell::cap(self::clean($stdout)), 'command did not finish within '.$timeout.' s', $ms, true);
        }

        return new ShellResult($code, SshShell::cap(self::clean($stdout)), '', $ms, $code === 124, strlen($stdout) > self::OUTPUT_CAP);
    }

    public function available(): bool
    {
        return $this->configured;
    }

    public function describe(): string
    {
        return "aapanel api shell on {$this->instanceKey}";
    }

    private static function clean(string $output): string
    {
        $lines = preg_split('/\r?\n/', $output) ?: [];
        $lines = array_filter($lines, function (string $line): bool {
            foreach (self::NOISE as $noise) {
                if (str_contains($line, $noise)) {
                    return false;
                }
            }

            return true;
        });

        return implode("\n", $lines);
    }
}
