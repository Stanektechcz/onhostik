<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Enums\TaskStatus;
use App\Domains\Provisioning\Jobs\ProvisionHostingServiceJob;
use App\Domains\Provisioning\Jobs\RegisterDomainJob;
use App\Domains\Provisioning\Models\ProvisioningTask;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProvisioningController extends Controller
{
    public function index(Request $request): View
    {
        $status          = $request->string('status')->toString();
        $operationFilter = $request->string('operation')->toString();

        return view('admin.provisioning', [
            'tasks' => ProvisioningTask::query()
                ->with('service.customer')
                ->when(TaskStatus::tryFrom($status) !== null, fn ($q) => $q->where('status', $status))
                ->when($operationFilter !== '', fn ($q) => $q->where('operation', $operationFilter))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'filter'          => $status,
            'operationFilter' => $operationFilter,
            'operations'      => ProvisioningTask::query()->distinct()->orderBy('operation')->pluck('operation'),
            'failedCount'     => ProvisioningTask::query()->where('status', TaskStatus::Failed->value)->count(),
            'pendingCount'    => ProvisioningTask::query()->whereIn('status', [TaskStatus::Pending->value, TaskStatus::Running->value, TaskStatus::Retrying->value])->count(),
            'reviewCount'     => ProvisioningTask::query()->where('status', TaskStatus::ManualReview->value)->count(),
            'successCount'    => ProvisioningTask::query()->where('status', TaskStatus::Success->value)->count(),
        ]);
    }

    /**
     * Manually re-queues a failed / manual-review task. The job itself
     * re-checks idempotency and attempt limits — the admin button only
     * requests the retry, it never forces a remote operation.
     */
    public function retry(ProvisioningTask $task): RedirectResponse
    {
        if (!$task->canRetry()) {
            return back()->withErrors([
                'retry' => __('panel.admin.retry_not_allowed', ['status' => $task->status->label()]),
            ]);
        }

        $service = $task->service;

        if ($service === null) {
            return back()->withErrors(['retry' => 'Task has no service attached.']);
        }

        $task->update(['status' => TaskStatus::Retrying]);

        /** @var array<string, mixed> $payload */
        $payload = $task->payload ?? [];

        match ($task->operation) {
            'register_domain' => RegisterDomainJob::dispatch(
                $service->id,
                is_string($payload['domain'] ?? null) ? $payload['domain'] : (string) $service->label,
            ),
            default => ProvisionHostingServiceJob::dispatch($service->id),
        };

        activity('provisioning')
            ->performedOn($service)
            ->causedBy(auth()->user())
            ->withProperties(['task_id' => $task->id, 'operation' => $task->operation])
            ->log('provisioning.retry_requested');

        return back()->with('status', __('panel.admin.retry_queued'));
    }
}
