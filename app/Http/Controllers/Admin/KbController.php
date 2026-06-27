<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KbController extends Controller
{
    public function index(Request $request): View
    {
        $search   = $request->string('q')->toString();
        $category = $request->string('cat')->toString();
        $status   = $request->string('status')->toString();

        $articles = KbArticle::query()
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($status === 'published', fn ($q) => $q->where('is_published', true))
            ->when($status === 'draft', fn ($q) => $q->where('is_published', false))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->paginate(30)
            ->withQueryString();

        $categories = KbArticle::query()->distinct()->orderBy('category')->pluck('category')->filter()->values();

        return view('admin.kb.index', [
            'articles'   => $articles,
            'categories' => $categories,
            'search'     => $search,
            'category'   => $category,
            'status'     => $status,
        ]);
    }

    public function show(KbArticle $kb): View
    {
        $related = KbArticle::query()
            ->where('id', '!=', $kb->id)
            ->when($kb->category, fn ($q) => $q->where('category', $kb->category))
            ->orderBy('sort_order')
            ->limit(5)
            ->get();

        $allCategories = KbArticle::query()
            ->where('is_published', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        return view('admin.kb.show', [
            'article'       => $kb,
            'related'       => $related,
            'allCategories' => $allCategories,
        ]);
    }

    public function create(): View
    {
        return view('admin.kb.form', ['article' => new KbArticle()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = Str::slug($validated['title']);

        KbArticle::create($validated);

        return redirect()->route('admin.kb.index')
            ->with('status', 'Článek byl vytvořen.');
    }

    public function edit(KbArticle $kb): View
    {
        return view('admin.kb.form', ['article' => $kb]);
    }

    public function update(Request $request, KbArticle $kb): RedirectResponse
    {
        $validated = $this->validated($request);

        $kb->update($validated);

        return redirect()->route('admin.kb.index')
            ->with('status', 'Článek byl aktualizován.');
    }

    public function destroy(KbArticle $kb): RedirectResponse
    {
        $kb->delete();

        return redirect()->route('admin.kb.index')
            ->with('status', 'Článek byl smazán.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'        => 'required|string|max:255',
            'category'     => 'nullable|string|max:100',
            'excerpt'      => 'nullable|string|max:500',
            'body'         => 'nullable|string',
            'is_published' => 'boolean',
            'sort_order'   => 'integer|min:0',
        ]);
    }
}
