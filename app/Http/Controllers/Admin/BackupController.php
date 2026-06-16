<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Backups\Enums\BackupJobStatus;
use App\Domains\Backups\Models\BackupJob;
use App\Domains\Backups\Models\BackupPolicy;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class BackupController extends Controller
{
    public function index(): View
    {
        return view('admin.backups', [
            'jobs' => BackupJob::query()
                ->with('service.customer')
                ->latest('id')
                ->paginate(25),
            'policies' => BackupPolicy::query()
                ->with(['service.customer'])
                ->withCount('jobs')
                ->latest('id')
                ->paginate(25, ['*'], 'policies'),
            'failedCount'  => BackupJob::query()->where('status', BackupJobStatus::Failed->value)->count(),
            'runningCount' => BackupJob::query()->where('status', BackupJobStatus::Running->value)->count(),
            'successCount' => BackupJob::query()->where('status', BackupJobStatus::Success->value)->count(),
            'totalSizeMb'  => (int) BackupJob::query()->where('status', BackupJobStatus::Success->value)->sum('size_mb'),
            'policyCount'  => BackupPolicy::query()->where('is_active', true)->count(),
        ]);
    }
}
