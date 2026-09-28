<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Identity\Authorization\Models\PolicyBinding;
use Onhost\Domain\Identity\Models\User;
use Onhost\Domain\Organizations\GrantCascade;
use Onhost\Domain\Organizations\GrantPolicy;
use Onhost\Domain\Organizations\Models\Organization;
use Onhost\Domain\Organizations\Models\OrganizationInvitation;
use Onhost\Domain\Services\Models\ServiceAccessGrant;

/**
 * `operator:grants:cascade --dry-run` — the active grants whose granter could not give them now (TASK-0042, permission program
 * I6, S1-02; audit TD-6): memberships, project roles and single-service shares handed out by somebody who has since left or been
 * demoted. With `onhost.grants.cascade_enabled` off (the default) such grants are only recorded when their granter loses the
 * right; this lists every one of them, including those from before the record existed. Nothing is changed and there is no
 * `--apply` (owner rule: existing customers are never changed en masse): the operator reviews the list, the organizations are
 * told, and then either the switch goes on — it acts on the next loss — or an organization removes what it does not want.
 */
final class GrantCascadeReport extends Command
{
    protected $signature = 'operator:grants:cascade
        {--dry-run : list only (the default and the only mode)}
        {--apply : refused — the switch onhost.grants.cascade_enabled decides, per loss, after review}
        {--organization= : only this organization id}';

    protected $description = 'List active grants no longer backed by the person who gave them (read-only, TASK-0042)';

    public function handle(GrantPolicy $policy): int
    {
        if ((bool) $this->option('apply')) {
            $this->error('There is no --apply: unbacked grants are revoked only by the switch onhost.grants.cascade_enabled, on the next loss of their granter, each with an access snapshot. Nothing was changed.');

            return self::FAILURE;
        }
        $only = trim((string) ($this->option('organization') ?? ''));
        $rows = [];
        foreach (Organization::query()->when($only !== '', fn ($q) => $q->whereKey($only))->orderBy('created_at')->cursor() as $organization) {
            foreach ($this->grantors($organization) as $grantorId) {
                foreach ($policy->dependents($organization, $grantorId) as $dependent) {
                    $rows[] = [$organization->id.' ('.$organization->name.')', (string) (User::query()->whereKey($grantorId)->value('email') ?? $grantorId),
                        $dependent['kind'], (string) (User::query()->whereKey($dependent['user_id'])->value('email') ?? $dependent['user_id']), $dependent['role'], (string) ($dependent['scope_id'] ?? '—')];
                }
            }
        }
        $this->table(['organization', 'given by', 'kind', 'person', 'role / capabilities', 'scope'], $rows);
        $this->line(count($rows).' active grants are no longer backed by whoever gave them. Nothing was changed.');
        $this->line('Switch: onhost.grants.cascade_enabled is '.(GrantCascade::enabled() ? 'ON — the next loss of a granter revokes their unbacked grants.' : 'off — losses are recorded (audit organization.grant.cascade.flag), nothing is revoked.'));

        return self::SUCCESS;
    }

    /** Everybody recorded as having given something in the organization. @return list<string> */
    private function grantors(Organization $organization): array
    {
        $ids = PolicyBinding::query()->where('organization_id', $organization->id)->whereNotNull('granted_by')->distinct()->pluck('granted_by')
            ->merge(ServiceAccessGrant::query()->where('organization_id', $organization->id)->whereNotNull('granted_by')->distinct()->pluck('granted_by'))
            ->merge(OrganizationInvitation::query()->where('organization_id', $organization->id)->whereNotNull('accepted_at')->whereNotNull('invited_by')->distinct()->pluck('invited_by'));

        return $ids->map(fn ($id) => (string) $id)->filter(fn (string $id) => $id !== '' && $id !== (string) $organization->owner_user_id)->unique()->values()->all();
    }
}
