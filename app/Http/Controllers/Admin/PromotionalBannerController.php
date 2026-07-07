<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromotionalBanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionalBannerController extends Controller
{
    public function index(): View
    {
        $banners = PromotionalBanner::orderByDesc('created_at')->paginate(20);

        return view('admin.promotional-banners.index', compact('banners'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'         => 'required|max:100',
            'body'          => 'required',
            'cta_text'      => 'nullable|max:60',
            'cta_url'       => 'nullable|url|max:255',
            'type'          => 'required|in:info,success,warning,danger',
            'placement'     => 'required|in:panel_top,panel_dashboard,admin_top',
            'is_active'     => 'boolean',
            'is_dismissible' => 'boolean',
            'starts_at'     => 'nullable|date',
            'ends_at'       => 'nullable|date|after_or_equal:starts_at',
        ]);

        PromotionalBanner::create($validated);

        return back()->with('success', 'Banner vytvořen.');
    }

    public function update(Request $request, PromotionalBanner $promotionalBanner): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $promotionalBanner->update(['is_active' => $validated['is_active']]);

        return back()->with('success', 'Banner aktualizován.');
    }

    public function destroy(PromotionalBanner $promotionalBanner): RedirectResponse
    {
        $promotionalBanner->delete();

        return back()->with('success', 'Banner smazán.');
    }
}
