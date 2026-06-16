<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Billing\Services\PaymentProviderRegistry;
use App\Domains\Integrations\Models\IntegrationSetting;
use App\Domains\Integrations\Services\ConnectionTester;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index(PaymentProviderRegistry $payments): View
    {
        return view('admin.integrations', [
            'integrations'     => IntegrationSetting::query()->orderBy('provider')->get(),
            'paymentProviders' => $payments->statuses(),
        ]);
    }

    public function edit(IntegrationSetting $integration): View
    {
        /** @var array<string, array{label: string, category: string, fields: list<string>}> $catalog */
        $catalog = (array) config('integrations.providers', []);

        return view('admin.integration-edit', [
            'integration' => $integration,
            'fields'      => $catalog[$integration->provider]['fields'] ?? [],
            'masked'      => $integration->maskedCredentials(),
        ]);
    }

    /**
     * Saves flags + credentials. Empty credential inputs keep the stored
     * value (so the masked display never forces re-entry); secrets are
     * never echoed back and never logged.
     */
    public function update(Request $request, IntegrationSetting $integration): RedirectResponse
    {
        /** @var array<string, array{label: string, category: string, fields: list<string>}> $catalog */
        $catalog = (array) config('integrations.providers', []);
        $fields  = $catalog[$integration->provider]['fields'] ?? [];

        $validated = $request->validate([
            'is_active'   => ['nullable', 'boolean'],
            'mock_mode'   => ['nullable', 'boolean'],
            'dry_run'     => ['nullable', 'boolean'],
            'credentials' => ['nullable', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:500'],
        ]);

        $stored = $integration->credentials;

        /** @var array<string, string> $incoming */
        $incoming = array_filter(
            (array) ($validated['credentials'] ?? []),
            fn (mixed $value): bool => is_string($value) && $value !== '',
        );

        foreach ($fields as $field) {
            if (isset($incoming[$field])) {
                $stored[$field] = $incoming[$field];
            }
        }

        $integration->update([
            'is_active'   => $request->boolean('is_active'),
            'mock_mode'   => $request->boolean('mock_mode'),
            'dry_run'     => $request->boolean('dry_run'),
            'credentials' => $stored,
        ]);

        activity('integration')
            ->performedOn($integration)
            ->causedBy($request->user())
            ->withProperties([
                'provider'        => $integration->provider,
                'is_active'       => $integration->is_active,
                'mock_mode'       => $integration->mock_mode,
                'dry_run'         => $integration->dry_run,
                'credential_keys' => array_keys($incoming), // names only — never values
            ])
            ->log('integration.settings_updated');

        return back()->with('status', __('panel.admin.integration_saved'));
    }

    public function test(IntegrationSetting $integration, ConnectionTester $tester): RedirectResponse
    {
        $result = $tester->test($integration);

        return back()->with(
            $result['ok'] ? 'status' : 'integration_error',
            $result['message'],
        );
    }
}
