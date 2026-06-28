<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbArticleReview;
use App\Models\KbArticleVote;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KbController extends Controller
{
    public function index(Request $request): View
    {
        $search   = $request->string('q')->toString();
        $category = $request->string('cat')->toString();

        $articles = KbArticle::query()
            ->where('is_published', true)
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%"))
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->orderBy('category')
            ->orderBy('sort_order')
            ->paginate(30)
            ->withQueryString();

        return view('panel.kb.index', compact('articles', 'search'));
    }

    public function show(string $slug): View
    {
        $article = KbArticle::where('slug', $slug)->where('is_published', true)->firstOrFail();

        $related = KbArticle::query()
            ->where('id', '!=', $article->id)
            ->where('is_published', true)
            ->when($article->category, fn ($q) => $q->where('category', $article->category))
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        $allCategories = KbArticle::query()
            ->where('is_published', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        $voteStats = [
            'helpful'     => KbArticleVote::where('kb_article_id', $article->id)->where('is_helpful', true)->count(),
            'not_helpful' => KbArticleVote::where('kb_article_id', $article->id)->where('is_helpful', false)->count(),
        ];

        $userVote = null;
        if (auth()->check()) {
            $vote = KbArticleVote::where('kb_article_id', $article->id)
                ->where('user_id', auth()->id())
                ->first();
            $userVote = $vote?->is_helpful ? 'helpful' : ($vote ? 'not_helpful' : null);
        }

        $reviews = KbArticleReview::where('kb_article_id', $article->id)
            ->where('is_visible', true)
            ->latest()
            ->get();

        return view('panel.kb.show', compact('article', 'related', 'allCategories', 'voteStats', 'userVote', 'reviews'));
    }
}
