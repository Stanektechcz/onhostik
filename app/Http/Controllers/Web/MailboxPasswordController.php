<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Onhost\Domain\Services\Mail\MailboxPasswordLinks;
use Onhost\Platform\Errors\DomainError;

/** The one-time page where a mailbox user sets their password (audit §5i-5): signed link, one use, no session needed. */
final class MailboxPasswordController extends Controller
{
    public function show(Request $request, MailboxPasswordLinks $links, string $token): View
    {
        $row = $links->peek($token);

        return view('mailbox-password', ['token' => $token, 'address' => $row['address'] ?? null, 'expired' => $row === null, 'done' => false, 'error' => null]);
    }

    public function store(Request $request, MailboxPasswordLinks $links, string $token): View|RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'max:72', 'confirmed']]);
        try {
            $links->redeem($token, (string) $data['password']);
        } catch (DomainError $e) {
            return view('mailbox-password', ['token' => $token, 'address' => $links->peek($token)['address'] ?? null, 'expired' => $e->status === 410, 'done' => false, 'error' => $e->getMessage()]);
        }

        return view('mailbox-password', ['token' => $token, 'address' => null, 'expired' => false, 'done' => true, 'error' => null]);
    }
}
