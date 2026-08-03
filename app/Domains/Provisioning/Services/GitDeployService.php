<?php

declare(strict_types=1);

namespace App\Domains\Provisioning\Services;

use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\ServiceGitRepository;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Git deployment for hosting services — the equivalent of aaPanel's own git
 * feature, driven from the customer panel.
 *
 * SECURITY: deployment ultimately runs shell commands on the hosting node, so
 * every value that reaches a command line is validated against a strict
 * allow-list AND escaped. A repository URL like `https://x/y.git; rm -rf /`
 * must never survive validation, and even if validation were bypassed the
 * escaping would render it inert. Post-deploy commands are constrained to a
 * whitelist of build tools rather than arbitrary shell, because "run anything
 * on our node" is not a feature we can safely hand to customers.
 */
final class GitDeployService
{
    /** Commands a customer may run after a pull. Anything else is refused. */
    private const ALLOWED_POST_DEPLOY = [
        'composer', 'npm', 'yarn', 'pnpm', 'php', 'node', 'bun', 'make',
    ];

    public function __construct(private readonly AapanelClient $panel) {}

    /**
     * Validate a repository URL. Accepts https:// and git@host:path forms only:
     * ssh:// with a custom port, file:// or a URL containing shell
     * metacharacters are all rejected.
     */
    public function assertValidRepositoryUrl(string $url): void
    {
        $url = trim($url);

        if ($url === '' || mb_strlen($url) > 500) {
            throw new InvalidArgumentException('Adresa repozitáře je prázdná nebo příliš dlouhá.');
        }

        // No shell metacharacters, whitespace or control characters, ever.
        if (preg_match('/[\s;&|`$<>(){}\\\\\'"]/', $url) === 1) {
            throw new InvalidArgumentException('Adresa repozitáře obsahuje nepovolené znaky.');
        }

        $httpsForm = '#^https://[a-z0-9.\-]+\.[a-z]{2,}(:\d{2,5})?/[a-z0-9._/\-]+(\.git)?$#i';
        $scpForm   = '#^git@[a-z0-9.\-]+\.[a-z]{2,}:[a-z0-9._/\-]+(\.git)?$#i';

        if (preg_match($httpsForm, $url) !== 1 && preg_match($scpForm, $url) !== 1) {
            throw new InvalidArgumentException('Podporujeme pouze https://… nebo git@host:cesta/repo.git.');
        }
    }

    /** Branch names are restricted to what git itself considers sane. */
    public function assertValidBranch(string $branch): void
    {
        if (preg_match('#^[a-z0-9][a-z0-9._/\-]{0,99}$#i', $branch) !== 1) {
            throw new InvalidArgumentException('Neplatný název větve.');
        }
    }

    /**
     * Deploy paths stay inside the site root: no absolute paths, no traversal.
     */
    public function assertValidDeployPath(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (str_starts_with($path, '/') || str_contains($path, '..')
            || preg_match('#^[a-z0-9._/\-]{1,200}$#i', $path) !== 1) {
            throw new InvalidArgumentException('Cesta musí být relativní k rootu webu, bez „..".');
        }
    }

    /** @param list<string> $commands */
    public function assertValidPostDeployCommands(array $commands): void
    {
        foreach ($commands as $command) {
            if (preg_match('/[;&|`$<>(){}\\\\\'"]/', $command) === 1) {
                throw new InvalidArgumentException("Příkaz obsahuje nepovolené znaky: {$command}");
            }

            $binary = strtok(trim($command), ' ');

            if ($binary === false || ! in_array($binary, self::ALLOWED_POST_DEPLOY, true)) {
                throw new InvalidArgumentException(
                    'Povolené příkazy po nasazení: ' . implode(', ', self::ALLOWED_POST_DEPLOY) . '.',
                );
            }
        }
    }

