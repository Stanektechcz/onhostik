<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Enums\ChatConversationStatus;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportChatConversation;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\SupportChatService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
                ->withCount(['messages as unread_count' => fn ($q) => $q->where('role', 'user')->whereNull('read_at')])
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

    /** Convert the whole conversation (transcript) into a support ticket. */
    public function toTicket(SupportChatConversation $conversation, SupportChatService $chatService): RedirectResponse
    {
        $conversation->load(['messages', 'customer']);

        if ($conversation->customer === null) {
            return back()->withErrors(['chat' => 'Konverzace nemá přiřazeného zákazníka.']);
        }

        $firstUser = $conversation->messages->firstWhere('role', 'user');
        $subject   = $firstUser !== null ? $firstUser->body : 'Konverzace z chatu';

        $ticket = SupportTicket::create([
            'customer_id'   => $conversation->customer->id,
            'subject'       => 'Chat: ' . Str::limit($subject, 60),
            'status'        => TicketStatus::Open,
            'priority'      => TicketPriority::Normal,
            'department'    => 'support',
            'last_reply_at' => now(),
        ]);

        foreach ($conversation->messages as $m) {
            if ($m->role === 'system') {
                continue;
            }

            $ticket->messages()->create([
                'user_id'     => $m->author_id,
                'is_staff'    => in_array($m->role, ['bot', 'agent'], true),
                'is_internal' => false,
                'message'     => ($m->role === 'bot' ? '[AI] ' : '') . $m->body,
            ]);
        }

        $chatService->systemMessage($conversation, "Konverzace byla převedena do ticketu #{$ticket->id}.");
        $conversation->update(['status' => ChatConversationStatus::Closed, 'closed_at' => now()]);

        return redirect()->route('admin.support.show', $ticket)
            ->with('status', 'Ticket byl vytvořen z konverzace.');
    }

    public function close(SupportChatConversation $conversation, SupportChatService $chatService, Request $request): RedirectResponse
    {
        $agent = $request->user();
        abort_if($agent === null, 403);

        $chatService->close($conversation, $agent);

        return redirect()->route('admin.chat.index')->with('status', 'Konverzace byla uzavřena.');
    }
}
