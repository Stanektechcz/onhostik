<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbArticleComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KbArticleCommentController extends Controller
{
    public function store(Request $request, KbArticle $article): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        KbArticleComment::create([
            'kb_article_id' => $article->id,
            'user_id'       => $request->user()->id,
            'body'          => $validated['body'],
            'is_approved'   => false,
        ]);

        return back()->with('status', 'Komentář odeslán ke schválení.');
    }
}
