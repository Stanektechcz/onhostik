<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Enums\ServiceStatus;
use App\Domains\Provisioning\Jobs\WebhostingConfigActionJob;
use App\Domains\Provisioning\Models\Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Self-service webhosting configuration: databases, FTP accounts, cron jobs and
 * SSL on the customer's own service.
 *
 * All of this already existed for admins and the underlying data was already
 * being fetched for the service detail — the customer just could not see or
 * touch any of it, so every "please create me a database" was a support ticket.
 *
 * The customer surface is deliberately narrower than the admin one: no disk
 * quota (that is what the tariff is for) and no mailbox actions (those have
 * their own controller). Everything else routes through the same queued,
 * ProvisioningTask-tracked job, which goes through AapanelClient's gated write
 * methods — nothing here opens the real-writes gate.
 */
final class ServiceWebhostingController extends Controller
{
    /** Actions a customer may perform on their own hosting. */
    private const CUSTOMER_ACTIONS = [
        'create_database', 'delete_database',
        'create_ftp', 'delete_ftp',
        'create_cron', 'delete_cron',
        'issue_ssl',
    ];

    public function store(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('view', $service);

        if ($service->provisioning_driver !== ProvisioningDriver::AAPanel) {
            return back()->withErrors(['webhosting' => 'Tato služba není webhosting.']);
        }

        if ($service->status !== ServiceStatus::Active) {
            return back()->withErrors(['webhosting' => 'Konfigurace je dostupná jen pro aktivní služby.']);
        }

        $validated = $request->validate([
            'action'   => ['required', 'string', 'in:' . implode(',', self::CUSTOMER_ACTIONS)],
            'name'     => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-\.]+$/'],
            'username' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'path'     => ['nullable', 'string', 'max:255'],
            'command'  => ['nullable', 'string', 'max:500'],
            'type'     => ['nullable', 'string', 'in:day,hour,week,month'],
            'hour'     => ['nullable', 'integer', 'min:0', 'max:23'],
            'minute'   => ['nullable', 'integer', 'min:0', 'max:59'],
            'cron_id'  => ['nullable', 'string', 'max:32'],
        ], [
            'name.regex'     => 'Název smí obsahovat jen písmena, číslice, tečku, pomlčku a podtržítko.',
            'username.regex' => 'Uživatelské jméno smí obsahovat jen písmena, číslice, pomlčku a podtržítko.',
        ]);

        $action = (string) $validated['action'];

        if ($missing = $this->missingFor($action, $validated)) {
            return back()->withErrors(['webhosting' => "Chybí povinný údaj: {$missing}."]);
        }

        // An FTP path is confined to the customer's own site root; without this
        // a customer could hand themselves an account rooted at /.
        if ($action === 'create_ftp') {
            $validated['path'] = $this->safeFtpPath($service, $validated['path'] ?? null);
        }

        $params = array_filter(
            array_intersect_key($validated, array_flip([
                'name', 'username', 'path', 'command', 'type', 'hour', 'minute', 'cron_id',
            ])),
            static fn (mixed $v): bool => $v !== null && $v !== '',
        );

        WebhostingConfigActionJob::dispatchSync($service->id, $action, $params, $request->user()?->id);

        return back()->with(
            'status',
            WebhostingConfigActionJob::ACTIONS[$action] . ' — úloha byla odeslána, stav najdete v provisioning úlohách.',
        );
    }

    /**
     * Keep an FTP account inside this service's document root. Traversal and
     * absolute paths are rejected outright rather than sanitised, so a rejected
     * path never turns into a subtly different accepted one.
     */
    private function safeFtpPath(Service $service, ?string $path): string
    {
        $root = '/www/wwwroot/' . ($service->label ?? 'site');

        if ($path === null || trim($path) === '') {
            return $root;
        }

        $relative = trim($path, '/');

        if (str_contains($relative, '..') || preg_match('#^[A-Za-z0-9._/\-]{1,200}$#', $relative) !== 1) {
            return $root;
        }

        return $root . '/' . $relative;
    }

    /** @param array<string, mixed> $data */
    private function missingFor(string $action, array $data): ?string
    {
        $required = match ($action) {
            'create_database' => ['name' => 'název databáze', 'username' => 'uživatel databáze'],
            'delete_database' => ['name' => 'název databáze'],
            'create_ftp'      => ['username' => 'FTP uživatel'],
            'delete_ftp'      => ['username' => 'FTP uživatel'],
            'create_cron'     => ['name' => 'název úlohy', 'command' => 'příkaz'],
            'delete_cron'     => ['cron_id' => 'ID úlohy'],
            default           => [],
        };

        foreach ($required as $field => $label) {
            if (($data[$field] ?? null) === null || ($data[$field] ?? '') === '') {
                return $label;
            }
        }

        return null;
    }
}
