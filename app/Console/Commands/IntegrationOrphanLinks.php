<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Onhost\Domain\Integrations\ActionHookService;
use Onhost\Domain\Integrations\DiscordService;
use Onhost\Platform\Commands\CommandContext;

/**
 * One-off (TASK-0035, permission program IF-15 / P0-04): the Discord links and action hooks that the release before this one
 * left behind — memberships ended and roles lowered while nobody took the side doors back. From this release on the
 * RevokeMemberSideDoors listener does it at the moment of the removal or role change; this command finds what is older.
 *
 * A dry run by default (program §2.11: existing customers are never changed en masse silently): it lists every orphan with the
 * reason and changes nothing. `--apply` revokes the links and switches the hooks off, each with an audit record as the system.
 * Hooks are never deleted — the owner sees which one stopped and why.
 */
final class IntegrationOrphanLinks extends Command
{
    protected $signature = 'operator:integrations:orphan-links {--apply : revoke the links and switch the hooks off (without it: a dry run)} {--organization= : only this organization}';

    protected $description = 'List (and with --apply revoke) Discord links and action hooks of people who left the organization or whose role could no longer make them';

    public function handle(DiscordService $discord, ActionHookService $hooks): int
    {
        $organization = trim((string) ($this->option('organization') ?? '')) ?: null;
        $apply = (bool) $this->option('apply');
        $links = $discord->orphans($organization);
        $orphanHooks = $hooks->orphans($organization);

        $this->line('Discord links');
        $this->table(['link', 'organizace', 'uživatel', 'discord', 'důvod'], array_map(fn (array $o) => [$o['link']->id, $o['link']->organization_id, (string) $o['link']->user_id, (string) $o['link']->discord_username, $o['reason']], $links));
        $this->line('Action hooks');
        $this->table(['hook', 'organizace', 'vytvořil', 'akce', 'důvod'], array_map(fn (array $o) => [$o['hook']->id, $o['hook']->organization_id, (string) $o['hook']->created_by, $o['hook']->action, $o['reason']], $orphanHooks));

        if (! $apply) {
            $this->line(sprintf('Dry run: %d links and %d hooks would be taken back. Nothing was changed; run again with --apply.', count($links), count($orphanHooks)));

            return self::SUCCESS;
        }
        $context = CommandContext::system('cli:operator:integrations:orphan-links');
        foreach ($links as $orphan) {
            $discord->revoke($orphan['link'], $orphan['reason'], $context);
        }
        foreach ($orphanHooks as $orphan) {
            $hooks->disable($orphan['hook'], $orphan['reason'], $context);
        }
        $this->info(sprintf('Revoked %d links, switched off %d hooks (audited as integration.discord.revoke / integration.hook.disable).', count($links), count($orphanHooks)));

        return self::SUCCESS;
    }
}
