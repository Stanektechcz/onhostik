<?php

declare(strict_types=1);

namespace Onhost\Domain\Identity\Authorization;

/**
 * A customer command that a /v1/staff/* route sends too (TASK-0039, P0-16 re-check of the final Phase-0 chain). In staff mode the
 * bus asks the staff key this names instead of the customer key of permission(), and the run asks it again (H315) — the staff
 * routes reused the customer controllers with the customer keys, so a member of staff reached the staff powers through their own
 * membership or a share (EXPL-1..3, SS-1 on another URL). StaffActor::permissionOf is the one place that picks the key.
 */
interface StaffModeCommand
{
    /** The permission asked when the context is in staff mode: a staff-audience key, or the customer key where none stands for it. */
    public function staffPermission(): string;
}
