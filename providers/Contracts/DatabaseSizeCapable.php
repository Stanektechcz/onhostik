<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

use Onhost\Platform\Errors\ProviderException;

/**
 * A web panel that can say how much room a site's databases take (TASK-0023 web-disk-total). Only the site's own
 * databases are answered for — a panel client may own sites the platform never made, and those are never counted. A
 * database the panel has not measured yet is `used_bytes = null`, never 0; a refusal is thrown, never swallowed.
 */
interface DatabaseSizeCapable
{
    /**
     * @return list<array{name:string, used_bytes:int|null}>
     *
     * @throws ProviderException
     */
    public function databaseSizes(ResourceRef $site): array;
}
