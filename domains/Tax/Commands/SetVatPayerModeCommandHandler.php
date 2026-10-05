<?php

declare(strict_types=1);

namespace Onhost\Domain\Tax\Commands;

use Onhost\Domain\Tax\VatPayerMode;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;

/**
 * Sets `legal_entities.vat_payer` and keeps the history of the mode on the legal entity (`meta.vat_payer_history`: from when,
 * which mode, who, why). It touches no document: every issued document froze its seller, VAT mode included (G2).
 *
 * @implements CommandHandler<SetVatPayerModeCommand>
 */
final class SetVatPayerModeCommandHandler implements CommandHandler
{
    /** @return array{legal_entity:string, payer:bool, changed:bool} */
    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof SetVatPayerModeCommand) {
            throw new \LogicException('Unexpected command '.$command::class);
        }
        $payer = $command->get('payer');
        if (! is_bool($payer)) {
            throw new DomainError('vat_payer_mode_invalid', 'The mode is payer (true) or non-payer (false).', 422, ['field' => 'payer']);
        }
        $entity = VatPayerMode::legalEntity();
        if ($entity === null) {
            throw new DomainError('legal_entity_missing', 'Legal entity is not configured; run LegalEntitySeeder.', 500);
        }
        $changed = (bool) $entity->vat_payer !== $payer;
        if ($changed) {
            $meta = (array) $entity->meta;
            $history = array_values((array) ($meta['vat_payer_history'] ?? []));
            $history[] = ['payer' => $payer, 'from' => now()->toIso8601String(), 'by' => $context->actorType.':'.($context->actorId ?? 'system'), 'reason' => mb_substr((string) $command->get('reason', ''), 0, 500)];
            $entity->forceFill(['vat_payer' => $payer, 'meta' => array_merge($meta, ['vat_payer_history' => $history])])->save();
        }

        return ['legal_entity' => (string) $entity->key, 'payer' => $payer, 'changed' => $changed];
    }
}
