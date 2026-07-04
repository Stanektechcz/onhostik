<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailDripEnrollment;
use App\Models\EmailDripSequence;
use App\Models\EmailDripStep;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DripSequenceController extends Controller
{
    public function index(): View
    {
        $sequences = EmailDripSequence::withCount(['steps', 'enrollments'])->latest()->get();

        return view('admin.drip.index', compact('sequences'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:150'],
            'trigger_event' => ['required', 'string', 'in:manual,signup,service_created'],
            'description'   => ['nullable', 'string', 'max:500'],
        ]);

        $sequence = EmailDripSequence::create([
            ...$validated,
            'is_active'  => false,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.drip.show', $sequence)
            ->with('status', 'Sekvence "' . $sequence->name . '" byla vytvořena.');
    }

    public function show(EmailDripSequence $drip): View
    {
        $drip->loadMissing('steps');

        $activeEnrollments    = $drip->enrollments()->whereNull('completed_at')->whereNull('unsubscribed_at')->count();
        $completedEnrollments = $drip->enrollments()->whereNotNull('completed_at')->count();

        return view('admin.drip.show', compact('drip', 'activeEnrollments', 'completedEnrollments'));
    }

    public function toggleActive(EmailDripSequence $drip): RedirectResponse
    {
        $drip->update(['is_active' => ! $drip->is_active]);

        $msg = $drip->is_active ? 'Sekvence aktivována.' : 'Sekvence deaktivována.';

        return back()->with('status', $msg);
    }

    public function storeStep(Request $request, EmailDripSequence $drip): RedirectResponse
    {
        $validated = $request->validate([
            'subject'    => ['required', 'string', 'max:255'],
            'body_html'  => ['required', 'string'],
            'delay_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $sortOrder = $drip->steps()->max('sort_order') + 1;

        EmailDripStep::create([
            ...$validated,
            'drip_sequence_id' => $drip->id,
            'sort_order'       => $sortOrder,
        ]);

        return back()->with('status', 'Krok přidán.');
    }

    public function destroyStep(EmailDripSequence $drip, EmailDripStep $step): RedirectResponse
    {
        abort_if($step->drip_sequence_id !== $drip->id, 404);
        $step->delete();

        return back()->with('status', 'Krok smazán.');
    }

    public function enroll(Request $request, EmailDripSequence $drip): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'name'  => ['nullable', 'string', 'max:150'],
        ]);

        $firstStep = $drip->steps()->orderBy('sort_order')->first();

        EmailDripEnrollment::updateOrCreate(
            ['drip_sequence_id' => $drip->id, 'email' => $validated['email']],
            [
                'name'            => $validated['name'] ?? null,
                'next_step_index' => 0,
                'next_send_at'    => $firstStep !== null ? now()->addDays((int) $firstStep->delay_days) : null,
                'completed_at'    => null,
                'unsubscribed_at' => null,
            ],
        );

        return back()->with('status', 'Kontakt ' . $validated['email'] . ' zapsán do sekvence.');
    }

    public function destroy(EmailDripSequence $drip): RedirectResponse
    {
        $name = $drip->name;
        $drip->delete();

        return redirect()
            ->route('admin.drip.index')
            ->with('status', 'Sekvence "' . $name . '" byla smazána.');
    }
}
