<?php

declare(strict_types=1);

use Onhost\Domain\Identity\Authorization\Authorizer;
use Onhost\Domain\Identity\Authorization\PermissionCatalog;
use Onhost\Domain\Integrations\ActionHookService;
use Onhost\Domain\Integrations\DiscordService;
use Onhost\Domain\Provisioning\BulkActionService;
use Onhost\Domain\Provisioning\Models\Operation;
use Onhost\Domain\Provisioning\Workflows\ServiceActionWorkflow;
use Onhost\Domain\Services\Commands\ServiceActionCommand;
use Onhost\Domain\Services\CustomerActionParams;
use Onhost\Domain\Services\DestructivePreview;
use Onhost\Domain\Services\LegalHold;
use Onhost\Domain\Services\ServiceFeatures;
use Onhost\Domain\Support\Assistant\AssistantProposals;
use Onhost\Domain\Support\Assistant\AssistantScope;
use Onhost\Platform\Commands\CommandBus;
use Onhost\Platform\Commands\CommandContext;
use Onhost\Platform\Errors\DomainError;

/*
 * TASK-0029 (audit C13, C13-H1, C13-H1b, C13-H1c): 121 of 132 service actions fell through a default arm to `service.manage` at
 * NORMAL risk. The map is now exhaustive and closed: every action of the workflow is named once, with its permission, and an
 * action nobody named is refused — for the owner and for the system alike — before any operation exists.
 */

function sapmCommand(string $action, array $params = []): ServiceActionCommand
{
    return new ServiceActionCommand('org_sapm', 'sapm-key', ['service_id' => 'svc_sapm', 'action' => $action, 'params' => $params]);
}

/** The error slug a callable ends with, or null when it returns. */
function sapmError(callable $call): ?string
{
    try {
        $call();
    } catch (DomainError $e) {
        return $e->error;
    }

    return null;
}

/** Every action a declarative spec apply can chain (ServiceSpecService::apply): read from the code, so a new section is covered. @return list<string> */
function sapmSpecActions(): array
{
    $source = (string) file_get_contents(base_path('domains/Services/ServiceSpecService.php'));
    preg_match_all("/'([a-z_]+(?:\\.[a-z_]+)+|rename)'/", $source, $m);

    return array_values(array_intersect(array_unique($m[1]), ServiceActionWorkflow::ACTIONS));
}

it('maps every service action and nothing else', function () {
    $mapped = array_keys(ServiceActionCommand::PERMISSIONS);
    sort($mapped);
    $actions = array_values(array_unique(ServiceActionWorkflow::ACTIONS));
    sort($actions);

    expect($mapped)->toBe($actions)->and($mapped)->toHaveCount(132);
    foreach (ServiceActionCommand::PERMISSIONS as $action => $permission) {
        expect(PermissionCatalog::exists($permission))->toBeTrue("{$action} maps to {$permission}, which the catalogue does not know")
            ->and(ServiceActionCommand::permissionFor($action))->toBe($action === 'schedule.create' ? 'service.manage' : $permission);
    }
});

it('refuses an action nobody mapped, for the owner and the system alike, before any operation exists', function () {
    expect(sapmError(fn () => ServiceActionCommand::permissionFor('no.such')))->toBe('service_action_unknown');

    [$owner, $org] = $this->customerWithOrganization();
    $service = featureWebService($org, 'aapanel');
    foreach ([$this->contextFor($owner, $org), CommandContext::system('sapm')->withScope($org->id)] as $i => $context) {
        $command = new ServiceActionCommand($org->id, "sapm-unknown-{$i}", ['action' => 'no.such', 'service_id' => $service->id]);
        expect(sapmError(fn () => app(CommandBus::class)->dispatch($command, $context)))->toBe('service_action_unknown');
    }
    expect(Operation::query()->where('service_id', $service->id)->exists())->toBeFalse();

    // the read paths never throw: the assistant can be handed any string by the model
    expect(AssistantScope::for($org, $owner, app(Authorizer::class))->mayRun($service, 'no.such'))->toBeFalse()
        ->and(AssistantScope::for($org, $owner, app(Authorizer::class))->mayRun($service, 'php.set'))->toBeTrue()
        ->and(sapmCommand('no.such')->riskLevel())->toBe(PermissionCatalog::NORMAL)
        ->and(sapmCommand('no.such')->requiresStepUp())->toBeFalse();
});

