<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Commands;

use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Tax\Models\VatValidation;
use Onhost\Domain\Tax\VatNumber;
use Onhost\Domain\Tax\VatStanding;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Events\GenericEvent;
use Onhost\Platform\Outbox\OutboxPublisher;

/**
 * Keeps one VIES verdict as evidence (a vat_validations row) and as the organization's recorded check (TASK-0031, D31.2):
 * vat_status valid|invalid for exactly the number that was asked about, when, under which consultation number. A verdict
 * for a number the customer has changed since is thrown away — it says nothing about the new one. It never writes the
 * customer class (C1f is a task of its own) and never touches an issued document. Publishes `tax.vat_number.checked`
 * without the trader's name, address or the full number (docs/architecture/events-catalog.md).
 *
 * @implements CommandHandler<RecordVatCheckCommand>
 */
final class RecordVatCheckHandler implements CommandHandler
{
    public function __construct(private readonly OutboxPublisher $outbox) {}

    /** @return array{recorded:bool, reason?:string, status?:string, validation_id?:string, changed?:bool} */
    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof RecordVatCheckCommand || $context->actorType !== 'system') {
            throw new DomainError('system_only', 'Only the platform\'s own VIES check records a VAT number verdict.', 403);
        }
        $status = (string) $command->get('status', '');
        if (! in_array($status, [VatStanding::VALID, VatStanding::INVALID], true)) {
            throw new DomainError('vat_status_invalid', 'A recorded check is valid or invalid; an unknown answer is not recorded.', 422, ['field' => 'status']);
        }
        $organization = Organization::query()->whereKey($command->organizationId)->lockForUpdate()->first();
        if ($organization === null) {
            throw DomainError::notFound('organization');
        }
        $number = (string) $command->get('number', '');
        $subject = VatNumber::forOrganization($organization);
        if ($subject === null || $subject->value !== $number) {
            return ['recorded' => false, 'reason' => 'number_changed'];
        }

        $trigger = mb_substr((string) $command->get('trigger', 'operator'), 0, 40);
        $standing = VatStanding::standing($organization);
        if ($standing['reason'] === 'staff_override' && $trigger !== 'operator') {
            // the second line behind VatNumberChecks (review round 1): only the operator's explicit check replaces a staff override
            return ['recorded' => false, 'reason' => 'staff_override'];
        }
        $previous = $standing['status'];
        $previousSource = $organization->vat_status_source;
        $source = $command->get('source') === 'format' ? 'format' : 'vies';
        $validation = VatValidation::query()->create([
            'organization_id' => $organization->id,
            'vat_id' => $number,
            'valid' => $status === VatStanding::VALID,
            'status' => $status,
            'name' => self::text($command->get('name'), 250),
            'address' => self::text($command->get('address'), 500),
            'consultation_number' => self::text($command->get('consultation_number'), 80),
            'source' => $source,
            'reason' => $trigger,
            'note' => self::text($command->get('error_code'), 60),
            'requester_vat_id' => self::text($command->get('requester_vat_id'), 20),
            'country_code' => $subject->toIsoCountry(),
            'raw' => null,
            'checked_at' => now(),
        ]);
        $organization->forceFill([
            'vat_status' => $status,
            'vat_checked_at' => now(),
            'vat_validated_at' => $status === VatStanding::VALID ? now() : $organization->vat_validated_at,
            'vat_checked_number' => $number,
            'vat_consultation_number' => $validation->consultation_number,
            'vat_validation_id' => $validation->id,
            'vat_status_source' => $source === 'format' ? 'format' : 'vies',
            'vat_override_until' => null,
        ])->save();
        $result = VatStanding::effectiveStatus($organization);

        $this->outbox->publish(GenericEvent::of('tax.vat_number.checked', 'organization', $organization->id, [
            'result' => $status, 'previous' => $previous, 'previous_source' => $previousSource, 'changed' => $previous !== $result, 'effective' => $result, 'source' => $source, 'trigger' => $trigger,
            'country' => $subject->toIsoCountry(), 'number_hint' => $subject->hint(), 'consultation_number' => $validation->consultation_number,
        ], $organization->id));

        return ['recorded' => true, 'status' => $status, 'validation_id' => $validation->id, 'changed' => $previous !== $result];
    }

    private static function text(mixed $value, int $max): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;
    }
}
