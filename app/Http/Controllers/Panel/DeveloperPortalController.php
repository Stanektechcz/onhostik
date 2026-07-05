<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Developer\Models\OAuthApplication;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeveloperPortalController extends Controller
{
    public function index(Request $request): View
    {
        $user     = $request->user();
        $customer = $user?->customer;

        $tokens   = $user?->tokens()->latest()->get() ?? collect();
        $oauthApps = $customer
            ? OAuthApplication::where('customer_id', $customer->id)->latest()->get()
            : collect();

        $abilities = TokenController::ALLOWED_ABILITIES;

        return view('panel.developer-portal.index', compact('tokens', 'oauthApps', 'abilities'));
    }

    public function storeOAuthApp(Request $request): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null, 403);

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'redirect_uris' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($customer->oauthApplications()->count() >= 10) {
            return back()->withErrors(['name' => 'Maximální počet aplikací (10) byl dosažen.']);
        }

        $secret = Str::random(64);

        OAuthApplication::create([
            'customer_id'       => $customer->id,
            'name'              => $validated['name'],
            'client_id'         => Str::uuid()->toString(),
            'client_secret_hash' => bcrypt($secret),
            'redirect_uris'     => $this->parseRedirectUris($validated['redirect_uris'] ?? ''),
            'is_active'         => true,
        ]);

        return back()
            ->with('new_secret', $secret)
            ->with('status', 'OAuth aplikace byla vytvořena. Zkopírujte client secret — nebude znovu zobrazen.');
    }

    public function destroyOAuthApp(Request $request, OAuthApplication $oauthApp): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null || $oauthApp->customer_id !== $customer->id, 403);

        $oauthApp->delete();

        return back()->with('status', 'OAuth aplikace byla odstraněna.');
    }

    public function regenSecret(Request $request, OAuthApplication $oauthApp): RedirectResponse
    {
        $customer = $request->user()?->customer;
        abort_if($customer === null || $oauthApp->customer_id !== $customer->id, 403);

        $secret = Str::random(64);
        $oauthApp->update(['client_secret_hash' => bcrypt($secret)]);

        return back()
            ->with('new_secret', $secret)
            ->with('status', 'Client secret byl obnoven. Zkopírujte ho — nebude znovu zobrazen.');
    }

    /** @return list<string> */
    private function parseRedirectUris(string $raw): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/[\n,]+/', $raw) ?: []),
        ));
    }
}
