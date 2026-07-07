<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutoSuspendRule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutoSuspendLogController extends Controller
{
    public function index(Request $request): View
    {
        $rules = AutoSuspendRule::orderBy('name')->get();

        $activeCount   = $rules->where('is_active', true)->count();
        $inactiveCount = $rules->where('is_active', false)->count();

        return view('admin.auto-suspend-log.index', compact('rules', 'activeCount', 'inactiveCount'));
    }
}
