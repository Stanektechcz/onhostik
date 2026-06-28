<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use Illuminate\Contracts\View\View;

class KbController extends Controller
{
    public function index(): View
    {
        $locale = app()->getLocale();

        $categories = KbArticle::published()
            ->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale'))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        return view('front.knowledge-base', compact('categories'));
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        $article = KbArticle::published()
            ->where('slug', $slug)
            ->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale'))
            ->firstOr(fn () => KbArticle::published()->where('slug', $slug)->firstOrFail());

        $related = KbArticle::published()
            ->where('id', '!=', $article->id)
            ->where('category', $article->category)
            ->where(fn ($q) => $q->where('locale', $locale)->orWhereNull('locale'))
            ->orderBy('sort_order')
            ->take(5)
            ->get();

        return view('front.knowledge-base-article', compact('article', 'related'));
    }
}
