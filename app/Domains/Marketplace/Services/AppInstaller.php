<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Services;

use App\Domains\Integrations\Clients\AapanelClient;
use App\Domains\Marketplace\Models\AppInstallation;
use App\Domains\Marketplace\Models\MarketplaceApp;
use App\Domains\Provisioning\Models\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * One-click application install.
 *
 * This used to be a fiction: the installation row was flipped to "installed"
 * and a provisioning task was written with status Success and `mock: true`,
 * regardless of whether anything could have run. That is the opposite of how
 * the rest of the platform treats the hosting node — every other aaPanel call
 * goes through the refusal gates and reports a dry run honestly.
 *
 * So the installer now builds a real script and hands it to AapanelClient. With
 * real writes disabled the client refuses and reports what it *would* run; the
 * installation records that as a dry run rather than claiming success. When the
 * gates are open, the same script actually installs the app.
 *
 * SECURITY: the recipe comes from the marketplace_apps table (operator-managed,
 * not customer input), but it still reaches a shell, so every field is
 * validated against an allow-list and escaped. An operator typo must not become
 * remote code execution.
 */
final class AppInstaller
{
    /** Build tools an install recipe may invoke after unpacking. */
    private const ALLOWED_POST_INSTALL = [
        'composer', 'npm', 'yarn', 'pnpm', 'php', 'node', 'bun', 'make', 'chmod', 'mv',
    ];

    public function __construct(private readonly AapanelClient $panel) {}

