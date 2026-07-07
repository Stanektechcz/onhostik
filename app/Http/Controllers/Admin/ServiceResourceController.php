<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Services\ServiceResourceService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceResourceController extends Controller
{
    public function __construct(private readonly ServiceResourceService $resourceService) {}

    public function index(Request $request): View
    {
        $filter = $request->string('filter', 'all')->toString();

        $query = Service::query()
            ->with(['customer', 'product'])
            ->whereNotNull('last_resource_check_at');

        if ($filter === 'alerts') {
            $query->where(function ($q): void {
                $q->whereRaw('cpu_limit_percent IS NOT NULL AND cpu_usage_percent IS NOT NULL AND cpu_usage_percent >= CAST(cpu_limit_percent AS REAL) * resource_alert_threshold / 100')
                  ->orWhereRaw('ram_limit_mb IS NOT NULL AND ram_usage_mb IS NOT NULL AND ram_usage_mb >= CAST(ram_limit_mb AS REAL) * resource_alert_threshold / 100')
                  ->orWhereRaw('disk_limit_gb IS NOT NULL AND disk_usage_gb IS NOT NULL AND disk_usage_gb >= CAST(disk_limit_gb AS REAL) * resource_alert_threshold / 100')
                  ->orWhereRaw('bandwidth_limit_gb IS NOT NULL AND bandwidth_usage_gb IS NOT NULL AND bandwidth_usage_gb >= CAST(bandwidth_limit_gb AS REAL) * resource_alert_threshold / 100');
            });
        }

        $services = $query->latest('last_resource_check_at')->paginate(30)->withQueryString();
        $stats    = $this->resourceService->stats();

        return view('admin.service-resources.index', compact('services', 'stats', 'filter'));
    }

    public function updateLimits(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'cpu_limit_percent'        => ['nullable', 'integer', 'min:1', 'max:100'],
            'ram_limit_mb'             => ['nullable', 'integer', 'min:1'],
            'disk_limit_gb'            => ['nullable', 'integer', 'min:1'],
            'bandwidth_limit_gb'       => ['nullable', 'integer', 'min:1'],
            'resource_alert_threshold' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $service->update([
            'cpu_limit_percent'        => $data['cpu_limit_percent'] ?? null,
            'ram_limit_mb'             => $data['ram_limit_mb'] ?? null,
            'disk_limit_gb'            => $data['disk_limit_gb'] ?? null,
            'bandwidth_limit_gb'       => $data['bandwidth_limit_gb'] ?? null,
            'resource_alert_threshold' => $data['resource_alert_threshold'] ?? 80,
        ]);

        return back()->with('success', 'Limity zdrojů byly uloženy.');
    }

    public function recordUsage(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'cpu_percent'  => ['nullable', 'integer', 'min:0', 'max:100'],
            'ram_mb'       => ['nullable', 'integer', 'min:0'],
            'disk_gb'      => ['nullable', 'integer', 'min:0'],
            'bandwidth_gb' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->resourceService->recordUsage($service, $data);

        return back()->with('success', 'Využití zdrojů bylo zaznamenáno.');
    }
}
