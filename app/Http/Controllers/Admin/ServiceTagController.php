<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use App\Models\ServiceTag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceTagController extends Controller
{
    public function index(): View
    {
        return view('admin.service-tags.index', [
            'tags' => ServiceTag::query()->withCount('services')->latest('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'min:2', 'max:50', 'unique:service_tags,name'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        ServiceTag::create([
            'name'  => $validated['name'],
            'color' => $validated['color'] ?? '#6c757d',
        ]);

        return back()->with('status', 'Štítek byl vytvořen.');
    }

    public function destroy(ServiceTag $serviceTag): RedirectResponse
    {
        $serviceTag->delete();

        return back()->with('status', 'Štítek byl smazán.');
    }

    public function assign(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'tag_id' => ['required', 'integer', 'exists:service_tags,id'],
        ]);

        $service->tags()->syncWithoutDetaching([$validated['tag_id']]);

        return back()->with('status', 'Štítek přiřazen.');
    }

    public function detach(Service $service, ServiceTag $serviceTag): RedirectResponse
    {
        $service->tags()->detach($serviceTag->id);

        return back()->with('status', 'Štítek odebrán.');
    }
}
