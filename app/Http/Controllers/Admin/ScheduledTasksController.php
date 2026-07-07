<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScheduledTasksController extends Controller
{
    public function index(): View
    {
        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(50)
            ->get();

        $failedCount = DB::table('failed_jobs')->count();

        $pendingCount = 0;
        try {
            $pendingCount = DB::table('jobs')->count();
        } catch (\Throwable) {}

        return view('admin.scheduled-tasks', compact('failedJobs', 'failedCount', 'pendingCount'));
    }

    public function retryJob(int $id): \Illuminate\Http\RedirectResponse
    {
        DB::table('failed_jobs')->where('id', $id)->delete();

        return back()->with('status', 'Úloha smazána z fronty selhání (spusťte queue:retry ručně pokud potřeba).');
    }

    public function clearFailed(): \Illuminate\Http\RedirectResponse
    {
        DB::table('failed_jobs')->truncate();

        return back()->with('status', 'Fronta selhání vymazána.');
    }
}
