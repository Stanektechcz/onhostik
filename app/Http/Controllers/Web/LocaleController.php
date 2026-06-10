<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    private const SUPPORTED = ['cs', 'en'];

    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, self::SUPPORTED, true), 404);

        $request->session()->put('locale', $locale);

        if ($user = $request->user()) {
            $user->update(['locale' => $locale]);
        }

        return back(fallback: route('front.home'));
    }
}
