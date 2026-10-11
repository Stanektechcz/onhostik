<?php

declare(strict_types=1);

namespace Onhost\Domain\Services\Penpot;

use Onhost\Platform\Commands\GlobalCommand;

/**
 * The "paper parent" of the staging Penpot end-to-end test (J2, TASK-0154). Penpot is ordered for a running service of the same
 * organisation (`PenpotParents`); staging has no web panel to build a real one, and the production panels must stay out of reach.
 * This command writes ONE service row for a sandbox organisation — family web/managed, state ACTIVE, without a provider instance,
 * node, binding, order or subscription — so the Penpot can be ordered for it at "included" price. Nothing is written to a panel
 * and nothing is billed. payload: op `create` {organization_id, product_key, plan, reason?} · op `end` {organization_id,
 * service_id, reason?}.
 *
 * Only the command line runs it (`onhost:penpot:paper-parent`, a system context; the handler refuses any person or token), never
 * in production, only for a sandbox organisation. The bus audits every attempt, refused ones included.
 */
final class PaperParentCommand extends GlobalCommand
{
    public const OPS = ['create', 'end'];

    /** The tag that marks the row; `end` acts on nothing without it. */
    public const TAG = 'staging_paper_parent';

    public const LABEL = 'penpot-e2e-parent';

    /** A parent is a web hosting: that is where Penpot is included in the tariff, at 0 Kč (H-R7). */
    public const FAMILIES = ['web', 'managed'];

    public function permission(): ?string
    {
        return null; // system-internal: the handler admits the system actor only
    }

    public function name(): string
    {
        return 'penpot.paper_parent.'.(in_array($this->get('op'), self::OPS, true) ? (string) $this->get('op') : 'unknown');
    }
}
