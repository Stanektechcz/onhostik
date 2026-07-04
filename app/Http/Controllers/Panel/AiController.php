<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Ai\Models\AiRun;
use App\Domains\Ai\Services\AiAssistantService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        return view('panel.ai.index', [
            'features' => AiAssistantService::CUSTOMER_FEATURES,
            'runs'     => AiRun::query()
                ->where('customer_id', $customer->id)
                ->with('messages')
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function run(Request $request, AiAssistantService $assistant): RedirectResponse
    {
        $validated = $request->validate([
            'feature' => ['required', Rule::in(AiAssistantService::CUSTOMER_FEATURES)],
            'text'    => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $user = $request->user();
        abort_if($user === null || $user->customer === null, 403);

        $assistant->run($user, $validated['feature'], ['text' => $validated['text']]);

        return back()->with('status', __('panel.ai.completed'));
    }

    /** POST /panel/ai/chat — floating chatbot widget. Returns JSON. */
    public function chat(Request $request, AiAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $user = $request->user();
        abort_if($user === null, 403);

        try {
            $run   = $assistant->run($user, 'support_draft', ['text' => $validated['message']]);
            $reply = (string) ($run->messages()->where('role', 'assistant')->latest('id')->value('content') ?? '');

            return response()->json(['reply' => $reply]);
        } catch (\Throwable) {
            return response()->json(['reply' => 'Omlouváme se, AI asistent je momentálně nedostupný.']);
        }
    }
}
