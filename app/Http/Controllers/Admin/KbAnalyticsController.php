<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use Illuminate\Contracts\View\View;

class KbAnalyticsController extends Controller
{
    public function index(): View
    {
        $topArticles = KbArticle::query()
            ->where('is_published', true)
            ->orderByDesc('views_count')
            ->limit(20)
            ->get(['id', 'title', 'slug', 'category', 'views_count']);

        $totalViews = KbArticle::sum('views_count');

        return view('admin.kb-analytics', compact('topArticles', 'totalViews'));
    }
}
