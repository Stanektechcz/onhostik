<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * A panel that can say whose account a server sits under today, judged against the organization it is sold to (TASK-0033,
 * permission program IF-6). What a domain needs to know before it acts on the panel's account for a customer — nothing more.
 */
interface ServerOwnership
{
    /** The panel names exactly this organization's account. */
    public const OWNED = 'owned';

    /** The panel names another owner than the platform recorded (moved or changed by hand on the panel). */
    public const MOVED = 'moved';

    /**
     * One of owned | foreign | unmarked | administrator | missing | moved (the vocabulary of `onhost:game:panel-identity`).
     *
     * @throws \Onhost\Platform\Errors\ProviderException when the panel cannot be asked
     */
    public function ownerVerdict(ResourceRef $server, string $organizationId): string;
}
