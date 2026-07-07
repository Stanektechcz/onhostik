<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketRatingController extends Controller
{
    public function store(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($ticket->customer_id !== $customer?->id, 403);
        abort_if($ticket->status !== TicketStatus::Closed, 422);
        abort_if($ticket->csat_rated_at !== null, 422);

        $validated = $request->validate([
            'csat_score'   => ['required', 'integer', 'min:1', 'max:5'],
            'csat_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket->update(array_merge($validated, ['csat_rated_at' => now()]));

        return back()->with('status', 'Děkujeme za hodnocení!');
    }
}
