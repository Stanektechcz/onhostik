<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Server;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ServerController extends Controller
{
    public function index(): View
    {
        return view('admin.servers', [
            'servers' => Server::query()
                ->withCount('services')
                ->orderBy('name')
                ->paginate(25),
        ]);
    }
}
