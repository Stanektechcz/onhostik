<?php

declare(strict_types=1);

namespace Onhost\Domain\Organizations\Commands;

use Onhost\Platform\Commands\OrganizationCommand as BaseOrganizationCommand;

/**
 * Organization administration, dispatched by `op`:
 *  update{…attributes} · invite{email,role} · cancel_invitation{invitation_id} · change_role{user_id,role} · remove_member{user_id} · create_project{name,…} · transfer_ownership{user_id}
 *  update_project{project_id,…} · archive_project/restore_project{project_id} · add_project_member{project_id,user_id,role} · remove_project_member{project_id,user_id}
 *  assign_service_project{service_id,project_id|null} · rotate_calendar_feed{}
 */
final class OrganizationCommand extends BaseOrganizationCommand
{
    public const OPS = ['update', 'invite', 'cancel_invitation', 'change_role', 'remove_member', 'create_project', 'update_project', 'archive_project', 'restore_project', 'add_project_member', 'remove_project_member', 'assign_service_project', 'rotate_calendar_feed', 'transfer_ownership'];

    public function op(): string
    {
        return (string) $this->get('op');
    }

    public function permission(): ?string
    {
        return match ($this->op()) {
            'invite', 'cancel_invitation', 'change_role', 'remove_member' => 'organization.members.manage',
            'create_project', 'update_project', 'archive_project', 'restore_project', 'add_project_member', 'remove_project_member', 'assign_service_project' => 'project.manage',
            'transfer_ownership' => 'organization.close',
            default => 'organization.manage',
        };
    }

    public function name(): string
    {
        return 'organization.'.$this->op();
    }
}
