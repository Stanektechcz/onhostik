<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Backups\Enums\BackupJobStatus;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Monitoring\Services\SchedulerHeartbeat;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemHealthController extends Controller
{
    public function index(): View
    {
        return view('admin.system', [
            'checks'    => $this->checks(),
            'providers' => IntegrationSetting::query()->orderBy('provider')->get()
                ->map(fn (IntegrationSetting $setting): array => [
                    'provider' => $setting->provider,
                    'label'    => $setting->label,
                    'health'   => $setting->healthStatus(),
                    'mock'     => $setting->mock_mode,
                ]),
        ]);
    }

    /** @return list<array{name: string, ok: bool, detail: string}> */
    private function checks(): array
    {
        $checks = [];

        // ── Application ──────────────────────────────────────────
        $debug = (bool) config('app.debug');
        $checks[] = [
            'name'   => 'APP_DEBUG',
            'ok'     => ! $debug,
            'detail' => $debug
                ? 'true — must be false in production'
                : 'false ✓',
        ];

        $env = (string) config('app.env');
        $checks[] = [
            'name'   => 'APP_ENV',
            'ok'     => $env !== 'local',
            'detail' => $env,
        ];

        // ── Database ─────────────────────────────────────────────
        try {
            DB::connection()->getPdo();
            $checks[] = ['name' => 'database', 'ok' => true, 'detail' => DB::connection()->getDatabaseName()];
        } catch (Throwable $e) {
            $checks[] = ['name' => 'database', 'ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 120)];
        }

        // ── Queue ────────────────────────────────────────────────
        try {
            $pending = (int) DB::table('jobs')->count();
            $failed  = (int) DB::table('failed_jobs')->count();
            $driver  = (string) config('queue.default');
            $checks[] = [
                'name'   => 'queue',
                'ok'     => $failed === 0 && $driver !== 'sync',
                'detail' => "driver={$driver} | {$pending} pending | {$failed} failed",
            ];
        } catch (Throwable $e) {
            $checks[] = ['name' => 'queue', 'ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 120)];
        }

        // ── Scheduler ────────────────────────────────────────────
        // Real dead-cron detection via the per-minute heartbeat (not a hardcoded
        // "ok" that would hide a scheduler that stopped weeks ago).
        $heartbeat = app(SchedulerHeartbeat::class);
        $lastRun   = $heartbeat->lastRunAt();
        $checks[] = [
            'name'   => 'scheduler',
            'ok'     => ! $heartbeat->isStale(),
            'detail' => $lastRun === null
                ? 'nikdy neběžel — cron/supervisor nejspíš neběží (* * * * * php artisan schedule:run)'
                : 'poslední běh ' . $lastRun->diffForHumans(),
        ];

        // ── Storage ──────────────────────────────────────────────
        try {
            Storage::disk('local')->put('.health-check', (string) now()->timestamp);
            Storage::disk('local')->delete('.health-check');
            $symlinkOk = file_exists(public_path('storage')) && is_link(public_path('storage'));
            $checks[] = [
                'name'   => 'storage',
                'ok'     => $symlinkOk,
                'detail' => $symlinkOk ? 'local disk writable + symlink OK' : 'local disk writable | symlink missing — run php artisan storage:link',
            ];
        } catch (Throwable $e) {
            $checks[] = ['name' => 'storage', 'ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 120)];
        }

        // ── Backups ──────────────────────────────────────────────
        // A silent backup failure is a classic disaster; surface the last
        // success + any recent failures instead of assuming it works.
        try {
            $lastSuccess = BackupJob::query()
                ->where('status', BackupJobStatus::Success)
                ->latest('finished_at')
                ->first()?->finished_at;
            $recentFailures = BackupJob::query()
                ->where('status', BackupJobStatus::Failed)
                ->where('created_at', '>=', now()->subDay())
                ->count();

            $checks[] = [
                'name'   => 'backups',
                'ok'     => $lastSuccess !== null && $recentFailures === 0,
                'detail' => $lastSuccess === null
                    ? 'žádná úspěšná záloha nezaznamenána'
                    : 'poslední úspěch ' . $lastSuccess->diffForHumans()
                        . ($recentFailures > 0 ? " | {$recentFailures} selhání za 24 h" : ''),
            ];
        } catch (Throwable $e) {
            $checks[] = ['name' => 'backups', 'ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 120)];
        }

        // ── Provisioning ─────────────────────────────────────────
        $mockMode = config('provisioning.mock_mode');
        $checks[] = [
            'name'   => 'provisioning mode',
            'ok'     => $mockMode !== true,
            'detail' => $mockMode === true
                ? 'MOCK ON — PROVISIONING_MOCK_MODE=false needed for production'
                : 'MOCK OFF — real drivers active',
        ];

        $allowAapanel = (bool) config('provisioning.aapanel.allow_real_writes', false);
        $allowWedos   = (bool) config('provisioning.wedos.allow_real_writes', false);
        $checks[] = [
            'name'   => 'write gates',
            'ok'     => true,
            'detail' => 'aaPanel=' . ($allowAapanel ? 'OPEN' : 'closed') . ' | WEDOS=' . ($allowWedos ? 'OPEN' : 'closed'),
        ];

        // ── Comgate ──────────────────────────────────────────────
        $comgateMerchantId = (string) config('comgate.merchant_id', '');
        $comgateSecret     = (string) config('comgate.secret', '');
        $comgateTest       = (bool) config('comgate.test_mode', true);
        $checks[] = [
            'name'   => 'Comgate',
            'ok'     => $comgateMerchantId !== '' && $comgateSecret !== '',
            'detail' => $comgateMerchantId !== ''
                ? 'merchant_id configured | test_mode=' . ($comgateTest ? 'true' : 'false')
                : 'merchant_id not configured',
        ];

        // ── WEDOS ────────────────────────────────────────────────
        $wapiUser = (string) config('provisioning.wedos.user', '');
        $checks[] = [
            'name'   => 'WEDOS WAPI',
            'ok'     => $wapiUser !== '',
            'detail' => $wapiUser !== '' ? 'credentials configured' : 'WAPI_USER not set',
        ];

        // ── Stripe ───────────────────────────────────────────────
        $stripeKey     = (string) config('stripe.api_key', '');
        $stripeWebhook = (string) config('stripe.webhook_secret', '');
        $checks[] = [
            'name'   => 'Stripe',
            'ok'     => $stripeKey !== '',
            'detail' => $stripeKey !== ''
                ? 'api_key_set: true | webhook_secret_set: ' . ($stripeWebhook !== '' ? 'true' : 'false')
                : 'STRIPE_SECRET_KEY not configured (Stripe payments disabled)',
        ];

        return $checks;
    }
}