it('names only real actions in every list of actions', function () {
    $lists = [
        'ServiceFeatures::ACTIONS' => array_merge(...array_values(ServiceFeatures::ACTIONS)),
        'AssistantProposals::ACTIONS' => array_keys(AssistantProposals::ACTIONS),
        'BulkActionService::ACTIONS' => BulkActionService::ACTIONS,
        'LegalHold::DESTRUCTIVE_ACTIONS' => LegalHold::DESTRUCTIVE_ACTIONS,
        'DestructivePreview::ACTIONS' => DestructivePreview::ACTIONS,
        'ServiceActionCommand::STEP_UP' => ServiceActionCommand::STEP_UP,
        'ServiceActionCommand::HIGH_RISK' => ServiceActionCommand::HIGH_RISK,
        // monitoring.set is the platform's own monitor (ServiceSpecService / MonitoringService), not a workflow action
        'ActionHookService::ALLOWED' => array_values(array_diff(ActionHookService::ALLOWED, ['monitoring.set'])),
        'DiscordService::BUTTON_ACTIONS' => array_values(array_diff(DiscordService::BUTTON_ACTIONS, ['monitoring.set'])),
        'spec apply' => sapmSpecActions(),
    ];
    foreach ($lists as $name => $actions) {
        expect($actions)->not->toBeEmpty();
        foreach ($actions as $action) {
            expect(in_array($action, ServiceActionWorkflow::ACTIONS, true))->toBeTrue("{$name} names {$action}, which is no service action");
        }
    }
    expect(sapmSpecActions())->toContain('schedule.create', 'php.set', 'mailbox.create', 'firewall.apply');
});

it('offers staff bulk only plain management, never a console or a deletion', function () {
    // BulkActionService runs every action under staff.service.manage: whatever it lists must be what that permission stands for
    foreach (BulkActionService::ACTIONS as $action) {
        expect(ServiceActionCommand::permissionFor($action))->toBe('service.manage', "{$action} is more than managing")
            ->and(in_array($action, ServiceActionCommand::HIGH_RISK, true))->toBeFalse("{$action} is a high-risk action")
            ->and(ServiceActionCommand::needsFreshStepUp($action))->toBeFalse();
    }
});

it('asks for its own permission to delete a copy', function () {
    expect(ServiceActionCommand::permissionFor('backup.delete'))->toBe('backup.delete')
        ->and(ServiceActionCommand::permissionFor('gbackup.delete'))->toBe('game.manage')
        ->and(ServiceActionCommand::permissionFor('snapshot.delete'))->toBe('compute.vm.delete');
    foreach (['backup.delete', 'gbackup.delete', 'snapshot.delete'] as $action) {
        expect(sapmCommand($action)->riskLevel())->toBe(PermissionCatalog::HIGH)
            ->and(sapmCommand($action)->requiresStepUp())->toBeTrue()
            ->and(sapmCommand($action)->requiresApproval())->toBeFalse(); // no customer four-eyes (D29.2); the catalogue keeps rating backup.delete CRITICAL
    }
    expect(PermissionCatalog::risk('backup.delete'))->toBe(PermissionCatalog::CRITICAL);
});

it('treats a shell or root as the console, and logins that give what managing gives as managing', function () {
    foreach (['access.reset', 'rescue.start', 'subuser.create', 'command.run', 'command.send', 'shell.create', 'shell.key', 'shell.delete'] as $action) {
        expect(ServiceActionCommand::permissionFor($action))->toBe('service.console', $action);
    }
    foreach (['ftp.create', 'ftp.password', 'dbuser.create', 'gamedb.create', 'rescue.stop', 'subuser.delete'] as $action) {
        expect(ServiceActionCommand::permissionFor($action))->toBe('service.manage', $action);
    }
    expect(ServiceActionCommand::permissionFor('panel.password'))->toBe('service.panel_account.manage')
        ->and(ServiceActionCommand::permissionFor('terminate'))->toBe('service.delete')
        ->and(ServiceActionCommand::permissionFor('purge'))->toBe('service.delete')
        ->and(ServiceActionCommand::permissionFor('archive.restore'))->toBe('backup.restore');
});

it('makes a schedule the console only when it carries a command', function () {
    $for = fn (mixed $actions) => ServiceActionCommand::permissionFor('schedule.create', ['name' => 'n', 'cron' => '0 3 * * *', 'actions' => $actions]);

    expect($for([['action' => 'command', 'payload' => 'say hi']]))->toBe('service.console')
        ->and($for([['action' => 'power', 'payload' => 'restart'], ['action' => 'backup', 'payload' => '']]))->toBe('service.manage')
        ->and($for([['action' => 'power', 'payload' => 'restart'], ['action' => 'command', 'payload' => 'op me']]))->toBe('service.console')
        ->and($for([['action' => 'Command', 'payload' => 'op me']]))->toBe('service.console')
        ->and($for(['command']))->toBe('service.console')
        ->and($for([['payload' => 'op me']]))->toBe('service.console')
        ->and(ServiceActionCommand::permissionFor('schedule.create'))->toBe('service.manage')
        // the command the bus checks and the operation row re-checks are one map (H315)
        ->and(sapmCommand('schedule.create', ['actions' => [['action' => 'command', 'payload' => 'x']]])->permission())->toBe('service.console');
});

