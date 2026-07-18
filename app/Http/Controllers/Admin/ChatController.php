<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Enums\ChatConversationStatus;
use App\Domains\Support\Models\SupportChatConversation;
use App\Domains\Support\Services\SupportChatService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Live-support inbox: agents pick up escalated chats, reply, and close them.
 */
class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->string('status')->toString();

        $counts = SupportChatConversation::query()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('admin.chat.index', [
            'conversations' => SupportChatConversation::query()
                ->with(['customer', 'startedBy', 'agent'])
                ->withCount('messages')
                ->when($filter !== '', fn ($q) => $q->where('status', $filter))
                // Waiting chats first, then most recent activity.
                ->orderByRaw("CASE status WHEN 'waiting_agent' THEN 0 WHEN 'agent_active' THEN 1 WHEN 'bot' THEN 2 ELSE 3 END")
                ->latest('last_message_at')
                ->paginate(20)
                ->withQueryString(),
            'filter'         => $filter,
            'countWaiting'   => (int) ($counts[ChatConversationStatus::WaitingAgent->value] ?? 0),
            'countActive'    => (int) ($counts[ChatConversationStatus::AgentActive->value] ?? 0),
            'countBot'       => (int) ($counts[ChatConversationStatus::Bot->value] ?? 0),
        ]);
    }

    public function show(SupportChatConversation $conversation): View
    {
        $conversation->load(['messages.author', 'customer.user', 'startedBy', 'agent']);

        // Mark inbound (customer) messages as read.
        $conversation->messages()
            ->where('role', 'user')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('admin.chat.show', ['conversation' => $conversation]);
    }

    public function reply(Request $request, SupportChatConversation $conversation, SupportChatService $chatService): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $agent = $request->user();
        abort_if($agent === null, 403);

        if ($conversation->status === ChatConversationStatus::Closed) {
            return back()->withErrors(['body' => 'Konverzace je uzavřená.']);
        }

        $chatService->agentMessage($conversation, $agent, $validated['body']);

        return back()->with('status', 'Odpověď byla odeslána.');
    }

    public function close(SupportChatConversation $conversation, SupportChatService $chatService, Request $request): RedirectResponse
    {
        $agent = $request->user();
        abort_if($agent === null, 403);

        $chatService->close($conversation, $agent);

        return redirect()->route('admin.chat.index')->with('status', 'Konverzace byla uzavřena.');
    }
}
