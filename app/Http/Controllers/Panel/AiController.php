<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Ai\Models\AiRun;
use App\Domains\Ai\Services\AiAssistantService;
use App\Domains\Ai\Services\AiChatbotService;
use App\Domains\Support\Enums\ChatConversationStatus;
use App\Domains\Support\Models\SupportChatMessage;
use App\Domains\Support\Services\SupportChatService;
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

    /**
     * POST /panel/ai/chat — the support chat widget.
     *
     * Every turn is persisted to the DB (support_chat_* tables). While the
     * conversation is with the AI bot, the assistant answers and its reply
     * (text + categorised quick-replies + deep links) is saved. Once the
     * customer has escalated to a live agent, the bot stays silent — the
     * message is stored and an agent answers from the admin inbox.
     */
    public function chat(Request $request, AiChatbotService $chatbot, SupportChatService $chatService): JsonResponse
    {
        $validated = $request->validate([
            'message'  => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();
        abort_if($user === null, 403);

        $conversation = $chatService->openConversationFor($user);
        $message      = trim((string) ($validated['message'] ?? ''));

        // Persist the customer's message (skip the silent opening-menu load).
        if ($message !== '') {
            $chatService->userMessage($conversation, $message, $user);
        }

        // With a live agent, the bot does not answer — the agent will.
        if (in_array($conversation->status, [ChatConversationStatus::WaitingAgent, ChatConversationStatus::AgentActive], true)) {
            return response()->json([
                'reply'         => 'Vaše zpráva byla předána živé podpoře. Operátor vám odpoví zde v chatu.',
                'suggestions'   => [],
                'links'         => [],
                'category'      => null,
                'conversation'  => $conversation->uuid,
                'status'        => $conversation->status->value,
                'escalated'     => true,
            ]);
        }

        try {
            $result = $chatbot->reply($user, $message, $validated['category'] ?? null);
        } catch (\Throwable) {
            $result = [
                'reply'       => 'Omlouváme se, AI asistent je momentálně nedostupný. Zkuste to prosím znovu.',
                'suggestions' => [],
                'links'       => [],
                'category'    => null,
            ];
        }

        // Persist the bot reply (with its suggestions/links) so the thread
        // survives a refresh and the agent sees the full context.
        $botMessage = $chatService->botMessage($conversation, $result['reply'], [
            'suggestions' => $result['suggestions'],
            'links'       => $result['links'],
            'category'    => $result['category'],
        ]);

        activity('support')
            ->performedOn($conversation)
            ->causedBy($user)
            ->withProperties(['category' => $result['category']])
            ->log('chat.bot_reply');

        return response()->json($result + [
            'conversation' => $conversation->uuid,
            'status'       => $conversation->status->value,
            'lastId'       => $botMessage->id,
        ]);
    }

    /** POST /panel/ai/eskalovat — hand the conversation to a live agent. */
    public function escalate(Request $request, SupportChatService $chatService): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $conversation = $chatService->openConversationFor($user);
        $chatService->escalate($conversation);

        return response()->json([
            'status'  => $conversation->fresh()?->status->value,
            'message' => 'Spojujeme vás s živou podporou. Operátor se ozve zde v chatu.',
        ]);
    }

    /** GET /panel/ai/poll?after=ID — new agent/system messages for the widget. */
    public function poll(Request $request, SupportChatService $chatService): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $afterId      = (int) $request->integer('after');
        $conversation = $chatService->openConversationFor($user);

        $messages = $chatService->messagesSince($conversation, $afterId)
            ->map(fn (SupportChatMessage $m): array => [
                'id'   => $m->id,
                'role' => $m->role,
                'body' => $m->body,
                'meta' => $m->meta,
            ])->values();

        return response()->json([
            'status'   => $conversation->status->value,
            'messages' => $messages,
        ]);
    }
}
