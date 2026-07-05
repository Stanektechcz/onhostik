<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Monitoring\Models\SlaIncident;
use App\Domains\Monitoring\Services\SlaService;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SlaIncidentController extends Controller
{
    public function __construct(private readonly SlaService $slaService) {}

    public function index(Request $request): View
    {
        $query = SlaIncident::with(['service.customer'])
            ->orderByDesc('started_at');

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'open') {
                $query->whereIn('status', ['open', 'investigating']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity')->toString());
        }

        $incidents = $query->paginate(20)->withQueryString();
        $stats     = $this->slaService->incidentStats();

        return view('admin.sla-incidents.index', compact('incidents', 'stats'));
    }

    public function create(): View
    {
        $services = Service::orderBy('name')->get();
        return view('admin.sla-incidents.create', compact('services'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_id'  => 'required|exists:services,id',
            'title'       => 'required|string|max:200',
            'severity'    => 'required|in:critical,high,medium,low',
            'description' => 'nullable|string',
        ]);

        /** @var Service $service */
        $service = Service::findOrFail($data['service_id']);

        $this->slaService->openIncident(
            $service,
            $data['title'],
            $data['severity'],
            $data['description'] ?? null,
            $request->user(),
        );

        return redirect()->route('admin.sla-incidents.index')->with('status', 'Incident otevřen.');
    }

    public function show(SlaIncident $slaIncident): View
    {
        $slaIncident->load(['service.customer', 'updates.creator', 'creator']);
        return view('admin.sla-incidents.show', ['incident' => $slaIncident]);
    }

    public function addUpdate(Request $request, SlaIncident $slaIncident): RedirectResponse
    {
        $data = $request->validate([
            'message' => 'required|string',
            'status'  => 'required|in:investigating,identified,monitoring,resolved',
        ]);

        if ($data['status'] === 'resolved') {
            $this->slaService->resolve($slaIncident, $data['message'], $request->user());
        } else {
            $this->slaService->addUpdate($slaIncident, $data['message'], $data['status'], $request->user());
            $slaIncident->update(['status' => 'investigating']);
        }

        return redirect()->route('admin.sla-incidents.show', $slaIncident)
            ->with('status', 'Aktualizace přidána.');
    }
}
