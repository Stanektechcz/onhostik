<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflows;

use Onhost\Domain\Dns\DnsService;
use Onhost\Domain\Dns\Models\DnsZone;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Domain\Provisioning\Workflow\Workflow;
use Onhost\Domain\Provisioning\Workflows\Steps\ScheduleNodeStep;
use Onhost\Domain\Services\Mail\MailSettings;
use Onhost\Domain\Services\Models\MailDomain;
use Onhost\Domain\Services\Models\Service;
use Onhost\Domain\Services\ServiceService;
use Onhost\Providers\Contracts\MailProvider;

/** Mail hosting saga (blueprint §13): place → mail domain with DKIM → MX/SPF/DKIM/DMARC on ONhost zones → ACTIVE. */
final class ProvisionMailDomainWorkflow implements Workflow
{
    public static function kind(): string
    {
        return 'provision.mail';
    }

    public function queue(Operation $operation): string
    {
        return 'provider-ispconfig';
    }

    public function steps(Operation $operation): array
    {
        return [
            new ScheduleNodeStep('mail', 'ispconfig'),
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Poštovní doména';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $mail = $this->capability($context, MailProvider::class);
                    $result = $mail->createMailDomain($context->spec('mail_domain', ['domain' => $context->desired('domain'), 'entitlements' => (array) $service->entitlements]));
                    if ($result->ref !== null) {
                        $context->bind($context->instance(), 'mail_domain', $result->ref->remoteId, $result->ref->node, $result->ref->meta, ['managed_by' => 'onhost']);
                    }

                    return $this->settle($result, ['mail_domain_meta' => $result->ref?->meta ?? [], 'already_existed' => $result->alreadyExisted]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'DNS pro poštu';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $domain = (string) $context->desired('domain');
                    $zone = DnsZone::query()->where('name', $domain)->where('organization_id', $service->organization_id)->where('state', 'active')->first();
                    $meta = (array) $context->get('mail_domain_meta', []);
                    $dkim = $this->capability($context, MailProvider::class)->dkim($this->ref($context, 'mail_domain')) ?? ['selector' => $meta['dkim_selector'] ?? null, 'public' => $meta['dkim_public'] ?? null];
                    // one builder for both sagas (MailSettings): a mail service's customers looked their settings up by
                    // hand, because the autoconfig records were published only for a web service's mail
                    $records = MailSettings::records($domain, (string) ($context->instance()->option('mail_host') ?: config('onhost.dns.mail_host')),
                        isset($dkim['selector']) ? (string) $dkim['selector'] : null, isset($dkim['public']) ? (string) $dkim['public'] : null);
                    if ($zone === null) {
                        return StepResult::done(['dns_records_required' => $records, 'dkim_selector' => $dkim['selector'] ?? null, 'dkim_public' => $dkim['public'] ?? null]); // external DNS: shown to the customer in the panel
                    }
                    $version = $context->container->make(DnsService::class)->syncSystemRecords($zone, $records, $context->actor, "mail hosting {$service->id}", 'mail:'.$domain);

                    return StepResult::done(['dns_version' => $version?->version, 'dkim_selector' => $dkim['selector'] ?? null, 'dkim_public' => $dkim['public'] ?? null]);
                }
            },
            new class extends ServiceStep
            {
                public function label(): string
                {
                    return 'Aktivace';
                }

                public function run(StepContext $context): StepResult
                {
                    $service = $this->service($context);
                    $ref = $this->ref($context, 'mail_domain');
                    $meta = (array) $context->get('mail_domain_meta', []);
                    MailDomain::query()->updateOrCreate(['service_id' => $service->id, 'domain' => (string) $context->desired('domain')], [
                        'remote_client_id' => isset($meta['client_id']) ? (int) $meta['client_id'] : null, 'remote_id' => (int) $ref->remoteId, 'remote_node' => $ref->node,
                        'dkim_selector' => $context->get('dkim_selector'), 'dkim_public' => $context->get('dkim_public'), 'sending_enabled' => true, 'state' => 'active',
                    ]);
                    $context->container->make(ServiceService::class)->activate($service, $context->actor, $context->operation, ['domain' => $context->desired('domain'), 'dns_records_required' => $context->get('dns_records_required')]);

                    return StepResult::done(['activated' => true]);
                }
            },
        ];
    }

    public function compensate(StepContext $context): void
    {
        $service = $context->service ?? Service::query()->find($context->operation->service_id);
        if ($service === null) {
            return;
        }
        $binding = $context->binding('mail_domain');
        if ($binding !== null && $context->get('already_existed') !== true) {
            try {
                $adapter = $context->adapter();
                if ($adapter instanceof MailProvider) {
                    $adapter->deleteMailDomain($binding->ref());
                }
            } catch (\Throwable) {
            }
        }
        $context->container->make(ServiceService::class)->fail($service, $context->actor, (string) ($context->operation->error['message'] ?? 'provisioning failed'), $context->operation);
    }
}
