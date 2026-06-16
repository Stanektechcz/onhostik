<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use Illuminate\Contracts\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::published()->latest('published_at')->paginate(9);
        $recent = BlogPost::published()->latest('published_at')->take(4)->get();
        $categories = BlogPost::published()->distinct()->pluck('category');

        return view('front.blog.index', compact('posts', 'recent', 'categories'));
    }

    public function show(string $slug): View
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();
        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->where('category', $post->category)
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('front.blog.show', compact('post', 'related'));
    }
}
