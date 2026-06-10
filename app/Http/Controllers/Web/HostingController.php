<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class HostingController extends Controller
{
    public function webhosting(): View
    {
        return view('front.webhosting');
    }

    public function gamehosting(): View
    {
        return view('front.gamehosting');
    }

    public function vps(): View
    {
        return view('front.vps');
    }
}
