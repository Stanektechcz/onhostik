<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoginHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $logins = DB::table('user_login_history')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('panel.account.login-history', compact('logins'));
    }
}
