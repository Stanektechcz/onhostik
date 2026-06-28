<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $search   = $request->string('q')->toString();
        $category = $request->string('cat')->toString();

        $locale = app()->getLocale();

        $posts = BlogPost::with('author')
            ->where('is_published', true)
            ->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale'))
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        $categories = BlogPost::query()
            ->where('is_published', true)
            ->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale'))
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values();

        return view('panel.blog.index', compact('posts', 'categories', 'search', 'category'));
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();
        $post = BlogPost::where('slug', $slug)->where('is_published', true)
            ->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale'))
            ->firstOr(fn () => BlogPost::where('slug', $slug)->where('is_published', true)->firstOrFail());

        $related = BlogPost::with('author')
            ->where('id', '!=', $post->id)
            ->where('is_published', true)
            ->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale'))
            ->when($post->category, fn ($q) => $q->where('category', $post->category))
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('panel.blog.show', compact('post', 'related'));
    }
}
