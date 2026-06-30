<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbArticleReview;
use App\Models\KbArticleVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KbVoteController extends Controller
{
    public function vote(Request $request, KbArticle $article): JsonResponse
    {
        $request->validate([
            'helpful' => ['required', 'boolean'],
        ]);

        $userId = $request->user()?->id;
        $ip     = $request->ip();

        $vote = KbArticleVote::updateOrCreate(
            [
                'kb_article_id' => $article->id,
                'user_id'       => $userId,
            ],
            [
                'ip_address' => $ip,
                'is_helpful' => $request->boolean('helpful'),
            ],
        );

        $helpful    = KbArticleVote::where('kb_article_id', $article->id)->where('is_helpful', true)->count();
        $notHelpful = KbArticleVote::where('kb_article_id', $article->id)->where('is_helpful', false)->count();

        return response()->json([
            'success'     => true,
            'helpful'     => $helpful,
            'not_helpful' => $notHelpful,
            'user_vote'   => $request->boolean('helpful') ? 'helpful' : 'not_helpful',
        ]);
    }

    public function review(Request $request, KbArticle $article): RedirectResponse
    {
        $validated = $request->validate([
            'author_name' => ['nullable', 'string', 'max:100'],
            'content'     => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        KbArticleReview::create([
            'kb_article_id' => $article->id,
            'user_id'       => $request->user()?->id,
            'author_name'   => $validated['author_name'] ?? $request->user()->name ?? 'Anonymní',
            'content'       => $validated['content'],
            'is_visible'    => false,
        ]);

        return back()->with('status', 'Vaše recenze byla odeslána a čeká na schválení administrátorem.');
    }
}
