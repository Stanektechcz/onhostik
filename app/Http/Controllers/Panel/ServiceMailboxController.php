<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Jobs\WebhostingConfigActionJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customer self-service e-mail hosting — mailbox management on a webhosting
 * service (email hosting product).
 *
 * The backend already existed for admins; this gives the OWNER of the service
 * a scoped subset — create/delete a mailbox and change its quota, nothing else.
 * Every action routes through the same WebhostingConfigActionJob, so the
 * AAPANEL_ALLOW_REAL_WRITES gate still applies: with it closed the call is
 * simulated and recorded as dry_run. The mailbox password is passed to the
 * panel and never stored on our side.
 */
final class ServiceMailboxController extends Controller
{
    /** Only these actions — a customer manages mailboxes, not databases/cron. */
    private const ALLOWED = ['create_mailbox', 'delete_mailbox', 'set_mailbox_quota'];

    public function store(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        if ($service->provisioning_driver !== ProvisioningDriver::AAPanel) {
            return back()->withErrors(['mailbox' => 'E-mailové schránky jsou dostupné jen u webhostingu.']);
        }

        $validated = $request->validate([
            'action'   => ['required', 'string', 'in:' . implode(',', self::ALLOWED)],
            'username' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-\.]+$/'],
            'password' => ['nullable', 'string', 'min:10', 'max:128'],
            'quota_mb' => ['nullable', 'integer', 'min:0', 'max:51200'],
        ]);

        $action = (string) $validated['action'];

        if ($action === 'create_mailbox' && empty($validated['password'])) {
            return back()->withErrors(['password' => 'Zadejte heslo nové schránky (min. 10 znaků).']);
        }
        if ($action === 'set_mailbox_quota' && ($validated['quota_mb'] ?? null) === null) {
            return back()->withErrors(['quota_mb' => 'Zadejte kvótu schránky.']);
        }

        $params = array_filter(
            array_intersect_key($validated, array_flip(['username', 'password', 'quota_mb'])),
            static fn (mixed $v): bool => $v !== null && $v !== '',
        );

        WebhostingConfigActionJob::dispatchSync($service->id, $action, $params, $request->user()?->id);

        $labels = [
            'create_mailbox'    => 'Schránka byla vytvořena',
            'delete_mailbox'    => 'Schránka byla smazána',
            'set_mailbox_quota' => 'Kvóta schránky byla změněna',
        ];

        return back()->with('status', ($labels[$action] ?? 'Hotovo') . ' — ověřte stav v přehledu služby.');
    }
}
