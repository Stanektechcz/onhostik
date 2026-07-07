<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerSegmentTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerSegmentTagController extends Controller
{
    public function index(): View
    {
        $tags = CustomerSegmentTag::orderBy('name')->paginate(25);
        return view('admin.customer-segment-tags.index', compact('tags'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'max:80', 'unique:customer_segment_tags,name'],
            'color'       => ['required', 'max:7'],
            'description' => ['nullable', 'max:255'],
        ]);

        CustomerSegmentTag::create($validated);

        return back()->with('status', 'Štítek vytvořen.');
    }

    public function destroy(CustomerSegmentTag $customerSegmentTag): RedirectResponse
    {
        $customerSegmentTag->delete();
        return back()->with('status', 'Štítek smazán.');
    }
}
