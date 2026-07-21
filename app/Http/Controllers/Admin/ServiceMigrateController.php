<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Actions\MigrateServiceToServerAction;
use App\Domains\Provisioning\Models\Server;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Moves a service to another server (audit E53) — used to drain a full or
 * failing box.
 *
 * Rebinds the service only; file/database movement stays an operator step
 * against the two panels. The confirmation copy says so, because an admin who
 * believes the data followed automatically would cut over a live site.
 */
class ServiceMigrateController extends Controller
{
    public function __invoke(
        Request $request,
        Service $service,
        MigrateServiceToServerAction $migrate,
    ): RedirectResponse {
        $validated = $request->validate([
            'server_id' => ['required', 'integer', 'exists:servers,id'],
            'reason'    => ['nullable', 'string', 'max:255'],
        ]);

        $target = Server::findOrFail($validated['server_id']);

        try {
            $migrate->execute($service, $target, $validated['reason'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['server_id' => $e->getMessage()]);
        }

        return back()->with(
            'status',
            'Služba byla přeřazena na server ' . $target->name . '. Data je nutné přenést zvlášť.',
        );
    }
}
