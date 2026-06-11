<?php

declare(strict_types=1);

use App\Domains\Provisioning\Drivers\AapanelMockDriver;
use App\Domains\Provisioning\Models\Service;

it('creates a mock site with external id and credentials', function (): void {
    $service = new Service(['label' => 'test-web.cz']);

    $result = (new AapanelMockDriver())->create($service);

    expect($result->success)->toBeTrue()
        ->and((string) $result->externalId)->toStartWith('MOCK-AAP-')
        ->and($result->credentials)->toHaveKeys(['panel_username', 'panel_password'])
        ->and($result->metadata['mock'])->toBeTrue();
});

it('is idempotent for an already provisioned service', function (): void {
    $service = new Service(['label' => 'existing.cz']);
    $service->external_id = 'MOCK-AAP-EXISTING';

    $result = (new AapanelMockDriver())->create($service);

    expect($result->success)->toBeTrue()
        ->and($result->externalId)->toBe('MOCK-AAP-EXISTING')
        ->and($result->metadata['idempotent'] ?? false)->toBeTrue()
        ->and($result->credentials)->toBe([]); // never re-issues credentials
});

it('supports simulated failure', function (): void {
    $service = new Service(['label' => 'fail.cz']);

    $result = (new AapanelMockDriver())->create($service, ['simulate_failure' => true]);

    expect($result->success)->toBeFalse()
        ->and((string) $result->errorMessage)->toContain('Simulated')
        ->and($result->externalRequestId)->not->toBeNull();
});

it('passes the connection test without any network call', function (): void {
    expect((new AapanelMockDriver())->testConnection())->toBeTrue();
});
