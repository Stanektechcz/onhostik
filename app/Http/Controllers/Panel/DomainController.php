<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\DomainRegistration;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DomainController extends Controller
{
    public function index(): View
    {
        return view('panel.domains.index');
    }

    public function show(DomainRegistration $domain): View
    {
        $this->authorize('view', $domain);

        return view('panel.domains.show', ['domain' => $domain]);
    }
}
