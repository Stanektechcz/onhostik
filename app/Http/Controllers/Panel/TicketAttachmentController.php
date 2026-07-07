<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use App\Models\TicketAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketAttachmentController extends Controller
{
    public function store(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $customer = $request->user()->customer;
        abort_if($ticket->customer_id !== $customer?->id, 403);

        $validated = $request->validate([
            'attachment' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,gif,zip,txt,doc,docx'],
        ]);

        $file = $validated['attachment'];
        $path = $file->store('ticket-attachments/' . $ticket->id, 'local');

        TicketAttachment::create([
            'ticket_id'     => $ticket->id,
            'user_id'       => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path'   => (string) $path,
            'mime_type'     => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes'    => $file->getSize(),
        ]);

        return back()->with('status', 'Příloha nahrána.');
    }

    public function destroy(Request $request, TicketAttachment $attachment): RedirectResponse
    {
        abort_if($attachment->user_id !== $request->user()->id, 403);

        \Illuminate\Support\Facades\Storage::disk('local')->delete($attachment->stored_path);
        $attachment->delete();

        return back()->with('status', 'Příloha smazána.');
    }
}
