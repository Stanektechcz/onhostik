<?php

declare(strict_types=1);

namespace Onhost\Providers\Shell;

use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\NodeShell;
use Onhost\Providers\Contracts\ShellResult;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

/**
 * SSH to a hosting node with a private key (phpseclib, pure PHP). Used as the site's jailed agent user on ISPConfig
 * nodes and as an optional root/operator channel on aaPanel nodes (`shell: ssh` instance option). One connection per
 * object, reconnected on demand; output is captured in quiet mode so stderr stays separate.
 */
final class SshShell implements NodeShell
{
    public const OUTPUT_CAP = 1048576;

    private ?SSH2 $ssh = null;

    private ?SFTP $sftp = null;

    /** Unix time until which a refused login means "the account is still being prepared on the node" (jailed users arrive through a job queue), not a credential problem. */
    public ?int $preparingUntil = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $privateKey,
        private readonly ?string $keyPassword = null,
        private readonly ?string $hostKeyFingerprint = null,
        private readonly int $connectTimeout = 10,
        private readonly string $provider = 'node-shell',
    ) {}

    public function run(string $command, array $options = []): ShellResult
    {
        $timeout = min(900, max(1, (int) ($options['timeout'] ?? 120)));
        $ssh = $this->connect();
        $ssh->setTimeout($timeout);
        $started = hrtime(true);
        $stdout = (string) $ssh->exec(self::compose($command, $options));
        $stderr = (string) $ssh->getStdError();
        $timedOut = $ssh->isTimeout();
        $status = $ssh->getExitStatus();
        $exit = $timedOut ? 124 : ($status === false ? 0 : (int) $status);
        $ms = (int) ((hrtime(true) - $started) / 1_000_000);

        return new ShellResult($exit, self::cap($stdout), self::cap($stderr), $ms, $timedOut, strlen($stdout) > self::OUTPUT_CAP || strlen($stderr) > self::OUTPUT_CAP);
    }

    public function available(): bool
    {
        return $this->host !== '' && $this->user !== '' && $this->privateKey !== '';
    }

    public function describe(): string
    {
        return "ssh {$this->user}@{$this->host}:{$this->port}";
    }

    /** SFTP on the same credentials (file transport). */
    public function sftp(): SFTP
    {
        if ($this->sftp !== null && $this->sftp->isConnected()) {
            return $this->sftp;
        }
        $sftp = new SFTP($this->host, $this->port, $this->connectTimeout);
        $this->login($sftp);

        return $this->sftp = $sftp;
    }

    /** `cd <cwd> && env K=V … <command>` — the command itself is the caller's, the wrapping is ours. */
    public static function compose(string $command, array $options): string
    {
        $prefix = '';
        if (! empty($options['cwd'])) {
            $prefix .= 'cd '.Q::arg((string) $options['cwd']).' && ';
        }
        if (! empty($options['env']) && is_array($options['env'])) {
            $parts = [];
            foreach ($options['env'] as $k => $v) {
                if (preg_match('/^[A-Z_][A-Z0-9_]*$/', (string) $k)) {
                    $parts[] = $k.'='.Q::arg((string) $v);
                }
            }
            if ($parts !== []) {
                $prefix .= 'export '.implode(' ', $parts).' && ';
            }
        }

        return $prefix.$command;
    }

    public static function cap(string $text): string
    {
        return strlen($text) > self::OUTPUT_CAP ? substr($text, 0, self::OUTPUT_CAP)."\n[… output truncated]" : $text;
    }

    private function connect(): SSH2
    {
        if ($this->ssh !== null && $this->ssh->isConnected()) {
            return $this->ssh;
        }
        $ssh = new SSH2($this->host, $this->port, $this->connectTimeout);
        $this->login($ssh);
        $ssh->enableQuietMode();

        return $this->ssh = $ssh;
    }

    private function login(SSH2 $ssh): void
    {
        if (! $this->available()) {
            throw new ProviderException($this->provider, ProviderErrorCode::AUTH, 'The node shell has no SSH credentials configured');
        }
        try {
            $key = PublicKeyLoader::load($this->privateKey, $this->keyPassword ?? false);
        } catch (\Throwable $e) {
            throw new ProviderException($this->provider, ProviderErrorCode::AUTH, 'The SSH private key could not be loaded: '.$e->getMessage(), previous: $e);
        }
        try {
            $ok = $ssh->login($this->user, $key);
        } catch (\Throwable $e) {
            throw new ProviderException($this->provider, ProviderErrorCode::TRANSIENT, "SSH connection to {$this->host}:{$this->port} failed: ".$e->getMessage(), previous: $e);
        }
        if (! $ok) {
            if ($this->preparingUntil !== null && $this->preparingUntil > time()) {
                throw new ProviderException($this->provider, ProviderErrorCode::TRANSIENT, "The shell account {$this->user} is still being prepared on the node; try again in a minute", retryAfterSeconds: 60);
            }
            throw new ProviderException($this->provider, ProviderErrorCode::AUTH, "SSH login as {$this->user}@{$this->host} was refused");
        }
        if ($this->hostKeyFingerprint !== null && $this->hostKeyFingerprint !== '') {
            $actual = $ssh->getServerPublicHostKey();
            $fingerprint = $actual === false ? '' : 'SHA256:'.rtrim(base64_encode(hash('sha256', (string) $actual, true)), '=');
            if ($fingerprint !== $this->hostKeyFingerprint) {
                $ssh->disconnect();
                throw new ProviderException($this->provider, ProviderErrorCode::AUTH, "SSH host key of {$this->host} does not match the configured fingerprint");
            }
        }
    }
}
