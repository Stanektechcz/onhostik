<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceResourceUsageController extends Controller
{
    public function index(Request $request): View
    {
        $services = Service::with('customer')
            ->whereNotNull('disk_usage_gb')
            ->whereRaw('disk_usage_gb >= (usage_alert_threshold / 100.0) * disk_limit_gb')
            ->whereNotNull('disk_limit_gb')
            ->orderByRaw('(disk_usage_gb / disk_limit_gb) DESC')
            ->paginate(25);

        return view('admin.service-resource-usage.index', compact('services'));
    }
}
