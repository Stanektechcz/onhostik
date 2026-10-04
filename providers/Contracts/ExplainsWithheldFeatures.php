<?php

declare(strict_types=1);

namespace Onhost\Providers\Contracts;

/**
 * An executor that withholds some of its site features for a reason of its own, and can name it: a closed aaPanel node
 * several customers share offers no terminal, no Node projects and no in-panel file tools (owner decisions O1/R2,
 * TASK-0034). `ServiceFeatures` passes the reason on, so the panel says why a tool is gone instead of showing nothing.
 * Reasons are codes; the words are the UI's to translate, and the panel's name is never one of them.
 */
interface ExplainsWithheldFeatures
{
    /** The node is shared with other customers and was closed to tools that act on it beside every other tenant. */
    public const SHARED_NODE = 'shared_node';

    /**
     * The site features this executor withholds right now, as feature => reason code.
     *
     * @return array<string, string>
     */
    public function withheldFeatures(): array;
}
