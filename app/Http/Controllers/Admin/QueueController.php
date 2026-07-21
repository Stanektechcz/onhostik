<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Queue health for operators (audit E71).
 *
 * Provisioning runs on the queue, so a stalled worker means paid orders
 * silently never get provisioned. Until now there was no way to see that
 * from the admin at all — you had to SSH in and query the tables.
 */
class QueueController extends Controller
{
    public function index(Request $request): View
    {
        $hasJobs   = Schema::hasTable('jobs');
        $hasFailed = Schema::hasTable('failed_jobs');

        return view('admin.queue', [
            'available'    => $hasJobs || $hasFailed,
            'pendingCount' => $hasJobs ? (int) DB::table('jobs')->count() : 0,
            'failedCount'  => $hasFailed ? (int) DB::table('failed_jobs')->count() : 0,
            'byQueue'      => $hasJobs
                ? DB::table('jobs')->selectRaw('queue, COUNT(*) as total')->groupBy('queue')->pluck('total', 'queue')
                : collect(),
            // A job reserved long ago means a worker died mid-run and the
            // payload will sit there until its reserve expires.
            'stuckCount'   => $hasJobs
                ? (int) DB::table('jobs')->whereNotNull('reserved_at')->where('reserved_at', '<', now()->subMinutes(15)->timestamp)->count()
                : 0,
            'oldestPending' => $hasJobs
                ? DB::table('jobs')->orderBy('created_at')->value('created_at')
                : null,
            'failed' => $hasFailed
                ? DB::table('failed_jobs')->orderByDesc('failed_at')->limit(50)->get()
                : collect(),
        ]);
    }

    /** Retry one failed job, or all of them. */
    public function retry(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $uuid = is_string($validated['uuid'] ?? null) ? $validated['uuid'] : null;

        Artisan::call('queue:retry', ['id' => [$uuid ?? 'all']]);

        activity('system')
            ->causedBy($request->user())
            ->withProperties(['uuid' => $uuid ?? 'all'])
            ->log('queue.job_retried');

        return back()->with('status', $uuid !== null
            ? 'Úloha byla zařazena k opakování.'
            : 'Všechny neúspěšné úlohy byly zařazeny k opakování.');
    }

    /** Delete a failed job (or flush them all) once it is understood. */
    public function forget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $uuid = is_string($validated['uuid'] ?? null) ? $validated['uuid'] : null;

        if ($uuid !== null) {
            DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        } else {
            DB::table('failed_jobs')->delete();
        }

        activity('system')
            ->causedBy($request->user())
            ->withProperties(['uuid' => $uuid ?? 'all'])
            ->log('queue.job_forgotten');

        return back()->with('status', 'Záznam neúspěšné úlohy byl odstraněn.');
    }
}
