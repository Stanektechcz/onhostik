<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceChangelog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceChangelogController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $services = Service::where('customer_id', $customerId)->get();
        $changelogs = collect();

        if ($request->service_id) {
            $service = $services->firstWhere('id', (int) $request->service_id);
            abort_unless($service !== null, 403);

            $changelogs = ServiceChangelog::where('service_id', $service->id)
                ->orderByDesc('created_at')
                ->paginate(20);
        }

        return view('panel.service-changelogs.index', compact('services', 'changelogs'));
    }
}
