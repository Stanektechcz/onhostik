<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteContentController extends Controller
{
    public function index(): View
    {
        $groups = SiteContent::orderBy('group')->orderBy('sort_order')->get()->groupBy('group');

        return view('admin.site-content.index', compact('groups'));
    }

    public function edit(SiteContent $siteContent): View
    {
        return view('admin.site-content.edit', ['item' => $siteContent]);
    }

    public function update(Request $request, SiteContent $siteContent): RedirectResponse
    {
        $validated = $request->validate([
            'value' => 'nullable|string|max:65535',
        ]);

        $siteContent->update($validated);

        return redirect()->route('admin.site-content.index')
            ->with('status', "Obsah \"{$siteContent->label}\" byl uložen.");
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->input('contents', []);

        foreach ($data as $id => $value) {
            SiteContent::where('id', $id)->update(['value' => $value]);
        }

        SiteContent::all()->each(fn ($m) => \Illuminate\Support\Facades\Cache::forget("site_content:{$m->key}"));

        return redirect()->route('admin.site-content.index')
            ->with('status', 'Obsah webu byl uložen.');
    }
}
