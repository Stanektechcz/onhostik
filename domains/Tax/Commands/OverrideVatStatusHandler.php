<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Commands;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\Models\VatValidation;
use Onhost\Domain\Tax\VatNumber;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Audit\AuditRecorder;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Records a staff override of the VAT status (TASK-0031, D31.5) as evidence — a vat_validations row with the reason, what it
 * relied on, who set it and until when — and as the organization's standing for the given number. It ends by itself
 * (`vat_override_until`; VatStanding then reads `unknown` until VIES answers or staff confirm again). Only a number of an EU
 * member state can be overridden: VIES knows nothing else, and nobody outside the EU gets reverse charge or is called invalid.
 * It never writes the customer class and never touches an issued document.
 *
 * @implements CommandHandler<OverrideVatStatusCommand>
 */
final class OverrideVatStatusHandler implements CommandHandler
{
    public function __construct(private readonly OutboxPublisher $outbox, private readonly AuditRecorder $audit) {}

    /** @return array{organization_id:string, status:string, effective:string, override_until:string, validation_id:string} */
    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof OverrideVatStatusCommand) {
            throw new \LogicException('Unexpected command '.$command::class);
        }
        $status = (string) $command->get('status', '');
        if (! in_array($status, [VatStanding::VALID, VatStanding::INVALID], true)) {
            throw new DomainError('vat_status_invalid', 'An override sets the status to valid or invalid.', 422, ['field' => 'status']);
        }
        $reason = trim((string) $command->get('reason', ''));
        $evidence = trim((string) $command->get('evidence', ''));
        if (mb_strlen($reason) < 10 || mb_strlen($evidence) < 5) {
            throw new DomainError('vat_override_unexplained', 'An override needs the reason and the evidence it relies on.', 422, ['field' => mb_strlen($reason) < 10 ? 'reason' : 'evidence']);
        }
        $maxDays = max(1, (int) config('onhost.vies.override_days', 30));
        $days = (int) $command->get('days', $maxDays);
        if ($days < 1 || $days > $maxDays) {
            throw new DomainError('vat_override_days', "An override counts for 1 to {$maxDays} days.", 422, ['field' => 'days', 'max' => $maxDays]);
        }
        $organization = Organization::query()->whereKey((string) $command->get('organization_id'))->lockForUpdate()->first() ?? throw DomainError::notFound('organization');
        $subject = VatStanding::subject($organization);
        // the subject the requester saw and the second person approved (stack polish, MEDIUM): the number and the name are
        // self-service fields, and the approved command may run up to a day after the request — a partner that switched to
        // another company's DIČ and name meanwhile would have that company confirmed as its supplier identity and VAT paid out
        $boundNumber = $command->get('vat_number');
        $boundName = $command->get('organization_name');
        if (! is_string($boundNumber) || ! is_string($boundName) || $boundNumber !== ($subject?->value ?? '') || $boundName !== (string) $organization->name) {
            throw new DomainError('vat_override_subject_changed', 'The VAT number or the name of the organization changed since the override was asked for; ask again for the organization as it is now.', 409, ['field' => 'vat_id']);
        }
        if ($subject === null) {
            throw new DomainError('vat_number_missing', 'The organization has no VAT ID or DIČ to set a status for.', 422, ['field' => 'vat_id']);
        }
        if (! $subject->isEuPrefixed() || ! in_array(strtoupper((string) $organization->country), VatNumber::euMembers(), true)) {
            throw new DomainError('vat_country_not_eu', 'Only the VAT number of an EU member state has a VIES status.', 422, ['field' => 'vat_id']);
        }
        if (strlen($subject->value) > 20) {
            throw new DomainError('vat_number_invalid', 'This is not a VAT number of any member state.', 422, ['field' => 'vat_id']);
        }

        $previous = VatStanding::effectiveStatus($organization);
        $until = now()->addDays($days);
        $validation = VatValidation::query()->create([
            'organization_id' => $organization->id, 'vat_id' => $subject->value, 'valid' => $status === VatStanding::VALID, 'status' => $status,
            'source' => 'staff', 'reason' => 'staff', 'note' => mb_substr($reason, 0, 1000), 'evidence' => mb_substr($evidence, 0, 1000),
            'actor' => mb_substr((string) ($context->actorId ?? $context->actorType), 0, 60), 'country_code' => $subject->toIsoCountry(),
            'consultation_number' => null, 'raw' => null, 'checked_at' => now(), 'expires_at' => $until,
            // the supplier finance confirmed (closing review): VatStanding::supplierIdentity compares the organization's name with it
            // before VAT is paid out to a partner, so a later rename — a self-service field — needs finance again
            'name' => mb_substr((string) $organization->name, 0, 250),
        ]);
        $organization->forceFill([
            'vat_status' => $status,
            'vat_status_source' => 'staff',
            'vat_override_until' => $until,
            'vat_checked_number' => $subject->value,
            'vat_validation_id' => $validation->id,
            'vat_consultation_number' => null, // the evidence is the row above, not a VIES consultation
        ])->save();
        $effective = VatStanding::effectiveStatus($organization);

        $this->audit->record($context->withScope($organization->id), 'tax.vat_status.override', 'succeeded', [
            'status' => $status, 'previous' => $previous, 'reason' => $reason, 'evidence' => $evidence, 'days' => $days, 'until' => $until->toIso8601String(), 'number_hint' => $subject->hint(),
        ], 'organization', $organization->id);
        $this->outbox->publish(GenericEvent::of('tax.vat_number.checked', 'organization', $organization->id, [
            'result' => $status, 'previous' => $previous, 'changed' => $previous !== $effective, 'effective' => $effective, 'source' => 'staff', 'trigger' => 'staff',
            'country' => $subject->toIsoCountry(), 'number_hint' => $subject->hint(), 'consultation_number' => null,
        ], $organization->id));

        return ['organization_id' => $organization->id, 'status' => $status, 'effective' => $effective, 'override_until' => $until->toIso8601String(), 'validation_id' => $validation->id];
    }
}
