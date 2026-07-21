<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Communication\Models\ProductUpdate;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for the in-app changelog (audit I130).
 */
class ProductUpdateController extends Controller
{
    public function index(): View
    {
        $updates = ProductUpdate::query()
            ->latest('published_at')
            ->latest('id')
            ->paginate(25);

        return view('admin.product-updates.index', compact('updates'));
    }

    public function create(): View
    {
        return view('admin.product-updates.form', ['update' => new ProductUpdate()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['created_by'] = $request->user()?->id;

        ProductUpdate::create($data);

        return redirect()
            ->route('admin.product-updates.index')
            ->with('status', 'Záznam changelogu byl vytvořen.');
    }

    public function edit(ProductUpdate $productUpdate): View
    {
        return view('admin.product-updates.form', ['update' => $productUpdate]);
    }

    public function update(Request $request, ProductUpdate $productUpdate): RedirectResponse
    {
        $productUpdate->update($this->validated($request));

        return redirect()
            ->route('admin.product-updates.index')
            ->with('status', 'Záznam changelogu byl upraven.');
    }

    public function destroy(ProductUpdate $productUpdate): RedirectResponse
    {
        $productUpdate->delete();

        return redirect()
            ->route('admin.product-updates.index')
            ->with('status', 'Záznam changelogu byl smazán.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'        => ['required', 'string', 'max:160'],
            'body'         => ['required', 'string', 'max:8000'],
            'category'     => ['required', Rule::in(array_keys(ProductUpdate::CATEGORIES))],
            'version'      => ['nullable', 'string', 'max:30'],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['is_published'] = $request->boolean('is_published');

        /*
         | Publishing without a date would make the entry invisible: the
         | customer-facing scope requires published_at to be set and in the
         | past. Defaulting it to now is what the admin means by "publish".
         */
        if ($data['is_published'] && ($data['published_at'] ?? null) === null) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
