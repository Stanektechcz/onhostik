<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceChangelog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceChangelogController extends Controller
{
    public function index(Request $request): View
    {
        $changelogs = ServiceChangelog::with(['service'])
            ->when($request->service_id, fn ($q) => $q->where('service_id', $request->service_id))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.service-changelogs.index', compact('changelogs'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_id'  => ['required', 'integer'],
            'event_type'  => ['required', 'max:60'],
            'summary'     => ['required', 'max:500'],
            'caused_by'   => ['nullable', 'max:100'],
        ]);

        ServiceChangelog::create($validated);

        return back()->with('status', 'Záznam přidán.');
    }
}
