<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Domains\Provisioning\Models\ServiceMaintenanceWindow;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceMaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->input('status', 'upcoming');

        $query = ServiceMaintenanceWindow::with(['service.customer.user', 'creator'])
            ->orderBy('scheduled_start', $filter === 'upcoming' ? 'asc' : 'desc');

        if ($filter === 'upcoming') {
            $query->whereIn('status', ['scheduled', 'in_progress'])
                  ->where('scheduled_end', '>', now());
        } elseif ($filter === 'past') {
            $query->whereIn('status', ['completed', 'cancelled']);
        }

        $windows = $query->paginate(30)->withQueryString();

        return view('admin.maintenance.index', compact('windows', 'filter'));
    }

    public function create(): View
    {
        $services = Service::with('customer.user')
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        return view('admin.maintenance.form', [
            'window'   => new ServiceMaintenanceWindow(),
            'services' => $services,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $validated['created_by'] = $request->user()?->id;

        ServiceMaintenanceWindow::create($validated);

        return redirect()->route('admin.maintenance.index')
            ->with('status', 'Okno údržby bylo vytvořeno.');
    }

    public function edit(ServiceMaintenanceWindow $maintenance): View
    {
        $services = Service::with('customer.user')
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get();

        return view('admin.maintenance.form', [
            'window'   => $maintenance,
            'services' => $services,
        ]);
    }

    public function update(Request $request, ServiceMaintenanceWindow $maintenance): RedirectResponse
    {
        $maintenance->update($this->validatedData($request));

        return redirect()->route('admin.maintenance.index')
            ->with('status', 'Okno údržby bylo upraveno.');
    }

    public function destroy(ServiceMaintenanceWindow $maintenance): RedirectResponse
    {
        $maintenance->delete();

        return redirect()->route('admin.maintenance.index')
            ->with('status', 'Okno údržby bylo smazáno.');
    }

    public function start(ServiceMaintenanceWindow $maintenance): RedirectResponse
    {
        $maintenance->update([
            'status'       => 'in_progress',
            'actual_start' => now(),
        ]);

        return back()->with('status', 'Údržba zahájena.');
    }

    public function complete(ServiceMaintenanceWindow $maintenance): RedirectResponse
    {
        $maintenance->update([
            'status'     => 'completed',
            'actual_end' => now(),
        ]);

        return back()->with('status', 'Údržba dokončena.');
    }

    public function cancel(ServiceMaintenanceWindow $maintenance): RedirectResponse
    {
        $maintenance->update(['status' => 'cancelled']);

        return back()->with('status', 'Okno údržby zrušeno.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'title'            => ['required', 'string', 'max:200'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'service_id'       => ['nullable', 'integer', 'exists:services,id'],
            'status'           => ['required', 'in:scheduled,in_progress,completed,cancelled'],
            'scheduled_start'  => ['required', 'date'],
            'scheduled_end'    => ['required', 'date', 'after:scheduled_start'],
            'notify_customers' => ['boolean'],
        ]) + ['notify_customers' => $request->boolean('notify_customers', true)];
    }
}
