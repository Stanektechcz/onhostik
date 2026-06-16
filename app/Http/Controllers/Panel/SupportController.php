<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Customer\Models\Customer;
use App\Domains\Support\Enums\TicketPriority;
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
        $customer = $this->customer($request);

        return view('panel.support.index', [
            'tickets' => $customer->supportTickets()->latest('last_reply_at')->paginate(15),
        ]);
    }

    public function store(Request $request, TicketService $tickets): RedirectResponse
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

        return redirect()
            ->route('panel.support.show', $ticket)
            ->with('status', __('panel.support.created'));
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        return view('panel.support.show', [
            'ticket' => $ticket->load(['messages.author', 'events']),
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

    private function customer(Request $request): Customer
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        return $customer;
    }
}
