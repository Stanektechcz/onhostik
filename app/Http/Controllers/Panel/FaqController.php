<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\KbArticle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $kbArticles  = KbArticle::query()->where('is_published', true)->orderBy('sort_order')->limit(4)->get();
        $blogPosts   = BlogPost::query()->where('is_published', true)->latest('published_at')->limit(6)->get();
        $recentTickets = [];

        if (class_exists(\App\Domains\Support\Models\Ticket::class)) {
            $customer = $request->user()?->customer;
            if ($customer) {
                $recentTickets = \App\Domains\Support\Models\Ticket::query()
                    ->where('customer_id', $customer->id)
                    ->latest('updated_at')
                    ->limit(4)
                    ->get();
            }
        }

        $search = $request->string('q')->toString();

        return view('panel.faq', compact('kbArticles', 'blogPosts', 'recentTickets', 'search'));
    }
}
