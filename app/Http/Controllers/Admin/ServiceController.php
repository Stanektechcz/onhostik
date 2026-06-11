<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('admin.services', [
            'services' => Service::query()
                ->with(['customer', 'product', 'server', 'domainRegistration'])
                ->latest('id')
                ->paginate(25),
        ]);
    }
}
