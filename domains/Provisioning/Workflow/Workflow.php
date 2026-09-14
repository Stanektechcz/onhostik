<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflow;

use Onhost\Domain\Provisioning\Models\Operation;

/**
 * A saga of ordered steps executed by the OperationRunner. Steps are idempotent
 * and resumable; `compensate()` undoes partial work when the saga fails for good
 * (blueprint §41 DoD: "rollback/compensation behavior").
 */
interface Workflow
{
    /** Stable kind used in operations.kind, e.g. `provision.vps`. */
    public static function kind(): string;

    /** Queue name; one queue per provider so a dead panel never blocks the others. */
    public function queue(Operation $operation): string;

    /** @return list<Step> */
    public function steps(Operation $operation): array;

    public function compensate(StepContext $context): void;
}
