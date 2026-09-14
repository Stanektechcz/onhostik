<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Models\ProviderInstance;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Services\Models\ManagedCertificate;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Services\Web\AcmeClient;
use Onhost\Domain\Services\Web\CertificateService;
use Onhost\Providers\Contracts\AsyncStatus;
use Onhost\Providers\Contracts\WebHostingProvider;
use Throwable;

/**
 * Wildcard certificate: order at the CA → publish the DNS-01 TXT records in our zone → let the CA validate →
 * finalize with a fresh key → store, install on the panel, clean the TXT records up. Renewals run the same way.
 */
final class CertificateWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'service.certificate';
    }

    public function queue(Operation $operation): string
    {
        $instance = $operation->provider_instance_id ? ProviderInstance::query()->find($operation->provider_instance_id) : null;

        return 'provider-'.($instance?->provider ?? 'default');
    }

    public function steps(Operation $operation): array
    {
        return [$this->orderStep(), $this->validateStep(), $this->finalizeStep()];
    }

    public function compensate(StepContext $context): void
    {
        $certs = $context->container->make(CertificateService::class);
        $cert = ManagedCertificate::query()->find((string) $context->get('certificate_id'));
        if ($cert !== null) {
            $certs->fail($cert, (string) ($context->operation->error['message'] ?? 'certificate issuance failed'));
        }
        try {
            $zone = $certs->zoneFor($context->service->organization_id, (string) $context->desired('domain'));
            if ($zone !== null && (string) $context->get('record_name', '') !== '') {
                $certs->removeChallenge($zone, (string) $context->get('record_name'), $context->actor);
            }
        } catch (Throwable) {
            // the zone may be gone; nothing else to clean
        }
    }

    private function orderStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Objednávka certifikátu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $certs = $context->container->make(CertificateService::class);
                $acme = $context->container->make(AcmeClient::class);
                $domain = strtolower((string) $context->desired('domain'));
                $zone = $certs->zoneFor($service->organization_id, $domain);
                if ($zone === null) {
                    return StepResult::fail("wildcard certificates need the DNS of {$domain} at ONhost (DNS-01 validation); the zone is not managed here", false);
                }
                $cert = $certs->open($service, $domain, $context->operation);
                $order = $acme->newOrder([$domain, '*.'.$domain]);
                $challenges = [];
                $values = [];
                foreach ($order['authorizations'] as $url) {
                    $challenge = $acme->dnsChallenge($url);
                    if ($challenge['status'] === 'valid') {
                        continue; // still authorized from a previous order
                    }
                    if ($challenge['challenge_url'] === null || $challenge['record_value'] === null) {
                        return StepResult::fail('the certificate authority offered no DNS-01 challenge for '.$challenge['domain'], false);
                    }
                    $challenges[] = ['authorization' => $url, 'challenge' => $challenge['challenge_url']];
                    $values[] = $challenge['record_value'];
                }
                $name = $certs->challengeName($zone, $domain);
                if ($values !== []) {
                    $certs->publishChallenge($zone, $name, $values, $context->actor);
                }
                $cert->forceFill(['order_url' => $order['url'], 'account_url' => $acme->accountUrl(), 'state' => 'validating'])->save();

                return StepResult::done(['certificate_id' => $cert->id, 'order_url' => $order['url'], 'finalize_url' => $order['finalize'], 'challenges' => $challenges, 'record_name' => $name, 'record_values' => $values, 'zone_id' => $zone->id]);
            }
        };
    }

    private function validateStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Ověření domény (DNS-01)';
            }

            public function run(StepContext $context): StepResult
            {
                $acme = $context->container->make(AcmeClient::class);
                $challenges = (array) $context->get('challenges', []);
                if ($challenges === []) {
                    return StepResult::done(['validated' => true]);
                }
                if (! (bool) $context->get('responded', false)) {
                    foreach ($challenges as $c) {
                        $acme->respond((string) $c['challenge']);
                    }
                    $context->operation->withContext(['responded' => true])->save();
                }
                $deadline = microtime(true) + 90;
                while (true) {
                    $pending = 0;
                    foreach ($challenges as $c) {
                        $status = $acme->authorizationStatus((string) $c['authorization']);
                        if (str_starts_with($status, 'valid')) {
                            continue;
                        }
                        if (str_starts_with($status, 'invalid') || str_starts_with($status, 'revoked') || str_starts_with($status, 'expired')) {
                            return StepResult::fail('the certificate authority rejected the DNS-01 validation: '.$status, false);
                        }
                        $pending++;
                    }
                    if ($pending === 0) {
                        return StepResult::done(['validated' => true, 'responded' => true]);
                    }
                    if (microtime(true) > $deadline) {
                        return StepResult::fail('DNS-01 validation still pending at the certificate authority', true, ['responded' => true], 30);
                    }
                    sleep(5);
                }
            }
        };
    }

    private function finalizeStep(): ServiceStep
    {
        return new class extends ServiceStep
        {
            public function label(): string
            {
                return 'Vystavení a instalace certifikátu';
            }

            public function run(StepContext $context): StepResult
            {
                $service = $this->service($context);
                $acme = $context->container->make(AcmeClient::class);
                $certs = $context->container->make(CertificateService::class);
                $cert = ManagedCertificate::query()->findOrFail((string) $context->get('certificate_id'));
                $domain = strtolower((string) $context->desired('domain'));
                if ((string) $context->get('fullchain', '') === '') {
                    $csr = $acme->csr([$domain, '*.'.$domain]);
                    $status = $acme->finalize((string) $context->get('finalize_url'), $csr['csr_der']);
                    $deadline = microtime(true) + 60;
                    $order = $acme->order((string) $context->get('order_url'));
                    while ($order['status'] !== 'valid' && microtime(true) < $deadline) {
                        if ($order['status'] === 'invalid') {
                            return StepResult::fail('the certificate authority marked the order invalid', false);
                        }
                        sleep(3);
                        $order = $acme->order((string) $context->get('order_url'));
                    }
                    if ($order['status'] !== 'valid' || $order['certificate'] === null) {
                        return StepResult::fail('the certificate was not ready in time ('.$status.'/'.$order['status'].')', true, [], 30);
                    }
                    $fullchain = $acme->certificate($order['certificate']);
                    $certs->store($cert, $fullchain, $csr['key']);
                    $context->operation->withContext(['fullchain' => true])->save();
                }
                $material = $certs->material($cert);
                $zone = $certs->zoneFor($service->organization_id, $domain);
                if ($zone !== null && (string) $context->get('record_name', '') !== '') {
                    try {
                        $certs->removeChallenge($zone, (string) $context->get('record_name'), $context->actor);
                    } catch (Throwable) {
                        // cleanup is best effort; the TXT record is harmless
                    }
                }
                $web = $this->capability($context, WebHostingProvider::class);
                $context->container->make(ServiceFeatures::class)->forget($service);

                return $this->settle($web->uploadCertificate($this->ref($context), ['cert' => $material['cert'], 'key' => $material['key'], 'chain' => $material['chain']]), ['fullchain' => true, 'installed' => true, 'expires_at' => $cert->expires_at?->toIso8601String()]);
            }

            protected function afterAsyncSuccess(StepContext $context, AsyncStatus $status): StepResult
            {
                return StepResult::done(['installed' => true]);
            }
        };
    }
}