    /**
     * Install $app onto $service, recording the real outcome on $installation.
     *
     * @return array{ok: bool, dry_run: bool, error: string|null, database: string|null}
     */
    public function install(Service $service, MarketplaceApp $app, AppInstallation $installation): array
    {
        try {
            $this->assertInstallable($app);

            $target   = $this->targetDirectory($service, $app);
            $database = null;

            // Apps that need MySQL get their own database + user, created
            // before the files land so the installer can be pointed at it.
            if ($app->requires_database) {
                $database = $this->databaseName($service, $app);
                $this->panel->createDatabase($database, $database);
            }

            $result = $this->panel->runShellCommand($this->buildScript($app, $target), 600);

            $dryRun = (bool) ($result['dry_run'] ?? false);
            $output = is_string($result['msg'] ?? null)
                ? $result['msg']
                : (string) json_encode($result, JSON_UNESCAPED_UNICODE);

            $installation->forceFill([
                // A dry run installed nothing — saying "installed" would be a lie
                // the customer discovers only when they open the URL.
                'status'        => $dryRun ? 'pending' : 'installed',
                'installed_at'  => $dryRun ? null : now(),
                'version'       => 'latest',
                'install_path'  => $target,
                'database_name' => $database,
                'last_output'   => mb_substr($output, 0, 5000),
                'last_error'    => null,
                'was_dry_run'   => $dryRun,
            ])->save();

            return ['ok' => true, 'dry_run' => $dryRun, 'error' => null, 'database' => $database];
        } catch (InvalidArgumentException $e) {
            // A broken recipe is an operator problem, not an outage — surface it.
            $installation->forceFill(['status' => 'failed', 'last_error' => $e->getMessage()])->save();

            return ['ok' => false, 'dry_run' => false, 'error' => $e->getMessage(), 'database' => null];
        } catch (Throwable $e) {
            $installation->forceFill([
                'status'     => 'failed',
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            Log::warning('marketplace.install_failed', [
                'service_id' => $service->id,
                'app_slug'   => $app->slug,
                'error'      => mb_substr($e->getMessage(), 0, 300),
            ]);

            return [
                'ok'       => false,
                'dry_run'  => false,
                'error'    => 'Instalace selhala, zkuste to prosím znovu nebo kontaktujte podporu.',
                'database' => null,
            ];
        }
    }

    /**
     * Remove the installed files (and only those) from the site.
     *
     * Apps installed at the site root are deliberately NOT deleted: `rm -rf` on
     * a site root would take the customer's whole website with it, and no
     * marketplace bookkeeping is worth that risk. Those are unregistered only,
     * and the caller is expected to say so rather than claim a cleanup.
     *
     * @return array{ok: bool, dry_run: bool, files_removed: bool, error: string|null}
     */
    public function uninstall(Service $service, AppInstallation $installation): array
    {
        $path = $installation->install_path;

        // Must be a subdirectory under a site root: /www/wwwroot/<site>/<sub>.
        if ($path === null || ! str_starts_with($path, '/www/wwwroot/') || substr_count(rtrim($path, '/'), '/') < 4) {
            return ['ok' => true, 'dry_run' => false, 'files_removed' => false, 'error' => null];
        }

        try {
            $result = $this->panel->runShellCommand('rm -rf ' . escapeshellarg($path), 120);
            $dryRun = (bool) ($result['dry_run'] ?? false);

            return ['ok' => true, 'dry_run' => $dryRun, 'files_removed' => ! $dryRun, 'error' => null];
        } catch (Throwable $e) {
            Log::warning('marketplace.uninstall_failed', [
                'service_id' => $service->id,
                'path'       => $path,
                'error'      => mb_substr($e->getMessage(), 0, 300),
            ]);

            return [
                'ok'            => false,
                'dry_run'       => false,
                'files_removed' => false,
                'error'         => 'Odinstalaci se nepodařilo dokončit.',
            ];
        }
    }

    /** Absolute directory the app is installed into. */
    public function targetDirectory(Service $service, MarketplaceApp $app): string
    {
        $root = '/www/wwwroot/' . ($service->label ?? 'site');
        $sub  = trim((string) $app->install_path, '/');

        return $sub === '' ? $root : $root . '/' . $sub;
    }

    // ------------------------------------------------------------- validation

    private function assertInstallable(MarketplaceApp $app): void
    {
        if (! $app->isInstallable()) {
            throw new InvalidArgumentException(
                'Aplikace zatím nemá nastavený instalační zdroj — kontaktujte prosím podporu.',
            );
        }

        $url = (string) $app->install_url;

        if (preg_match('/[\s;&|`$<>(){}\\\\\'"]/', $url) === 1
            || preg_match('#^https://[a-z0-9.\-]+\.[a-z]{2,}(:\d{2,5})?/[a-z0-9._/\-~%]*$#i', $url) !== 1) {
            throw new InvalidArgumentException('Instalační zdroj aplikace není platná https adresa.');
        }

        foreach (['archive_subdir' => $app->archive_subdir, 'install_path' => $app->install_path] as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (str_contains($value, '..') || str_starts_with($value, '/')
                || preg_match('#^[a-z0-9._/\-]{1,100}$#i', $value) !== 1) {
                throw new InvalidArgumentException('Instalační cesta aplikace není platná.');
            }
        }

        foreach ($app->postInstallCommandList() as $command) {
            if (preg_match('/[;&|`$<>(){}\\\\\'"]/', $command) === 1) {
                throw new InvalidArgumentException("Příkaz po instalaci obsahuje nepovolené znaky: {$command}");
            }

            $binary = strtok(trim($command), ' ');

            if ($binary === false || ! in_array($binary, self::ALLOWED_POST_INSTALL, true)) {
                throw new InvalidArgumentException(
                    'Povolené příkazy po instalaci: ' . implode(', ', self::ALLOWED_POST_INSTALL) . '.',
                );
            }
        }
    }

    /**
     * Database names are derived, never taken from input: MySQL identifiers
     * have their own rules and a customer label can contain anything.
     */
    private function databaseName(Service $service, MarketplaceApp $app): string
    {
        $base = Str::slug(($service->label ?? 'site') . '-' . $app->slug, '_');
        $base = preg_replace('/[^a-z0-9_]/i', '', $base) ?? 'app';

        return mb_substr($base, 0, 48) . '_' . mb_substr((string) $service->id, -4);
    }

    /**
     * Download, unpack, flatten the versioned folder if the archive has one,
     * then run the recipe's build commands. Every value is escaped.
     */
    private function buildScript(MarketplaceApp $app, string $target): string
    {
        $url    = escapeshellarg((string) $app->install_url);
        $dir    = escapeshellarg($target);
        $isZip  = str_ends_with(strtolower((string) $app->install_url), '.zip');
        $subdir = trim((string) $app->archive_subdir, '/');

        $lines = [
            'set -e',
            "mkdir -p {$dir}",
            "cd {$dir}",
            'curl -fsSL -o .install-payload ' . $url,
            $isZip
                ? 'unzip -oq .install-payload'
                : 'tar -xzf .install-payload',
            'rm -f .install-payload',
        ];

        if ($subdir !== '') {
            // Archives like WordPress unpack into wordpress/ — move the contents
            // up so the app sits where the customer's URL points.
            $sub = escapeshellarg($subdir);
            $lines[] = "if [ -d {$sub} ]; then cp -a {$sub}/. . && rm -rf {$sub}; fi";
        }

        foreach ($app->postInstallCommandList() as $command) {
            $parts   = preg_split('/\s+/', trim($command)) ?: [];
            $lines[] = implode(' ', array_map(static fn (string $p): string => escapeshellarg($p), $parts));
        }

        return implode("\n", $lines);
    }
}
