<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = EmailLog::with('user')->orderByDesc('created_at');

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request): void {
                $q->where('to_address', 'like', '%' . $request->q . '%')
                  ->orWhere('subject', 'like', '%' . $request->q . '%');
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        return view('admin.email-logs', compact('logs'));
    }
}
