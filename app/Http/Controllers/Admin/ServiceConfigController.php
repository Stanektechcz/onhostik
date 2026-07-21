<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Jobs\WebhostingConfigActionJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Webhosting configuration actions on a service — databases, FTP accounts,
 * cron jobs, SSL and disk quota, mirroring what aaPanel itself offers.
 *
 * Every action is queued through WebhostingConfigActionJob, which routes to
 * AapanelClient's gated write methods. With AAPANEL_ALLOW_REAL_WRITES closed
 * the call is simulated and recorded as dry_run — nothing here opens the gate.
 */
class ServiceConfigController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        if ($service->provisioning_driver !== ProvisioningDriver::AAPanel) {
            return back()->withErrors(['config' => 'Tato služba není hostovaná na aaPanelu.']);
        }

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:' . implode(',', array_keys(WebhostingConfigActionJob::ACTIONS))],

            // create/delete database
            'name'     => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-\.]+$/'],
            'username' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/'],

            // create ftp
            'path' => ['nullable', 'string', 'max:255'],

            // cron
            'command' => ['nullable', 'string', 'max:500'],
            'type'    => ['nullable', 'string', 'in:day,hour,minute-n,day-n,week,month'],
            'hour'    => ['nullable', 'integer', 'min:0', 'max:23'],
            'minute'  => ['nullable', 'integer', 'min:0', 'max:59'],
            'cron_id' => ['nullable', 'string', 'max:32'],

            // quota
            'quota_mb' => ['nullable', 'integer', 'min:0', 'max:1024000'],

            // mailbox — the password is passed to the panel and never stored
            'password' => ['nullable', 'string', 'min:10', 'max:128'],
        ]);

        $action = (string) $validated['action'];

        if ($missing = $this->missingFor($action, $validated)) {
            return back()->withErrors(['config' => "Chybí povinný údaj: {$missing}."]);
        }

        $params = array_filter(
            array_intersect_key($validated, array_flip([
                'name', 'username', 'path', 'command', 'type', 'hour', 'minute', 'cron_id', 'quota_mb', 'password',
            ])),
            static fn (mixed $v): bool => $v !== null && $v !== '',
        );

        WebhostingConfigActionJob::dispatchSync(
            $service->id,
            $action,
            $params,
            $request->user()?->id,
        );

        return back()->with('status', WebhostingConfigActionJob::ACTIONS[$action] . ' — úloha byla provedena, zkontrolujte výsledek v provisioning úlohách.');
    }

    /**
     * Required field per action, so a half-filled form fails with a clear
     * message instead of creating an empty database or FTP account.
     *
     * @param  array<string, mixed>  $data
     */
    private function missingFor(string $action, array $data): ?string
    {
        $required = match ($action) {
            'create_database' => ['name' => 'název databáze', 'username' => 'uživatel'],
            'delete_database' => ['name' => 'název databáze'],
            'create_ftp'      => ['username' => 'FTP uživatel'],
            'delete_ftp'      => ['username' => 'FTP uživatel'],
            'create_cron'     => ['name' => 'název úlohy', 'command' => 'příkaz'],
            'delete_cron'     => ['cron_id' => 'ID úlohy'],
            'set_quota'       => ['quota_mb' => 'kvóta'],
            'create_mailbox'  => ['username' => 'jméno schránky', 'password' => 'heslo schránky'],
            'delete_mailbox'  => ['username' => 'jméno schránky'],
            'set_mailbox_quota' => ['username' => 'jméno schránky', 'quota_mb' => 'kvóta'],
            default           => [],
        };

        foreach ($required as $field => $label) {
            $value = $data[$field] ?? null;

            if ($value === null || $value === '') {
                return $label;
            }
        }

        return null;
    }
}
