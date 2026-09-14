<?php

declare(strict_types=1);

namespace Onhost\Domain\Provisioning\Workflow;

use Onhost\Providers\Contracts\AsyncHandle;

interface Step
{
    public function label(): string;

    /** Execute (or re-execute idempotently) the step. */
    public function run(StepContext $context): StepResult;

    /** Poll a previously returned async handle. */
    public function poll(StepContext $context, AsyncHandle $handle): StepResult;
}
