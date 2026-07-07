<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Models\Server;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ServerCapacityController extends Controller
{
    public function index(): View
    {
        $servers = Server::withCount('services as active_services_count')->get();

        $rows = $servers->map(function (Server $server) {
            $meta = $server->capacity_meta ?? [];
            $max  = max(1, (int) $server->max_services);
            $used = (int) $server->active_services_count;

            return [
                'server'    => $server,
                'used'      => $used,
                'max'       => $server->max_services,
                'pct'       => (int) round(($used / $max) * 100),
                'disk_gb'   => $meta['disk_total_gb'] ?? null,
                'ram_gb'    => $meta['ram_total_gb'] ?? null,
                'cpu_cores' => $meta['cpu_cores'] ?? null,
                'alert'     => ($used / $max) >= 0.8,
            ];
        });

        $totalUsed = $rows->sum('used');
        $totalMax  = max(1, $rows->sum('max'));

        return view('admin.server-capacity', compact('rows', 'totalUsed', 'totalMax'));
    }
}
