<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\NpsResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NpsSurveyController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $nps = NpsResponse::where('survey_token', $token)->firstOrFail();

        if ($nps->submitted_at !== null) {
            return redirect()->route('front.home')
                ->with('info', 'Váš průzkum byl již odeslán. Děkujeme za zpětnou vazbu!');
        }

        return view('panel.nps.show', ['nps' => $nps]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $nps = NpsResponse::where('survey_token', $token)->firstOrFail();

        if ($nps->submitted_at !== null) {
            return redirect()->route('front.home')
                ->with('info', 'Váš průzkum byl již odeslán.');
        }

        $validated = $request->validate([
            'score'   => 'required|integer|min:0|max:10',
            'comment' => 'nullable|string|max:2000',
        ]);

        $nps->update([
            'score'        => (int) $validated['score'],
            'comment'      => $validated['comment'] ?? null,
            'submitted_at' => now(),
        ]);

        return redirect()->route('front.home')
            ->with('success', 'Děkujeme za vaši zpětnou vazbu!');
    }
}