it('makes unlocking a game backup what deleting one is', function () {
    // review round 2, MEDIUM: an unlocked copy can be deleted on the panel (and rotated away by the panel's own scheduled backup
    // task at the backup limit), so taking the owner's lock off is the game operator's, like gbackup.delete. Locking stays managing.
    // Fail closed: only a definite "keep it locked" (or no word at all, which ServiceService::featureParams reads as locking) is
    // managing — featureParams reads anything else as `false`, i.e. as an unlock.
    $for = fn (array $params) => ServiceActionCommand::permissionFor('gbackup.lock', ['remote_id' => 'bk-1'] + $params);

    expect($for(['locked' => false]))->toBe('game.manage')
        ->and($for(['locked' => '0']))->toBe('game.manage')
        ->and($for(['locked' => 'nonsense']))->toBe('game.manage')
        ->and($for(['locked' => ['x']]))->toBe('game.manage')
        ->and($for(['locked' => true]))->toBe('service.manage')
        ->and($for(['locked' => 'true']))->toBe('service.manage')
        ->and($for([]))->toBe('service.manage')
        // the command the bus checks and the operation row re-checks are one map (H315)
        ->and(sapmCommand('gbackup.lock', ['remote_id' => 'bk-1', 'locked' => false])->permission())->toBe('game.manage');
});

it('makes every destructive action HIGH with a fresh step-up, and none CRITICAL', function () {
    foreach (DestructivePreview::ACTIONS as $action) {
        expect(sapmCommand($action)->riskLevel())->toBe(PermissionCatalog::HIGH, $action)
            ->and(sapmCommand($action)->requiresStepUp())->toBeTrue($action)
            ->and(ServiceActionCommand::needsFreshStepUp($action))->toBeTrue($action);
    }
    // C13-H1c: overwriting a live site from an archive was the one restore without a step-up
    expect(sapmCommand('archive.restore')->riskLevel())->toBe(PermissionCatalog::HIGH)->and(sapmCommand('archive.restore')->requiresStepUp())->toBeTrue();

    foreach (ServiceActionWorkflow::ACTIONS as $action) {
        expect(sapmCommand($action, ['allow_prune' => true])->riskLevel())->not->toBe(PermissionCatalog::CRITICAL, $action);
    }
});

it('never takes a second person or a fresh step-up for anything a spec apply chains', function () {
    // the spec gate (WP2) runs the bus authorizer per action; an approval must never be consumed there. Nor does any step need a
    // fresh step-up: the step's decision then carries no step-up method of its own, and the step's audit row may keep the spec
    // command's context (the session's grant, as the /actions path records it). A spec action that ever needs a step-up must
    // hand the decision's method to requestAction first — this fails before that can be forgotten (review round 1, LOW).
    expect(sapmSpecActions())->toContain('schedule.create', 'cron.create', 'mailbox.create');
    foreach (sapmSpecActions() as $action) {
        $command = sapmCommand($action, ['allow_prune' => true, 'actions' => [['action' => 'command', 'payload' => 'x']]]);
        expect($command->requiresApproval())->toBeFalse($action)
            ->and($command->requiresStepUp())->toBeFalse($action)
            ->and($command->riskLevel())->toBe(PermissionCatalog::NORMAL, $action)
            ->and(ServiceActionCommand::needsFreshStepUp($action))->toBeFalse($action);
    }
});

it('does not yet ask a fresh step-up for a persistent console grant', function () {
    // Pinned on purpose (review round 1, MEDIUM; decisions_needed in the TASK-0029 handoff): a game sub-user and a schedule with
    // a console command ask `service.console` (D29.3/D29.4) but no fresh step-up, although both outlive the session that made
    // them. If the owner decides for a step-up, flip these to toBeTrue together with STEP_UP/HIGH_RISK — a deliberate diff.
    $console = ['actions' => [['action' => 'command', 'payload' => 'op me']]];
    expect(ServiceActionCommand::needsFreshStepUp('subuser.create'))->toBeFalse()
        ->and(ServiceActionCommand::needsFreshStepUp('schedule.create'))->toBeFalse()
        ->and(sapmCommand('subuser.create')->requiresStepUp())->toBeFalse()
        ->and(sapmCommand('schedule.create', $console)->requiresStepUp())->toBeFalse()
        ->and(sapmCommand('schedule.create', $console)->permission())->toBe('service.console');
});

it('keeps mailbox backup retention the operator\'s', function () {
    expect(ServiceActionCommand::permissionFor('mailbox.backup_retention'))->toBe('backup.policy.manage')
        ->and(PermissionCatalog::all()['backup.policy.manage']['audience'])->toBe('staff')
        ->and(sapmCommand('mailbox.backup_retention')->riskLevel())->toBe(PermissionCatalog::HIGH)
        ->and(sapmCommand('mailbox.backup_retention')->requiresApproval())->toBeFalse()
        // pruning deletes backups: a second person, unless the operator runs the platform alone (ONHOST_FOUR_EYES=false)
        ->and(sapmCommand('mailbox.backup_retention', ['allow_prune' => true])->requiresApproval())->toBeTrue()
        ->and(sapmCommand('mailbox.backup_retention', ['allow_prune' => '0'])->requiresApproval())->toBeFalse()
        // review round 2, LOW: a non-scalar allow_prune neither crashes (filter_var answers false for an array on PHP 8.3) nor
        // opens a prune without a second person — MailboxBackupRetentionStep prunes only on a literal `true`
        ->and(sapmCommand('mailbox.backup_retention', ['allow_prune' => ['x' => 1]])->requiresApproval())->toBeFalse();
    expect(sapmError(fn () => CustomerActionParams::filter('mailbox.backup_retention', [])))->toBe('operator_only');
});
