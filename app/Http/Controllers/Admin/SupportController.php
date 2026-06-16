<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $status         = $request->string('status')->toString();
        $priorityFilter = $request->string('priority')->toString();
        $search         = $request->string('q')->toString();

        return view('admin.support', [
            'tickets' => SupportTicket::query()
                ->with(['customer.user', 'assignee'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->when($priorityFilter !== '', fn ($q) => $q->where('priority', $priorityFilter))
                ->when($search !== '', function ($q) use ($search): void {
                    $q->where(function ($inner) use ($search): void {
                        $inner->where('subject', 'like', "%{$search}%")
                              ->orWhereHas('customer', fn ($c) => $c->where('email', 'like', "%{$search}%")
                                  ->orWhere('company_name', 'like', "%{$search}%"));
                    });
                })
                ->latest('last_reply_at')
                ->paginate(25)
                ->withQueryString(),
            'filter'         => $status,
            'priorityFilter' => $priorityFilter,
            'search'         => $search,
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        return view('admin.support-show', [
            'ticket' => $ticket->load(['customer.user', 'messages.author', 'events.user']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, TicketService $tickets): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $admin = $request->user();
        abort_if($admin === null, 403);

        $tickets->reply($ticket, $admin, $validated['message'], isStaff: true);

        return back()->with('status', __('panel.admin.ticket_replied'));
    }

    /** Status + priority transitions, each audited via TicketService. */
    public function update(Request $request, SupportTicket $ticket, TicketService $tickets): RedirectResponse
    {
        $validated = $request->validate([
            'status'   => ['nullable', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
        ]);

        $admin = $request->user();
        abort_if($admin === null, 403);

        if (isset($validated['status'])) {
            $tickets->changeStatus($ticket, $admin, TicketStatus::from((string) $validated['status']));
        }

        if (isset($validated['priority'])) {
            $tickets->changePriority($ticket, $admin, TicketPriority::from((string) $validated['priority']));
        }

        return back()->with('status', __('panel.admin.ticket_updated'));
    }
}
