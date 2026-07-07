<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Customer\Models\Customer;
use App\Domains\Customer\Models\CustomerTag;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerTagController extends Controller
{
    public function index(Request $request): View
    {
        $tagId     = $request->integer('tag');
        $tags      = CustomerTag::orderBy('name')->get();
        $customers = collect();

        if ($tagId > 0) {
            $activeTag = $tags->firstWhere('id', $tagId);
            $customers = Customer::query()
                ->whereHas('tags', fn ($q) => $q->where('customer_tags.id', $tagId))
                ->with(['user', 'tags'])
                ->paginate(30)
                ->withQueryString();
        }

        return view('admin.customer-tags.index', compact('tags', 'customers', 'tagId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:60', 'unique:customer_tags,name'],
            'color'       => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        CustomerTag::create([
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']),
            'color'       => $data['color'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('admin.customer-tags.index')
            ->with('success', 'Štítek byl vytvořen.');
    }

    public function update(Request $request, CustomerTag $customerTag): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:60', 'unique:customer_tags,name,' . $customerTag->id],
            'color'       => ['required', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $customerTag->update([
            'name'        => $data['name'],
            'slug'        => Str::slug($data['name']),
            'color'       => $data['color'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('admin.customer-tags.index')
            ->with('success', 'Štítek byl aktualizován.');
    }

    public function destroy(CustomerTag $customerTag): RedirectResponse
    {
        $customerTag->delete();

        return redirect()->route('admin.customer-tags.index')
            ->with('success', 'Štítek byl smazán.');
    }

    public function assign(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'tag_id' => ['required', 'exists:customer_tags,id'],
        ]);

        $customer->tags()->syncWithoutDetaching([$data['tag_id'] => [
            'assigned_by' => $request->user()?->id,
        ]]);

        $this->syncCount((int) $data['tag_id']);

        return back()->with('success', 'Štítek byl přiřazen.');
    }

    public function detach(Request $request, Customer $customer, CustomerTag $customerTag): RedirectResponse
    {
        $customer->tags()->detach($customerTag->id);

        $this->syncCount($customerTag->id);

        return back()->with('success', 'Štítek byl odebrán.');
    }

    private function syncCount(int $tagId): void
    {
        CustomerTag::where('id', $tagId)->update([
            'customer_count' => Customer::whereHas('tags', fn ($q) => $q->where('customer_tags.id', $tagId))->count(),
        ]);
    }
}
