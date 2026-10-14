<?php

declare(strict_types=1);

namespace Onhost\Domain\Payments\Commands;

use Onhost\Domain\Payments\ComgateCheck;
use Onhost\Platform\Commands\Command;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Commands\CommandHandler;
use Onhost\Platform\Errors\DomainError;
use Onhost\Platform\Settings\SettingsStore;
use Onhost\Providers\Payments\Comgate\ComgateMode;

final class ComgateCheckCommandHandler implements CommandHandler
{
    public function __construct(private readonly ComgateCheck $check, private readonly SettingsStore $settings) {}

    public function handle(Command $command, CommandContext $context): mixed
    {
        if (! $command instanceof ComgateCheckCommand) {
            throw new \LogicException('Unsupported command '.get_class($command));
        }

        return match ($command->op()) {
            'check' => $this->check->run((string) $command->get('kind'), $context->actorId),
            'test_mode.set' => (function () use ($command, $context): array {
                $enabled = $command->get('enabled');
                if ($enabled !== null && ! is_bool($enabled)) {
                    throw new DomainError('comgate_test_mode_invalid', 'enabled is true, false or null (the deployment decides).', 422, ['field' => 'enabled']);
                }
                $before = ['test_mode' => ComgateMode::test(), 'source' => ComgateMode::source()];
                if ($enabled === null) {
                    $this->settings->forget(ComgateMode::SETTING);
                } else {
                    $this->settings->set(ComgateMode::SETTING, $enabled, $context->actorId);
                }

                return ['before' => $before, 'test_mode' => ComgateMode::test(), 'source' => ComgateMode::source()];
            })(),
            default => throw new DomainError('comgate_op_unknown', "Unknown Comgate operation {$command->op()}.", 422, ['field' => 'op']),
        };
    }
}
