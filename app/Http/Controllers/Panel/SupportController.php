<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Customer\Models\Customer;
use App\Domains\Ai\Services\AiAssistantService;
use App\Domains\Support\Actions\AnalyseTicketAction;
use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $this->customer($request);

        return view('panel.support.index', [
            'tickets' => $customer->supportTickets()->latest('last_reply_at')->paginate(15),
        ]);
    }

    public function store(Request $request, TicketService $tickets, AnalyseTicketAction $analyser): RedirectResponse
    {
        $validated = $request->validate([
            'subject'  => ['required', 'string', 'min:3', 'max:150'],
            'message'  => ['required', 'string', 'min:10', 'max:5000'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
        ]);

        $user = $request->user();
        abort_if($user === null, 403);

        $ticket = $tickets->open(
            customer: $this->customer($request),
            author: $user,
            subject: $validated['subject'],
            message: $validated['message'],
            priority: TicketPriority::tryFrom((string) ($validated['priority'] ?? '')) ?? TicketPriority::Normal,
        );

        $analyser->handle($ticket, $user);

        return redirect()
            ->route('panel.support.show', $ticket)
            ->with('status', __('panel.support.created'));
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        $kbArticles = KbArticle::query()
            ->where('is_published', true)
            ->where(function ($q) use ($ticket): void {
                $words = collect(explode(' ', $ticket->subject))
                    ->filter(fn ($w) => mb_strlen($w) > 3)
                    ->take(5);
                foreach ($words as $word) {
                    $q->orWhere('title', 'like', "%{$word}%");
                }
            })
            ->limit(3)
            ->get(['id', 'title', 'slug']);

        return view('panel.support.show', [
            'ticket'     => $ticket->load(['messages.author', 'events']),
            'kbArticles' => $kbArticles,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, TicketService $tickets): RedirectResponse
    {
        $this->authorize('reply', $ticket);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $user = $request->user();
        abort_if($user === null, 403);

        $tickets->reply($ticket, $user, $validated['message'], isStaff: false);

        return back()->with('status', __('panel.support.replied'));
    }

    public function close(Request $request, SupportTicket $ticket, TicketService $tickets): RedirectResponse
    {
        $this->authorize('reply', $ticket);

        if ($ticket->status === TicketStatus::Closed) {
            return back()->withErrors(['ticket' => __('panel.support.already_closed')]);
        }

        $user = $request->user();
        abort_if($user === null, 403);

        $tickets->changeStatus($ticket, $user, TicketStatus::Closed);

        return redirect()
            ->route('panel.support.index')
            ->with('status', __('panel.support.closed'));
    }

    /**
     * POST /panel/podpora/{ticket}/ai-navrh
     * Returns an AI-drafted reply suggestion for the ticket (JSON).
     * Requires the AI feature to be active (AI_ALLOW_REAL_CALLS=true + Claude integration).
     */
    public function aiSuggest(SupportTicket $ticket, AiAssistantService $assistant): JsonResponse
    {
        $this->authorize('view', $ticket);

        $user = request()->user();
        abort_if($user === null, 403);

        try {
            $lastMessage = $ticket->messages()->latest('id')->value('content') ?? $ticket->subject;

            $run = $assistant->run($user, 'support_draft', [
                'text'    => $lastMessage,
                'subject' => $ticket->subject,
            ]);

            $reply = $run->messages()->where('role', 'assistant')->latest('id')->value('content') ?? '';

            return response()->json(['suggestion' => $reply]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'AI návrh není k dispozici: ' . $e->getMessage()], 503);
        }
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        return $customer;
    }
}
