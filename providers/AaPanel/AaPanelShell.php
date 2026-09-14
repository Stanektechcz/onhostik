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
 * runs the command in the background as root, so every call is wrapped: `timeout … bash -c … > /tmp/<id>.out 2>&1;
 * echo $? > /tmp/<id>.exit`, the exit file is polled through `GetFileBody`, the output read the same way and both
 * removed afterwards. Site-level work switches to the site user (`su -s /bin/bash www -c …`) — aaPanel runs every
 * site as `www`, so that is the same identity the customer's PHP already has.
 */
final class AaPanelShell implements NodeShell
{
    public const OUTPUT_CAP = 1048576;

    /** Lines the panel's `www` shell wrapper prints on every `su`; not part of the command's output. */
    private const NOISE = ['Your request has been recorded. Tips from BT security !!!', 'Tips from BT security'];

    /** @param Closure(string, array<string,mixed>, string, bool): mixed $post the adapter's signed request */
    public function __construct(private readonly Closure $post, private readonly string $instanceKey, private readonly bool $configured = true) {}

    public function run(string $command, array $options = []): ShellResult
    {
        $timeout = min(900, max(1, (int) ($options['timeout'] ?? 120)));
        $id = 'onhost-'.bin2hex(random_bytes(6));
        $out = "/tmp/{$id}.out";
        $exit = "/tmp/{$id}.exit";
        $inner = SshShell::compose($command, $options);
        if (! empty($options['user'])) {
            $inner = 'su -s /bin/bash '.Q::arg((string) $options['user']).' -c '.Q::arg($inner);
        }
        $wrapped = 'timeout '.$timeout.'s bash -c '.Q::arg($inner).' > '.$out.' 2>&1; echo $? > '.$exit;
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
