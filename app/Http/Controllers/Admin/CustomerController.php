<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        return view('admin.customers');
    }
}
