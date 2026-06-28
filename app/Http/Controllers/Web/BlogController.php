<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $locale     = app()->getLocale();
        $category   = $request->string('kategorie')->toString();

        $posts = BlogPost::published()
            ->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale'))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->latest('published_at')
            ->paginate(9);

        $categories = BlogPost::published()
            ->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale'))
            ->distinct()
            ->pluck('category')
            ->filter();

        return view('front.blog.index', compact('posts', 'categories'));
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        $post = BlogPost::published()
            ->where('slug', $slug)
            ->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale'))
            ->firstOr(fn () => BlogPost::published()->where('slug', $slug)->firstOrFail());

        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->where('category', $post->category)
            ->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale'))
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('front.blog.show', compact('post', 'related'));
    }
}
