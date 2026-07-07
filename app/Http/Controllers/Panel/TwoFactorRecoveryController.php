<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorRecoveryCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TwoFactorRecoveryController extends Controller
{
    public function show(Request $request): View
    {
        $codes = TwoFactorRecoveryCode::where('user_id', $request->user()->id)
            ->orderBy('id')
            ->get();

        return view('panel.account.2fa-recovery', compact('codes'));
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();

        TwoFactorRecoveryCode::where('user_id', $user->id)->delete();

        $newCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $newCodes[] = [
                'user_id'    => $user->id,
                'code'       => strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
                'used_at'    => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        TwoFactorRecoveryCode::insert($newCodes);

        return back()->with('status', 'Záložní kódy byly obnoveny.');
    }
}
