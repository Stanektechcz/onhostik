<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Ai\Enums\ApprovalStatus;
use App\Domains\Ai\Models\AiActionApproval;
use App\Domains\Ai\Models\AiPromptTemplate;
use App\Domains\Ai\Models\AiRun;
use App\Domains\Ai\Models\AiUsageLog;
use App\Domains\Ai\Services\AiAssistantService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiController extends Controller
{
    public function index(): View
    {
        return view('admin.ai', [
            'features'  => AiAssistantService::ADMIN_FEATURES,
            'runs'      => AiRun::query()->with(['user', 'messages'])->latest('id')->limit(15)->get(),
            'templates' => AiPromptTemplate::query()->orderBy('audience')->orderBy('key')->get(),
            'approvals' => AiActionApproval::query()
                ->with(['requester', 'reviewer'])
                ->latest('id')
                ->limit(20)
                ->get(),
            'usage' => AiUsageLog::query()->latest('id')->limit(15)->get(),
        ]);
    }

    public function run(Request $request, AiAssistantService $assistant): RedirectResponse
    {
        $validated = $request->validate([
            'feature' => ['required', Rule::in(AiAssistantService::ADMIN_FEATURES)],
            'text'    => ['required', 'string', 'min:3', 'max:4000'],
        ]);

        $admin = $request->user();
        abort_if($admin === null, 403);

        $assistant->run($admin, $validated['feature'], ['text' => $validated['text']]);

        return back()->with('status', __('panel.ai.completed'));
    }

    /** Approve/reject a parked high-risk AI action. Never executes anything. */
    public function review(Request $request, AiActionApproval $approval, AiAssistantService $assistant): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason'   => ['nullable', 'string', 'max:255'],
        ]);

        $admin = $request->user();
        abort_if($admin === null, 403);

        if ($approval->status !== ApprovalStatus::Pending) {
            return back()->withErrors(['approval' => 'Approval already decided.']);
        }

        $assistant->review(
            $approval,
            $admin,
            approve: $validated['decision'] === 'approve',
            reason: $validated['reason'] ?? null,
        );

        return back()->with('status', __('panel.admin.approval_saved'));
    }
}
