<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTask;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $status   = $request->string('status')->toString();
        $priority = $request->string('priority')->toString();

        $tasks = AdminTask::query()
            ->with('creator', 'assignee')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($priority !== '', fn ($q) => $q->where('priority', $priority))
            ->orderByRaw("CASE WHEN due_date IS NULL THEN 1 ELSE 0 END")
            ->orderBy('due_date')
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->get();

        $taskStats = [
            'total'      => AdminTask::count(),
            'done'       => AdminTask::where('status', 'done')->count(),
            'inprogress' => AdminTask::where('status', 'inprogress')->count(),
            'pending'    => AdminTask::where('status', 'pending')->count(),
        ];

        $admins = User::role('admin')->orderBy('name')->get();

        return view('admin.tasks', compact('tasks', 'taskStats', 'admins', 'status', 'priority'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority'    => ['required', 'in:low,medium,high'],
            'due_date'    => ['nullable', 'date', 'after_or_equal:today'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        AdminTask::create([
            ...$validated,
            'created_by' => $user->id,
            'status'     => 'pending',
        ]);

        return back()->with('status', 'Úkol byl vytvořen.');
    }

    public function update(Request $request, AdminTask $task): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority'    => ['required', 'in:low,medium,high'],
            'status'      => ['required', 'in:pending,inprogress,done'],
            'due_date'    => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        if ($validated['status'] === 'done' && $task->status !== 'done') {
            $validated['completed_at'] = now();
        } elseif ($validated['status'] !== 'done') {
            $validated['completed_at'] = null;
        }

        $task->update($validated);

        return back()->with('status', 'Úkol byl upraven.');
    }

    public function toggleStatus(Request $request, AdminTask $task): JsonResponse|RedirectResponse
    {
        $next = match ($task->status) {
            'pending'    => 'inprogress',
            'inprogress' => 'done',
            default      => 'pending',
        };

        $task->update([
            'status'       => $next,
            'completed_at' => $next === 'done' ? now() : null,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['status' => $next]);
        }

        return back()->with('status', 'Stav úkolu byl změněn.');
    }

    public function destroy(AdminTask $task): RedirectResponse
    {
        $task->delete();

        return back()->with('status', 'Úkol byl smazán.');
    }
}