    /**
     * Clone (first run) or pull (subsequent runs) the repository into the site
     * root, then run the whitelisted post-deploy commands.
     *
     * @return array{ok: bool, output: string, error: string|null, dry_run: bool}
     */
    public function deploy(ServiceGitRepository $repo): array
    {
        $service = $repo->service;

        if ($service === null) {
            return ['ok' => false, 'output' => '', 'error' => 'Služba neexistuje.', 'dry_run' => false];
        }

        $repo->forceFill(['status' => 'deploying', 'last_error' => null])->save();

        try {
            $this->assertValidRepositoryUrl($repo->repository_url);
            $this->assertValidBranch($repo->branch);
            $this->assertValidDeployPath($repo->deploy_path);
            $this->assertValidPostDeployCommands($repo->postDeployCommandList());

            $target = $this->targetDirectory($service, $repo);
            $result = $this->panel->runShellCommand($this->buildScript($repo, $target), 300);

            $dryRun = (bool) ($result['dry_run'] ?? false);
            $output = is_string($result['msg'] ?? null) ? $result['msg'] : json_encode($result, JSON_UNESCAPED_UNICODE);

            $repo->forceFill([
                'status'           => 'success',
                'last_output'      => mb_substr((string) $output, 0, 5000),
                'last_deployed_at' => now(),
            ])->save();

            activity('service')
                ->performedOn($service)
                ->withProperties(['repository' => $repo->shortName(), 'branch' => $repo->branch, 'dry_run' => $dryRun])
                ->log('service.git_deployed');

            return ['ok' => true, 'output' => (string) $output, 'error' => null, 'dry_run' => $dryRun];
        } catch (InvalidArgumentException $e) {
            // A validation failure is the customer's input, not an outage.
            $repo->forceFill(['status' => 'failed', 'last_error' => $e->getMessage()])->save();

            return ['ok' => false, 'output' => '', 'error' => $e->getMessage(), 'dry_run' => false];
        } catch (Throwable $e) {
            $repo->forceFill([
                'status'     => 'failed',
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            Log::warning('git_deploy.failed', [
                'service_id' => $service->id,
                'repository' => $repo->shortName(),
                'error'      => mb_substr($e->getMessage(), 0, 300),
            ]);

            return ['ok' => false, 'output' => '', 'error' => 'Nasazení selhalo, zkuste to prosím znovu.', 'dry_run' => false];
        }
    }

    /** Absolute directory the repository is checked out into. */
    public function targetDirectory(Service $service, ServiceGitRepository $repo): string
    {
        $root = '/www/wwwroot/' . ($service->label ?? 'site');

        return $repo->deploy_path !== null && $repo->deploy_path !== ''
            ? rtrim($root, '/') . '/' . trim($repo->deploy_path, '/')
            : $root;
    }

    /**
     * The shell script. Every interpolated value is escaped, and the clone/pull
     * choice is made on the host so a half-finished checkout self-heals rather
     * than needing manual repair.
     */
    private function buildScript(ServiceGitRepository $repo, string $target): string
    {
        $url    = escapeshellarg($repo->repository_url);
        $branch = escapeshellarg($repo->branch);
        $dir    = escapeshellarg($target);

        $lines = [
            'set -e',
            "mkdir -p {$dir}",
            "cd {$dir}",
            // Existing checkout → fetch + hard reset to the tracked branch, so
            // local drift never blocks a deploy. Otherwise clone fresh.
            "if [ -d .git ]; then git remote set-url origin {$url}; git fetch --depth=1 origin {$branch}; "
                . "git checkout -B {$branch} origin/{$branch}; git reset --hard origin/{$branch}; "
                . "else git clone --depth=1 --branch {$branch} {$url} .; fi",
            'git rev-parse --short HEAD',
        ];

        foreach ($repo->postDeployCommandList() as $command) {
            // Already validated against the binary allow-list; escape each word
            // so an argument can never break out into a new command.
            $parts  = preg_split('/\s+/', trim($command)) ?: [];
            $safe   = implode(' ', array_map(static fn (string $p): string => escapeshellarg($p), $parts));
            $lines[] = $safe;
        }

        return implode("\n", $lines);
    }
}
