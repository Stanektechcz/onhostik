<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domains\Support\Services\TicketService;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\KbArticle;
use App\Models\Subscriber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

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

    public function contactSend(Request $request, TicketService $tickets): RedirectResponse
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'email'   => ['required', 'email', 'max:180'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $user     = $request->user();
        $customer = $user?->customer;

        if ($user !== null && $customer !== null) {
            // Logged-in customer → create a real support ticket
            $ticket = $tickets->open(
                customer: $customer,
                author: $user,
                subject: $data['subject'],
                message: $data['message'],
                department: 'sales',
            );

            return redirect()->route('panel.support.show', $ticket)
                ->with('status', 'Váš dotaz byl přijat jako ticket #' . $ticket->id . '.');
        }

        // Guest visitor → send plain e-mail to admin
        $adminEmail = config('mail.admin_address', config('mail.from.address', 'info@onhost.cz'));

        try {
            Mail::raw(
                "Jméno: {$data['name']}\nE-mail: {$data['email']}\nPředmět: {$data['subject']}\n\n{$data['message']}",
                function ($msg) use ($data, $adminEmail): void {
                    $msg->to($adminEmail)
                        ->replyTo($data['email'], $data['name'])
                        ->subject("[Kontakt] {$data['subject']}");
                }
            );
        } catch (\Throwable) {
            // Never block the form on mail failure
        }

        return redirect()->route('front.contact')
            ->with('contact_success', true);
    }

    public function newsletterSubscribe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email'  => ['required', 'email', 'max:180'],
            'locale' => ['nullable', 'string', 'in:cs,en'],
        ]);

        $email = strtolower($data['email']);

        $existing = Subscriber::where('email', $email)->first();

        if ($existing === null) {
            Subscriber::create([
                'email'        => $email,
                'locale'       => $data['locale'] ?? app()->getLocale(),
                'source'       => 'website',
                'confirmed_at' => now(),
                'is_active'    => true,
            ]);
        } elseif (! $existing->is_active) {
            $existing->update(['is_active' => true, 'unsubscribed_at' => null]);
        }

        return back()->with('newsletter_success', true);
    }
}
