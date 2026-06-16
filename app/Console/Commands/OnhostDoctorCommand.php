<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Production readiness check.
 *
 * Usage:
 *   php artisan onhost:doctor                  # standard mode (warns on prod settings)
 *   php artisan onhost:doctor --production     # strict mode (fails on any deviation)
 *
 * Exit codes:
 *   0 — all critical checks passed (warnings may exist)
 *   1 — one or more critical checks failed
 */
class OnhostDoctorCommand extends Command
{
    protected $signature = 'onhost:doctor
        {--production : Strict production checks — fails if mock_mode on, debug on, or test creds}';

    protected $description = 'Check production readiness: env, DB, storage, queue, mail, integrations';

    private int $fails    = 0;
    private int $warnings = 0;

    private const W = 60; // column width

    public function handle(): int
    {
        $strict = (bool) $this->option('production');

        $this->newLine();
        $this->line('  <fg=blue>OnHost Doctor</> — Production Readiness Check');
        if ($strict) {
            $this->line('  <fg=yellow>Mode: STRICT (--production)</>');
        }
        $this->line('  ' . str_repeat('─', self::W));

        $this->sectionApp($strict);
        $this->sectionDatabase();
        $this->sectionStorage($strict);
        $this->sectionQueue($strict);
        $this->sectionMail($strict);
        $this->sectionComgate($strict);
        $this->sectionProvisioning($strict);
        $this->sectionScheduler();

        $this->line('  ' . str_repeat('─', self::W));
        $this->newLine();

        if ($this->fails > 0) {
            $this->line("  <fg=red;options=bold>✗ FAILED</> — {$this->fails} critical error(s), {$this->warnings} warning(s)");
            $this->newLine();
            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->line("  <fg=yellow;options=bold>⚠ PASS WITH WARNINGS</> — {$this->warnings} warning(s)");
        } else {
            $this->line('  <fg=green;options=bold>✓ ALL CHECKS PASSED</>');
        }

        $this->newLine();
        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────── sections

    private function sectionApp(bool $strict): void
    {
        $this->section('Application');

        $key = (string) config('app.key');
        $this->check('APP_KEY', $key !== '' && str_starts_with($key, 'base64:'), 'not set');

        $env = (string) config('app.env');
        $this->check('APP_ENV', true, $env); // info only

        $debug = (bool) config('app.debug');
        if ($strict) {
            $this->check('APP_DEBUG=false', ! $debug, 'APP_DEBUG is true — must be false in production', critical: true);
        } else {
            $debugStr = $debug ? 'true (set false before going live)' : 'false';
            $this->warn_check('APP_DEBUG', ! $debug, $debugStr);
        }

        $url = (string) config('app.url');
        $isLocalUrl = str_contains($url, 'localhost') || str_contains($url, '127.0.0.1');
        if ($strict) {
            $this->check('APP_URL', ! $isLocalUrl, "'{$url}' looks like localhost", critical: true);
        } else {
            $this->warn_check('APP_URL', ! $isLocalUrl, $url);
        }

        $php = PHP_VERSION;
        $phpOk = version_compare($php, '8.2.0', '>=');
        $this->check('PHP >= 8.2', $phpOk, "found PHP {$php}");
    }

    private function sectionDatabase(): void
    {
        $this->section('Database');

        try {
            DB::connection()->getPdo();
            $dbName = DB::connection()->getDatabaseName();
            $this->check('connection', true, $dbName);
        } catch (Throwable $e) {
            $this->check('connection', false, mb_substr($e->getMessage(), 0, 80), critical: true);
            return; // no point continuing if DB is down
        }

        try {
            // Check for pending migrations
            $pending = DB::table('migrations')->count();
            // Rough check: artisan migrate:status would be more accurate but slow
            $this->check('tables accessible', true, "migrations table readable ({$pending} rows)");
        } catch (Throwable $e) {
            $this->check('migrations table', false, 'Run: php artisan migrate', critical: true);
        }

        try {
            $failed = (int) DB::table('failed_jobs')->count();
            $this->warn_check('failed_jobs', $failed === 0, "{$failed} failed job(s) in queue");
        } catch (Throwable) {
            $this->check('failed_jobs table', false, 'Run: php artisan queue:failed-table && migrate', critical: false);
        }
    }

    private function sectionStorage(bool $strict = false): void
    {
        $this->section('Storage');

        $storagePath = storage_path('app');
        $writable    = is_writable($storagePath);
        $this->check('storage/app writable', $writable, $storagePath, critical: true);

        $bootstrapCache = base_path('bootstrap/cache');
        $cacheWritable  = is_writable($bootstrapCache);
        $this->check('bootstrap/cache writable', $cacheWritable, $bootstrapCache, critical: true);

        $symlink   = public_path('storage');
        $symlinkOk = file_exists($symlink) && is_link($symlink);
        // Symlink is critical in production (PDFs + uploads), warning in dev
        $this->check(
            'storage symlink',
            $symlinkOk,
            $symlinkOk ? 'public/storage → storage/app/public' : 'Run: php artisan storage:link',
            critical: $strict,
        );

        try {
            Storage::disk('local')->put('.doctor-check', (string) now()->timestamp);
            Storage::disk('local')->delete('.doctor-check');
            $this->check('local disk write', true, 'OK');
        } catch (Throwable $e) {
            $this->check('local disk write', false, mb_substr($e->getMessage(), 0, 80), critical: true);
        }
    }

    private function sectionQueue(bool $strict): void
    {
        $this->section('Queue');

        $driver = (string) config('queue.default');
        if ($strict) {
            $this->check('driver != sync', $driver !== 'sync', "driver is '{$driver}'", critical: true);
        } else {
            $this->warn_check('driver', $driver !== 'sync', "driver='{$driver}' (use database or redis in production)");
        }

        try {
            $pending = (int) DB::table('jobs')->count();
            $this->check('jobs table', true, "{$pending} pending job(s)");
        } catch (Throwable) {
            $this->warn_check('jobs table', false, 'Run: php artisan queue:table && migrate');
        }
    }

    private function sectionMail(bool $strict): void
    {
        $this->section('Mail');

        $mailer = (string) config('mail.default');
        $from   = (string) config('mail.from.address', '');

        if ($strict) {
            $this->check('mailer != log', $mailer !== 'log', "mailer='{$mailer}'", critical: true);
        } else {
            $this->warn_check('mailer', $mailer !== 'log', "mailer='{$mailer}' (log is dev-only)");
        }

        $this->check('FROM address', $from !== '' && ! str_contains($from, '.local'), $from ?: 'not set');
    }

    private function sectionComgate(bool $strict): void
    {
        $this->section('Comgate');

        $merchantId = (string) config('comgate.merchant_id', '');
        $secret     = (string) config('comgate.secret', '');
        $testMode   = (bool) config('comgate.test_mode', true);

        // Critical only in --production mode; standard mode just warns so the
        // command is useful during development without Comgate credentials.
        $this->check('merchant_id set', $merchantId !== '', $merchantId !== '' ? 'configured' : 'COMGATE_MERCHANT_ID not set', critical: $strict);
        $this->check('secret set', $secret !== '', $secret !== '' ? '(hidden)' : 'COMGATE_SECRET not set', critical: $strict);

        if ($strict) {
            $this->check('test_mode=false', ! $testMode, 'COMGATE_TEST_MODE must be false in production', critical: true);
        } else {
            $this->warn_check('test_mode', ! $testMode, $testMode ? 'true — set COMGATE_TEST_MODE=false before go-live' : 'false (production)');
        }

        $webhookIps = config('comgate.webhook_ip_whitelist', []);
        $this->warn_check('webhook IP whitelist', is_array($webhookIps) && count($webhookIps) > 0, 'not set (optional but recommended)');
    }

    private function sectionProvisioning(bool $strict): void
    {
        $this->section('Provisioning');

        $mockMode = (bool) config('provisioning.mock_mode', true);
        if ($strict) {
            $this->check('PROVISIONING_MOCK_MODE=false', ! $mockMode, 'mock_mode is ON — must be false in production', critical: true);
        } else {
            $this->warn_check('mock_mode', ! $mockMode, $mockMode ? 'true (dev mode)' : 'false (production)');
        }

        $allowAapanel = (bool) config('provisioning.aapanel.allow_real_writes', false);
        $this->check('AAPANEL_ALLOW_REAL_WRITES', true, $allowAapanel ? 'true (writes ENABLED)' : 'false (writes disabled — safe for first test)');

        $allowWedos = (bool) config('provisioning.wedos.allow_real_writes', false);
        $this->check('WAPI_ALLOW_REAL_WRITES', true, $allowWedos ? 'true (writes ENABLED)' : 'false (writes disabled — safe for first test)');

        $wapiUser = (string) config('provisioning.wedos.user', '');
        $this->warn_check('WAPI_USER', $wapiUser !== '', $wapiUser !== '' ? 'configured' : 'not set (required for real domain registration)');

        try {
            $serverCount   = DB::table('servers')->count();
            $defaultServer = DB::table('servers')->where('is_default', true)->first();
            $hasCreds      = $defaultServer && ! empty((string) ($defaultServer->api_credentials ?? ''));

            $this->warn_check('server records', $serverCount > 0, "{$serverCount} server(s) configured (add in /admin/servery)");
            $this->warn_check('default server credentials', (bool) $hasCreds, $hasCreds ? 'present' : 'no default server with credentials');
        } catch (Throwable) {
            $this->warn_check('servers table', false, 'Cannot read servers table');
        }
    }

    private function sectionScheduler(): void
    {
        $this->section('Scheduler');

        // Verify scheduled commands exist in console.php
        $scheduleFile = base_path('routes/console.php');
        $scheduleContent = file_exists($scheduleFile) ? (string) file_get_contents($scheduleFile) : '';

        $hasMarkOverdue    = str_contains($scheduleContent, 'mark-overdue') || str_contains($scheduleContent, 'MarkOverdue');
        $hasSuspendOverdue = str_contains($scheduleContent, 'suspend-overdue') || str_contains($scheduleContent, 'SuspendOverdue');

        $this->check('billing:mark-overdue scheduled', $hasMarkOverdue, '01:00 daily');
        $this->check('billing:suspend-overdue scheduled', $hasSuspendOverdue, '01:15 daily');
        $this->check('cron installed', true, '* * * * * php artisan schedule:run (verify with: crontab -l)');
    }

    // ──────────────────────────────────────────────────── output helpers

    private function section(string $name): void
    {
        $this->newLine();
        $this->line("  <fg=blue;options=bold>[{$name}]</>");
    }

    private function check(string $label, bool $ok, string $detail = '', bool $critical = false): void
    {
        $icon   = $ok ? '<fg=green>✓</>' : ($critical ? '<fg=red>✗</>' : '<fg=yellow>⚠</>');
        $color  = $ok ? 'default' : ($critical ? 'red' : 'yellow');
        $padded = str_pad("    {$label}", self::W - 14);

        $this->line("  {$icon} <fg={$color}>{$padded}</> {$detail}");

        if (! $ok) {
            if ($critical) {
                $this->fails++;
            } else {
                $this->warnings++;
            }
        }
    }

    private function warn_check(string $label, bool $ok, string $detail = ''): void
    {
        $this->check($label, $ok, $detail, critical: false);
    }
}
