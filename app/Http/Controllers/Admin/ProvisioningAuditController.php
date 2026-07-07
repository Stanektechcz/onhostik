<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ProvisioningAuditLog;
use Illuminate\View\View;

class ProvisioningAuditController extends Controller
{
    public function index(Service $service): View
    {
        $logs = ProvisioningAuditLog::where('service_id', $service->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.provisioning-audit', compact('service', 'logs'));
    }
}
