<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

interface PowerCapable
{
    /** @param 'start'|'stop'|'shutdown'|'reboot'|'reset'|'kill' $action */
    public function power(ResourceRef $ref, string $action): ProviderResult;
}
