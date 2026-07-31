<?php

declare(strict_types=1);

namespace App\Http\Controllers\OAuth;

use App\Domains\Developer\Models\OAuthApplication;
use App\Domains\Developer\Services\OAuthException;
use App\Domains\Developer\Services\OAuthGrantService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * OAuth2 authorization endpoint (the consent screen). Runs behind the web
 * session guard — the user must be logged in to grant access on their own
 * behalf.
 *
 * Security: a request whose client_id is unknown or whose redirect_uri is not
 * pre-registered is rejected outright (a hard error page), never redirected —
 * so an attacker cannot use this endpoint as an open redirector or smuggle a
 * code to an unregistered URI. Only once the redirect_uri is proven registered
 * do recoverable errors (denied, bad scope) get sent back to it per RFC 6749.
 */
final class AuthorizationController extends Controller
{
    public function __construct(private readonly OAuthGrantService $grants) {}

    public function show(Request $request): View|RedirectResponse
    {
        [$app, $redirectUri] = $this->resolveClient($request);

        if ($request->query('response_type') !== 'code') {
            return $this->error($redirectUri, 'unsupported_response_type', $request->query('state'));
        }

        try {
            $scopes = $this->grants->resolveScopes($this->stringOrNull($request->query('scope')));
        } catch (OAuthException $e) {
            return $this->error($redirectUri, $e->error, $request->query('state'));
        }

        return view('oauth.authorize', [
            'app'                 => $app,
            'scopes'              => $scopes,
            'scopeLabels'         => $this->scopeLabels(),
            'redirectUri'         => $redirectUri,
            'state'               => $this->stringOrNull($request->query('state')),
            'codeChallenge'       => $this->stringOrNull($request->query('code_challenge')),
            'codeChallengeMethod' => $this->stringOrNull($request->query('code_challenge_method')),
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        [$app, $redirectUri] = $this->resolveClient($request);

        $user = $request->user();
        abort_if($user === null, 403);

        try {
            $scopes = $this->grants->resolveScopes($this->stringOrNull($request->input('scope')));
        } catch (OAuthException $e) {
            return $this->error($redirectUri, $e->error, $request->input('state'));
        }

        $code = $this->grants->issueCode(
            $app,
            $user,
            $redirectUri,
            $scopes,
            $this->stringOrNull($request->input('code_challenge')),
            $this->stringOrNull($request->input('code_challenge_method')),
        );

        return redirect()->away($this->buildUrl($redirectUri, array_filter([
            'code'  => $code,
            'state' => $this->stringOrNull($request->input('state')),
        ], fn ($v) => $v !== null)));
    }

    public function deny(Request $request): RedirectResponse
    {
        [, $redirectUri] = $this->resolveClient($request);

        return $this->error($redirectUri, 'access_denied', $request->input('state'));
    }

    /**
     * Resolve and hard-validate the client + redirect URI. Aborts (never
     * redirects) on an unknown client or unregistered redirect URI.
     *
     * @return array{0: OAuthApplication, 1: string}
     */
    private function resolveClient(Request $request): array
    {
        $clientId    = (string) ($request->input('client_id') ?? '');
        $redirectUri = (string) ($request->input('redirect_uri') ?? '');

        $app = OAuthApplication::where('client_id', $clientId)->where('is_active', true)->first();

        abort_if($app === null, 400, 'Neplatný nebo neaktivní client_id.');
        abort_unless($this->grants->redirectUriAllowed($app, $redirectUri), 400, 'redirect_uri není u aplikace zaregistrováno.');

        return [$app, $redirectUri];
    }

    private function error(string $redirectUri, string $error, mixed $state): RedirectResponse
    {
        return redirect()->away($this->buildUrl($redirectUri, array_filter([
            'error' => $error,
            'state' => is_string($state) ? $state : null,
        ], fn ($v) => $v !== null)));
    }

    /** @param array<string, string> $params */
    private function buildUrl(string $base, array $params): string
    {
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . http_build_query($params);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @return array<string, string> */
    private function scopeLabels(): array
    {
        return [
            'read'          => 'Číst data vašeho účtu (služby, faktury, domény, kredit)',
            'write:tickets' => 'Vytvářet a odpovídat na tikety podpory',
            'write:credit'  => 'Dobíjet kredit vytvořením faktury',
            'write:orders'  => 'Vytvářet nové objednávky',
            'manage:tokens' => 'Spravovat API tokeny',
        ];
    }
}
