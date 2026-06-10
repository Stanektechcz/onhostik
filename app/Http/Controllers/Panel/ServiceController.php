<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('panel.services.index');
    }

    public function show(Service $service): View
    {
        $this->authorize('view', $service);

        return view('panel.services.show', ['service' => $service]);
    }
}
