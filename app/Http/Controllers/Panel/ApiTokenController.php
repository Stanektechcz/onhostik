<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        $tokens = $request->user()?->tokens()->latest()->get() ?? collect();

        return view('panel.api-tokens', compact('tokens'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:80'],
            'abilities'   => ['sometimes', 'array'],
            'abilities.*' => ['string', 'in:' . implode(',', TokenController::ALLOWED_ABILITIES)],
        ]);

        $user = $request->user();
        abort_if($user === null, 403);

        if ($user->tokens()->count() >= 5) {
            return back()->withErrors(['name' => 'Maximální počet tokenů (5) byl dosažen.']);
        }

        $abilities = $validated['abilities'] ?? ['read'];
        if (! in_array('read', $abilities, true)) {
            $abilities[] = 'read';
        }

        $plainToken = $user->createToken($validated['name'], $abilities)->plainTextToken;

        return back()->with('new_token', $plainToken)
                     ->with('status', 'API token byl vytvořen. Zkopírujte ho — nebude znovu zobrazen.');
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()?->tokens()->where('id', $tokenId)->delete();

        return back()->with('status', 'API token byl odstraněn.');
    }
}
