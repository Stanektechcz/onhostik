<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\KbArticle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PageController extends Controller
{
    public function contact(): View
    {
        return view('front.contact');
    }

    public function about(): View
    {
        return view('front.about');
    }

    public function faq(): View
    {
        return view('front.faq');
    }

    public function legal(): View
    {
        return view('front.legal');
    }

    public function gdpr(): View
    {
        return view('front.gdpr');
    }

    public function cookies(): View
    {
        return view('front.cookies');
    }

    public function sla(): View
    {
        return view('front.sla');
    }

    public function refundPolicy(): View
    {
        return view('front.refund-policy');
    }

    public function aiFeature(): View
    {
        return view('front.features.ai');
    }

    public function monitoringFeature(): View
    {
        return view('front.features.monitoring');
    }

    public function backupsFeature(): View
    {
        return view('front.features.backups');
    }

    public function builder(): View
    {
        return view('front.builder');
    }

    public function support(): View
    {
        return view('front.support');
    }

    public function knowledgeBase(): View
    {
        return view('front.knowledge-base');
    }

    public function ssl(): View
    {
        return view('front.ssl');
    }

    public function reseller(): View
    {
        return view('front.reseller');
    }

    public function developer(): View
    {
        return view('front.developer');
    }

    public function ddos(): View
    {
        return view('front.ddos');
    }

    public function colocation(): View
    {
        return view('front.colocation');
    }

    public function emailSecurity(): View
    {
        return view('front.email-security');
    }

    public function database(): View
    {
        return view('front.database');
    }

    public function datacenter(): View
    {
        return view('front.datacenter');
    }

    public function sitemap(): Response
    {
        $staticUrls = [
            '/', '/webhosting', '/wordpress-hosting', '/managed-hosting', '/gamehosting',
            '/vps', '/mailhosting', '/dedikovane-servery', '/ssl-certifikaty',
            '/reseller-hosting', '/developer', '/ddos-ochrana', '/kolokace',
            '/domeny', '/znalostni-baze', '/blog', '/podpora', '/kontakt',
            '/o-nas', '/faq', '/obchodni-podminky', '/gdpr', '/cookies',
            '/sla', '/refundace', '/website-builder',
            '/funkce/ai-asistent', '/funkce/monitoring', '/funkce/zalohy',
            '/email-security', '/databaze', '/datacenter',
        ];

        $blogPosts = BlogPost::published()->get(['slug', 'updated_at']);
        $kbArticles = KbArticle::published()->get(['slug', 'updated_at']);

        $content = view('front.sitemap', compact('staticUrls', 'blogPosts', 'kbArticles'))->render();

        return response($content, 200, ['Content-Type' => 'application/xml']);
    }

    public function contactSend(Request $request): RedirectResponse
    {
        $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'email'   => ['required', 'email', 'max:180'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        return redirect()->route('front.contact')
            ->with('contact_success', true);
    }
}
