<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceHealthIncident;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ServiceHealthIncidentController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $request->user()->customer?->id;
        abort_unless($customerId !== null, 403);

        $incidents = ServiceHealthIncident::whereIn(
            'service_id',
            Service::where('customer_id', $customerId)->select('id')
        )
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('panel.service-health-incidents.index', compact('incidents'));
    }
}
