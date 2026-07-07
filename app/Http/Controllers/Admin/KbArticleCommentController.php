<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbArticleComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KbArticleCommentController extends Controller
{
    public function index(): View
    {
        $pending = KbArticleComment::with('article', 'author')
            ->where('is_approved', false)
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.kb-comments.index', compact('pending'));
    }

    public function approve(KbArticleComment $comment): RedirectResponse
    {
        $comment->update(['is_approved' => true, 'approved_at' => now()]);

        return back()->with('status', 'Komentář schválen.');
    }

    public function destroy(KbArticleComment $comment): RedirectResponse
    {
        $comment->delete();

        return back()->with('status', 'Komentář smazán.');
    }
}
