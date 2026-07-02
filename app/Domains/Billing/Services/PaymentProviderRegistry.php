<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Integrations\Models\IntegrationSetting;

/**
 * Read-model of payment provider readiness for the admin panel.
 *
 * Phase 3 reality: the MOCK gateway is the only one that completes
 * payments in dev. Comgate, Stripe, and GoPay are wired (gateway clients +
 * idempotent webhook processors) but stay in test mode.
 */
final class PaymentProviderRegistry
{
    /** @return list<array{key: string, label: string, status: string, ready: bool}> */
    public function statuses(): array
    {
        $rows = IntegrationSetting::query()
            ->whereIn('provider', ['comgate', 'gopay', 'stripe'])
            ->get()
            ->keyBy('provider');

        $describe = function (string $key, string $wiring) use ($rows): array {
            $row = $rows->get($key);

            return [
                'key'    => $key,
                'label'  => $row->label ?? ucfirst($key),
                'status' => $row === null
                    ? 'not seeded'
                    : ($row->allowsRealCalls() ? 'REAL MODE (should not happen in this phase!)' : $wiring),
                'ready'  => $row !== null,
            ];
        };

        return [
            [
                'key'    => 'mock',
                'label'  => 'Mock platební brána',
                'status' => config('provisioning.mock_mode') === true ? 'active — completes payments locally' : 'disabled',
                'ready'  => true,
            ],
            $describe('comgate', 'prepared — client + idempotent webhook wired, test mode only'),
            $describe('gopay', 'prepared — client + idempotent webhook wired, test mode only'),
            $describe('stripe', 'prepared — Stripe Checkout Sessions wired, test mode only'),
        ];
    }
}
