<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class SupportController extends Controller
{
    public function index(): View
    {
        return view('panel.support.index');
    }
}
