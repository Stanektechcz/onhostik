<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two links the platform mails that the prototype has no view for: setting a new password from a reset / guest
 * account link (`/obnova-hesla?token=`) and confirming an e-mail address (`/overeni-emailu?token=`). Without a token
 * `/obnova-hesla` stays the prototype's "request a reset" card. Both pages are small server-rendered forms in the
 * design system that call the same API the surfaces use (`/v1/auth/password/reset/confirm`, `/v1/auth/verify-email`).
 */
final class AuthPagesController extends Controller
{
    public function resetPassword(Request $request, SurfaceController $surfaces): Response|View
    {
        $token = trim((string) $request->query('token', ''));
        if ($token === '' || strlen($token) > 120) {
            return $surfaces->public($request, 'obnova-hesla');
        }

        return view('auth.reset', ['token' => $token, 'stylesheet' => $this->stylesheet()]);
    }

    public function verifyEmail(Request $request): View
    {
        return view('auth.verify', ['token' => mb_substr(trim((string) $request->query('token', '')), 0, 120), 'stylesheet' => $this->stylesheet()]);
    }

    private function stylesheet(): ?string
    {
        $ds = glob(base_path('apps/surfaces/_ds/*/styles.css')) ?: [];

        return $ds === [] ? null : '/surfaces/'.str_replace('\\', '/', substr($ds[0], strlen(base_path('apps/surfaces')) + 1));
    }
}
