<?php

declare(strict_types=1);

namespace Onhost\Domain\Incidents\Commands;

use Onhost\Platform\Commands\GlobalCommand;

/** Staff act on an on-call alert (audit §5q-1): `ack{alert_id}`, `resolve{alert_id}`, `test{}`; the rota (§5r-1): `shift.add{user, starts_at, ends_at, note?}`, `shift.remove{shift_id}`. */
final class OnCallCommand extends GlobalCommand
{
    public function op(): string
    {
        return (string) $this->get('op', 'ack');
    }

    public function permission(): ?string
    {
        return 'incident.manage';
    }

    public function name(): string
    {
        return 'oncall.'.$this->op();
    }
}
