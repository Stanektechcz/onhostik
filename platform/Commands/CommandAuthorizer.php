<?php

declare(strict_types=1);

namespace Onhost\Platform\Commands;

/**
 * Bridges the command bus to the capability-based authorization model in
 * Onhost\Domain\Identity. Implemented there; the platform only knows the contract.
 */
interface CommandAuthorizer
{
    public function authorize(Command $command, CommandContext $context): AuthorizationDecision;
}
