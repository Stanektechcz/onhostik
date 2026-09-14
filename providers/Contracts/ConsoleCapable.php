<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

interface ConsoleCapable
{
    /**
     * Short-lived console access (noVNC ticket, Wings websocket token). Never the
     * provider account credential. TTL is tens of seconds to minutes.
     *
     * @return array{kind:string,url:string,token:string,expires_at:string,meta?:array<string,mixed>}
     */
    public function consoleAccess(ResourceRef $ref): array;
}
