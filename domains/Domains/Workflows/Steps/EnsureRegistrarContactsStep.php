<?php

declare(strict_types=1);

namespace Onhost\Domain\Domains\Workflows\Steps;

use Onhost\Domain\Domains\Models\RegistrarContact;
use Onhost\Domain\Domains\Workflows\DomainStep;
use Onhost\Domain\Provisioning\Workflow\StepContext;
use Onhost\Domain\Provisioning\Workflow\StepResult;
use Onhost\Platform\Errors\ProviderErrorCode;
use Onhost\Platform\Errors\ProviderException;
use Onhost\Providers\Contracts\ProviderResult;
use Onhost\Providers\Contracts\RegistrarProvider;

/** Creates registrant/admin contacts at the registrar when they are not synced yet (idempotent by remote handle). */
final class EnsureRegistrarContactsStep extends DomainStep
{
    public function label(): string
    {
        return 'Kontakty u registrátora';
    }

    public function run(StepContext $context): StepResult
    {
        $domain = $this->domain($context);
        $registrar = $this->registrar($context);
        $out = [];
        foreach (['registrant_contact_id', 'admin_contact_id'] as $field) {
            $contactId = $domain->{$field};
            if ($contactId === null) {
                continue;
            }
            $contact = RegistrarContact::query()->find($contactId);
            if ($contact === null) {
                return StepResult::fail("Contact {$contactId} does not exist", false);
            }
            if ($contact->registrar_provider !== $domain->registrar_provider) { // handles are registrar-specific: use (or create) the copy for the holding registrar
                $contact = $contact->siblingFor((string) $domain->registrar_provider);
                $domain->forceFill([$field => $contact->id])->save();
            }
            if (isset($out[$contact->kind.'_handle']) && $domain->registrant_contact_id === $domain->admin_contact_id && $field === 'admin_contact_id') {
                continue;
            }
            if ($contact->isSynced()) {
                $out[$contact->kind.'_handle'] = $contact->remote_id;

                continue;
            }
            $payload = $contact->toProviderContact($domain->tld);
            $payload['handle'] ??= $this->handleFor($contact);
            try {
                $registrar->mutate('contact-create', $domain, $payload, function (RegistrarProvider $adapter, string $clTrid) use ($payload, $contact): ProviderResult {
                    $created = $adapter->createContact($payload, $clTrid);
                    $contact->forceFill(['remote_id' => $created['remote_id'] ?: $payload['handle'], 'state' => 'synced'])->save();

                    return ProviderResult::completed(null, $created);
                }, $context->operation->id, $domain->organization_id, (bool) $context->desired('test_mode', false));
            } catch (ProviderException $e) {
                if ($e->errorCode === ProviderErrorCode::CONFLICT) {
                    $contact->forceFill(['remote_id' => $payload['handle'], 'state' => 'synced'])->save(); // handle already exists at the registry
                } else {
                    $contact->forceFill(['state' => 'error'])->save();

                    return self::fromProviderException($e);
                }
            }
            $out[$contact->kind.'_handle'] = $contact->remote_id;
        }

        return StepResult::done($out);
    }

    private function handleFor(RegistrarContact $contact): string
    {
        return strtoupper('ONH-'.substr(preg_replace('/[^a-z0-9]/i', '', $contact->id) ?? '', -12));
    }
}
