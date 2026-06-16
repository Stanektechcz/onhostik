<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Integrations\Models\IntegrationSetting;
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

        try {
            DB::connection()->getPdo();
            $checks[] = ['name' => 'database', 'ok' => true, 'detail' => DB::connection()->getDatabaseName()];
        } catch (Throwable $e) {
            $checks[] = ['name' => 'database', 'ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 120)];
        }

        try {
            $pending = (int) DB::table('jobs')->count();
            $failed  = (int) DB::table('failed_jobs')->count();
            $checks[] = [
                'name'   => 'queue',
                'ok'     => $failed === 0,
                'detail' => "{$pending} pending / {$failed} failed (worker: php artisan queue:work)",
            ];
        } catch (Throwable $e) {
            $checks[] = ['name' => 'queue', 'ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 120)];
        }

        $checks[] = [
            'name'   => 'scheduler',
            'ok'     => true,
            'detail' => 'run via: php artisan schedule:work (no scheduled jobs registered yet)',
        ];

        try {
            Storage::disk('local')->put('.health-check', (string) now()->timestamp);
            $checks[] = ['name' => 'storage', 'ok' => true, 'detail' => 'local disk writable'];
        } catch (Throwable $e) {
            $checks[] = ['name' => 'storage', 'ok' => false, 'detail' => mb_substr($e->getMessage(), 0, 120)];
        }

        $checks[] = [
            'name'   => 'mock mode',
            'ok'     => true,
            'detail' => config('provisioning.mock_mode') === true
                ? 'PROVISIONING_MOCK_MODE=true — no real providers reachable'
                : 'MOCK MODE OFF — real providers would be required!',
        ];

        return $checks;
    }
}
