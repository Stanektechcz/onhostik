<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminAuditTrailController extends Controller
{
    public function index(Request $request): View
    {
        $adminId = $request->input('admin_id');
        $action  = $request->input('action');

        $query = AdminActionLog::with('admin')
            ->latest('created_at');

        if ($adminId) {
            $query->where('admin_user_id', $adminId);
        }

        if ($action) {
            $query->where('action', $action);
        }

        $logs   = $query->paginate(50)->withQueryString();
        $admins = User::role('admin')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.audit-trail', [
            'logs'     => $logs,
            'admins'   => $admins,
            'actions'  => AdminActionLog::ACTIONS,
            'adminId'  => $adminId,
            'action'   => $action,
        ]);
    }
}
