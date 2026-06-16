<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logName = $request->string('log')->toString();
        $search  = $request->string('q')->toString();

        return view('admin.audit', [
            'activities' => Activity::query()
                ->with(['causer', 'subject'])
                ->when($logName !== '', fn ($query) => $query->where('log_name', $logName))
                ->when($search !== '', fn ($query) => $query->where('description', 'like', "%{$search}%"))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'filter' => $logName,
        ]);
    }
}
