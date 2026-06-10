<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class AccountController extends Controller
{
    public function profile(): View
    {
        return view('panel.account.profile');
    }

    public function billing(): View
    {
        return view('panel.account.billing');
    }

    public function security(): View
    {
        return view('panel.account.security');
    }
}
