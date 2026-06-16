<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Provisioning\Drivers\AapanelMockDriver;
use App\Domains\Provisioning\Enums\ProvisioningDriver;
use App\Domains\Provisioning\Models\Server;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    public function index(): View
    {
        $servers = Server::query()->withCount('services')->orderBy('name')->paginate(25);

        return view('admin.servers', [
            'servers'      => $servers,
            'totalCount'   => Server::count(),
            'activeCount'  => Server::where('status', 'active')->count(),
            'mockCount'    => Server::where('mock_mode', true)->count(),
            'failedHealth' => Server::where('last_health_ok', false)->whereNotNull('last_health_check_at')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.server-form', [
            'server'  => null,
            'drivers' => ProvisioningDriver::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateServer($request);

        $server = Server::create([
            'name'            => $validated['name'],
            'driver'          => $validated['driver'],
            'api_url'         => $validated['api_url'] ?? '',
            'api_credentials' => $this->buildCredentials($validated),
            'status'          => $validated['status'],
            'max_services'    => $validated['max_services'] ?: null,
            'mock_mode'       => $request->boolean('mock_mode'),
            'is_default'      => $request->boolean('is_default'),
        ]);

        activity('provisioning')
            ->performedOn($server)
            ->causedBy($request->user())
            ->withProperties(['driver' => $server->driver->value])
            ->log('server.created');

        return redirect()->route('admin.servers.index')->with('status', __('panel.admin.server_saved'));
    }

    public function edit(Server $server): View
    {
        return view('admin.server-form', [
            'server'  => $server,
            'drivers' => ProvisioningDriver::cases(),
        ]);
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $validated = $this->validateServer($request);

        // Merge incoming credentials over stored ones; blank fields keep old value.
        $credentials = $server->api_credentials;
        foreach ($this->buildCredentials($validated) as $k => $v) {
            $credentials[$k] = $v;
        }

        $server->update([
            'name'            => $validated['name'],
            'driver'          => $validated['driver'],
            'api_url'         => $validated['api_url'] ?? '',
            'api_credentials' => $credentials,
            'status'          => $validated['status'],
            'max_services'    => $validated['max_services'] ?: null,
            'mock_mode'       => $request->boolean('mock_mode'),
            'is_default'      => $request->boolean('is_default'),
        ]);

        activity('provisioning')
            ->performedOn($server)
            ->causedBy($request->user())
            ->withProperties(['driver' => $server->driver->value])
            ->log('server.updated');

        return redirect()->route('admin.servers.index')->with('status', __('panel.admin.server_saved'));
    }

    public function destroy(Request $request, Server $server): RedirectResponse
    {
        if ($server->services()->exists()) {
            return back()->withErrors(['server' => 'Nelze smazat server s aktivními službami.']);
        }

        activity('provisioning')
            ->performedOn($server)
            ->causedBy($request->user())
            ->withProperties(['name' => $server->name])
            ->log('server.deleted');

        $server->delete();

        return redirect()->route('admin.servers.index')->with('status', 'Server byl smazán.');
    }

    /** Connection test — mock/dry-run only in this phase. */
    public function test(Request $request, Server $server): RedirectResponse
    {
        $ok = $server->mock_mode || config('provisioning.mock_mode', true) === true
            ? app(AapanelMockDriver::class)->testConnection()
            : false;

        $server->update([
            'last_health_check_at' => now(),
            'last_health_ok'       => $ok,
        ]);

        activity('provisioning')
            ->performedOn($server)
            ->causedBy($request->user())
            ->withProperties(['ok' => $ok, 'mock' => true])
            ->log('server.connection_tested');

        return back()->with(
            $ok ? 'status' : 'integration_error',
            $ok ? __('panel.admin.server_test_ok') : __('panel.admin.server_test_failed'),
        );
    }

    /** @return array<string, mixed> */
    private function validateServer(Request $request): array
    {
        return $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'driver'            => ['required', Rule::enum(ProvisioningDriver::class)],
            'api_url'           => ['nullable', 'url', 'max:255'],
            'status'            => ['required', Rule::in(['active', 'maintenance', 'offline'])],
            'max_services'      => ['nullable', 'integer', 'min:1', 'max:10000'],
            'mock_mode'         => ['nullable', 'boolean'],
            'is_default'        => ['nullable', 'boolean'],
            'cred_api_key'      => ['nullable', 'string', 'max:500'],
            'cred_api_token'    => ['nullable', 'string', 'max:500'],
            'cred_api_token_id' => ['nullable', 'string', 'max:500'],
            'cred_api_user'     => ['nullable', 'string', 'max:200'],
            'cred_api_password' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /** @param array<string, mixed> $validated
     *  @return array<string, string> */
    private function buildCredentials(array $validated): array
    {
        $map = [
            'api_key'      => (string) ($validated['cred_api_key']      ?? ''),
            'api_token'    => (string) ($validated['cred_api_token']    ?? ''),
            'api_token_id' => (string) ($validated['cred_api_token_id'] ?? ''),
            'api_user'     => (string) ($validated['cred_api_user']     ?? ''),
            'api_password' => (string) ($validated['cred_api_password'] ?? ''),
        ];

        return array_filter($map, fn (string $v): bool => $v !== '');
    }
}
