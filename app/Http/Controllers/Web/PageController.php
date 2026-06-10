<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

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
}
