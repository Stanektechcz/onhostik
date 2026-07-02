<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Enums\TicketPriority;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use App\Models\User;
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

        $counts = SupportTicket::query()->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->pluck('cnt', 'status');

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
            'filter'          => $status,
            'priorityFilter'  => $priorityFilter,
            'search'          => $search,
            'countOpen'       => (int) ($counts[TicketStatus::Open->value] ?? 0),
            'countPending'    => (int) ($counts[TicketStatus::Pending->value] ?? 0),
            'countAnswered'   => (int) ($counts[TicketStatus::Answered->value] ?? 0),
            'countClosed'     => (int) ($counts[TicketStatus::Closed->value] ?? 0),
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        return view('admin.support-show', [
            'ticket'     => $ticket->load(['customer.user', 'messages.author', 'events.user', 'assignee']),
            'staffUsers' => User::role('admin')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, TicketService $tickets): RedirectResponse
    {
        $validated = $request->validate([
            'message'     => ['required', 'string', 'min:2', 'max:5000'],
            'is_internal' => ['boolean'],
        ]);

        $admin = $request->user();
        abort_if($admin === null, 403);

        if ($request->boolean('is_internal')) {
            $tickets->addInternalNote($ticket, $admin, $validated['message']);

            return back()->with('status', 'Interní poznámka přidána.');
        }

        $tickets->reply($ticket, $admin, $validated['message'], isStaff: true);

        return back()->with('status', __('panel.admin.ticket_replied'));
    }

    public function setSla(Request $request, SupportTicket $ticket, TicketService $tickets): RedirectResponse
    {
        $validated = $request->validate([
            'sla_deadline' => ['nullable', 'date'],
        ]);

        $admin = $request->user();
        abort_if($admin === null, 403);

        $deadline = $validated['sla_deadline'] ? new \DateTime($validated['sla_deadline']) : null;
        $tickets->setSlaDeadline($ticket, $admin, $deadline);

        return back()->with('status', $deadline
            ? 'SLA termín nastaven na ' . $deadline->format('d.m.Y H:i') . '.'
            : 'SLA termín odstraněn.');
    }

    /** Status + priority + assignee transitions, each audited via TicketService. */
    public function update(Request $request, SupportTicket $ticket, TicketService $tickets): RedirectResponse
    {
        $validated = $request->validate([
            'status'      => ['nullable', Rule::enum(TicketStatus::class)],
            'priority'    => ['nullable', Rule::enum(TicketPriority::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $admin = $request->user();
        abort_if($admin === null, 403);

        if (isset($validated['status'])) {
            $tickets->changeStatus($ticket, $admin, TicketStatus::from((string) $validated['status']));
        }

        if (isset($validated['priority'])) {
            $tickets->changePriority($ticket, $admin, TicketPriority::from((string) $validated['priority']));
        }

        if (array_key_exists('assigned_to', $validated)) {
            $newAssignee = $validated['assigned_to'] ? (int) $validated['assigned_to'] : null;
            if ($ticket->assigned_to !== $newAssignee) {
                $ticket->update(['assigned_to' => $newAssignee]);
                activity('support')
                    ->performedOn($ticket)
                    ->causedBy($admin)
                    ->withProperties(['assigned_to' => $newAssignee])
                    ->log('ticket.assigned');
            }
        }

        return back()->with('status', __('panel.admin.ticket_updated'));
    }
}
