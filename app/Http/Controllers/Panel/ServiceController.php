<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user()?->customer;

        abort_if($customer === null, 403, 'No customer profile attached to this account.');

        return view('panel.services.index', [
            'services' => $customer->services()->with('product')->latest('id')->paginate(15),
        ]);
    }

    public function show(Service $service): View
    {
        $this->authorize('view', $service);

        return view('panel.services.show', [
            'service' => $service->load([
                'product',
                'domainRegistration',
                'provisioningTasks' => fn ($query) => $query->latest('id'),
            ]),
        ]);
    }
}
