<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $search   = $request->string('q')->toString();
        $category = $request->string('cat')->toString();
        $status   = $request->string('status')->toString(); // 'published' | 'draft' | ''
        $locale   = $request->string('locale')->toString(); // 'cs' | 'en' | ''

        $posts = BlogPost::with('author')
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($status === 'published', fn ($q) => $q->where('is_published', true))
            ->when($status === 'draft', fn ($q) => $q->where('is_published', false))
            ->when($locale !== '', fn ($q) => $q->where('locale', $locale))
            ->latest('published_at')
            ->paginate(20)
            ->withQueryString();

        $categories = BlogPost::query()->distinct()->orderBy('category')->pluck('category')->filter()->values();

        return view('admin.blog.index', [
            'posts'      => $posts,
            'categories' => $categories,
            'search'     => $search,
            'category'   => $category,
            'status'     => $status,
        ]);
    }

    public function show(BlogPost $blog): View
    {
        $related = BlogPost::query()
            ->where('id', '!=', $blog->id)
            ->where('is_published', true)
            ->when($blog->category, fn ($q) => $q->where('category', $blog->category))
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('admin.blog.show', ['post' => $blog, 'related' => $related]);
    }

    public function create(): View
    {
        return view('admin.blog.form', ['post' => new BlogPost()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = Str::slug($validated['title']);
        $validated['author_id'] = auth()->id();

        BlogPost::create($validated);

        return redirect()->route('admin.blog.index')
            ->with('status', 'Příspěvek byl vytvořen.');
    }

    public function edit(BlogPost $blog): View
    {
        return view('admin.blog.form', ['post' => $blog]);
    }

    public function update(Request $request, BlogPost $blog): RedirectResponse
    {
        $validated = $this->validated($request, $blog->id);

        $blog->update($validated);

        return redirect()->route('admin.blog.index')
            ->with('status', 'Příspěvek byl aktualizován.');
    }

    public function destroy(BlogPost $blog): RedirectResponse
    {
        $blog->delete();

        return redirect()->route('admin.blog.index')
            ->with('status', 'Příspěvek byl smazán.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'title'        => 'required|string|max:255',
            'category'     => 'nullable|string|max:100',
            'excerpt'      => 'nullable|string|max:500',
            'body'         => 'nullable|string',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
            'locale'       => 'nullable|string|in:cs,en',
        ]);
    }
}
