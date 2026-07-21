<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Jobs\GameServerActionJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin game server actions (Pterodactyl). */
class ServiceGameActionController extends Controller
{
    public function __invoke(Request $request, Service $service): RedirectResponse
    {
        abort_unless($service->provisioning_driver === ProvisioningDriver::Pterodactyl, 404);

        $validated = $request->validate([
            'action' => 'required|in:reinstall',
            // Pterodactyl's reinstall always resets files to the egg defaults —
            // there is no native "keep data". So "preserve" means: take a
            // backup FIRST, then reinstall (audit E81).
            'mode'   => 'nullable|in:wipe,backup_first',
        ]);

        $mode = is_string($validated['mode'] ?? null) ? $validated['mode'] : 'wipe';

        if ($mode === 'backup_first') {
            $policy = \App\Domains\Backups\Models\BackupPolicy::where('service_id', $service->id)->first();

            if ($policy === null) {
                return back()->withErrors([
                    'service' => 'Služba nemá zálohovací politiku — zálohu před reinstalací nelze pořídit. '
                        . 'Nastavte zálohování, nebo zvolte reinstalaci bez zálohy.',
                ]);
            }

            $backup = \App\Domains\Backups\Models\BackupJob::create([
                'backup_policy_id' => $policy->id,
                'service_id'       => $service->id,
                'type'             => 'pre_reinstall',
                'status'           => \App\Domains\Backups\Enums\BackupJobStatus::Pending,
            ]);

            \App\Domains\Backups\Jobs\RunBackupJob::dispatch($backup->id);
        }

        GameServerActionJob::dispatch($service->id, $validated['action'], $request->user()->id);

        activity('provisioning')
            ->performedOn($service)
            ->causedBy($request->user())
            ->withProperties(['action' => $validated['action'], 'mode' => $mode])
            ->log('service.game_action_requested');

        return back()->with('status', $mode === 'backup_first'
            ? 'Záloha byla zařazena a poté proběhne reinstalace game serveru.'
            : 'Požadavek na reinstalaci game serveru byl zařazen. Data budou smazána.');
    }
}
